# タスク化: shooting3Dterrer4 VR負荷改善（2026-06-09）

## 前段階ファイル読込
前段階のmdファイルを読み込みました（`.claude_workflow/design.md`）。

## タスク一覧

### Task E-1: Stage2通常敵の同時出現を4体に制限
- ファイル: `resources/views/shooting3Dterrer4/_components.blade.php`
- 作業: `activateModels()` のスポーン対象をStage2のみ4体化
- ステータス: ✅ 完了

### Task E-2: ボール簡易オブジェクトプール化
- ファイル: `resources/views/shooting3Dterrer4/_components.blade.php`
- 作業: acquire/release関数追加、shoot処理を再利用方式へ変更
- ステータス: ✅ 完了

### Task E-3: 遷移/終了時のボール返却統一
- ファイル: `resources/views/shooting3Dterrer4/_components.blade.php`
- 作業: Next Stage・GameOver時の弾処理をプール返却へ統一
- ステータス: ✅ 完了

### Task E-4: README追記 + 構文チェック
- ファイル: `README.md`, `resources/views/shooting3Dterrer4/_components.blade.php`
- 作業: 変更内容をREADMEへ記載、`php -l` 実行
- ステータス: ✅ 完了

---

# タスク化: shooting3Dterrer4 ゲーム性拡張（2026-06-09）

## 前段階ファイル読込
前段階のmdファイルを読み込みました（`.claude_workflow/design.md`）。

## タスク一覧

### Task D-1: ステージ時間の更新
- ファイル: `resources/views/shooting3Dterrer4/_components.blade.php`, `resources/views/shooting3Dterrer4/_scene.blade.php`
- 作業: Stage1=100秒、Stage2=80秒、初期タイマー文言更新
- ステータス: ✅ 完了

### Task D-2: ゲーム中武器切替の解放
- ファイル: `resources/views/shooting3Dterrer4/_components.blade.php`
- 作業: Grip/A/B と switchGun の制限解除、切替時UI同期
- ステータス: ✅ 完了

### Task D-3: 残弾システム + HUD 実装
- ファイル: `resources/views/shooting3Dterrer4/_components.blade.php`, `resources/views/shooting3Dterrer4/_scene.blade.php`
- 作業: Gun1/Gun2 初期20、常時HUD、発射時消費、残弾0時発射不可
- ステータス: ✅ 完了

### Task D-4: 01/06撃破時の補給演出と加算
- ファイル: `resources/views/shooting3Dterrer4/_components.blade.php`
- 作業: 補給ボール飛来、到達時に未使用武器へ +5 / +10
- ステータス: ✅ 完了

### Task D-5: Stage2 GameOver の VR解除仕様化
- ファイル: `resources/views/shooting3Dterrer4/_components.blade.php`
- 作業: close/blank を廃止し、VR解除のみ実行
- ステータス: ✅ 完了

### Task D-6: README 追記 + 構文チェック
- ファイル: `README.md`, 変更Bladeファイル
- 作業: 新機能追記、`php -l` 実行
- ステータス: ✅ 完了

---

# タスク化: admin/dashboard202606 景品交換セクションを最上部へ移動（2026-05-31）

## 前段階ファイル読込
前段階のmdファイルを読み込みました（`.claude_workflow/design.md`）。

## タスク一覧

### Task B-1: prizes-grid ブロックをヘッダー直後に移動
- **ファイル**: `resources/views/admin/dashboard202606.blade.php`
- **作業**: ページ末尾の `<!-- 【新規追加】景品交換セクション -->` ～ prizes-grid 閉じ `</div>` を削除し、ヘッダー `</div>` 直後に挿入
- **ステータス**: ✅ 完了

---

# タスク化: admin/dashboard202606 動物別統計の動物名表示修正（2026-05-31）

## 前段階ファイル読込
前段階のmdファイルを読み込みました（`.claude_workflow/design.md`）。

## タスク一覧

### Task A-1: AdminController.php の $animals 配列を実際の動物名に更新
- **ファイル**: `app/Http/Controllers/AdminController.php`
- **メソッド**: `dashboard202606()`
- **作業**: `$animals` 配列の値（キャラクター01〜20）を実際の動物名に書き換え
- **優先度**: 高（唯一のタスク）
- **ステータス**: ✅ 完了

### Task A-2: php -l で構文チェック
- **コマンド**: `php -l app/Http/Controllers/AdminController.php`
- **ステータス**: ✅ 完了（No syntax errors detected）

---

# タスク化: ARstampRally202606 オフライン GLB 表示対応（2026-05-30）

## 前段階ファイル読込
前段階のmdファイルを読み込みました（`.claude_workflow/design.md`）。

## タスク一覧

### Task C-1: aframe-components.blade.php — lazy-model init() に pre-fetch 追加
- 作業: `lazy-model` の `init()` 末尾（markerLost リスナー登録の直後）に以下を追加
  ```js
  if (this.data.src) { fetch(this.data.src, { cache: 'default' }).catch(function () {}); }
  ```
- 完了条件: コード追加後 php -l で構文エラーなし
- 状態: 完了

### Task C-2: php -l 構文確認
- 作業: `php -l resources/views/ARstampRally202606/aframe-components.blade.php`
- 完了条件: `No syntax errors detected` を確認
- 状態: 完了

---

# タスク化: ARstampRally202606 ギャラリー選択モデルスケール縮小（2026-05-21）

## 前段階ファイル読込
前段階のmdファイルを読み込みました（`.claude_workflow/design.md`）。

## タスク一覧

### Task A-1: js-gallery.blade.php のスケール値変更
- 作業: `loadNextModel()` 内の `entity.setAttribute('scale', '0.6 0.6 0.6')` を `'0.48 0.48 0.48'` に変更
- 完了条件: php -l で構文エラーなし
- 状態: 完了

---

# タスク化: ARstampRally202606 新規作成（2026-05-20）

## 前段階ファイル読込
前段階のmdファイルを読み込みました（`.claude_workflow/design.md`）。

---

## タスク一覧

### Task 1: ARstampRally202606.blade.php（エントリポイント作成）
- 作業: 202605エントリポイントをベースに `@include('ARstampRally202606.xxx')` で10モジュールを読み込む
- 完了条件: php -l で構文エラーなし
- 状態: 未着手

### Task 2: ARstampRally202606/head.blade.php 作成
- 作業: 202605/head.blade.php をベースにキー・タイトルを202606に変更
  - タイトル → `AR Stamp Rally 202606`
  - `ar-camera-reload-202605` → `ar-camera-reload-202606`
  - `window.allGalleryHitboxes = []` を初期化コードに追加
- 完了条件: php -l で構文エラーなし
- 状態: 未着手

### Task 3: ARstampRally202606/ui.blade.php 作成
- 作業: 202605/ui.blade.php をベースに変更
  - 「コイを逃がす」→「キャラクターを逃がす」
  - ガイド説明内の景品条件文言 → 「10種類以上」
- 完了条件: php -l で構文エラーなし
- 状態: 未着手

### Task 4: ARstampRally202606/scene.blade.php 作成
- 作業: 202605/scene.blade.php をベースに変更
  - CGパス `cg/202605/` → `cg/202606/`
  - maker00: Model_00エンティティを `id="model-00"` + `lazy-model` + `position="0 0 0"` で定義（click-animation/hitboxなし）
  - ループ `$i = 1; $i <= 10` → `$i = 1; $i <= 20`
- 完了条件: php -l で構文エラーなし
- 状態: 未着手

### Task 5: ARstampRally202606/aframe-components.blade.php 作成
- 作業: 202605/aframe-components.blade.php をベースに変更
  - `gallery-hitbox` コンポーネントを新規追加（`window.allGalleryHitboxes[]` に登録）
  - `pokeball-throwable` の `tick()` にギャラリーhitboxチェック追加（`allGalleryHitboxes` ループ）
  - ヒット時 `window.playGalleryHitAnimation(entity)` 呼び出し（スタンプ取得なし）
  - 他コンポーネント（lazy-model, hitbox, click-animation）は変更なし
- 完了条件: php -l で構文エラーなし
- 状態: 未着手

### Task 6: ARstampRally202606/js-stamps.blade.php 作成
- 作業: 202605/js-stamps.blade.php をベースに変更
  - `STAMPS`: model_01〜model_20 に20種拡張
  - `TOTAL_STAMP_SLOTS = 20`
  - `GALLERY_MAX_DISPLAY = 4`（5→4）
  - キー類を `202606` サフィックスに変更
  - `getCapturedAnimals` / `saveCapturedAnimals` 関数名変更
- 完了条件: php -l で構文エラーなし
- 状態: 未着手

### Task 7: ARstampRally202606/js-prize.blade.php 作成
- 作業: 202605/js-prize.blade.php をベースに変更
  - `PRIZE_EXCHANGE_THRESHOLD = 10`（5→10）
  - `UserIdDB202606`, `CookieHelper202606` に変更
  - `generateUUID202606()`, `getUserId202606()` に変更
  - LocalStorage/Cookieキーを `202606` サフィックスに変更
  - `CAPTURED_KEY` → `ar-prize-exchanged-202606`, `ar-prize-code-202606`
- 完了条件: php -l で構文エラーなし
- 状態: 未着手

### Task 8: ARstampRally202606/js-throw.blade.php 作成
- 作業: 202605/js-throw.blade.php をそのままコピー（変更なし）
- 完了条件: php -l で構文エラーなし
- 状態: 未着手

### Task 9: ARstampRally202606/js-gallery.blade.php 作成
- 作業: 202605/js-gallery.blade.php を大幅変更
  - `GALLERY_POSITIONS` 配列定義: (0,0,1),(0,0,-1),(1,0,0),(-1,0,0)
  - Model_00 (`id="model-00"`): markerFound時にanime01ループ＋gallery-hitbox付与、markerLost時に非表示
  - 選択4体の逐次ロード: GALLERY_POSITIONS に配置、anime01ループ＋gallery-hitbox付与
  - `window.playGalleryHitAnimation(entity)`: anime02再生→完了後anime01ループに戻る
  - `window.galleryMixers` + RAF ループ管理（202605踏襲）
  - anime03 に関するコードは削除（202606では不使用）
- 完了条件: php -l で構文エラーなし
- 状態: 未着手

### Task 10: ARstampRally202606/js-camera.blade.php 作成
- 作業: 202605/js-camera.blade.php をそのままコピー（変更なし）
- 完了条件: php -l で構文エラーなし
- 状態: 未着手

### Task 11: ARstampRally202606/js-init.blade.php 作成
- 作業: 202605/js-init.blade.php をベースに変更
  - markerイベントループ: `$i <= 10` → `$i <= 20`
  - リセット時ループ: `i <= 10` → `i <= 20`
  - LocalStorageキー: `202606` サフィックスに変更
  - ガイドテキスト: `10種類以上のキャラクターを捕まえると景品と交換できます。`
- 完了条件: php -l で構文エラーなし
- 状態: 未着手

### Task 12: AdminController.php への dashboard202606() 追加
- 作業: `dashboard202605()` メソッドを参考に `dashboard202606()` を追加
  - 期間: `2026-05-20 00:00:00` 〜 `2026-06-10 23:59:59` (JST → UTC換算: `2026-05-19 15:00:00` 〜 `2026-06-10 14:59:59`)
  - `$animals`: model_01〜model_20 の20種配列
- 完了条件: php -l で構文エラーなし
- 状態: 未着手

### Task 13: resources/views/admin/dashboard202606.blade.php 作成
- 作業: `dashboard202605.blade.php` をベースにコピー・変更
  - タイトル → `管理ダッシュボード202606`
  - 「ARスタンプラリー202606」
  - 動物リスト部分: 10種 → 20種に変更
- 完了条件: php -l で構文エラーなし
- 状態: 未着手

### Task 14: routes/web.php への追加
- 作業: 以下2ルートを追加
  1. `Route::match(['get', 'head'], '/stamp202606', ...)` をpublicルートに追加
  2. `Route::get('/dashboard202606', [..., 'dashboard202606'])` をadmin middlewareグループに追加
- 完了条件: php -l で構文エラーなし
- 状態: 未着手

### Task 15: php -l による最終構文チェック
- 作業: 作成・変更した全PHPファイルを `php -l` でチェック
- 完了条件: 全ファイルでエラーなし
- 状態: 未着手

---

## 実行順序
Task 1 → Task 2 → Task 3 → Task 4 → Task 5 → Task 6 → Task 7 → Task 8 → Task 9 → Task 10 → Task 11 → Task 12 → Task 13 → Task 14 → Task 15

---

# 旧タスク化: /stamp202605 Androidズーム未解決問題（案1）

---

# タスク化: /stamp202603 UI改修・自動GET実装（2026-04-22）

## 前段階ファイル読込
前段階のmdファイルを読み込みました（`.claude_workflow/design.md`）。

## 注記（設計からの訂正）
- Block 2 の gesture/touchend/touchmove イベントは 202603 に **既に存在**（line 307-344）。
  → CSS (`video`, `a-scene canvas`) と `look-controls` 無効化のみ対応する。

---

## タスク一覧

### Task A: ガイド初期非表示
- 対象行: line 14, line 3969-3976
- 作業:
  1. `window.guideModalOpen = true;` → `window.guideModalOpen = false;`
  2. "Show guide modal on startup" コメントから `}` までの6行ブロックを削除
- 完了条件: フラグが `false`、起動時表示ブロックが消えている
- 状態: 未着手

### Task B: Android Zoom修正 CSS
- 対象行: line 1114 の `a-scene {` の直前
- 作業: 以下を挿入
  ```css
  /* ========== Androidカメラズーム防止 ========== */
  video { object-fit: contain !important; }
  a-scene canvas {
      object-fit: contain !important;
      width: 100% !important;
      height: 100% !important;
  }
  ```
- 完了条件: video と canvas に contain が適用される
- 状態: 未着手

### Task C: look-controls 無効化
- 対象行: line 2219
- 作業: `<a-entity camera="near: 0.2; far: 800;">` → `<a-entity camera="near: 0.2; far: 800;" look-controls="enabled: false">`
- 完了条件: look-controls 属性が設定されている
- 状態: 未着手

### Task D: UI CSS 削除・置換
- 対象行:
  - `#camera-button` ブロック（line ~1138-1162）
  - `#switch-camera-button` ブロック（line ~1165-1191）
  - `#video-button` ブロック（line ~1193-1217）
  - `#stamp-book-button` ブロック（line ~1340-1382）
  - `#guide-button` ブロック（line ~1386-1406）
  - `#throw-button` 内 `display: none !important;`（line 1223）
- 作業:
  1. 上記5ブロックを削除し、`#top-left-buttons` コンテナCSS に置換
  2. `#throw-button` の `display: none !important;` 行のみ削除（他スタイルは維持）
  3. `#stamp-book-button .badge`、`#video-button.recording`、`@keyframes pulse` は維持
- 完了条件: 個別 position:fixed CSS が消え、top-left-buttons コンテナが追加されている
- 状態: 未着手

### Task E: HTML 変更（ボタン再配置 + switch-camera 削除）
- 対象: line ~2057-2172 のボタン群 HTML
- 作業:
  1. `<button id="switch-camera-button">` 行を削除
  2. `#stamp-book-button`, `#guide-button`, `#camera-button`, `#video-button` を `<div id="top-left-buttons">` で囲む
- 完了条件: switch-camera-button が消え、4ボタンがコンテナ内に入っている
- 状態: 未着手

### Task F: カメラ切替 JS 削除
- 対象行:
  1. `let currentFacingMode = ...`（line 3716）
  2. `const switchCameraButton = ...`（line 3755）
  3. `isUIButton()` 内 switch-camera-button チェック 2行（line 4534, 4541）
  4. `switchCameraButton.addEventListener('click', ...)` ブロック（line 6515-6564）
- 完了条件: 削除対象4箇所がすべて消えている
- 状態: 未着手

### Task G: pokeball-throwable コンポーネント改修
- 対象行: line 349（`AFRAME.registerComponent('pokeball-throwable', {` 直後）
- 作業:
  1. `schema:` ブロック（`autoGetStampId`, `autoGetDelayMs`）を追加
  2. `init()` に `this.autoGetTimer = null; this.autoGetTriggered = false;` 追加
  3. `remove()` に `autoGetTimer` クリア処理追加
  4. `throw()` に遅延タイマー起動ロジック追加（`autoGetStampId` がある場合のみ）
  5. `tryAutoGet()` メソッド追加
  6. `handleHit()` 先頭に `autoGetTimer` キャンセル + `autoGetTriggered = true` 追加
- 完了条件: schema, tryAutoGet, タイマーロジックが組み込まれている
- 状態: 未着手

### Task H: 投擲経路への autoGetStampId 接続
- 作業:
  1. DOMContentLoaded 内 IIFE の直前に `getActiveVisibleStampId()` 関数を追加
  2. IIFE `throwBall()` 内の `setAttribute('pokeball-throwable', '')` を `autoGetStampId` 付きに修正（line ~7169）
  3. `throwPokeballToCenter()` 内の `setAttribute('pokeball-throwable', '')` を修正（line ~4659）
- 完了条件: 両投擲経路で `getActiveVisibleStampId()` の結果が pokeball に渡される
- 状態: 未着手

### Task I: 構文チェック
- 作業: `php -l resources/views/ARstampRally202603.blade.php`
- 完了条件: No syntax errors detected
- 状態: 未着手

---

## 実行順序
A → B → C → D → E → F → G → H → I

## 進捗管理
- [ ] Task A: ガイド初期非表示
- [ ] Task B: Android Zoom修正 CSS
- [ ] Task C: look-controls 無効化
- [ ] Task D: UI CSS 削除・置換
- [ ] Task E: HTML 変更
- [ ] Task F: カメラ切替 JS 削除
- [ ] Task G: pokeball-throwable 改修
- [ ] Task H: 投擲経路接続
- [ ] Task I: 構文チェック

## 前段階ファイル読込
前段階のmdファイルを読み込みました（`.claude_workflow/design.md`）。

## 実行タスク一覧

### Task 1: AR.js設定重複の解消
- 対象: `resources/views/ARstampRally202605/js-init.blade.php`
- 作業: Android 640x480 の `setAttribute('arjs', ...)` 上書きブロックを削除
- 目的: `scene.blade.php` の設定を単一ソース化
- 完了条件:
  - Android上書きブロックが削除されている
  - 他の初期化処理に影響がない
- 状態: 完了

### Task 2: AR描画キャンバス向けズーム抑制CSS追加
- 対象: `resources/views/ARstampRally202605/head.blade.php`
- 作業: `a-scene canvas` を対象に `object-fit` / サイズ制御を追加
- 目的: `video` のみ指定では不足する端末差異を補完
- 完了条件:
  - `video` と `canvas` の両方に必要な表示制御がある
  - 既存UI（ボタン・モーダル）の重なり順が維持される
- 状態: 完了

### Task 3: シーン内ピンチズーム機能の無効化
- 対象: `resources/views/ARstampRally202605/js-init.blade.php`
- 作業: `scene` に登録しているピンチ関連 `touchstart/touchmove/touchend` を削除
- 目的: 要件「ピンチ無効化」を満たす
- 完了条件:
  - ピンチでモデル縮尺が変化しない
  - PCホイール操作は維持される
- 状態: 完了

### Task 4: 構文チェックと影響確認
- 対象: 変更ファイル2件
- 作業:
  - `php -l resources/views/ARstampRally202605/head.blade.php`
  - `php -l resources/views/ARstampRally202605/js-init.blade.php`
- 目的: Blade/PHP構文の安全性担保
- 完了条件:
  - 構文エラー・警告なし
- 状態: 完了

## 実行順序
1. Task 1
2. Task 2
3. Task 3
4. Task 4

## 進捗管理（第1フェーズ）
- [x] Task 1
- [x] Task 2
- [x] Task 3
- [x] Task 4

---

## 第2フェーズ タスク（2026-04-21）

### Task 5: look-controls 無効化
- 対象: `resources/views/ARstampRally202605/scene.blade.php`
- 作業: `<a-entity camera>` → `<a-entity camera look-controls="enabled: false">`
- 目的: Android DeviceOrientationEvent 誤介入によるボール方向ずれ・ページズームを解消
- 完了条件:
  - `look-controls="enabled: false"` が設定されている
  - `php -l` 構文エラーなし
- 状態: **完了**

### Task 6: php -l 構文チェック
- 対象: `scene.blade.php`
- 結果: No syntax errors detected
- 状態: **完了**

## 進捗管理（第2フェーズ）
- [x] Task 5
- [x] Task 6

---

## 第3フェーズ タスク（2026-04-21）

### Task 7: 案Bの1秒遅延自動GET実装
- 対象: `resources/views/ARstampRally202605/aframe-components.blade.php`
- 作業:
  - `pokeball-throwable` に `schema` 追加（`autoGetStampId`, `autoGetDelayMs`）
  - 投擲後1秒で `tryAutoGet()` を実行
  - 命中時には遅延タイマーを停止し、二重処理を防止
- 目的: 投げて1秒後に自動GET（演出は維持）
- 状態: **完了**

### Task 8: 投擲側から対象スタンプIDを連携
- 対象: `resources/views/ARstampRally202605/js-throw.blade.php`
- 作業:
  - 可視ヒットボックスから `stampId` を取得する関数を追加
  - `throwPokeballInDirection` へ `autoGetStampId` を渡す
  - `pokeball-throwable` に `autoGetDelayMs: 1000` を設定
- 目的: マーカー検出中のみ自動GETを有効化
- 状態: **完了**

### Task 9: 構文チェック
- 対象:
  - `resources/views/ARstampRally202605/aframe-components.blade.php`
  - `resources/views/ARstampRally202605/js-throw.blade.php`
- 結果: No syntax errors detected
- 状態: **完了**

## 進捗管理（第3フェーズ）
- [x] Task 7
- [x] Task 8
- [x] Task 9

---

# タスク: shooting3Dterrer4 VRシューティングゲーム 2ステージ構成（2026-06-09）

## 実行順序

### Task T4-1: ディレクトリ作成 + index.blade.php 作成
- `resources/views/shooting3Dterrer4/` ディレクトリを作成
- `index.blade.php` を作成（HTMLヘッド + @includeのみ、~60行）
- `php -l` で構文チェック
- [ ] 完了

### Task T4-2: _components.blade.php 作成（グローバル変数・ユーティリティ部）
- `<script>` タグ開始
- window.DEBUG_MODE, debugLog, gameStarted 等グローバル変数定義
- window.currentStage, selectedGun, STAGE_CONFIG, GUN_CONFIG 定義
- window.activeBalls, activeTimers, cachedXxx 等ユーティリティ定義
- registerTimeout, getAvailablePattern, updateDebug ヘルパー関数
- enhance-materials コンポーネント
- face-camera コンポーネント
- [ ] 完了

### Task T4-3: _components.blade.php 追記（start-menu コンポーネント）
- AFRAME.registerComponent('start-menu', ...) 全体
- init(): gun切り替えイベントリスナー追加
- switchGun() 新規追加
- selectLevel() を削除、startGame() をステージ1固定に変更
- startTimer(): ステージ別resultMenuを表示するよう変更
- spawnBoss(): STAGE_CONFIG から bossModel 取得
- showResult(), saveScoreToDatabase(), fetchAndDisplayRankings(), displayRankings()
- recreateInitialModels(): 5体(01〜05)に変更、gltfをSTAGE_CONFIGから取得
- restartGame() → ステージ2からステージ1へのリセットには使用しない（廃止）
- [ ] 完了

### Task T4-4: _components.blade.php 追記（result-menu コンポーネント）
- AFRAME.registerComponent('result-menu', ...)
- init(): nextStageButton / gameOverButton にイベント設定
- handleNextStage(): フェードアウト→ステージ2初期化→フェードイン
- handleGameOver(): フェードアウト→5秒待機→window.close()
- performClose(): window.close()試行 + 失敗時メッセージ
- [ ] 完了

### Task T4-5: _components.blade.php 追記（shoot・approach-camera・hit-box コンポーネント）
- AFRAME.registerComponent('shoot', ...)
  - shoot(): GUN_CONFIG[selectedGun].ball を発射弾に使用
  - updateBallPosition(): modelsList を5体+boss に変更
  - setupControllerListeners(): gun切り替え用gripdown/abutton/bbuttonリスナーは start-menu 側で管理のためここでは変更なし
- AFRAME.registerComponent('approach-camera', ...) ← terrer3 からほぼそのまま
- AFRAME.registerComponent('hit-box', ...)
  - requiredHits を STAGE_CONFIG[currentStage] から取得に変更
  - saveScoreToDatabase の game_mode を STAGE_CONFIG から取得
- [ ] 完了

### Task T4-6: _components.blade.php 追記（auto-enter-vr・vr-controller + </script>）
- AFRAME.registerComponent('auto-enter-vr', ...) ← terrer3 からほぼそのまま
- AFRAME.registerComponent('vr-controller', ...) ← terrer3 からそのまま
- `</script>` タグ終了
- php -l で構文チェック
- [ ] 完了

### Task T4-7: _scene.blade.php 作成（a-assets + ライト + カーソル/コントローラー）
- a-scene 開始（renderer属性付き）
- a-assets: ステージ1・2全モデル、サウンド2系統、背景2枚、gun2種
- ライト4灯
- #mouseCursor, #leftController, #rightController（右コントローラーはgun_01初期 + #controllerGunModel）
- [ ] 完了

### Task T4-8: _scene.blade.php 追記（スタートメニュー + タイマー + デバッグ）
- #startMenu: タイトル・武器表示・切替案内・STARTボタン
- #timerDisplay: TIME / SCORE
- #debugDisplay
- [ ] 完了

### Task T4-9: _scene.blade.php 追記（リザルトメニュー2系統 + フェードoverlay）
- #resultMenu_s1: STAGE 1 CLEAR + Next Stageボタン（緑）
- #resultMenu_s2: GAME OVER + Game Overボタン（赤）
- #fadeOverlay（カメラ子要素として後でカメラタグ内に配置）
- [ ] 完了

### Task T4-10: _scene.blade.php 追記（モデルグループ + 背景 + パーティクル + カメラ）
- #modelGroup_01 〜 #modelGroup_05（初期非表示）
- #aSky（src="#sky_s1"初期）
- パーティクル（particle-normal, tier1〜3, celebration）
- #my_camera（shoot属性付き）＋ fadeOverlay を子要素として配置
- a-scene 終了
- php -l で構文チェック
- [ ] 完了

### Task T4-11: ルーティング追加
- routes/web.php に `/terrer4` ルートを追加（terrer3の直後）
- php -l で構文チェック
- [ ] 完了

### Task T4-12: 動作確認・修正
- php -l で全ファイル構文チェック
- ブラウザで /terrer4 にアクセスして表示確認
- スタートメニュー表示確認
- 武器切り替え表示確認
- ゲーム開始・タイマー動作確認
- ステージ1→2遷移確認
- [ ] 完了

