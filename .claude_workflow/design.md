# 設計: /stamp202605 投擲時自動GET化（ズーム継続時の運用回避）

## 前段階ファイル読込
前段階のmdファイルを読み込みました（`.claude_workflow/requirements.md`）。

## 設計方針
- 最小変更で「投げたらGET」を実現する。
- 既存の投擲演出（ボール飛行）を維持する。
- 全端末（Android / iPhone / PC）で同一仕様にする。
- マーカー検出中（`activeModel` 存在時）のみ自動GETを有効化する。

## 現状ロジック整理
- 投擲入力: `resources/views/ARstampRally202605/js-throw.blade.php`
  - `touchend` / `mouseup` で方向計算して `throwPokeballInDirection()` 実行
- 命中判定: `resources/views/ARstampRally202605/aframe-components.blade.php`
  - `pokeball-throwable.tick()` で `window.allHitboxes` へレイ判定
- GET確定: `resources/views/ARstampRally202605/js-stamps.blade.php`
  - `collectAndMarkWithRetry(stampId, ...)` が通知・保存・捕獲表示まで一元処理

## 解決案（ユーザー選択用）

### 案A: 投擲直後に `activeModel` を即GET（最小変更・推奨）
- 変更箇所: `js-throw.blade.php` の `touchend` / `mouseup` の末尾
- 方法:
  1. ボール投擲（既存）をそのまま実行
  2. `activeModel` と `currentMarkerStampId` が存在する場合だけ `collectAndMarkWithRetry(currentMarkerStampId, null, 3, 2000)` を呼ぶ
  3. 二重実行防止として短時間クールダウン（例: 500ms）を追加
- メリット:
  - 変更範囲が小さい（1ファイル）
  - 現在の命中判定ロジックを壊さない
  - 失敗時も既存の命中判定が保険として残る
- デメリット:
  - 物理命中前にGETが成立するため、厳密な命中感は弱まる

### 案B: `pokeball-throwable` に自動GETモード追加（中変更）
- 変更箇所: `aframe-components.blade.php`（`throw` 時に対象stampIdを保持し、`tick` 冒頭で即GET）
- メリット:
  - 投擲コンポーネントにロジック集約できる
  - 入力経路（タッチ/マウス）を問わず共通化される
- デメリット:
  - 既存の当たり判定ロジックへ触るためリスク増
  - 影響範囲が広く、最小変更要件にやや反する

### 案C: グローバルフラグ方式（低変更だが保守性低）
- 変更箇所: `js-throw.blade.php` で `window._forceCaptureStampId` を設定し、`aframe-components.blade.php` 側で参照
- メリット:
  - 実装速度が速い
- デメリット:
  - グローバル状態依存が増え、将来不具合の温床になりやすい
  - 推奨しない

## 推奨案
- **案A**（最小変更・低リスク・要件適合）

## 変更対象（案A採用時）
- `resources/views/ARstampRally202605/js-throw.blade.php` のみ

## 実施ステップ（案A）
1. `touchend` の投擲処理末尾に「`activeModel` がある時だけ自動GET」処理を追加
2. `mouseup` の投擲処理末尾にも同等処理を追加
3. 二重GET防止のクールダウン変数を追加（短時間）
4. `php -l resources/views/ARstampRally202605/js-throw.blade.php` を実行

## リスクと対策（案A）
- リスク: 連打時に同一対象へGET処理が複数回走る可能性
  - 対策: クールダウンと既存 `collectStamp` の重複防止に依存
- リスク: マーカー見失い直前の境界タイミング
  - 対策: `activeModel` と `currentMarkerStampId` の両方存在チェック

## 検証観点（案A）
- Android: ズームが残っていても、マーカー検出中に投げれば捕獲できること
- iPhone / PC: 従来の投擲演出が維持され、捕獲処理が壊れないこと
- 重複防止: 同一対象へ連打してもデータ破損しないこと
