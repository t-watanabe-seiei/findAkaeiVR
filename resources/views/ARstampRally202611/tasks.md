# ARstampRally202611 作業タスク

> 進め方: 1タスクずつ実施し、各ステップで検証してから次へ進む（小刻み変更・各段階で `php -l` / ルート確認）。
> **ステータス: Phase 1 完了（ドキュメント・景品仕様改訂・Q-11 / Q-12 確定）／Phase 2 以降は未着手（2026-10-28）**
> **ブロッカー: なし**（Q-12 は 2026-10-28 に**案A で確定**: 収集 `2026-11-01 00:00:00` 〜 `2026-11-15 23:59:59` / 応募期限 `2026-11-16 23:59:59` JST。T6 / T12 / T31 / T32 / T43 が案A の影響を受ける）
> 依存: `requirements.md`（FR-1〜FR-53 / AC-1〜AC-16）→ `design.md`（§1〜§11 ＋ §10.1 Q-12 変更手順）

## Phase 1: ドキュメント作成
- [x] T1 `requirements.md` 作成（FR-1〜FR-36 / AC-1〜AC-8 / 未確定 Q-1〜Q-7）
- [x] T2 利用者による仕様確認（Q-1 期間: 11/01〜11/30 で実装し config 1 箇所で後確定、と決定）
- [x] T3 `design.md` 作成（§1〜§11。`config/ar202611.php` の単一切替点を含む）
- [x] T4 `tasks.md` 作成（本ファイル）
- [x] T5 `README.md` 作成・更新内容の記録
- [x] T5b **景品仕様の改訂（2026-10-28）**: お菓子ガラポン 4 段階（5 / 10 / 15 / 20・LocalStorage 管理）＋ 市内共通商品券抽選応募（10 種以上・15 種 2 倍・20 種 3 倍・1 email 1 回・CSV 出力）を requirements §3-2 / design §5.3〜§5.6 / tasks Phase 3B に反映。景品コード（`prize_exchanges`）は廃止
- [x] T5c **Q-12 三案比較の起案 → 案A で確定（2026-10-28）**: 「オフラインで集めて帰宅後に応募する」の可否を requirements §10（Q-12 行＋案A/B/C 比較表）に起案し、**利用者回答により案A を採用**（`collect_start` `2026-11-01 00:00:00` / `collect_end` `2026-11-15 23:59:59` / `apply_deadline` `2026-11-16 23:59:59` JST＋クライアント `scannedAt` の条件付き採用）。FR-49〜FR-53 / AC-14〜AC-16 を確定（**Q-9 確定時は 3 値の書き換えのみで吸収**）

## Phase 2: 基盤（設定とビュー複製）
- [x] T6 `config/ar202611.php` を作成（design.md §3 の内容。`asset_dir` 既定 `202610` / `marker_count` 20 / **`candy_tiers` `[5,10,15,20]`** / **`voucher_threshold` 10** / **`voucher_weights` `[10=>1, 15=>2, 20=>3]`** / **Q-12 案A 確定: `collect_start` `2026-11-01 00:00:00` / `collect_end` `2026-11-15 23:59:59` / `apply_deadline` `2026-11-16 23:59:59`**）★Q-9（2 日間開催）確定時は 3 値の書き換えのみ → **完了（2026-10-28）**: `php -l` OK、`php artisan tinker` で `config('ar202611.*')` の 3 値が確定値で読めることを確認
- [x] T7 `resources/views/ARstampRally202611.blade.php` を作成（202610 エントリの複製 → 識別子 202611 化）→ **完了（2026-10-28）**: `@include('ARstampRally202611.*')` 10 個
- [x] T8 `resources/views/ARstampRally202611/` にパーシャル10個を複製（head / ui / scene / aframe-components / js-stamps / js-prize / js-throw / js-gallery / js-camera / js-init）→ **完了（2026-10-28）**: 202610 の 11 ファイル（3,880 行）を複製
- [x] T9 識別子を 202611 化（design.md §4 の置換一覧。LocalStorage / IndexedDB / Cookie / API URL / console `[AR202611]` / JS 関数名。**サフィックスなし**）→ **完了（2026-10-28）**: `-202611`（`-r2` 廃止）/ `ARStampRallyDB202611` / `ar_user_id_202611` / `/stamp202611/{record-scan,check-status,apply-voucher}` / `sw-stamp202611.js` / `[AR202611]` / `getUserId202611` 等。**AC-3 達成**: `grep -rn 202610`（md 除く）= **0 件**
- [x] T10 アセット参照を config 経由に置換（`scene.blade.php` の pattern・lazy-model、`js-stamps.blade.php` の `STAMPS[].model`。`@php $dir = config('ar202611.asset_dir'); @endphp` と `config('ar202611.marker_count')` ループ）→ **完了（2026-10-28）**: `asset('cg/' . $dir . '/…')` 4 箇所 / `@for ($i = 1; $i <= $count; $i++)` / `STAMPS[].model = '{{ $dir }}/Model_NN.glb'` / `TOTAL_STAMP_SLOTS = {{ (int) config('ar202611.marker_count') }}`
- [ ] T11 検証: `GET /stamp202611` がレンダリングされ、pattern / lazy-model URL が `cg/202610/` であること（AC-2 / AC-3）→ **Blade 側は合格（2026-10-28）**: `view('ARstampRally202611')->render()` = 215,429 字、`cg/202610/pattern-maker01.patt` / `cg/202610/Model_20.glb` を含む、pattern 参照 21（maker00 + 20）。**HTTP 検証は T13（ルート追記）後に実施**

## Phase 3: サーバー側（API・レートリミット）
- [x] T12 `app/Http/Controllers/StampRally202611Controller.php` を作成（202610 版を複製し、`const PRIZE_EXCHANGE_THRESHOLD` と `exchange()` を**削除** → `checkStatus()`（design §5.2）と `applyVoucher()`（design §5.3）へ置換。`voucherThreshold()` / `weightFor()` / `stampCountFor()` / **`syncVoucherWeight()`（check-status と apply-voucher 共通の倍率上方更新ヘルパー・FR-43）** を静的メソッドで実装し、email は `trim` + `strtolower` で正規化。セッションキーは `ar_fingerprint_202611`。`PrizeExchange` の import は削除）
  - **Q-12 案A 確定（FR-50 / FR-51）**: 静的メソッド **`collectPeriod(): array`**（`collect_start` `2026-11-01 00:00:00` / `collect_end` `2026-11-15 23:59:59` を JST→UTC）と **`applyDeadline(): Carbon`**（`2026-11-16 23:59:59` を JST→UTC）を追加。`stampCountFor()` は `collectPeriod()` で絞り、`recordScan()` の `scanned_at` は design §5.1 の 3 条件（parse 可能・収集期間内・`now()+24h` 以下）を満たす client `scannedAt` を採用し条件外は `now()`（日次重複の `whereDate` は採用した日付で行う）。`applyVoucher()` は `now() > applyDeadline()` → 422 `deadline_passed`（案B/案C への差し替えは design §10.1 の表参照）→ **完了（2026-10-28）**: `php -l` OK。**実装上の決定（design §5.1 の JST→UTC 表記について）**: `APP_TIMEZONE=Asia/Tokyo` で `marker_scans.scanned_at` は **JST 壁時計の文字列**で保存されている（既存 188 件で確認）。そのため `collectPeriod()` / `applyDeadline()` は **Asia/Tokyo で parse してそのまま比較**し、クライアントの UTC ISO8601（`new Date().toISOString()`）は保存前に `setTimezone('Asia/Tokyo')` して既存データと同一の壁時計系へ揃える。UTC 変換を挟むと収集期間の末尾 9 時間がこぼれるため不採用（design.md §5.1 に注記追記）
- [x] T13 `routes/web.php` に 202611 群を追記（`Route::match(['get','head'], '/stamp202611', …)` + `Route::prefix('stamp202611')` 内に offline / csrf / record-scan / **check-status** / **apply-voucher**）→ **完了（2026-10-28）**: 202610 群の直後（95〜123 行）に追記。ルート名 `stamp202611.{index,offline,csrf}`
- [x] T14 `app/Providers/AppServiceProvider.php` にレートリミッター3種を追記（`stamp202611_scan` / `stamp202611_check` = `Limit::perMinute(120)`、**`stamp202611_apply` = `Limit::perHour(10)`**、key は既存 `$key($request)` 方式踏襲）→ **完了（2026-10-28）**: 202611 専用の `$key202611`（`session('ar_fingerprint_202611')` → 無ければ IP）を追加。202609 / 202610 の定義はそのまま
- [x] T15 検証: `php -l`（T12 / T13 / T14 / T39 / T40）→ `php artisan route:list --path=stamp202611`（6ルート）（AC-1 / AC-2）→ **完了（2026-10-28）**: 5 ファイルすべて `No syntax errors`。`route:list` は非 TTY 環境で `str_repeat()` 例外 / "stdout is not a tty" となるため、tinker で `Route::getRoutes()` を直接確認 → **6 ルート**（`GET|HEAD /stamp202611`, `/offline`, `/csrf`, `POST /record-scan`, `/check-status`, `/apply-voucher`）

## Phase 3B: 景品（お菓子ガラポン / 市内共通商品券抽選）
- [x] T39 `database/migrations/2026_11_XX_XXXXXX_create_stamp202611_voucher_applications_table.php` を作成（design §5.4 の DDL。`fingerprint` / `email` UNIQUE、`down()` は `dropIfExists` のみ）→ `php artisan migrate`（AC-11）→ **完了（2026-10-28）**: 実ファイル名 `2026_11_01_000000_create_stamp202611_voucher_applications_table.php`。`php artisan migrate` = DONE（57.76 ms）。既存 `marker_scans` 188 件 / `prize_exchanges` 1 件は無傷（AC-11）
- [x] T40 `app/Models/Stamp202611VoucherApplication.php` を作成（`$table = 'stamp202611_voucher_applications'`、`$fillable` = session_id / fingerprint / email / name / stamp_count / tier / weight / user_agent / ip_address / device_info / applied_at、`device_info` は array cast）→ **完了（2026-10-28）**: `stamp_count` / `tier` / `weight` は integer cast、`applied_at` は datetime cast
- [ ] T41 `js-gallery.blade.php` に**ガラポンボタン**を実装（`const CANDY_TIERS = @json(config('ar202611.candy_tiers'));`、到達済み段階を `ar-gacha-draws-202611` に保存、未到達段階は非表示・4 回で `disabled`、design §5.5）。**サーバー送信なし・お菓子の種類は記録しない**
- [ ] T42 ガラポン押下後に「会場スタッフにこの画面を見せてガラポンを回してください」の案内を表示（FR-40）
- [ ] T43 `js-gallery.blade.php` に**抽選応募ボタン**と email / name 入力フォーム、`js-prize.blade.php` に `applyVoucher202611()`（応募前に `flushScanQueue202611()` → `refreshCsrfToken202611()`）と `ar-voucher-applied-202611` キャッシュ（**応募済みフラグのみ保存し weight は保存しない**）を実装
  - **応募ボタンの表示条件（Q-12 案A 確定・FR-52）**: `check-status` の `stampCount >= VOUCHER_THRESHOLD` を正本とする（LocalStorage の収集数では表示しない）。`ar-scan-queue-202611` に 1 件以上残る間はボタンを非表示にし `#pending-scans`（「未送信のスタンプ N 件を自動送信します」）を表示。フラッシュ完了 → `checkStatus202611()` 再実行 → 反映。`apply_deadline`（blade 注入・`2026-11-16 23:59`）経過時はボタン非表示、サーバーの 422 `deadline_passed` には「応募期限を過ぎたため応募できません」を表示
- [ ] T44 応募完了メッセージの倍率切替は**常に `check-status` の応答 `voucher.weight`** で描画（1 → 「市内共通商品券の抽選会に応募しました。」／2 → 「市内共通商品券の抽選会に応募しました。（15個以上集めたので当選確率2倍です。）」／3 → 「市内共通商品券の抽選会に応募しました。（20個すべて集めたので当選確率3倍です。）」）。15 / 20 種到達後は**応募ボタンを再押下せずページ読み込みだけで昇格表示**（FR-43）。422 `already_applied` → 「すでに応募済みです」
- [ ] T45 検証: 9 / 10 / 15 / 20 種のスタンプを `php artisan tinker` で作って apply-voucher の 422 / `weight` 1 / 2 / 3 を確認。さらに 10 種応募後に 15 / 20 種へ増やして **`check-status` だけ**で `stamp_count` / `weight` が昇格することと下方更新しないことを確認。ブラウザでガラポン 4 回上限（`ar-gacha-draws-202611`）と倍率表示を確認（AC-6 / AC-9 / AC-10 / AC-13）
- [x] T47（追加）**サーバー側自動テスト** `tests/Feature/StampRally202611Test.php` を作成 → **完了（2026-10-28）**: `DB_CONNECTION=sqlite DB_DATABASE=:memory: ./vendor/bin/phpunit --filter=StampRally202611Test` = **OK (13 tests, 53 assertions)**。カバー: index の asset_dir（`cg/202610/…`）と `-r2` 不在 / offline・csrf ヘッダ / 収集日内の `scannedAt` 採用（11/10 11:00 JST 保持）/ 期間外は `now()` フォールバック / 日次重複は採用日付基準 / 9 種で 422 `not_eligible` / 10・15・20 種で weight 1・2・3（tier 保存）/ `collect_end` 経過後 11/16 応募成功 / 11/17 は 422 `deadline_passed` / `check-status` だけで weight 1→2 昇格（`updated:true`）と再実行は `updated:false` / 下方更新しない / email 正規化（`user@example.com` 保存）・別 fingerprint 同一 email は 422 `already_applied`・応募行は 1 件 / `prize_exchanges` 無書き込み。**注意**: `php artisan test` は非 TTY で "stdout is not a tty" となるため `./vendor/bin/phpunit` を使う。`RefreshDatabase` は `database/database.sqlite` を消すため **必ず `DB_DATABASE=:memory:` を併用**すること

## Phase 4: 管理ダッシュボード
- [ ] T16 `AdminController::dashboard202611()` を追記（202610 版を複製し、期間を **`config('ar202611.collect_start'|'collect_end')`（Q-12 案A 確定）** の `Asia/Tokyo`→`UTC` 変換に置換。動物リスト `model_01`〜`model_20`。**景品交換の集計は削除**し、`voucherTotal` / `voucherByWeight` / `voucherApplications`（20 件ページネーション・`q` 検索）を追加。応募実績は **`applied_at` ベースの別枠集計**（収集期間後に応募されうる）とし、画面上に収集期間（〜 2026-11-15）と応募期限（2026-11-16 23:59）を併記）
- [ ] T17 `resources/views/admin/dashboard202611.blade.php` を作成（202610 版のルート名・期間ラベル・見出しを 202611 化。応募一覧テーブルと「業者提出用 CSV ダウンロード」ボタンを配置）
- [ ] T18 `routes/web.php` の admin グループに `Route::get('/dashboard202611', [AdminController::class, 'dashboard202611'])->name('admin.dashboard202611');` を追記
- [ ] T46 `AdminController::exportCsv()` の `$allowed` に `voucherApplications202611` を追加し、`name, email, stamp_count, weight, applied_at` を `weight DESC, applied_at ASC`・`chunk(200)` で出力（design §5.6）
- [ ] T19 検証: `php -l` → `GET /admin/dashboard202611` が 200 で収集期間表示が 2026-11-01 〜 2026-11-15・応募期限表示が 2026-11-16 23:59、`GET /admin/export?dataset=voucherApplications202611` が CSV を返すこと（AC-1 / AC-2 / AC-12）

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
- [ ] T31 `js-prize.blade.php` にオフラインキュー（`ar-scan-queue-202611`・上限200・`scannedAt` 保持・4xx/5xx 非再送）と `refreshCsrfToken202611()`、応募直前の `flushScanQueue202611()` → `refreshCsrfToken202611()` を追加。**`scannedAt` は収集時の日時を保持したまま再送する**（サーバー側で収集日として記録される・FR-24 / FR-51）
- [ ] T32 `js-init.blade.php` に online/offline 監視（初期 `navigator.onLine`、`online` 時にトークン更新 → キュー再送、バナー文言に未送信件数）＋ **ページ起動時にも `refreshCsrfToken202611()` → `flushScanQueue202611()` → `checkStatus202611()` を実行**（帰宅後にページを開くだけで未送信分が自動再送され、種数と応募可否が反映される・FR-49 / AC-14）
- [x] T33 検証: SW fetch 戦略シミュレーション（navigate / 静的 cache-first / API 非介入 / 外部オリジン非介入 / offline フォールバック）とキューシミュレーション（AC-4 / AC-5）→ **完了（2026-10-28）**: Node.js（`vm` で SW を sandbox 化し `self` / `caches` / `fetch` を mock）で実施。SW = **48 断言すべて PASS**（install precache 29 件・ball.obj 非 precache / navigation はオンライン→ネットワーク、オフライン→precache 済み HTML、クエリ付きは `ignoreSearch`、他パスは `/offline`、キャッシュ皆無は 504 / 軽量アセットは stale-while-revalidate、重量アセット（`cg/`, `.patt`, `.glb`）は cache-first で背景再取得なし、未キャッシュ＋オフラインは 504 / API POST と `/csrf` と外部オリジンと他キャンペーンには非介入 / activate は `ar202611-*-v0` のみ削除し 202610 用キャッシュは保持 / `CLEAR_CACHES` は `ar202611-*` のみ / `BASE=/find/` 配置でも動作）。キュー = **23 断言すべて PASS**（上限200・時系列順・`scannedAt` 保持・4xx 非再送・419 はトークン更新後に再送・`navigator.onLine=false` では送信しない・再入防止）。**要確認**: `requirements.md` FR-20 の precache 一覧にある `geo-location.min.js` / `loaders/GLTFLoader.js` / `loaders/OBJLoader.js` / `css/stampRally.css` / `objects/ball.obj|ball.mtl` / `sounds/loading.ogg` は 202611 の blade が参照していないため SW の `CORE_URLS`（29 件）に含まれない → md 側の更新要（本タスクでは md を変更しない）

## Phase 7: 最終検証・完了報告
- [ ] T34 全 PHP の `php -l` 再実行（config / Controller / AdminController / routes / AppServiceProvider）
- [ ] T35 識別子監査: `grep -n "202610" resources/views/ARstampRally202611/*.blade.php`（コメントの出典参照を除く）と `grep -n "ar202610" public/sw-stamp202611.js` が 0 件（AC-3）
- [ ] T36 分離監査: `git status --short` で既存キャンペーン（202603 / 202605 / 202606 / 202609 / 202610）と `public/js/`・既存 SW が未変更であることを確認（AC-7）
- [ ] T37 実機検証: Android Chrome / iOS Safari（低スペック機含む）でカメラ起動・マーカー認識・ボール投擲・ガラポンボタン・抽選応募、機内モードで AR 継続とキュー再送（AC-8）
- [ ] T47 運営向け引継ぎメモを README に追記: ガラポンは画面提示により会場スタッフが回数を確認する（アプリは回数を記録しない）、商品券の抽選・当選連絡は業者が CSV を基に行う
- [ ] T48 **帰宅後応募の受け入れ検証（Q-12 案A 確定・AC-14 / AC-15 / AC-16）**: 機内モードで 10 種収集 → 解除してページ再読み込み → 未送信キューが自動再送され `marker_scans.scanned_at` が**収集日**で記録されることを tinker で確認 → `check-status` の `stampCount` = 10 → `apply-voucher` 成功（**`collect_end`（2026-11-15 23:59:59）経過後・`apply_deadline`（2026-11-16 23:59:59）前の再送でも成功**することを確認）。未送信 1 件以上時は応募ボタン非表示＋`#pending-scans` 表示。期間外 / +24h 超の `scannedAt` は `now()` にフォールバック、`apply_deadline` 経過後は 422 `deadline_passed`
- [x] T49〜T53 **検証データ分離（FR-54 / FR-55）と運用手順ガイド**（`?test=1` → fingerprint 前置 / 管理画面・CSV の既定除外 / `?show_test=1` 切替 / `test-data-guide.md`・`asset-swap-guide.md` / 付け忘れ時の事後整理）→ 詳細は末尾の「追記（2026-10-29）」。テスト 17 件（105 assertions）PASS

- [x] T38 `README.md` に完了状況と「`cg/202611` への切替手順」を追記（→ `asset-swap-guide.md` へ集約し README からリンク・T52 で完了）

## 検証結果（未実施）
| 項目 | 結果 |
|---|---|
| AC-1 `php -l` | 未実施（T15 / T34） |
| AC-2 ルート一覧 | 未実施（T15 / T19） |
| AC-3 識別子監査 | 未実施（T11 / T35） |
| AC-4 SW 戦略 | 合格（T33: Node.js シミュレーション 48/48 PASS） |
| AC-5 キュー | 合格（T33: Node.js シミュレーション 23/23 PASS） |
| AC-6 閾値・倍率 | 未実施（T45） |
| AC-7 分離（git status） | 未実施（T36） |
| AC-8 実機 | 未実施（T37） |
| AC-9 ガラポン 4 回上限 | 未実施（T45） |
| AC-10 応募 UI（email 重複・倍率表示） | 未実施（T45） |
| AC-11 DB（migrate / UNIQUE） | 未実施（T39） |
| AC-12 CSV 出力 | 部分合格（T51: `test_admin_dashboard_and_csv_exclude_test_fingerprints` でヘッダ `name,email,stamp_count,weight,applied_at` と行数（検証分除外 / `show_test` 時 1 行）を検証。期間絞り込み（`start` / `end`）は未検証） |
| AC-13 倍率の自動昇格 | 未実施（T45） |
| AC-14 帰宅後応募（Q-12 案A） | 未実施（T48） |
| AC-15 応募ボタン条件（未送信バナー） | 未実施（T48） |
| AC-16 `scannedAt` ガード / `deadline_passed` | 未実施（T48） |
| AC-17 検証データ除外（ダッシュボード / CSV） | 合格（T51: dashboard 既定除外 → `?show_test=1` 表示 → CSV 既定除外 → `&show_test=1` 表示、`/stamp202611` の `TEST_FP_PARAM` / `TEST_FP_PREFIX` / `isTestMode` 出力。2 テスト PASS） |
| AC-18 ガイド（検証データ / アセット切替） | 合格（T52: `test-data-guide.md` / `asset-swap-guide.md` 作成・README からリンク） |


## 参考（202610 での実施履歴 = 本キャンペーンで初期実装に内包する内容）
| 202610 | 内容 | 202611 での扱い |
|---|---|---|
| T15〜T20 / §7 | iOS カメラ誤表示の安全策（監視継続・二重 `getUserMedia` 防止・15000ms） | Phase 5 で初期実装 |
| T21〜T24 / §8 | video セレクタ根本原因（`document.body` 直下 append）→ `querySelector('video')` | Phase 5（T20）で最初から正しいセレクタ |
| T25〜T29 / §9 | 保存キー `-r2` 化による再交換 | Phase 2（T9）で新キー開始のため同等効果が最初から成立 |
| 202610 の景品コード交換（`exchange-prize` / `prize_exchanges`） | 10 種以上で景品コード 1 枚と交換 | **202611 では廃止**。お菓子ガラポン 4 段階（LocalStorage）＋ 商品券抽選応募（新規テーブル）に置き換え（Phase 3B） |
| Phase 9 / §10 | オフライン SW・offline ページ・csrf 再取得・キュー | Phase 6 で初期実装 |

## 追記（2026-10-29）: 検証データ分離（FR-54 / FR-55）と運用ガイド

- [x] T49 `config/ar202611.php` に `test_param`（既定 `test`）と `test_prefix`（既定 `test_`）を追加。
      `js-prize.blade.php` が `@json(config(...))` で注入された `TEST_FP_PARAM` / `TEST_FP_PREFIX` 定数を
      `isTestMode()` が読み、`?test=1` 時に `generateFingerprint()` が `getUserId202611()` の解決値へ `test_` を前置。
      fingerprint は保存されないため「`?test=1` 付きで読み込んだページから送信した分だけ test 扱い」
      （オフラインキューはスキャン時の fingerprint を保持 → 再送時も `test_`）
- [x] T50 `AdminController` に `applyTestFilter()` を追加し、`dashboard202611()` の全集計（総数・個別ユーザー数・
      マーカー別・最近のスキャン・日別統計・日別個別ユーザー数・応募総数・倍率別・応募一覧）と
      `exportCsv()` の 4 dataset に適用。**既定で除外**し `?show_test=1` を付けたときだけ表示。
      `dashboard202611.blade.php` に切替リンク・現在の状態（表示中 / 除外中 + 接頭辞）の表示・検索フォームの
      hidden とページネーション（`withQueryString()`）での `show_test` 維持・CSV リンクへの `show_test` 付与を実装
- [x] T51 テスト 2 件（`test_admin_dashboard_and_csv_exclude_test_fingerprints` /
      `test_prize_view_exposes_test_flag_and_prefix`）で dashboard 既定除外 → `?show_test=1` 表示 →
      CSV 既定除外 → `&show_test=1` 表示と blade 注入値を検証 → **17 tests / 105 assertions すべて PASS**
      （`DB_CONNECTION=sqlite DB_DATABASE=:memory: ./vendor/bin/phpunit --filter=StampRally202611Test`）
- [x] T52 `test-data-guide.md`（検証用 URL・管理画面の切替・CSV・端末リセット手順・限界）と
      `asset-swap-guide.md`（`cg/202611` への切替チェックリスト・キャッシュ対処・ロールバック）を作成し README からリンク
- [x] T53 `test-data-guide.md` に **§5 `?test=1` 付け忘れ時の事後整理**（fingerprint 末尾一致での特定 →
      `test_` 前置への `update()`（非破壊・推奨）/ `fingerprint` 完全一致での `delete()`（応募 → スキャンの順）/
      期間 + `user_agent` での候補特定 / 実施前チェック）と **§6 検証ログ**、§2.1 に「付け忘れが起きる実パターン」表を追加
      （docs のみ・アプリコード変更なし）。README / `requirements.md`（FR-54・AC-17）/ `design.md` §3.4 へ反映し、
      旧 §5〜§7 を §7〜§9 へ繰り下げて相互参照を修正
- **注意点**: `php artisan test` は非 TTY 環境で "stdout is not a tty" となるため `./vendor/bin/phpunit` を使う。
  `RefreshDatabase` が `database/database.sqlite` を削除するため `DB_DATABASE=:memory:` を必ず併用する
- **残タスク**: T20〜T25（iOS カメラ起動の根本修正）、T34〜T37（最終監査・実機検証）、T48（帰宅後応募の実機受け入れ）

