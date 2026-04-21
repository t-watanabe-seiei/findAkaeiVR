# タスク化: /stamp202605 Androidズーム未解決問題（案1）

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
