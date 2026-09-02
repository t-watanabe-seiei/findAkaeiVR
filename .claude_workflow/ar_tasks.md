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