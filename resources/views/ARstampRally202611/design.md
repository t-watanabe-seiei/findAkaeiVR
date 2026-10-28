# ARstampRally202611 設計書

> 前提: `requirements.md`（FR-1〜FR-48 / AC-1〜AC-12）。Q-1（期間）・Q-2（景品閾値）・Q-5（景品仕様）・Q-11（倍率の上方更新）は確定。Q-8（会場）/ Q-9（2日間開催）/ Q-10（CSV 文字コード）は未確定。
> 実装の基準は **ARstampRally202610 の完成形**（`ARstampRally202610/design.md` §1〜§10、コミット `stamp20261027_01 offline service worker`）。
> **状態: 設計（実装前）**

## 1. アーキテクチャ
```
[ブラウザ /stamp202611]
   ├─ Blade: エントリ + パーシャル11（head / ui / scene / aframe-components /
   │         js-stamps / js-prize / js-throw / js-gallery / js-camera / js-init / offline）
   ├─ A-Frame + AR.js（pattern マーカー 21枚 / lazy-model で GLB 遅延取得）
   ├─ Service Worker  public/sw-stamp202611.js（scope = SW 配置ディレクトリ）
   └─ API（web グループ: CSRF + セッション）
        POST /stamp202611/record-scan | check-status | apply-voucher
             ↓ StampRally202611Controller
        marker_scans（既存キャンペーンと共用）
        stamp202611_voucher_applications（202611 専用・新規テーブル）
        ※ prize_exchanges は 202611 では使用しない（景品コード廃止・FR-8）
```
- サーバー側で応募閾値（10 種）と当選倍率 `weight`（1 / 2 / 3）を**算出・強制**し、レートリミッター（分120 / 時10）を適用する。
- お菓子のガラポン（5 / 10 / 15 / 20 種の 4 段階・最大 4 回）は**クライアント LocalStorage のみ**で管理し、サーバーには送信しない（FR-37〜FR-40）。
- オフライン時は SW が navigation を network-first、静的アセットを cache-first で供給し、
  スキャン記録は LocalStorage キューに蓄積して `online` 時に自動再送する。
- 202610 で後付けした iOS カメラ誤表示の根本修正とオフライン対応を**初期実装に内包**する（FR-9〜FR-14 / FR-19〜FR-28）。

## 2. ファイル構成
| ファイル | 種別 | 内容 |
|---|---|---|
| `resources/views/ARstampRally202611.blade.php` | 新規 | エントリ（head / ui / scene / aframe-components / js-* の include） |
| `resources/views/ARstampRally202611/head.blade.php` | 新規 | head・CSRF meta・カメラ起動監視（`monitorCameraStartup` / `ensureCameraAccess`）・SW 登録・オフライン CSS |
| `resources/views/ARstampRally202611/ui.blade.php` | 新規 | HUD・ボタン・`#camera-error`・`#offline-banner` |
| `resources/views/ARstampRally202611/scene.blade.php` | 新規 | `<a-scene>`・maker00（ロード中）・maker01..20（`config('ar202611.asset_dir')` 経由） |
| `resources/views/ARstampRally202611/aframe-components.blade.php` | 新規 | `lazy-model` / `hitbox` / `click-animation` 等のカスタムコンポーネント |
| `resources/views/ARstampRally202611/js-stamps.blade.php` | 新規 | `STAMPS` 定義（`model: '{dir}/Model_NN.glb'`）・収集処理・日次キャッシュ |
| `resources/views/ARstampRally202611/js-prize.blade.php` | 新規 | 抽選応募導線（email / name 入力フォーム・倍率 1 / 2 / 3 の表示）・`recordMarkerScan()`・オフラインキュー・`refreshCsrfToken()` |
| `resources/views/ARstampRally202611/js-throw.blade.php` | 新規 | ボール投擲（`poke_ball_seinei2.glb`）・`ball_hit` 記録 |
| `resources/views/ARstampRally202611/js-gallery.blade.php` | 新規 | スタンプ帳（コレクション）・**ガラポンボタン**（5 / 10 / 15 / 20 段階・最大 4 回）・**抽選応募ボタン**（10 種以上） |
| `resources/views/ARstampRally202611/js-camera.blade.php` | 新規 | カメラ権限・リトライ導線・ヘルプモーダル |
| `resources/views/ARstampRally202611/js-init.blade.php` | 新規 | 初期化・userId/fingerprint・online/offline 接続（`refreshCsrfToken()` → `flushScanQueue()`） |
| `resources/views/ARstampRally202611/offline.blade.php` | 新規 | オフライン専用ページ（自己完結・no-store・noindex） |
| `public/sw-stamp202611.js` | 新規 | 202611 専用 Service Worker（`ar202611-{html,core,assets}-v1`） |
| `app/Http/Controllers/StampRally202611Controller.php` | 新規 | recordScan / checkStatus / **applyVoucher**（閾値・倍率・ガラポン段階は config 経由） |
| `app/Models/Stamp202611VoucherApplication.php` | 新規 | 抽選応募モデル（テーブル `stamp202611_voucher_applications`） |
| `database/migrations/2026_11_XX_XXXXXX_create_stamp202611_voucher_applications_table.php` | 新規 | 抽選応募テーブル（`email` / `fingerprint` UNIQUE） |
| `config/ar202611.php` | 新規 | アセットディレクトリ・マーカー数・ガラポン段階・応募閾値・当選倍率・集計期間の単一切替点 |
| `resources/views/admin/dashboard202611.blade.php` | 新規 | 管理画面（`admin/dashboard202610.blade.php` 基準）＋ 抽選応募一覧・CSV ダウンロードボタン |
| `routes/web.php` | 変更（追記） | `/stamp202611`（index）+ prefix group（offline / csrf / API3）+ admin ルート |
| `app/Providers/AppServiceProvider.php` | 変更（追記） | `stamp202611_scan` / `_check` / `_apply` |
| `app/Http/Controllers/AdminController.php` | 変更（追記） | `dashboard202611()`（期間は config 経由）・`exportCsv()` に dataset `voucherApplications202611` 追加 |
| `public/js/`・`public/service-worker.js`・`public/sw-stamp202610.js`・他キャンペーン | **非変更** | 分離原則（FR-2 / AC-7） |

## 3. 設定の単一点 `config/ar202611.php`（FR-32〜FR-36）
```php
<?php

/*
|--------------------------------------------------------------------------
| ARstampRally202611
|--------------------------------------------------------------------------
| アセット差し替え・閾値・集計期間の単一切替点。
| cg/202611 へ切り替える場合は asset_dir を変更し、
| public/sw-stamp202611.js の ASSET_DIR と VERSION を同期する（FR-36）。
*/

return [
    'asset_dir'    => env('AR202611_ASSET_DIR', '202610'),
    'marker_count' => (int) env('AR202611_MARKER_COUNT', 20),

    // お菓子のガラポン: 到達段階（各 1 回・最大 4 回）。回数はクライアント LocalStorage で管理（FR-37〜FR-40）
    'candy_tiers'  => [5, 10, 15, 20],

    // 市内共通商品券の抽選: 応募閾値と当選倍率（サーバー側で算出・強制・FR-41〜FR-44）
    'voucher_threshold' => (int) env('AR202611_VOUCHER_THRESHOLD', 10),
    'voucher_weights'   => [10 => 1, 15 => 2, 20 => 3],

    'start'        => env('AR202611_START', '2026-11-01 00:00:00'),
    'end'          => env('AR202611_END', '2026-11-30 23:59:59'),
];
```

### 3.1 参照箇所
| 参照側 | 記述 | 反映 FR |
|---|---|---|
| `scene.blade.php` | `@php $dir = config('ar202611.asset_dir'); $count = (int) config('ar202611.marker_count'); @endphp` → `asset('cg/'.$dir.'/pattern-maker'.$id.'.patt')` / `asset('cg/'.$dir.'/Model_'.$id.'.glb')`、`@for ($i = 1; $i <= $count; $i++)` | FR-32 |
| `js-stamps.blade.php` | `'model_01': { name: 'ひつじ', icon: '🐾', model: '{{ $dir }}/Model_01.glb' }` | FR-32 |
| `js-gallery.blade.php` | `const CANDY_TIERS = @json(config('ar202611.candy_tiers'));` / `const VOUCHER_THRESHOLD = {{ (int) config('ar202611.voucher_threshold') }};`（ガラポンボタン・抽選応募ボタンの有効化） | FR-34 / FR-40 |
| `StampRally202611Controller` | `private static function voucherThreshold(): int { return (int) config('ar202611.voucher_threshold', 10); }` / `private static function weightFor(int $stampCount): int`（`config('ar202611.voucher_weights')` の `<= $stampCount` の最大キー） | FR-34 / FR-43 |
| `AdminController::dashboard202611()` | `Carbon::createFromFormat('Y-m-d H:i:s', config('ar202611.start'), 'Asia/Tokyo')->setTimezone('UTC')`（end も同様） | FR-29 / FR-35 |
| `sw-stamp202611.js` | 先頭 `var ASSET_DIR = '202610';`（静的アセット判定は `cg/` 配下全般） | FR-33 |

### 3.2 注意（PHP の `const` では config を使えない）
202610 は `public const PRIZE_EXCHANGE_THRESHOLD = 10;` を使っている。PHP の定数初期化式は
静的評価が必要なため `config()` を入れられない。202611 では **静的メソッド `voucherThreshold()` / `weightFor()`** に置き換え、
`applyVoucher()` 内で `self::voucherThreshold()` / `self::weightFor($stampCount)` を使用する（422 の文言も同メソッドから生成）。
ガラポン段階 `candy_tiers` は blade の `@json()` でクライアントへ注入し、JS 側に数値を直書きしない。

### 3.3 `cg/202611` への切替手順（FR-36）
1. `public/cg/202611/` に `Model_00.glb`〜`Model_20.glb` と `pattern-maker00.patt`〜`pattern-maker20.patt` を配置（同名規則 Q-6）
2. `config/ar202611.php` の `asset_dir` を `'202611'` に変更（または `.env` の `AR202611_ASSET_DIR=202611`）→ `php artisan config:cache`（本番）
3. `public/sw-stamp202611.js` の `ASSET_DIR` を `'202611'`、`VERSION` を `'v2'` に変更（precache 差し替えと旧キャッシュ破棄）
4. 検証: `GET /stamp202611` の pattern / lazy-model URL が `cg/202611/` になっているか、SW の CORE_URLS が 21 枚分生成されるか

## 4. 202611 固有識別子（分離のための置換一覧）
| 種別 | 202610 | 202611 |
|---|---|---|
| ページタイトル | `AR Stamp Rally 202610` | `AR Stamp Rally 202611` |
| LocalStorage | `ar-stamp-rally-202610-r2` / `ar-captured-animals-202610-r2` / `ar-gallery-selection-202610-r2` / `ar-user-id-202610-r2` / `ar-prize-exchanged-202610-r2` / `ar-prize-code-202610-r2` / `ar-camera-reload-202610` | 同形・**サフィックスなし**: `ar-stamp-rally-202611` / `ar-captured-animals-202611` / `ar-gallery-selection-202611` / `ar-user-id-202611` / **`ar-gacha-draws-202611`**（ガラポン到達済み段階） / **`ar-voucher-applied-202611`**（応募状態キャッシュ） / `ar-camera-reload-202611` |
| オフラインキュー | `ar-scan-queue-202610-r2` | `ar-scan-queue-202611` |
| 日次スキャンキャッシュ | `marker-scan-cache-202610-{markerId}-{date}` | `marker-scan-cache-202611-{markerId}-{date}` |
| IndexedDB | `ARStampRallyDB202610r2` | `ARStampRallyDB202611` |
| Cookie | `ar_user_id_202610_r2` | `ar_user_id_202611` |
| API | `/stamp202610/{record-scan,check-prize,exchange-prize}`、`/stamp202610/{offline,csrf}` | `/stamp202611/{record-scan,check-status,apply-voucher}`、`/stamp202611/{offline,csrf}` |
| Service Worker | `/sw-stamp202610.js` / `ar202610-{html,core,assets}-v1` | `/sw-stamp202611.js` / `ar202611-{html,core,assets}-v1` |
| RateLimiter | `stamp202610_{scan,check,redeem}` | `stamp202611_{scan,check,apply}` |
| DB テーブル | `marker_scans` / `prize_exchanges` | `marker_scans`（共用） / **`stamp202611_voucher_applications`**（202611 専用） |
| ルート名 | `stamp202610.{index,offline,csrf}` / `admin.dashboard202610` | `stamp202611.{index,offline,csrf}` / `admin.dashboard202611` |
| クラス / メソッド | `StampRally202610Controller` / `dashboard202610()` | `StampRally202611Controller` / `dashboard202611()` |
| JS 関数・グローバル | `getUserId202610` / `CookieHelper202610` / `generateUUID202610` / `STAMPS` 等の 202610 系 | `getUserId202611` / `CookieHelper202611` / `generateUUID202611` 等へ |
| console プレフィックス | `[AR202610]` | `[AR202611]` |
| アセット参照 | `cg/202610/`（直書き） | `cg/{config('ar202611.asset_dir')}/`（既定 `202610`） |

- 置換漏れは AC-3（`grep -n "202610" resources/views/ARstampRally202611/` がコメント中の参照を除き 0 件）で検出する。
- 共用で残す意図的な識別子: `cg/poke_ball_seinei2.glb`、`cg/sound_stamp01.mp3` / `sound_stamp02.mp3`、`js/objects/ball.obj|ball.mtl`、`js/sounds/loading.ogg`、`img/howToOperate.png`、`css/stampRally.css`、`js/ar-engine.min.js` 等の共有ライブラリ（変更しない）。

## 5. API 設計（202610 と同一仕様 + config 閾値）
| ルート | ミドルウェア | Controller | 制限 |
|---|---|---|---|
| `GET|HEAD /stamp202611` | `web` | クロージャ（`view('ARstampRally202611')`） | — |
| `GET /stamp202611/offline` | `web` | クロージャ（`view('ARstampRally202611.offline')` + `Cache-Control: public, max-age=0, must-revalidate` + `X-Robots-Tag: noindex`） | — |
| `GET /stamp202611/csrf` | `web` | クロージャ（`{'token': csrf_token()}` + `Cache-Control: no-store` + `noindex`） | — |
| `POST /stamp202611/record-scan` | `web` + `throttle:stamp202611_scan` | `recordScan` | 分120 |
| `POST /stamp202611/check-status` | `web` + `throttle:stamp202611_check` | `checkStatus` | 分120 |
| `POST /stamp202611/apply-voucher` | `web` + `throttle:stamp202611_apply` | `applyVoucher` | 時10 |

### 5.1 recordScan
- validate: `markerId` / `markerName` / `fingerprint` 必須 string、`deviceInfo` 必須 array、`scannedAt` 必須 date、`captureType` nullable `in:marker_scan,ball_hit`
- `session(['ar_fingerprint_202611' => $fingerprint])` でセッションとフィンガープリントを紐付け（check-status / apply-voucher で同一識別。202610 の `ar_fingerprint` は使用しない）
- `capture_type === 'marker_scan'` は当日重複を `whereDate('scanned_at', today)` で除外 → `{'success':false,'duplicate':true}`
- `scan_count` は「session_id OR fingerprint」+ marker + capture_type の累積 + 1
- `scanned_at` は `now()`（クライアントの `scannedAt` は validate のみ使用。キュー再送時は元の `scannedAt` を body で送るが、DB 記録時刻はサーバー基準になる点に注意）

### 5.2 checkStatus（読み取り＋**倍率の自動昇格**・202610 の check-prize 相当）
- `session('ar_fingerprint_202611') ?: $request->input('fingerprint')`
- 収集種数は**サーバー側**で算出: `MarkerScan::where('session_id', $sid)->orWhere('fingerprint', $fp)->distinct()->count('marker_id')`（`scanned_at` を `config('ar202611.start')` / `end`（JST→UTC）で絞る）
- 応募状態: `Stamp202611VoucherApplication::where('fingerprint', $fp)->first()`
- **倍率の自動上方更新（FR-43・利用者決定 2026-10-28）**: 応募行が存在する場合、応答前に共通ヘルパー `syncVoucherWeight($application, $stampCount)` を呼び、現在種数が `stamp_count` より大きければ `stamp_count` / `tier` / `weight` を UPDATE する（**下方更新しない**）。利用者は応募ボタンを再押下せず、**ページを読み込むだけで 2 倍 / 3 倍へ昇格する**
- 返却: `{ success, stampCount, candyTiers: [5,10,15,20], voucher: { applied, weight, stampCount, appliedAt, updated } }`（`updated` はこの応答で昇格 occurred か）

### 5.3 applyVoucher（商品券抽選の応募・冪等）
- validate: `fingerprint` 必須 string、`deviceInfo` 必須 array、`email` 必須 `email|max:190`、`name` 必須 `string|max:100`
  - email は `trim` + `strtolower` で正規化して保存・比較する（FR-44）
- 収集種数は**サーバー側**の `marker_scans` から算出する（クライアントの `stamps` 配列は信用しない）
- 未応募かつ `stampCount < voucher_threshold()` → HTTP 422（`not_eligible`）
- 応募作成: `weight = self::weightFor($stampCount)`（`config('ar202611.voucher_weights')` の `<= stampCount` の最大キー）→ INSERT
- 既存応募がある場合:
  - `fingerprint` 一致 → `syncVoucherWeight()`（§5.2 と同一ヘルパー）で倍率の**上方更新**（`weight` / `stamp_count` / `tier` が現在値より大きい場合のみ UPDATE）→ `{ success: true, updated: true }`。**倍率昇格の主経路は `check-status`（ページ読み込み）であり、応募ボタンを再押下させる導線は不要**（FR-43）
  - `email` 一致（別 fingerprint）→ HTTP 422（`already_applied`）→ クライアントは「すでに応募済みです」を表示
- 返却: `{ success, applied: true, weight, stampCount, appliedAt }` → クライアントは `weight` で FR-42 の文言を選択
- `prize_exchanges` には一切書き込まない（景品コード廃止・FR-8）

### 5.4 新規テーブル `stamp202611_voucher_applications`（DDL）
```php
Schema::create('stamp202611_voucher_applications', function (Blueprint $table) {
    $table->id();
    $table->string('session_id')->index();
    $table->string('fingerprint', 64)->unique();   // 1 fingerprint = 1 応募
    $table->string('email', 190)->unique();        // 1 メールアドレス = 1 応募（FR-44）
    $table->string('name', 100);
    $table->unsignedSmallInteger('stamp_count');   // 応募／最終更新時点の収集種数
    $table->unsignedTinyInteger('tier');           // 10 / 15 / 20
    $table->unsignedTinyInteger('weight');         // 1 / 2 / 3（当選倍率）
    $table->string('user_agent');
    $table->string('ip_address');
    $table->json('device_info');
    $table->timestamp('applied_at');
    $table->timestamps();
    $table->index(['weight', 'applied_at']);
});
```
- `down()` は `Schema::dropIfExists('stamp202611_voucher_applications');` のみ。既存 `marker_scans` / `prize_exchanges` には一切触れない（AC-7 / AC-11）
- 個人情報（email・name）はこのテーブルにのみ保存する

### 5.5 ガラポンボタン（クライアント LocalStorage のみ・サーバー通信なし）
- 保存キー `ar-gacha-draws-202611` = 引抽済み段階の配列（例 `[5,10]`）
- 有効化: `CANDY_TIERS`（blade 注入・config 由来）のうち `stampCount >= tier && !draws.includes(tier)` が存在する間のみボタン有効
- 押下処理: `draws.push(tier)` → 保存 → 「会場スタッフにこの画面を見せてガラポンを回してください」の案内を表示。**お菓子の種類・くじ結果はアプリ側に記録しない**（FR-37）
- `draws.length >= CANDY_TIERS.length`（= 4）でボタンを `disabled` にし「ガラポンはすべて引きました」を表示
- オフラインでも動作（fetch なし）。サーバー記録はないため、ブラウザデータ削除で回数はリセットされる（FR-39 で許容）

### 5.6 CSV ダウンロード（業者提出用・FR-45 / FR-48）
- ルート: 既存 `GET /admin/export-csv?dataset=voucherApplications202611&start=…&end=…`（`AdminController::exportCsv()` の `$allowed` に追加）
- ヘッダ: `name, email, stamp_count, weight, applied_at`
- ソート: `weight DESC, applied_at ASC`（倍率順で業者の抽選を容易にする）
- 出力: 全応募（`applied_at` で期間絞り込み）。`chunk(200)` ストリーミング（既存 dataset と同一実装）
- 文字コード: UTF-8（BOM 無し）。Excel で文字化けする場合は BOM 付きへ変更（Q-10 で未確定）

## 6. 管理ダッシュボード
- ルート: `Route::get('/dashboard202611', [AdminController::class, 'dashboard202611'])->name('admin.dashboard202611');`（`auth` + admin グループ内）
- 期間: `config('ar202611.start')` / `config('ar202611.end')` を `Asia/Tokyo` → `UTC` に変換（既定 2026-11-01 00:00:00 〜 2026-11-30 23:59:59）
- 出力変数: `animalStats` / `recentScans` / `dailyStats` / `dailyUniqueUsers` / **`voucherTotal`** / **`voucherByWeight`**（weight 1/2/3 の人数）/ **`voucherApplications`**（ページネーション 20 件・`q` で name / email 検索）
- CSV ボタン: `GET /admin/export-csv?dataset=voucherApplications202611&start=…&end=…`（§5.6）。画面上に「業者提出用 CSV ダウンロード」ボタンを配置し、admin 認証下でのみ個人情報を見せる（FR-47）
- 景品交換（`prize_exchanges`）の集計ブロックは 202611 では**表示しない**（景品コード廃止・FR-8）
- 動物リスト: `model_01`〜`model_20`（202610 と同一。`cg/202611` 差し替え後も動物名が同じ場合は変更不要）
- ビュー: `resources/views/admin/dashboard202611.blade.php`（`dashboard202610.blade.php` の期間ラベル・ルート名を 202611 化）

## 7. iOS カメラ起動（202610 §8 の根本修正を初期実装）
| 論点 | 202611 の実装 |
|---|---|
| video 取得 | `document.querySelector('video')`（**3 箇所すべて**: `monitorCameraStartup` / `ensureCameraAccess` / `getUserMedia` 成功ハンドラ）。AR.js は camera video を `document.body` 直下に append するため `#ar-scene video` は常に `null`（202610 §8 で確認済みの根本原因） |
| 監視 | `setInterval` を timeout後も解除せず継続。video ready 時点で `#camera-error` / `#camera-help-modal` / `.arjs-loader` を自動非表示 |
| リトライ | `video.srcObject` が既存なら `getUserMedia` を再要求せず `video.play()` を try/catch で試行 |
| タイムアウト | UA 判定で iOS = 15000ms / その他 = 7000ms（監視とローダー強制非表示のフォールバックを同一値に整合） |
| 文言 | `#camera-error` は「起動に時間がかかる場合がある／タップで自動再開されることがある」導線（ID・構造は 202610 と同一維持） |
| 非変更 | `public/js/ar-engine.min.js` / `ar-tracking.min.js` 等の共有ライブラリは変更しない |

## 8. オフライン対応（Service Worker・初期実装）
### 8.1 構成
```
[ブラウザ /stamp202611]
   │  head.blade.php: ?sw=0 でない限り navigator.serviceWorker.register(BASE + 'sw-stamp202611.js')
   ▼
[Service Worker  public/sw-stamp202611.js]  scope = SW の配置ディレクトリ
   ├─ CACHE_HTML   ar202611-html-v1    … ページ HTML / オフラインページ
   ├─ CACHE_CORE   ar202611-core-v1    … install 時の事前キャッシュ（必須セット）
   └─ CACHE_ASSETS ar202611-assets-v1  … 実行時キャッシュ（GLB・画像等）
```
- `BASE = self.location.pathname.replace(/[^/]*$/, '')`（サブディレクトリデプロイ `/find/…` 対応）。全キャッシュ URL は `BASE` 付きで組み立てる。
- 202610 の SW（`public/sw-stamp202610.js` / `ar202610-*`）とはキャッシュ・登録範囲ともに独立。

### 8.2 事前キャッシュ（install / FR-20）
| 種別 | 対象 |
|---|---|
| HTML | `{BASE}stamp202611`, `{BASE}stamp202611/offline` |
| JS | `js/ar-engine.min.js`, `js/ar-tracking.min.js`, `js/geo-location.min.js`, `js/loaders/GLTFLoader.js`, `js/loaders/OBJLoader.js` |
| CSS | `css/stampRally.css` |
| マーカー | `cg/{ASSET_DIR}/pattern-maker00.patt` 〜 `pattern-maker20.patt`（21枚） |
| モデル | `cg/{ASSET_DIR}/Model_00.glb`（ロード中モデル） |
| その他 | `js/objects/ball.obj`, `js/objects/ball.mtl`, `js/sounds/loading.ogg`, `img/howToOperate.png` |

- 先頭に `var ASSET_DIR = '202610';`（切替時はこの 1 行と `VERSION` のみ変更・FR-33/FR-36）。
- 21 体すべての GLB（202610 参照時は計 59MB）は事前キャッシュしない（FR-21）。`lazy-model` の pre-fetch が SW 経由で `CACHE_ASSETS` に残る。
- install は `Promise.all` + 個別 try/catch（1件の 404 で install 失敗にしない）→ `skipWaiting()`。
- activate は `ar202611-` 接頭辞の旧バージョンキャッシュのみ削除 → `clients.claim()`。

### 8.3 フェッチ戦略（FR-22）
| リクエスト | 戦略 |
|---|---|
| `method !== GET`（API POST 3種） | 介入しない（常にネットワーク・キャッシュしない） |
| 同一オリジン以外 | 介入しない |
| `mode === 'navigate'`（ページ） | network-first → 成功時は `CACHE_HTML` に保存 → 失敗時は `CACHE_HTML` → `offline` ページ → 504 |
| `{BASE}(js|css|img|build|cg)/…` | cache-first（`ignoreSearch: true`）→ 命中時は背景で再取得して更新（stale-while-revalidate）→ 非命中時はネットワーク → 失敗時 504。**`cg/` 配下全般**が対象のため `cg/202610` → `cg/202611` 切替でも戦略変更不要 |
| `{BASE}stamp202611/csrf` | 介入しない（常にネットワーク・`no-store`） |
| その他 GET | 介入しない（ブラウザ既定） |

### 8.4 オフラインページ（FR-23）
- `GET /stamp202611/offline` → `view('ARstampRally202611.offline')`（外部アセット依存なしのインライン `<style>` / `<script>`）
- 表示: 見出し・説明・収集済み種別数（`ar-stamp-rally-202611`）・未送信スキャン数（`ar-scan-queue-202611`）・接続状態
- ボタン: 「AR を続ける」（`/stamp202611`）／「再試行」（`location.reload()`）／「キャッシュ削除」（SW へ `{type:'CLEAR_CACHES'}` postMessage → 登録解除してリロード。`ar202611-*` のみ消去）

### 8.5 スキャン記録のオフラインキュー（FR-24 / FR-26 / FR-27）
```
recordMarkerScan202611(markerId, ...)
  ├─ navigator.onLine === false → enqueueScan202611(payload)（LocalStorage）して resolve
  ├─ fetch 成功 → 通常処理
  └─ fetch 通信エラー → enqueueScan202611(payload)（HTTP 4xx/5xx は再送しない）
window 'online' → refreshCsrfToken202611() → flushScanQueue202611()
応募直前 → flushScanQueue202611() → refreshCsrfToken202611() → apply-voucher
```
- キューキー `ar-scan-queue-202611`、上限 200 件（超過は先頭＝最古を破棄）。
- 各要素は送信 body そのもの（markerId / markerName / fingerprint / deviceInfo / captureType / scannedAt）を保持し、再送時は元の `scannedAt` を使う。
- `refreshCsrfToken202611()` は `GET /stamp202611/csrf`（`no-store`）で新トークンを取得し `meta[name=csrf-token]` を更新（キャッシュ済み HTML のトークン陳腐化 = 419 対策）。
- 再送失敗時はキューへ戻して打ち切り（次回 `online` / 次回ロードで再試行）。
- 抽選応募はオフラインでは行わない。ガラポンボタンはオフラインでも押下可（LocalStorage のみ・fetch なし）。

### 8.6 UI 状態表示（FR-25）
- `ui.blade.php` に `#offline-banner`（初期 `display:none`、文言は `#offline-banner-text`）、CSS は `head.blade.php`。
- `js-init.blade.php` で `offline` / `online` イベントと初期 `navigator.onLine` を監視して表示切替、`online` 時に `refreshCsrfToken202611()` → `flushScanQueue202611()`。未送信キュー件数を文言に反映。

### 8.7 デバッグ導線（FR-28）
- `?sw=0` で SW 登録をスキップ（head.blade.php）。
- オフラインページの「キャッシュ削除」は `ar202611-*` のみ（既存キャンペーンのキャッシュに干渉しない）。

## 9. 保存キーと応募状態（FR-15〜FR-17）
- 202611 は**新キー（サフィックスなし）**で開始するため、202610 まで参加済みのブラウザでも 202611 は「ガラポン 0 回・未応募」で開始できる。
  - 新 `ar-user-id-202611` / Cookie `ar_user_id_202611` / IndexedDB `ARStampRallyDB202611` → 新 userId → 新 fingerprint
  - `ar-gacha-draws-202611`（ガラポン引抽済み段階）と `ar-voucher-applied-202611`（応募状態キャッシュ）も 202611 専用
- 応募済みの正本は **DB の `stamp202611_voucher_applications`**（`email` / `fingerprint` UNIQUE）。`ar-voucher-applied-202611` は UI 表示用のキャッシュにすぎず、
  削除されても `check-status` / `apply-voucher` の応答で復旧する（同じ email で再応募しようとしても 422 `already_applied`）
- `ar-voucher-applied-202611` には**応募済みフラグのみ**保存し、`weight` / `stampCount` は保存しない。
  倍率の文言（FR-42）は**常に `check-status` の応答**から描画する（自動昇格が即座に画面へ反映されるため・FR-43）
- ガラポンの回数は正本が LocalStorage のみ（FR-39）。ブラウザデータ削除でリセットされる仕様であることを README に明記する
- 既存レコード（`marker_scans` / `prize_exchanges`）は削除・更新しない（履歴保持）。
- 同一キャンペーン内で再度リセットが必要な場合のみ 202610 と同じ運用で `-r2` を付与する（本フェーズでは実施しない）。

## 10. リスク・注意
- `cg/202610/` の削除・改名は 202610 と 202611 の両方に影響する（202611 は既定で同じアセットを参照）。
- `config/ar202611.php` と `sw-stamp202611.js` の `ASSET_DIR` が不一致になると、blade は新しいモデルを参照するのに SW の precache は旧ディレクトリになる（切替手順 §3.3 で必ず同期）。
- 本番で `php artisan config:cache` を使っている場合、config 変更後は再キャッシュが必須。
- `routes/web.php` / `AppServiceProvider` / `AdminController` は**追記のみ**。既存行の並び替えや既存キャンペーン側の修正は行わない。
- オフライン中の商品券抽選応募は不可（FR-27）。オフラインページにはその旨を明記する。ガラポンボタンはオフラインでも押下可（LocalStorage のみ）。
- ガラポンの引抽回数はサーバー側では検証しない（FR-39）。**会場での二重配布は運営の目視確認に依存する**ため、配布ルール（画面の「引いた段階」表示）を運営へ周知する必要がある。
- 1 メールアドレス 1 回の制約は email 単位。家族で同一 email を共有するケースは 1 応募として扱われる（業者側で運用）。氏名の異なる重複を検出したい場合は管理画面の email 検索で確認する。
- `check-status` は応募行の `stamp_count` / `tier` / `weight` を**上方更新する書き込みを伴う**（FR-43）。下方更新しない・応募者自身の 1 行のみ・`stamp202611_check`（分120回）で律速されるため負荷は小さい。自動昇格をやめたい場合は Q-11 を「応募時点固定」へ変更し §5.2 の UPDATE を削除する。
- 202609 側に残る `#ar-scene video` 不具合（202610 §8 の既知未対応）は本キャンペーンでは扱わない（分離原則・Q-7）。

## 11. 検証計画（requirements.md §11 対応）
| 検証 | 方法 |
|---|---|
| AC-1 構文 | `php -l`（`config/ar202611.php` / `StampRally202611Controller.php` / `AdminController.php` / `routes/web.php`） |
| AC-2 ルート | `php artisan route:list --path=stamp202611` と `--path=dashboard202611`。既存キャンペーンルートの差分なし確認 |
| AC-3 識別子 | `grep -n "202610" resources/views/ARstampRally202611/*.blade.php`（コメント中の出典参照を除く）/ `grep -n "ar202610" public/sw-stamp202611.js` |
| AC-4 SW 戦略 | `sw-stamp202611.js` の fetch ハンドラを Node でシミュレート（navigate / 静的 / API / 外部オリジン / offline フォールバック） |
| AC-5 キュー | キューロジックのシミュレーション（上限200・最古破棄・`scannedAt` 維持・4xx/5xx 非再送・online 復帰再送・応募前フラッシュ） |
| AC-6 閾値・倍率 | 9 種で `POST /stamp202611/apply-voucher` → 422 `not_eligible`、10 種 → `weight:1`、15 種 → `weight:2`、20 種 → `weight:3`。client が `weight:3` を送っても 10 種なら保存値は 1（`php artisan tinker` / curl） |
| AC-7 分離 | `git status --short` で 202610 含む既存キャンペーンファイルが未変更（追記は routes / AppServiceProvider / AdminController ＋ 新規マイグレーション・モデル）。`marker_scans` / `prize_exchanges` のスキーマ変更なし |
| AC-8 実機 | Android Chrome / iOS Safari（低スペック機）でカメラ起動・マーカー認識・投擲・ガラポンボタン・抽選応募、機内モードで AR 継続とキュー再送 |
| AC-9 ガラポン | 5 / 10 / 15 / 20 到達時に 1 回ずつ押下 → `ar-gacha-draws-202611` に `[5,10,15,20]`。4 回目以降はボタン `disabled`。同一段階の再押下不可（DevTools で localStorage 確認） |
| AC-10 応募 UI | 10 種未満は応募ボタン非表示。同一 email（`Foo@x.com` と `foo@x.com`、前後空白付き）で 2 回目 → 422 `already_applied` → 「すでに応募済みです」。15 / 20 到達後に再訪すると「当選確率2倍 / 3倍」表示に変わる |
| AC-11 DB | `php artisan migrate` で `stamp202611_voucher_applications` 作成、`migrate:rollback` で削除。`marker_scans` / `prize_exchanges` のカラム・インデックスが不変であること（`SHOW CREATE TABLE` 比較）。UNIQUE 制約の検証（同一 email の直接 INSERT で例外） |
| AC-12 CSV | 管理画面から `dataset=voucherApplications202611` をダウンロードし、`name, email, stamp_count, weight, applied_at` の全応募行と `weight DESC` 順を確認。期間 `start` / `end` 絞り込みが動作すること |
| AC-13 自動昇格 | 10 種で応募 → tinker で 15 種へ増やして `POST /stamp202611/check-status` のみを呼ぶと DB の `stamp_count` が 15・`weight` が 2 に更新され `updated: true`。20 種で 3 倍。種数を減らしても更新されない（下方更新なし）。応募ボタンは再押下しない |


