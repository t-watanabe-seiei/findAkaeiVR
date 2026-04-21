# タスク化: /stamp202605 Androidズーム未解決問題（案1）

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
