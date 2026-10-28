# ARstampRally202611 作業タスク

> 進め方: 1タスクずつ実施し、各ステップで検証してから次へ進む（小刻み変更・各段階で `php -l` / ルート確認）。
> **ステータス: Phase 1 完了（ドキュメント・景品仕様改訂・Q-11 確定込み）／Phase 2 以降は未着手（2026-10-28）**
> 依存: `requirements.md`（FR-1〜FR-48）→ `design.md`（§1〜§11）

## Phase 1: ドキュメント作成
- [x] T1 `requirements.md` 作成（FR-1〜FR-36 / AC-1〜AC-8 / 未確定 Q-1〜Q-7）
- [x] T2 利用者による仕様確認（Q-1 期間: 11/01〜11/30 で実装し config 1 箇所で後確定、と決定）
- [x] T3 `design.md` 作成（§1〜§11。`config/ar202611.php` の単一切替点を含む）
- [x] T4 `tasks.md` 作成（本ファイル）
- [x] T5 `README.md` 作成・更新内容の記録
- [x] T5b **景品仕様の改訂（2026-10-28）**: お菓子ガラポン 4 段階（5 / 10 / 15 / 20・LocalStorage 管理）＋ 市内共通商品券抽選応募（10 種以上・15 種 2 倍・20 種 3 倍・1 email 1 回・CSV 出力）を requirements §3-2 / design §5.3〜§5.6 / tasks Phase 3B に反映。景品コード（`prize_exchanges`）は廃止

## Phase 2: 基盤（設定とビュー複製）
- [ ] T6 `config/ar202611.php` を作成（design.md §3 の内容。`asset_dir` 既定 `202610` / `marker_count` 20 / **`candy_tiers` `[5,10,15,20]`** / **`voucher_threshold` 10** / **`voucher_weights` `[10=>1, 15=>2, 20=>3]`** / `start` `2026-11-01 00:00:00` / `end` `2026-11-30 23:59:59`）
- [ ] T7 `resources/views/ARstampRally202611.blade.php` を作成（202610 エントリの複製 → 識別子 202611 化）
- [ ] T8 `resources/views/ARstampRally202611/` にパーシャル10個を複製（head / ui / scene / aframe-components / js-stamps / js-prize / js-throw / js-gallery / js-camera / js-init）
- [ ] T9 識別子を 202611 化（design.md §4 の置換一覧。LocalStorage / IndexedDB / Cookie / API URL / console `[AR202611]` / JS 関数名。**サフィックスなし**）
- [ ] T10 アセット参照を config 経由に置換（`scene.blade.php` の pattern・lazy-model、`js-stamps.blade.php` の `STAMPS[].model`。`@php $dir = config('ar202611.asset_dir'); @endphp` と `config('ar202611.marker_count')` ループ）
- [ ] T11 検証: `GET /stamp202611` がレンダリングされ、pattern / lazy-model URL が `cg/202610/` であること（AC-2 / AC-3）

## Phase 3: サーバー側（API・レートリミット）
- [ ] T12 `app/Http/Controllers/StampRally202611Controller.php` を作成（202610 版を複製し、`const PRIZE_EXCHANGE_THRESHOLD` と `exchange()` を**削除** → `checkStatus()`（design §5.2）と `applyVoucher()`（design §5.3）へ置換。`voucherThreshold()` / `weightFor()` / `stampCountFor()` / **`syncVoucherWeight()`（check-status と apply-voucher 共通の倍率上方更新ヘルパー・FR-43）** を静的メソッドで実装し、email は `trim` + `strtolower` で正規化。セッションキーは `ar_fingerprint_202611`。`PrizeExchange` の import は削除）
- [ ] T13 `routes/web.php` に 202611 群を追記（`Route::match(['get','head'], '/stamp202611', …)` + `Route::prefix('stamp202611')` 内に offline / csrf / record-scan / **check-status** / **apply-voucher**）
- [ ] T14 `app/Providers/AppServiceProvider.php` にレートリミッター3種を追記（`stamp202611_scan` / `stamp202611_check` = `Limit::perMinute(120)`、**`stamp202611_apply` = `Limit::perHour(10)`**、key は既存 `$key($request)` 方式踏襲）
- [ ] T15 検証: `php -l`（T12 / T13 / T14 / T39 / T40）→ `php artisan route:list --path=stamp202611`（6ルート）（AC-1 / AC-2）

## Phase 3B: 景品（お菓子ガラポン / 市内共通商品券抽選）
- [ ] T39 `database/migrations/2026_11_XX_XXXXXX_create_stamp202611_voucher_applications_table.php` を作成（design §5.4 の DDL。`fingerprint` / `email` UNIQUE、`down()` は `dropIfExists` のみ）→ `php artisan migrate`（AC-11）
- [ ] T40 `app/Models/Stamp202611VoucherApplication.php` を作成（`$table = 'stamp202611_voucher_applications'`、`$fillable` = session_id / fingerprint / email / name / stamp_count / tier / weight / user_agent / ip_address / device_info / applied_at、`device_info` は array cast）
- [ ] T41 `js-gallery.blade.php` に**ガラポンボタン**を実装（`const CANDY_TIERS = @json(config('ar202611.candy_tiers'));`、到達済み段階を `ar-gacha-draws-202611` に保存、未到達段階は非表示・4 回で `disabled`、design §5.5）。**サーバー送信なし・お菓子の種類は記録しない**
- [ ] T42 ガラポン押下後に「会場スタッフにこの画面を見せてガラポンを回してください」の案内を表示（FR-40）
- [ ] T43 `js-gallery.blade.php` に**抽選応募ボタン**（10 種以上で表示）と email / name 入力フォーム、`js-prize.blade.php` に `applyVoucher202611()`（応募前に `flushScanQueue202611()` → `refreshCsrfToken202611()`）と `ar-voucher-applied-202611` キャッシュ（**応募済みフラグのみ保存し weight は保存しない**）を実装
- [ ] T44 応募完了メッセージの倍率切替は**常に `check-status` の応答 `voucher.weight`** で描画（1 → 「市内共通商品券の抽選会に応募しました。」／2 → 「市内共通商品券の抽選会に応募しました。（15個以上集めたので当選確率2倍です。）」／3 → 「市内共通商品券の抽選会に応募しました。（20個すべて集めたので当選確率3倍です。）」）。15 / 20 種到達後は**応募ボタンを再押下せずページ読み込みだけで昇格表示**（FR-43）。422 `already_applied` → 「すでに応募済みです」
- [ ] T45 検証: 9 / 10 / 15 / 20 種のスタンプを `php artisan tinker` で作って apply-voucher の 422 / `weight` 1 / 2 / 3 を確認。さらに 10 種応募後に 15 / 20 種へ増やして **`check-status` だけ**で `stamp_count` / `weight` が昇格することと下方更新しないことを確認。ブラウザでガラポン 4 回上限（`ar-gacha-draws-202611`）と倍率表示を確認（AC-6 / AC-9 / AC-10 / AC-13）

## Phase 4: 管理ダッシュボード
- [ ] T16 `AdminController::dashboard202611()` を追記（202610 版を複製し、期間を `config('ar202611.start'|'end')` の `Asia/Tokyo`→`UTC` 変換に置換。動物リスト `model_01`〜`model_20`。**景品交換の集計は削除**し、`voucherTotal` / `voucherByWeight` / `voucherApplications`（20 件ページネーション・`q` 検索）を追加）
- [ ] T17 `resources/views/admin/dashboard202611.blade.php` を作成（202610 版のルート名・期間ラベル・見出しを 202611 化。応募一覧テーブルと「業者提出用 CSV ダウンロード」ボタンを配置）
- [ ] T18 `routes/web.php` の admin グループに `Route::get('/dashboard202611', [AdminController::class, 'dashboard202611'])->name('admin.dashboard202611');` を追記
- [ ] T46 `AdminController::exportCsv()` の `$allowed` に `voucherApplications202611` を追加し、`name, email, stamp_count, weight, applied_at` を `weight DESC, applied_at ASC`・`chunk(200)` で出力（design §5.6）
- [ ] T19 検証: `php -l` → `GET /admin/dashboard202611` が 200 で期間表示が 2026-11-01 〜 2026-11-30、`GET /admin/export-csv?dataset=voucherApplications202611` が CSV を返すこと（AC-1 / AC-2 / AC-12）

## Phase 5: iOS カメラ起動（202610 §8 の根本修正を初期実装）
- [ ] T20 `head.blade.php` の video セレクタを 3 箇所すべて `document.querySelector('video')` とする（`monitorCameraStartup` / `ensureCameraAccess` / `getUserMedia` 成功ハンドラ。`#ar-scene video` を書かない）
- [ ] T21 `monitorCameraStartup` は timeout後も `setInterval` を解除せず継続し、ready 時点で `#camera-error` / `#camera-help-modal` / `.arjs-loader` を非表示
- [ ] T22 `ensureCameraAccess` は `video.srcObject` 既存時に `getUserMedia` を再要求せず `video.play()` のみ試行
- [ ] T23 タイムアウトを UA 判定で iOS 15000ms / その他 7000ms に（ローダー強制非表示のフォールバックと同一値）
- [ ] T24 `ui.blade.php` の `#camera-error` 文言を「時間がかかる場合がある／タップで自動再開されることがある」導線に
- [ ] T25 検証: `grep -c "querySelector('video')" resources/views/ARstampRally202611/head.blade.php` = 3、`grep -n "#ar-scene video" resources/views/ARstampRally202611/*.blade.php` = 0（コード中）

## Phase 6: オフライン対応（Service Worker）
- [ ] T26 `public/sw-stamp202611.js` を作成（先頭 `var ASSET_DIR = '202610';` / `VERSION = 'v1'` / `ar202611-{html,core,assets}`。install 必須セット、fetch 戦略は design.md §8.2・§8.3。静的判定は `cg/` 配下全般）
- [ ] T27 `resources/views/ARstampRally202611/offline.blade.php` を作成（自己完結・収集済み種別数・未送信数・3ボタン）
- [ ] T28 `routes/web.php` の prefix group に `/offline`（`no-store` 相当 + `noindex`）と `/csrf`（`no-store` + `noindex`）を追加（T13 と同一グループ）
- [ ] T29 `head.blade.php` に SW 登録（`?sw=0` スキップ）と `#offline-banner` CSS を追加
- [ ] T30 `ui.blade.php` に `#offline-banner` / `#offline-banner-text` を追加
- [ ] T31 `js-prize.blade.php` にオフラインキュー（`ar-scan-queue-202611`・上限200・`scannedAt` 保持・4xx/5xx 非再送）と `refreshCsrfToken202611()`、応募直前の `flushScanQueue202611()` → `refreshCsrfToken202611()` を追加
- [ ] T32 `js-init.blade.php` に online/offline 監視（初期 `navigator.onLine`、`online` 時にトークン更新 → キュー再送、バナー文言に未送信件数）
- [ ] T33 検証: SW fetch 戦略シミュレーション（navigate / 静的 cache-first / API 非介入 / 外部オリジン非介入 / offline フォールバック）とキューシミュレーション（AC-4 / AC-5）

## Phase 7: 最終検証・完了報告
- [ ] T34 全 PHP の `php -l` 再実行（config / Controller / AdminController / routes / AppServiceProvider）
- [ ] T35 識別子監査: `grep -n "202610" resources/views/ARstampRally202611/*.blade.php`（コメントの出典参照を除く）と `grep -n "ar202610" public/sw-stamp202611.js` が 0 件（AC-3）
- [ ] T36 分離監査: `git status --short` で既存キャンペーン（202603 / 202605 / 202606 / 202609 / 202610）と `public/js/`・既存 SW が未変更であることを確認（AC-7）
- [ ] T37 実機検証: Android Chrome / iOS Safari（低スペック機含む）でカメラ起動・マーカー認識・ボール投擲・ガラポンボタン・抽選応募、機内モードで AR 継続とキュー再送（AC-8）
- [ ] T47 運営向け引継ぎメモを README に追記: ガラポンは画面提示により会場スタッフが回数を確認する（アプリは回数を記録しない）、商品券の抽選・当選連絡は業者が CSV を基に行う
- [ ] T38 `README.md` に完了状況と「`cg/202611` への切替手順」（design.md §3.3）を追記

## 検証結果（未実施）
| 項目 | 結果 |
|---|---|
| AC-1 `php -l` | 未実施（T15 / T34） |
| AC-2 ルート一覧 | 未実施（T15 / T19） |
| AC-3 識別子監査 | 未実施（T11 / T35） |
| AC-4 SW 戦略 | 未実施（T33） |
| AC-5 キュー | 未実施（T33） |
| AC-6 閾値・倍率 | 未実施（T45） |
| AC-7 分離（git status） | 未実施（T36） |
| AC-8 実機 | 未実施（T37） |
| AC-9 ガラポン 4 回上限 | 未実施（T45） |
| AC-10 応募 UI（email 重複・倍率表示） | 未実施（T45） |
| AC-11 DB（migrate / UNIQUE） | 未実施（T39） |
| AC-12 CSV 出力 | 未実施（T19 / T46） |
| AC-13 倍率の自動昇格 | 未実施（T45） |

## 参考（202610 での実施履歴 = 本キャンペーンで初期実装に内包する内容）
| 202610 | 内容 | 202611 での扱い |
|---|---|---|
| T15〜T20 / §7 | iOS カメラ誤表示の安全策（監視継続・二重 `getUserMedia` 防止・15000ms） | Phase 5 で初期実装 |
| T21〜T24 / §8 | video セレクタ根本原因（`document.body` 直下 append）→ `querySelector('video')` | Phase 5（T20）で最初から正しいセレクタ |
| T25〜T29 / §9 | 保存キー `-r2` 化による再交換 | Phase 2（T9）で新キー開始のため同等効果が最初から成立 |
| 202610 の景品コード交換（`exchange-prize` / `prize_exchanges`） | 10 種以上で景品コード 1 枚と交換 | **202611 では廃止**。お菓子ガラポン 4 段階（LocalStorage）＋ 商品券抽選応募（新規テーブル）に置き換え（Phase 3B） |
| Phase 9 / §10 | オフライン SW・offline ページ・csrf 再取得・キュー | Phase 6 で初期実装 |
