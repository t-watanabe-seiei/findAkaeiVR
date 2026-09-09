# タスク — ARstampRally202609 問題点修正

> 作成日: 2026-09-02 / 根拠: `.claude_workflow/ar_design.md`
> 進捗記法: `[ ]` 未着手 / `[~]` 実施中 / `[x]` 完了
> 変更対象は `resources/views/ARstampRally202609/` の6ファイル + `README.md` のみ（202606 への変更禁止）

## タスクリスト

### T1. `js-init.blade.php` の3修正（設計 §2.2）
- [x] T1-a 主因B: `applyCurrentScaleTo`（L31-56）— メッシュ `node.scale.set` を削除し、`traverse` は `frustumCulled=false` のみに限定
- [x] T1-b 主因A: `if (scene)` 内に `desiredMaxDetectionRate()` + `syncArjsToRealSize()` を新設。`arjs-video-loaded`（L585-606）の縦型限定ブロック（L591-604）を `syncArjsToRealSize()` 呼出に置換（hideArjsLoader/helpモーダル閉じは維持）。`resize`(200ms) / `orientationchange`(300ms) リスナ追加
- [x] T1-c 主因C: 低解像度リトライ（L523-535）の `arjs` 属性に `displayWidth/Height`（実画面サイズ）を追加
- [x] T1-d `php -l resources/views/ARstampRally202609/js-init.blade.php` が警告0件

### T2. `scene.blade.php` の2修正（設計 §2.1）
- [x] T2-a UA分岐（L1-7）を削除し全端末 `$_srcW/$_srcH/$_dispW/$_dispH = 1280/720`（暫定値）+ コメント付与
- [x] T2-b 末尾の Samsung 縦型上書き `<script>` ブロック（L73-92）を削除
- [x] T2-c `php -l resources/views/ARstampRally202609/scene.blade.php` が警告0件

### T3. `head.blade.php` の2修正（設計 §2.3）
- [x] T3-a CSSコメント（L172-173）を実態に合わせて修正（`object-fit: cover` 自体は維持）
- [x] T3-b `AR_FORCE_LOWRES`（L31-35）に Android の `hardwareConcurrency`/`deviceMemory` 判定を追加（`>0` ガード付き）
- [x] T3-c `php -l resources/views/ARstampRally202609/head.blade.php` が警告0件

### T4. `js-camera.blade.php` に MediaRecorder feature detection 追加（設計 §2.4）
- [x] T4-a `videoButton` 取得直後に `typeof MediaRecorder === 'undefined'` 時の非表示処理を追加
- [x] T4-b `php -l resources/views/ARstampRally202609/js-camera.blade.php` が警告0件

### T5. `js-prize.blade.php` に二重送信ガード追加（設計 §2.5）
- [x] T5-a `_exchanging` フラグを `exchangePrize()` に追加（先頭ガード / csrf通過後 set / 4終端パスで復元）
- [x] T5-b `php -l resources/views/ARstampRally202609/js-prize.blade.php` が警告0件

### T6. `README.md` に修正内容を追記（設計 §2.6 / CLAUDE.md ルール6）
- [x] T6-a 202609 修正セクションを追記（射影同期・スケール修正・Samsungパッチ削除・低スペック判定・feature detection・二重送信ガード）

### T7. 静的検証（設計 §5-1）
- [x] T7-a 202609 配下 blade ファイルに `samsung|SM-[A-Z]` 判定が0件（分析mdのみ検出・bladeは0件）
- [x] T7-b `applyCurrentScaleTo` に `node.scale.set` が残っていない（レンダリングHTMLでも0件）
- [x] T7-c `js-init.blade.php` に `vid.videoWidth < vid.videoHeight` の縦型限定ガードが残っていない（レンダリングHTMLでも0件）
- [x] T7-d 202606 配下に変更がない（git status: 変更は 202609 の5ファイル + README.md + ワークフローmd3件のみ）

### T8. 動作確認・実機チェックリスト（設計 §5-2,5-3）
- [x] T8-a 起動確認: `php artisan serve` で `GET /stamp202609` が **HTTP 200（191KB）** でレンダリング。レンダリングHTML検証12項目すべて合格（arjs初期値1280×720 / `syncArjsToRealSize` 5箇所 / `desiredMaxDetectionRate` 2箇所 / `samsung`=0 / `node.scale.set`=0 / 縦型ガード=0 / MediaRecorder判定1箇所 / `_exchanging` 7箇所 / `hardwareConcurrency` 2箇所 / 低解像度display更新2箇所 / `orientationchange` 1箇所 / `frustumCulled=false` 維持）
- [~] T8-b マーカー認識時: モデルスケールが `base²` でない（maker00≈0.6・maker01-20≈1.1 相当）。wheel 2倍でモデルも2倍 — **要実機/カメラ**（開発者実行）
- [~] T8-c 低解像度リトライ経路（`?lowres=1` またはボタン）でスケール/レートが整合 — **要実機/カメラ**（開発者実行）
- [~] T8-d 交換ボタンの連打で `/api/exchange-prize` が1回のみ — **要実機/カメラ**（開発者実行）
- [~] T8-e **開発者実機チェック（要実機）**: Android縦長/横長でカメラ比率・モデル位置・Samsung縦型の読み込み直後認識、iOS回帰、低スペック機（`hardwareConcurrency`≤2 / `deviceMemory`≤2 相当）での検出レート、`MediaRecorder` 非対応環境で動画ボタン非表示

## 進捗ログ

| 日付 | タスク | 内容・結果 |
|------|--------|-----------|
| 2026-09-02 | T1〜T5 | 5ファイルのコード修正完了。各ファイル `php -l` 警告0件 |
| 2026-09-02 | T6 | README.md に「ARstampRally202609 - カメラ射影同期・モデルスケール修正・補助修正 20260902」セクション追加 |
| 2026-09-02 | T7 | 静的検証4項目すべて合格（samsung/scale.set/縦型ガードはbladeで0件、202606未変更を確認） |
| 2026-09-02 | T8-a | `artisan serve` + `curl` でレンダリング成功（HTTP 200/191KB）。HTML検証12項目すべて合格。バックグラウンドサーバー（PID12600）は正常終了済み |
| 2026-09-02 | T8-b〜e | 実機/カメラが必要な項目 → 開発者実行待ち（README・タスク書に明記済み） |

---

# タスク — ARstampRally202609 景品交換APIのサーバー側強化

> 作成日: 2026-09-08 / 根拠: `.claude_workflow/ar_design.md`（202609専用エンドポイント設計）
> 進捗記法: `[ ]` 未着手 / `[~]` 実施中 / `[x]` 完了

## タスクリスト

### T10. 新コントローラー作成（設計 §ファイル別設計）
- [x] T10-a `app/Http/Controllers/StampRally202609Controller.php` を新設（`recordScan` / `checkStatus` / `exchange`、閾値10をサーバー側で強制）
- [x] T10-b `php -l app/Http/Controllers/StampRally202609Controller.php` が警告0件

### T11. 202609 専用3ルートを `routes/web.php` に追加（`web`グループ + `throttle`）
- [x] T11-a `POST /stamp202609/record-scan` → `throttle:stamp202609_scan`
- [x] T11-b `POST /stamp202609/check-prize` → `throttle:stamp202609_check`
- [x] T11-c `POST /stamp202609/exchange-prize` → `throttle:stamp202609_redeem`
- [x] T11-d `php -l routes/web.php` が警告0件

### T12. 命名レートリミッターを `AppServiceProvider::boot()` に定義
- [x] T12-a `stamp202609_scan` / `stamp202609_check`（1分間120回）
- [x] T12-b `stamp202609_redeem`（1時間10回）
- [x] T12-c `php -l app/Providers/AppServiceProvider.php` が警告0件

### T13. `js-prize.blade.php` の3 fetch を新エンドポイントへ差し替え
- [x] T13-a L149: `/api/record-marker-scan` → `/stamp202609/record-scan`
- [x] T13-b L170: `/api/check-prize-exchange` → `/stamp202609/check-prize`
- [x] T13-c L203: `/api/exchange-prize` → `/stamp202609/exchange-prize`
- [x] T13-d `X-CSRF-TOKEN` ヘッダは維持（`web` グループで検証される）
- [x] T13-e `php -l resources/views/ARstampRally202609/js-prize.blade.php` が警告0件

### T14. 静的検証
- [x] T14-a `php artisan route:list --path=stamp202609` で3新ルートが `StampRally202609Controller` に解決
- [x] T14-b 202609 配下 blade に旧 `/api/record-marker-scan`・`/api/check-prize-exchange`・`/api/exchange-prize` の参照が0件（.md 解析ドキュメントのみ残存）
- [x] T14-c 202605 / 202606 の `js-prize.blade.php`・`routes/api.php`・共有コントローラーに変更がない

### T15. 動作確認・実機チェックリスト
- [~] T15-a 10体捕獲 → 交換 → コード発行（要実機/カメラ）
- [~] T15-b 10体未満で API 直接叩いても 422（P0-1 無効化の目視確認、要セッション/DevTools）
- [~] T15-c 別サイトからのクロスサイトフォームPOSTが CSRF 419 で拒否（P0-2、要実機）
- [~] T15-d 202605 が閾値5のまま `/api/...` で動作（回帰、要実機）

## 進捗ログ（202609 景品交換API強化）

| 日付 | タスク | 内容・結果 |
|------|--------|-----------|
| 2026-09-08 | T10〜T13 | 新コントローラー・3ルート・レートリミッター・JS差し替えの4ファイル変更完了。各ファイル `php -l` 警告0件 |
| 2026-09-08 | T14 | 静的検証3項目すべて合格（route:list 3ルート解決 / 旧API参照0件 / 他キャンペーン未変更） |
| 2026-09-08 | T15 | 実機/セッションが必要な項目 → 開発者実行待ち |

---

# タスク — ARstampRally202609 最優先バグ修正（T-01 / T-02 / T-03）

> 作成日: 2026-09-09 / 根拠: `.claude_workflow/ar_design.md`（最優先バグ修正設計）
> 進捗記法: `[ ]` 未着手 / `[~]` 実施中 / `[x]` 完了
> 変更対象: `js-stamps.blade.php` / `js-prize.blade.php` / `StampRally202609Controller.php` + `README.md`

## タスクリスト

### T16. `js-stamps.blade.php` — `collectStamp()` catch 修正（設計 §T-01）
- [x] T16-a `collectStamp()` の catch（L164-167）を `screenshot:null` 化再保存に置換（フォールバック維持）
- [x] T16-b `php -l resources/views/ARstampRally202609/js-stamps.blade.php` が警告0件

### T17. `js-prize.blade.php` — `exchangePrize()` の stamps 整形（設計 §T-02）
- [x] T17-a `exchangePrize()` 内で `stamps` → `stampArr`（screenshot除外配列）に変換し、fetch body に `stamps: stampArr` を送付
- [x] T17-b `php -l resources/views/ARstampRally202609/js-prize.blade.php` が警告0件

### T18. `StampRally202609Controller.php` — `checkStatus()` に `exchangedAt` 追加（設計 §T-03）
- [x] T18-a `checkStatus()` のレスポンス配列に `'exchangedAt' => ...` を追加
- [x] T18-b `php -l app/Http/Controllers/StampRally202609Controller.php` が警告0件

### T19. 静的検証
- [x] T19-a `exchangePrize` 内に旧参照 `stamps: stamps` が0件、新参照 `stamps: stampArr` が1件
- [x] T19-b `collectStamp` の catch 内に `removeItem` が1件のみ（最終フォールバック）
- [x] T19-c `checkStatus()` に `exchangedAt` が含まれる
- [x] T19-d 202605 / 202606 のファイルに変更がない

### T20. `README.md` に修正内容を追記（CLAUDE.md ルール6）
- [x] T20-a 202609 最優先バグ修正セクションを追記（T-01/T-02/T-03 の概要・影響範囲）

## 進捗ログ（最優先バグ修正）

| 日付 | タスク | 内容・結果 |
|------|--------|-----------|
| 2026-09-09 | T16 | `js-stamps.blade.php` catch修正完了（`screenshot:null` 化再保存）。`php -l` 警告0件 |
| 2026-09-09 | T17 | `js-prize.blade.php` `exchangePrize()` に `stampArr` 変換追加。`php -l` 警告0件 |
| 2026-09-09 | T18 | `StampRally202609Controller.php` `checkStatus()` に `exchangedAt` 追加。`php -l` 警告0件 |
| 2026-09-09 | T19 | 静的検証4項目すべて合格（旧参照0件 / フォールバック1件 / exchangedAt有 / 他キャンペーン未変更） |
| 2026-09-09 | T20 | README.md に「最優先バグ修正（2026-09-09）」セクション追加 |

---

## T-08（P1-7）: head.blade.php IIFE 例外耐性

- [x] T21-a `head.blade.php` L31-45 の `AR_FORCE_LOWRES` IIFE 全体を try/catch で包み、例外時は `false` 返却
- [x] T21-b `php -l resources/views/ARstampRally202609/head.blade.php` が警告0件
- [x] T21-c 202605 / 202606 のファイルに変更がない

## T-04（P1-2）: js-init.blade.php ローダーフォールバックタイミング

- [x] T22-a `js-init.blade.php` L640 の `setTimeout(hideArjsLoader, 3000)` を `setTimeout(hideArjsLoader, 7000)` に変更（コメント更新含む）
- [x] T22-b `php -l resources/views/ARstampRally202609/js-init.blade.php` が警告0件
- [x] T22-c 202605 / 202606 のファイルに変更がない

## 進捗ログ（T-08 / T-04）

| 日付 | タスク | 内容・結果 |
|------|--------|-----------|
| 2026-09-09 | T21 | `head.blade.php` IIFE 全体を try/catch で保護。`php -l` 警告0件。202605/202606未変更 |
| 2026-09-09 | T22 | `js-init.blade.php` フォールバック 3秒→7秒（monitorCameraStartupと整合）。`php -l` 警告0件。202605/202606未変更 |


---

# タスク — ARstampRally202609 P1 高優先修正（T-05 / T-06 / T-07 / T-09）

> 作成日: 2026-09-09 / 根拠: `.claude_workflow/ar_design.md`（P1 高優先修正設計）
> 進捗記法: `[ ]` 未着手 / `[~]` 実施中 / `[x]` 完了
> 変更対象: `js-stamps.blade.php` / `js-camera.blade.php` / `js-throw.blade.php` / `js-prize.blade.php` / `head.blade.php` + `README.md`

## タスクリスト

### T23. T-05（P1-4）: `js-stamps.blade.php` — `showStampBook()` XSS 修正
- [x] T23-a `showStampBook()` 内 `innerHTML` 使用ブロック（L347-370）を `document.createElement` + `textContent` による安全DOM構築に置換
- [x] T23-b `php -l resources/views/ARstampRally202609/js-stamps.blade.php` が警告0件

### T24. T-06（P1-5）: `#switch-camera-button` デッドコード削除
- [x] T24-a `js-camera.blade.php` L8（`currentFacingMode` 変数宣言）を削除
- [x] T24-b `js-camera.blade.php` L17-37（`switchCameraBtn` 宣言 + 全イベントハンドラ）を削除
- [x] T24-c `js-throw.blade.php` L28（`isUIButton` 内 `#switch-camera-button` チェック行）を削除
- [x] T24-d `php -l resources/views/ARstampRally202609/js-camera.blade.php` / `js-throw.blade.php` が警告0件

### T25. T-07（P1-6）: `js-prize.blade.php` — iPadOS 13+ 検出修正
- [x] T25-a `collectDeviceInfo()` の `isIOS` 判定に `navigator.maxTouchPoints > 1 && /MacIntel/.test(navigator.platform)` 分岐を追加
- [x] T25-b `php -l resources/views/ARstampRally202609/js-prize.blade.php` が警告0件

### T26. T-09（P1-8）: `head.blade.php` — video セレクタ特定化
- [x] T26-a L51（`monitorCameraStartup` ポーリング）`document.querySelector('video')` → `document.querySelector('#ar-scene video')`
- [x] T26-b L75（`ensureCameraAccess` 初期チェック）`document.querySelector('video')` → `document.querySelector('#ar-scene video')`
- [x] T26-c L89（`ensureCameraAccess` attempt 内）`document.querySelector('video')` → `document.querySelector('#ar-scene video')`
- [x] T26-d `php -l resources/views/ARstampRally202609/head.blade.php` が警告0件

### T27. 静的検証
- [x] T27-a 202609 配下 blade に `switch-camera-button` が0件
- [x] T27-b 202609 配下 blade に `innerHTML.*s.name`（`showStampBook` 内）が0件
- [x] T27-c 202609 配下 blade に `document.querySelector('video')` が0件（`#ar-scene video` に置換済み）
- [x] T27-d 202609 配下 blade に `maxTouchPoints` + `MacIntel` が1件（iPadOS検出のみ）
- [x] T27-e 202605 / 202606 のファイルに変更がない

### T28. `README.md` に修正内容を追記（CLAUDE.md ルール6）
- [x] T28-a 202609 P1高優先修正セクションを追記（T-05 XSS / T-06 デッドコード / T-07 iPadOS / T-09 セレクタ）

## 進捗ログ（P1 高優先修正）

| 日付 | タスク | 内容・結果 |
|------|--------|-----------|
| 2026-09-09 | T23 | `js-stamps.blade.php` `showStampBook()` の `innerHTML` を安全DOM構築に置換。`php -l` 警告0件 |
| 2026-09-09 | T24 | `js-camera.blade.php`（L8, L17-37）・`js-throw.blade.php`（L28）の `#switch-camera-button` デッドコード削除。`php -l` 警告0件 |
| 2026-09-09 | T25 | `js-prize.blade.php` `isIOS` 判定に iPadOS 13+（Mac UA偽装）分岐追加。`php -l` 警告0件 |
| 2026-09-09 | T26 | `head.blade.php` 3箇所の `querySelector('video')` → `querySelector('#ar-scene video')`。`php -l` 警告0件 |
| 2026-09-09 | T27 | 静的検証5項目すべて合格（switch-camera-button=0 / innerHTML XSS=0 / generic video=0 / iPadOS=1 / 他キャンペーン未変更） |
| 2026-09-09 | T28 | README.md に「P1高優先修正（2026-09-09）」セクション追加 |

