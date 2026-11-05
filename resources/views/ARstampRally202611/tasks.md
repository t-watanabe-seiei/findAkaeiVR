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
- [x] T11 検証: `GET /stamp202611` がレンダリングされ、pattern / lazy-model URL が `cg/{asset_dir}/` であること（AC-2 / AC-3）→ **完了（2026-10-28 Blade 側 / 2026-11-04 切替後に再検証）**: `view('ARstampRally202611')->render()` = 235,283 字、`cg/202611/` 43 箇所（pattern 21 + lazy-model 21 + `Model_00`）、`cg/202610/` **0 箇所**、`pattern-maker` 21、`.glb` 44（共有 `poke_ball_seinei2.glb` 含む）。HTTP 検証は `StampRally202611Test::test_index_renders_with_asset_dir_from_config`（28 tests / 228 assertions PASS）で担保

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
- [x] T34 全 PHP の `php -l` 再実行（config / Controller / AdminController / routes / AppServiceProvider）→ **完了（2026-11-04）**: `config/ar202611.php` / `app/Http/Controllers/AdminController.php` / `tests/Feature/StampRally202611Test.php` すべて `No syntax errors`（T15 で Controller / routes / AppServiceProvider も確認済み）
- [x] T35 識別子監査: `grep -n "202610" resources/views/ARstampRally202611/*.blade.php`（コメントの出典参照を除く）と `grep -n "ar202610" public/sw-stamp202611.js` が 0 件（AC-3）→ **完了（2026-11-04）**: blade は `js-init.blade.php:485` のコメント 1 件のみ（出典参照・許容）、`sw-stamp202611.js` の `ar202610` は **0 件**
- [x] T36 分離監査: `git status --short` で既存キャンペーン（202603 / 202605 / 202606 / 202609 / 202610）と `public/js/`・既存 SW が未変更であることを確認（AC-7）→ **完了（2026-11-04）**: 変更は 202611 側の 5 ファイル（`config/ar202611.php` / `AdminController.php` / `js-stamps.blade.php` / `sw-stamp202611.js` / テスト）と新規 `public/cg/202611/` 42 点のみ。`public/cg/202610/` と既存キャンペーンファイルは未変更
- [ ] T37 実機検証: Android Chrome / iOS Safari（低スペック機含む）でカメラ起動・マーカー認識・ボール投擲・ガラポンボタン・抽選応募、機内モードで AR 継続とキュー再送（AC-8）
- [ ] T47 運営向け引継ぎメモを README に追記: ガラポンは画面提示により会場スタッフが回数を確認する（アプリは回数を記録しない）、商品券の抽選・当選連絡は業者が CSV を基に行う
- [ ] T48 **帰宅後応募の受け入れ検証（Q-12 案A 確定・AC-14 / AC-15 / AC-16）**: 機内モードで 10 種収集 → 解除してページ再読み込み → 未送信キューが自動再送され `marker_scans.scanned_at` が**収集日**で記録されることを tinker で確認 → `check-status` の `stampCount` = 10 → `apply-voucher` 成功（**`collect_end`（2026-11-15 23:59:59）経過後・`apply_deadline`（2026-11-16 23:59:59）前の再送でも成功**することを確認）。未送信 1 件以上時は応募ボタン非表示＋`#pending-scans` 表示。期間外 / +24h 超の `scannedAt` は `now()` にフォールバック、`apply_deadline` 経過後は 422 `deadline_passed`
- [x] T49〜T53 **検証データ分離（FR-54 / FR-55）と運用手順ガイド**（`?test=1` → fingerprint 前置 / 管理画面・CSV の既定除外 / `?show_test=1` 切替 / `test-data-guide.md`・`asset-swap-guide.md` / 付け忘れ時の事後整理）→ 詳細は末尾の「追記（2026-10-29）」。テスト 17 件（105 assertions）PASS

- [x] T38 `README.md` に完了状況と「`cg/202611` への切替手順」を追記（→ `asset-swap-guide.md` へ集約し README からリンク・T52 で完了）

## 検証結果（未実施）
| 項目 | 結果 |
|---|---|
| AC-1 `php -l` | 合格（T15 / T34: 2026-11-04 再実行で config / AdminController / テストすべて `No syntax errors`） |
| AC-2 ルート一覧 | 合格（T15 / T19 / T60: 6 ルート + `admin.dashboard202611` / `admin.export dataset=voucherApplications202611`。`GET /stamp202611` は 200） |
| AC-3 識別子監査 | 合格（T11 / T35: blade はコメント 1 件のみ、SW の `ar202610` 0 件、`202610-r2` 0 件） |
| AC-4 SW 戦略 | 合格（T33: Node.js シミュレーション 48/48 PASS） |
| AC-5 キュー | 合格（T33: Node.js シミュレーション 23/23 PASS） |
| AC-6 閾値・倍率 | 未実施（T45） |
| AC-7 分離（git status） | 合格（T36: 2026-11-04 監査で 202611 側 5 ファイル + `public/cg/202611/` 42 点のみ変更。既存キャンペーン / `public/js/` / 既存 SW / `cg/202610/` は未変更） |
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
| AC-19 端末別種数（FR-56 / FR-57） | 合格（T55 / T56 / T57: `deviceStampCountFor()` と `device_*` 列で判定、backfill は遡及降格なし。T58 の 6 テスト追加でクラス 23 tests / 182 assertions PASS） |
| AC-20 収集のみで応募資格（FR-58） | 合格（T60 / T61 / T62: `deviceStampCountFor()` が `capture_type='ball_hit'` だけ数え、`detectedStampCountFor()` は参考値。T63 の 4 テスト追加でクラス 27 tests / 212 assertions PASS。T65 の本番 DB への recount は実施前） |


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


---

## 追記（2026-10-30）: 応募資格・当選倍率を 1 端末（fingerprint）単位へ（FR-56 / FR-57・AC-19）

背景: 2026-11-01 に同一ブラウザ（同一 `session_id`）で 2 端末を使った利用者が合算 15 種で 2 倍を取得した。
`stampCountFor()` の `session_id` OR `fingerprint` 集計が別端末の種数を自端末へ誤帰属させた（design.md §3.5）。

### Phase 7 追加: 端末単位の正本列と grandfathering

- [x] **T54** migration `2026_11_02_000000_add_device_stamp_columns_to_stamp202611_voucher_applications_table`:
  `device_stamp_count` / `device_tier` / `device_weight`（nullable）を `stamp_count` の後ろに追加、
  `(device_weight, applied_at)` index。既存の `stamp_count` / `tier` / `weight` は合算値として保持（非破壊）
- [x] **T55** `StampRally202611Controller` へ `deviceStampCountFor()` / `syncDeviceVoucherWeight()` を追加し、
  `checkStatus()` / `applyVoucher()` の応募可否・倍率を 1 端末種数で判定。API へ `deviceStampCount` /
  `voucher.deviceWeight` / `voucher.deviceStampCount` / `voucher.stampCount` を追加
  （`syncVoucherWeight()` は合算列だけ更新し、weight の合算昇格は `device_weight` が NULL の行だけ）
- [x] **T56** `app/Console/Commands/BackfillStamp202611DeviceWeight.php`（`stamp202611:backfill-device-weight [--dry-run]`）で
  既存応募行の `device_*` を `marker_scans` から埋め戻す。`max(既存 weight, weightFor(1 端末種数))` で遡及降格せず冪等
- [x] **T57** `admin/dashboard202611` に「1端末種数（正本）」列を併記（合算との差分種数・grandfathering 注記）、
  CSV `voucherApplications202611` へ `device_stamp_count` / `device_weight` を追加
- [x] **T58** `StampRally202611Test` へ 6 テスト追加（`test_voucher_eligibility_and_weight_are_per_device` /
  `test_check_status_upgrades_weight_only_from_own_device_count` / `test_device_sync_never_downgrades_granted_weight` /
  `test_backfill_command_fills_device_columns_without_downgrading_weight` /
  `test_check_status_keeps_grandfathered_weight` / `test_admin_dashboard_lists_both_aggregate_and_device_counts`）
  → クラス **23 tests / 182 assertions PASS**

- [x] **T59**（追加）`test-data-guide.md` へ **§10「応募倍率の 1 端末基準化（FR-56 / FR-57）を本番 DB へ
  反映する手順」**を追加（バックアップ → `php artisan migrate` → `stamp202611:backfill-device-weight --dry-run`
  での差分確認 → 本実行 → ダッシュボード / CSV での確認。検証行（`test_`）も backfill 対象である注意）。
  併せて §5.6 の注記を「応募可否・倍率は 1 端末単位（`deviceStampCountFor()`）／`stampCountFor()` は合算で
  監査・表示用」へ更新し、§9 のテスト実績を 23 tests / 182 assertions へ更新

### Phase 8: 202611 専用アセットへの切替（FR-32 / FR-33 / FR-36・2026-11-04）

- [x] **T60** `public/cg/202611/` の 42 点（`Model_00..20.glb` / `pattern-maker00..20.patt`）受け入れと切替
  - 受け入れ監査: 42 点すべて存在（欠番なし）。`pattern-maker*.patt` は 202610 と md5 が**不一致**
    （= 202611 専用のマーカー。会場印刷物は差し替えが必要で、202610 の旧マーカーは認識されない）
  - `config/ar202611.php`: `asset_dir` 既定を `'202610'` → **`'202611'`**（`.env` の `AR202611_ASSET_DIR` で上書き可能）
  - `public/sw-stamp202611.js`: `VERSION` `'v1'` → **`'v2'`** / `ASSET_DIR` → **`'202611'`**
    （cache-first のため VERSION を上げないと旧 `cg/202610` コピーが残り続ける。`activate` で `ar202611-*-v1` を破棄）
  - 動物名 20 種を 202611 素材へ更新（**2 箇所同時**）: `js-stamps.blade.php` の `STAMPS[].name` と
    `AdminController::dashboard202611()` の `$animals`。一覧は `asset-swap-guide.md` §2.1
    （`model_01` ぶた① … `model_20` バーガー。キー `model_01..model_20` = `marker_id` は 202610 と同一のまま不変）
  - `StampRally202611Test::test_index_renders_with_asset_dir_from_config` の期待値を `cg/202611/…` へ更新し、
    動物名（`ぶた①` / `バーガー`）の出力も検証
  - 検証: `php -l` 3 件 OK / `node --check public/sw-stamp202611.js` OK /
    `DB_CONNECTION=sqlite DB_DATABASE=:memory: phpunit --filter=StampRally202611Test` = **28 tests・228 assertions PASS** /
    `view('ARstampRally202611')->render()` = 235,283 字・`cg/202611/` 43・`cg/202610/` 0・`config('ar202611.asset_dir')` = `202611`
  - **未実施（本番運用）**: `php artisan config:cache` の再実行、`GET /stamp202611` のブラウザ実機確認
    （DevTools で `cg/202611/…` の 200 と `ar202611-*-v2` キャッシュ生成、`-v1` の消滅）、202610 印刷物の回収

### 残（FR-56 / FR-57 関連の運用）

- [ ] 本番 DB への migration 適用 → `stamp202611:backfill-device-weight --dry-run` で差分確認 → 本実行
  （実行前に `stamp202611_voucher_applications` をバックアップ。手順は `test-data-guide.md` §10）
- [ ] 業者提出 CSV の `weight`（= `device_weight`）で抽選を行うことを運営へ周知。
  合算のみで閾値へ到達した応募者を 1 端末基準で差し戻す運用は行わない（FR-57）


---

## 追記（2026-11-02）: 応募資格と倍率は「収集（ball_hit）」だけで判定（FR-58・AC-20）

背景: `deviceStampCountFor()` が `capture_type` を絞らず distinct `marker_id` を数えていたため、
ボールを当ててコレクションに入った種数ではなく **マーカーを見つけた種数（`marker_scan`）** だけで
閾値へ到達・2 倍化していた応募者が発生していた（design.md §3.6）。

### Phase 7 追加: 収集 / 検出の分離

- [x] **T60** `StampRally202611Controller`: `deviceStampCountFor()` に `where('capture_type', 'ball_hit')` を追加
  （応募資格・倍率の正本）。`detectedStampCountFor()`（`capture_type='marker_scan'` の distinct 数）を新設し、
  `checkStatus()` の応答へ `deviceDetectedCount`、`applyVoucher()` の 422 `not_eligible` へ
  `deviceStampCount` / `deviceDetectedCount` / `voucherThreshold` と収集基準の文言を追加。
  合算 `stampCountFor()` は検出込みのまま監査・表示用として保持（`stamp_count` / `tier` に保存）
- [x] **T61** `js-prize.blade.php`（`updateVoucherUI202611()`）: 応募可否を `deviceStampCount` だけで判定。
  スタンプ帳の `#voucher-status` を「この端末の収集: N 種（抽選の基準・この端末 X 種）」を正本行とし、
  検出 1 種以上なら「この端末が見つけたマーカー: M 種（抽選には含めません）」、
  合算 > 収集 のときだけ「同一ブラウザの合算: K 種（参考）」を併記。
  収集が閾値未満で検出が閾値以上のときはボタンを `disabled` にし
  「マーカーを M 種見つけていますが、抽選にはボールを当てて収集した種数だけ使えます」を表示
  （ガラポンの到達判定は LocalStorage の収集種数のまま・FR-38 / FR-39）
- [x] **T62** `BackfillStamp202611DeviceWeight`: `device_stamp_count` / `device_tier` の算出を `ball_hit` 基準へ変更。
  既定モードは上方更新のみ（FR-57 踏襲）で、水増し値の是正はスキップして `--recount-device-count` へ委ねる。
  **`--recount-device-count`**（新設）は `device_stamp_count` / `device_tier` を収集基準で再集計し**下方更新も許容**するが、
  付与済みの `weight` / `device_weight` は降格させない（grandfathering 維持）。`--dry-run` と併用可・冪等
- [x] **T63** `StampRally202611Test`: `seedScansUnderSession()` を既定 `ball_hit` にし、検出専用の
  `seedDetections()` ヘルパーを追加。回帰テスト 4 件
  （`test_marker_scan_only_records_do_not_qualify_for_voucher` /
  `test_check_status_reports_collected_and_detected_counts` /
  `test_collected_count_at_threshold_qualifies_even_with_extra_detections` /
  `test_backfill_recount_corrects_inflated_device_count_without_downgrading_weight`）
  → クラス **27 tests / 212 assertions PASS**
  （`DB_CONNECTION=sqlite DB_DATABASE=:memory: ./vendor/bin/phpunit --filter=StampRally202611Test`）
- [x] **T64** `requirements.md` へ FR-58 / AC-20、`design.md` へ §3.6（収集と検出の分離・API 応答・
  backfill の 2 モード）、§5.2 / §5.3 の返却値、§8.6 の表示仕様、§10 のテスト一覧、AC-20 行を追記。
  `README.md` へ 2026-11-02 節（変更一覧と本番適用手順）、`test-data-guide.md` へ §9 のテスト数更新と
  §10.1（`--recount-device-count` の手順）を追記

### T65 本番反映手順（FR-58）

- [x] **T65-0** 検証: `php -l`（コントローラ / コマンド / テスト）と
  `DB_CONNECTION=sqlite DB_DATABASE=:memory: ./vendor/bin/phpunit --filter=StampRally202611Test`
  → **27 tests / 212 assertions PASS**。クラス全体（`./vendor/bin/phpunit`）は 31 tests 中
  `Tests\Feature\ExampleTest`（`GET /` が 404）だけが残存失敗（本変更とは無関係の既存失敗）
- [x] **T65-1** ローカル: `database/database.sqlite` を退避 → 実行後に削除（応募行 0 件で DB 変更なし）
- [x] **T65-2** ローカル: `optimize:clear` を実行（cache / compiled / config / events / routes / views すべて DONE）。
  非 TTY シェルでは `php artisan …` が "stdout is not a tty" で失敗するため、
  `bootstrap/app.php` を読む一時スクリプトから `$kernel->call('optimize:clear', [], new BufferedOutput())` を実行した
- [x] **T65-3** ローカル: `stamp202611:backfill-device-weight --recount-device-count --dry-run`
  → `dry-run / recount-device-count: 対象 0 件 / 更新 0 件（grandfathering 0 件・下方更新スキップ 0 件）`
  （ローカル DB には 202611 の応募行・収集期間内の `marker_scans` がなく、是正対象なし）
- [ ] **T65-4** **本番 DB での実行（未実施）**: ① バックアップ → ② `php artisan optimize:clear`
  （OPcache を使っている場合は `opcache_reset()` か PHP / Web サーバー再起動）→
  ③ `stamp202611:backfill-device-weight --recount-device-count --dry-run` で「収集基準で減る行」と
  「weight はそのまま」を確認 → ④ `--dry-run` を外して本実行 → ⑤ 再度 `--dry-run` で差分 0（冪等）を確認
- [ ] **T65-5** ダッシュボード（`device_stamp_count` 列）と CSV `voucherApplications202611`
  （`device_stamp_count` / `device_weight`）で是正結果を確認。業者提出は `device_weight` を正本とする

---

## 追記（2026-11-02）: 抽選 UI の参考値行を検証モード限定へ（FR-59・AC-21）

背景: 本番のスタンプ帳に「この端末が見つけたマーカー 11 種」「同一ブラウザの合算 11 種（参考）」などの
参考値行が出ており、利用者へ「検出しても抽選対象にならない」説明が先回りになっていた。
検証時は FR-58 の収集 / 検出判定を確認するため同じ行が必要なので、**検証モードだけに表示**する。

### Phase 7 追加: 参考値行の表示ゲート（案A・クライアント側）

- [x] **T66** `js-prize.blade.php`: `isTestMode()` の直後へ `const VOUCHER_DIAGNOSTICS_202611 = isTestMode();`
  を追加（fingerprint 前置字と同じ単一ソース）。`updateVoucherUI202611()` の参考値行 3 箇所
  （検出種数 / 同一ブラウザ合算 / 閾値未満時の注意文）を `VOUCHER_DIAGNOSTICS_202611 && ...` で囲む。
  正本行（収集種数）・応募期限・応募ボタン、`check-status` の返却値、管理画面 / CSV は変更しない
- [x] **T67** `StampRally202611Test`: `test_voucher_diagnostics_lines_are_gated_to_test_mode` を追加
  （`/stamp202611` と `/stamp202611?test=1` の HTML で、ゲート定義の出力と 3 文言がすべて
  `VOUCHER_DIAGNOSTICS_202611` ゲート内侧にあることを検査）→ クラス **28 tests / 226 assertions PASS**
- [x] **T68** ドキュメント: `requirements.md` へ FR-59 / AC-21、`design.md` へ §8.6 の表示仕様と §10 の
  テスト一覧・AC-21 行、`test-data-guide.md` へ §2.4（本番 / 検証で見える行の一覧）と §9 のテスト数、
  `ui.blade.php` の `#voucher-section` コメント、`README.md` へ 2026-11-02 節を追記
- [x] **T69** 検証: `php -l`（`js-prize.blade.php` / `ui.blade.php` / `StampRally202611Test.php`）すべて OK、
  `DB_CONNECTION=sqlite DB_DATABASE=:memory: ./vendor/bin/phpunit --filter=StampRally202611Test` →
  **OK (28 tests, 226 assertions)**
- [ ] **T69-1** 本番反映: `php artisan optimize:clear`（非 TTY では T65-2 の BufferedOutput 方法。
  OPcache 利用時は `opcache_reset()` か PHP 再起動）→ `/stamp202611` で参考値行 3 行が消えること、
  `/stamp202611?test=1` で 3 行が戻ることを実機で目視確認（`test-data-guide.md` §2.4）

### 残（FR-58 関連の運用）

- [ ] 検出のみで閾値へ到達していた応募者へ「収集種数ベースで抽選倍率が下がった（または応募資格が消えた）」旨を
  運営が案内するかどうか決定する（FR-57 の「遡及降格しない」は **付与済み weight** に限定され、
  `device_stamp_count` の是正は FR-58 で行うことを運営へ周知）
- [ ] `capture_type` の運用（`marker_scan` は検出のみ・`ball_hit` は収集完了）がクライアント実装と一致していることを
  実機で確認（T37 / T48 と併せて実施）


## 追記（2026-11-04）: ガラポンのスタッフ案内・倍率バッジ（FR-60）と応募のメールアドレス確認（FR-61・AC-22）

背景: ①ガラポンは会場本部でのみ回せるため、スタンプ帳に「スタッフにこの画面をお見せください」が常時必要で、
当選倍率（2 倍 / 3 倍）が視認しにくい。②抽選応募がメールアドレス入力のだけで、未実在アドレスの応募が
業者提出 CSV に混ざる。そこで ①UI の強調、②6 桁コードによるメールアドレス確認を導入する。

### Phase 3B 追加: UI の強調（FR-60）

- [x] **T70** `ui.blade.php`: `#gacha-section` へ `.gacha-staff-note`（「ガラポンは会場本部スタッフにこの画面をお見せください」）を常時表示
- [x] **T71** `head.blade.php`: `.voucher-weight-badge`（`--x2` 橙 / `--x3` 赤 / `--base` グレー小型）と `.gacha-staff-note` の CSS を追加。`js-prize.blade.php` の `VOUCHER_WEIGHT_INFO202611` / `voucherWeightBadge202611()` / `voucherToastHTML202611()` がバッジ要素で倍率を描画（文言は固定値のみ・外部入力を挿入しない）

### Phase 3B 追加: メールアドレス確認（FR-61）

- [x] **T72** migration `2026_11_04_000000_add_email_verification_to_stamp202611_voucher_applications_table`:
  `email_code_sent_at` / `email_verified_at`（nullable）を追加し、既存行へ `email_verified_at = applied_at`（grandfathering）
- [x] **T73** `Stamp202611VoucherApplication`: 2 列を fillable / datetime cast へ追加、`isEmailVerified()` / `isLegacyEmailApplication()`
- [x] **T74** `config/ar202611.php`: `email_verification`（`enabled` / `code_ttl` 900 / `max_attempts` 5 / `resend_interval` 30 / `dns_check`（既定 false・T82 で修正））を env 付きで追加
- [x] **T75** `App\Mail\Stamp202611VoucherCodeMail` + `resources/views/emails/stamp202611/voucher-code.blade.php`（同期送信・コードは本文のみ）
- [x] **T76** `StampRally202611Controller`: `requestEmailCode()`（期限 → 資格 → 応募済み → 送信間隔の順で検証し Cache へ 6 桁を TTL 付き保持）、
  `consumeEmailCode()`（`hash_equals` 一致で Cache 削除・試行回数管理）、`applyVoucher()` は一致後に応募行を作る（`code` は `nullable|max:10`）。
  `check-status` へ `voucher.emailVerified`、`maskEmail()` でマスク済みアドレスのみ返す
- [x] **T77** `routes/web.php` へ `POST /voucher-email-code`（`throttle:stamp202611_email_code`）、`AppServiceProvider` へ RateLimiter（1 分 6 回 / fingerprint）
- [x] **T78** `ui.blade.php` / `js-prize.blade.php`: 2 ステップフォーム（`#voucher-step-email` → `#voucher-email-confirm`（送信先を 2 押し込み確認）→ `#voucher-step-code`）、
  `normalizeEmailInput202611()`（全角 → 半角・スペース除去）、`requestVoucherEmailCode202611()`（CSRF 陳腐化は 1 回だけ再試行）、
  `applyVoucher202611(email, name, code)`、`verification_required: false` は step2 スキップ。`head.blade.php` に 2 ステップ用の CSS
- [x] **T79** 管理画面 / CSV: `dashboard202611` の応募一覧に確認状態（確認済み / 旧方式 / 未確認）、`AdminController::exportCsv()` の
  `voucherApplications202611` へ `email_verified_at` 列

### 検証（T80）

- [x] `php -l`: `StampRally202611Controller.php` / `AdminController.php` / `Stamp202611VoucherApplication.php` / `StampRally202611Test.php` すべて OK
- [x] `DB_CONNECTION=sqlite DB_DATABASE=:memory: ./vendor/bin/phpunit --filter=StampRally202611Test` → **OK (37 tests, 294 assertions)**
  （追加 8 件: コード送信と Cache 保持／コード一致で応募完了（未送信・空・誤コードは 422）／試行上限で破棄／
  再送信間隔 429／資格なしは送信しない／応募済みアドレスは送信しない／`check-status` の `emailVerified`／grandfathering、
  ほかメール本文の描画確認 1 件）
- [x] 全スイート: 既存の `Tests\Feature\ExampleTest`（`GET /` → 404）以外の失敗なし（`/` ルートを持たない既存の既知失敗）

### T81 本番反映手順（FR-61）

- [ ] `.env` へ SMTP 設定: `MAIL_MAILER=smtp` / `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_ENCRYPTION` /
  `MAIL_FROM_ADDRESS=subscription@seiei.online` / `MAIL_FROM_NAME`（現在の `.env` は `MAIL_MAILER=log` = コードがメールで届かない）
- [ ] `php artisan migrate --force`（2 列追加 + 既存行の `email_verified_at = applied_at`）。実行前に `stamp202611_voucher_applications` をバックアップし、
  実行後に `SELECT COUNT(*) WHERE email_verified_at IS NULL` が 0 であることを確認（grandfathering 完了）
- [ ] `php artisan optimize:clear`（非 TTY での実行方法は T65-2 参照。OPcache 利用時は `opcache_reset()` か PHP 再起動）
- [ ] 実機確認: 10 種以上の端末で「確認コードを送信」→ 送信先確認 → メール受信 → コード入力 → 応募完了トースト（倍率バッジ表示）。
  誤コード 1 回で「残り N 回」表示、60 秒未満の再送信で「送信間隔です」表示
- [ ] 管理画面で `email_verified_at` が「確認済み」、既存応募が「旧方式（grandfathering）」と表示されること、
  CSV に `email_verified_at` 列が入ることを確認
- [ ] メールが届かない場合の切り分け: `MAIL_FROM_ADDRESS` のドメイン権限 → SMTP 認証（502 `mail_failed` が返る）→
  一時的に `AR202611_EMAIL_VERIFICATION=false` へ（応募は 1 ステップに戻る）

### Phase 3B 修正（2026-11-05）: 確認コード送信の 500（`validateDns` 未定義）

背景: 本番で `POST /stamp202611/voucher-email-code` が
`BadMethodCallException: Method Illuminate\Validation\Validator::validateDns does not exist`（500）で失敗し、
確認コードが送信できなかった。原因は `email` のルールへ `|dns` を連結していたこと
（Laravel 11.20.0 には `dns` ルールが存在しない。`email:rfc,dns` の `dns` 引数とは別物）。
既存テストはすべて `dns_check => false` のため、この経路を通らず失敗を検出できていなかった。

- [x] **T82** `config/ar202611.php`: `email_verification.dns_check` の既定を `true` → `false`（env `AR202611_EMAIL_DNS_CHECK`）
- [x] **T83** `StampRally202611Controller::requestEmailCode()`: ルールを `required|email|max:190` に固定（`|dns` を撤去）。
  正規化後、`dns_check` が true のときだけ新 `emailDomainExists()`（`checkdnsrr()` の MX → A。関数不在・DNS 参照の例外は通過）で照会し、
  解決できなければ 422 `invalid_email`（メール送信なし）。`emailVerificationOptions()` の既定値も false へ
- [x] **T84** `tests/Feature/StampRally202611Test.php`: DNS 系 3 テスト追加（`dns_check=true` で 500 にならない／
  解決しないドメイン（`.invalid`）は 422 `invalid_email` でメール 0 通／`emailDomainExists()` の境界挙動）
  → `DB_CONNECTION=sqlite DB_DATABASE=:memory: ./vendor/bin/phpunit --filter=StampRally202611Test` **OK (40 tests, 304 assertions)**
- [ ] **T85** 本番反映: `.env` へ `AR202611_EMAIL_DNS_CHECK=false`（既定も false のため未設定でも復旧する）→
  `php artisan optimize:clear`（OPcache 時は `opcache_reset()` か PHP 再起動）→ 実機でコード送信が 200 になることを確認。
  500 が残る場合は `storage/logs/laravel.log` の `validateDns` 行と `php artisan tinker` の `config('ar202611.email_verification')` を確認

### 残（FR-61 関連の運用）

- [ ] 確認コードのメール文面（差出人・件名・本文）を運営へ確認してもらう（`resources/views/emails/stamp202611/voucher-code.blade.php`）
- [ ] 抽選実施前に `email_verified_at` が NULL の行（未確認）が存在しないこと運用で確認する（FR-61 では未確認行を作らないため 0 件のはず）
- [ ] `AR202611_EMAIL_DNS_CHECK=true` を有効化している場合だけ、参加者のドメインが 422 `invalid_email` になっていないか本番初日に監視する（既定は false で DNS 参照しない。DNS 参照そのものの失敗は通過する設計）

## 追記（2026-11-05）: ガラポン前の確認（FR-62）と応募後のメールアドレス変更（FR-63・AC-23 / AC-24）

背景: ①ガラポンボタンを誤タップすると即座に「引き済み」になり、その段階の引抽権を失う（会場での目視確認前に消費される）
②応募時に入力ミスで届かないメールアドレスへ応募していると、参加者自身に変更手段が無い（事務局メールの往復になる）。

### Phase 3B 追加: ガラポン引抽前の確認ダイアログ（FR-62）

- [x] **T86** `ui.blade.php`: `#gacha-confirm-overlay` / `#gacha-confirm-dialog`（`role="alertdialog"`・`#gacha-confirm-message`・
  `#gacha-confirm-yes` / `#gacha-confirm-no`）を既存の `#confirm-dialog` と並べて追加
- [x] **T87** `head.blade.php`: 既存 `#confirm-dialog` の CSS セレクタへ `#gacha-confirm-*` を併記し、
  `.confirm-yes` だけ橙色（`#ff8c00`）にして「はい = 引き済み確定」を視覚化
- [x] **T88** `js-prize.blade.php`: `openGachaConfirm202611()` / `closeGachaConfirm202611()` / `confirmGachaDraw202611()` /
  `initGachaConfirm202611()`（保留段階は `_gachaPendingTier202611`）を追加。`renderGacha202611()` のクリックハンドラは
  引抽済み → トーストだけ / 未到達 → トーストだけ / それ以外 → 確認ダイアログ（この時点では `drawGacha202611()` を呼ばない）
- [x] **T89** `js-init.blade.php` へ `initGachaConfirm202611()`（1 回だけ結線）、`js-throw.blade.php` の UI 要素除外リストへ
  `#gacha-confirm-dialog` / `#gacha-confirm-overlay` を追加（AR 画面のタップ処理と干渉させない）

### Phase 3B 追加: 応募後のメールアドレス変更（FR-63）

- [x] **T90** `config/ar202611.php`: `email_change_deadline`（既定 `2026-11-30 23:59:59`・env `AR202611_EMAIL_CHANGE_DEADLINE`）。
  `.env.example` へコメント付きで追記
- [x] **T91** `StampRally202611Controller::emailChangeDeadline()`（`Asia/Tokyo`・未設定/空文字は既定、parse 失敗は `applyDeadline()`）。
  `checkStatus()` の返却へ `emailChangeDeadline` と `voucher.email`（`maskEmail()` 済み）を追加
- [x] **T92** `emailCodeCacheKey($fingerprint, $purpose)` / `consumeEmailCode(..., $purpose)` へ `'change'` を追加
  （キー `ar202611:voucher-email-change:{fingerprint}`・応募用コードと使い回さない）
- [x] **T93** `requestEmailChangeCode()`（期限 → 応募行 → `same_email` → DNS → `email_in_use` → 確認無効 → 送信間隔の順）と
  `changeVoucherEmail()`（同じ検証後にコード一致でだけ `email` / `email_code_sent_at` / `email_verified_at` を更新。
  UNIQUE 競合は `refresh()` で戻し 422 `email_in_use`）。`findApplicationForChange()` は `checkStatus()` と同一単位
- [x] **T94** `routes/web.php` へ `POST /voucher-email-change-code`（`throttle:stamp202611_email_change_code`・1 時間 3 回）と
  `POST /voucher-email-change`（`throttle:stamp202611_email_change`・1 時間 10 回）、`AppServiceProvider` へ RateLimiter 2 種
- [x] **T95** `Stamp202611VoucherCodeMail` へ `purpose`（既定 `'apply'`）を追加し、`'change'` は件名と本文を差し替え
  （「このコードを入力しなければメールアドレスは変わりません」）
- [x] **T96** `ui.blade.php`: `#voucher-applied-info`（応募したメールアドレス＋変更ボタン）と `#voucher-change-form`
  （2 ステップ・「やめる」）、`#voucher-lottery-note`（「※11月末頃に抽選し、当選者のみ事務局より御連絡します。」）を追加
- [x] **T97** `js-prize.blade.php`: `updateVoucherAppliedInfo202611()`（`updateVoucherUI202611()` 末尾から呼ぶ・期限経過後はボタン非表示）、
  `isEmailChangeDeadlinePassed202611()`、`requestVoucherEmailChangeCode202611()`、`changeVoucherEmail202611()`（419 は 1 回だけ再試行、
  成功後は `checkStatus202611()` で表示を同期）、`initVoucherEmailChange202611()`。エラー文言は `voucherCodeErrorText202611()` へ集約
- [x] **T98** `public/sw-stamp202611.js`: `VERSION` を `v2` → `v3`（ページ HTML は network-first だが、オフライン利用者の
  初回キャッシュに新 UI が確実に反映されるように上げる）

### 検証（T99）

- [x] **T99** `php -l`（`StampRally202611Controller.php` / `config/ar202611.php` / `Stamp202611VoucherCodeMail.php` /
  `AppServiceProvider.php` / `routes/web.php` / `StampRally202611Test.php`）すべて `No syntax errors`。
  `DB_CONNECTION=sqlite DB_DATABASE=:memory: ./vendor/bin/phpunit --filter=StampRally202611Test`
  → **OK (48 tests, 398 assertions)**（変更系 8 テスト追加: 応募行無し 422 `not_applied` / 別キー送信 /
  コード一致必須 / `same_email`・`email_in_use` / 期限経過後 422 / `check-status` のマスク済みアドレスと期限 /
  `enabled=false` は無送信で変更可 / 再送信 429 `too_soon`）。
  描画検証は一時スクリプトで `/stamp202611` の HTML を書き出し、インライン 6 スクリプトすべて
  `node --check` OK、`#gacha-confirm-dialog`・`#voucher-applied-info`・`#voucher-change-form`・
  `initGachaConfirm202611()`・`initVoucherEmailChange202611()` の出力を確認（スクリプトと HTML は検証後に削除）
- [ ] **T100** 実機受け入れ（ブラウザ目視）: ガラポン 5 種で「いいえ」→ `ar-gacha-draws-202611` が空のまま、
  「はい」→ `[5]`。応募後の画面でマスク済みアドレスと「登録したメールアドレスを変更する」が表示され、
  変更フロー（新しいアドレスへコード → 入力）で `email` が変わり応募倍率が変わらないこと。
  手順は `test-data-guide.md` §2.5
- [ ] **T101** 本番反映: 新規ファイルはなし。`routes/web.php` / `AppServiceProvider.php` / `config/ar202611.php` /
  `StampRally202611Controller.php` / `Stamp202611VoucherCodeMail.php` / メールビュー / blade 4 枚 / `sw-stamp202611.js` をアップロード →
  `php artisan optimize:clear`（非 TTY では T65-2 の BufferedOutput 方法。OPcache 時は `opcache_reset()` か PHP 再起動）→
  実機でガラポン確認と変更フローを確認。**DB 変更（migration）は不要**（既存の `email` / `email_code_sent_at` / `email_verified_at` を使う）

### T102: 確認コードの打ち間違い復旧と SQLite のロック解消（FR-61 / FR-64・2026-11-05）

- [x] **T102-1** `config/database.php` の sqlite 接続に `journal_mode=WAL` / `busy_timeout=5000` /
  `synchronous=NORMAL` を追加（`DB_SQLITE_JOURNAL_MODE` / `DB_SQLITE_BUSY_TIMEOUT` / `DB_SQLITE_SYNCHRONOUS` で調整）
- [x] **T102-2** `bootstrap/app.php` の `withExceptions()` で `stamp202611/*` の 429 を JSON
  `{success:false, error:"too_many", waitSeconds}`、`database is locked` の `QueryException` を
  503 JSON `{success:false, error:"server_busy"}` へ変換（`Exceptions::throttle()` はレポート回数を抑える API なので使わない）
- [x] **T102-3** `requestEmailCode()` / `requestEmailChangeCode()` の `resend_interval` 判定を
  **Cache entry の `email` と同じアドレス宛だけ**に限定（違うアドレスへの送信＝打ち間違いの修正は待たせない）
- [x] **T102-4** `email_verification.code_send_limit_per_hour`（既定 6・`AR202611_EMAIL_CODE_LIMIT_PER_HOUR`）を追加し
  `stamp202611_email_code` / `stamp202611_email_change_code` を 1 時間 3 回 → 6 回へ
- [x] **T102-5** `applyVoucher()` / `changeVoucherEmail()` の `catch (QueryException)` に `isDatabaseLock()` を入れ、
  ロックは `already_applied` / `email_in_use` に変換せず再スロー（`changeVoucherEmail()` は `refresh()` 前に抜ける）
- [x] **T102-6** `js-prize.blade.php` のコード送信系 fetch へ `Accept: application/json` を付け、
  `voucherCodeErrorText202611()` へ `too_many` / `server_busy` の文言分岐を追加
  （`too_soon` は「アドレスを直した場合はそのままもう一度押してください」へ更新）
- [x] **T102-7** `.env.example` へ `DB_SQLITE_JOURNAL_MODE` / `DB_SQLITE_BUSY_TIMEOUT` /
  `DB_SQLITE_SYNCHRONOUS` / `AR202611_EMAIL_CODE_LIMIT_PER_HOUR` を追記
- [x] **T102-8** テスト 4 本追加（`test_email_code_resend_to_corrected_email_is_immediate` /
  `test_email_change_code_resend_to_corrected_email_is_immediate` /
  `test_email_code_throttle_returns_json_too_many` / `test_sqlite_lock_returns_json_503`）。
  `StampRally202611Test` は **52 tests / 444 assertions** PASS（`DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test`）
- [ ] **T102-9** 実機受け入れ: 打ち間違いアドレスへ送信 → 直したアドレスへ即再送信（429 にならない）→
  新しいコードで応募完了。`storage/logs/laravel.log` に `database is locked` が出ないこと
- [ ] **T102-10** 本番反映: `config/database.php` / `bootstrap/app.php` / `app/Providers/AppServiceProvider.php` /
  `config/ar202611.php` / `StampRally202611Controller.php` / `js-prize.blade.php` / `.env` をアップロード →
  `php artisan optimize:clear`。**Xserver で DB がネットワーク FS（NFS）上の場合は `DB_SQLITE_JOURNAL_MODE=DELETE` に戻す**。
  WAL 化後は `database.sqlite-wal` / `database.sqlite-shm` も書き込み可能な権限で揃うこと（migration は不要）

### T103: 応募フォーム開期中の応募ボタン非表示（FR-61・AC-26・2026-11-05）

- [x] **T103-1** `js-prize.blade.php` へ開期中フラグ `var _voucherFormOpen202611 = false;` を追加
- [x] **T103-2** `updateVoucherUI202611()` — 開期中は `#voucher-apply-button` を `display:none` にし、
  フォームを閉じる条件を `if (form && !showButton)` → `if (form && applied)` へ変更
  （`check-status` 応答・`online` 復帰・スタンプ帳を開いた時の背景更新で 6 桁欄を消さない）
- [x] **T103-3** 応募フォームへ `openForm()` を追加（`_voucherFormOpen202611 = true` + ボタン非表示）。
  緑ボタンの click は `openForm()` を呼ぶだけ。`closeForm()` は「やめる」と応募成功時だけ呼び、
  閉じた後に `updateVoucherUI202611()` で収集種数・応募状態に応じてもう一度ボタンを出す
- [x] **T103-4** 変更フォーム（FR-63）も同型 — `openForm()` が `#voucher-change-email` を隠し、
  `canChange` へ `!_voucherChangeOpen202611` を追加。応募行が無い時の自動閉じは開期中以外だけ
- [x] **T103-5** テスト追加 `test_voucher_apply_button_is_hidden_while_apply_form_is_open`
  （描画 JS の needle 検査 + 旧実装 `if (form && !showButton) form.style.display` を含まないこと）。
  `StampRally202611Test` は **53 tests / 453 assertions** PASS。描画済み HTML のインライン 6 スクリプトは
  `node --check` すべて OK
- [ ] **T103-6** 実機受け入れ: フォームを開く → 緑ボタンが消える → 確認コード送信 → 6 桁欄が残ったまま
  （緑ボタンは出ない）→ 「やめる」で緑ボタンが戻る。スタンプ帳を開き直しても機内モードへしても消えない
  （`test-data-guide.md` §2.6）
- [ ] **T103-7** 本番反映: `resources/views/ARstampRally202611/js-prize.blade.php` をアップロード →
  `php artisan optimize:clear`。Service Worker は navigation を network-first で取得するため
  `public/sw-stamp202611.js` の `VERSION` は `v3` のまま（オフライン用の事前キャッシュ HTML だけ旧版が残るが
  応募は通信必須のため影響なし）。反映後はハードリロードで確認（migration は不要）

### 残（FR-62 / FR-63 関連の運用）

- [ ] 変更用メールの文面（件名「…メールアドレス変更 確認コード」）を運営へ確認してもらう
- [ ] 抽選実施前に `email_change_deadline`（既定 2026-11-30 23:59:59）が抽選日と矛盾していないか確認する。
  抽選を前倒しする場合は `.env` の `AR202611_EMAIL_CHANGE_DEADLINE` を抽選実施時刻より前に設定する
- [ ] 変更後は `email_code_sent_at` / `email_verified_at` が更新されるため、管理画面の「確認済み」は維持される。
  変更履歴（旧アドレス）は保持しない仕様であることを運営へ周知する（FR-63 は 1 行の `email` を書き換える）

