# 設計: /stamp202605 Androidズーム未解決問題（案1: 最小修正）

## 前段階ファイル読込
前段階のmdファイルを読み込みました（`.claude_workflow/requirements.md`）。

## 設計方針
- 変更は `resources/views/ARstampRally202605/` 配下のみに限定する。
- 端末特化（moto g64y 固有分岐）は行わず、汎用的に効く最小修正を採用する。
- 既存の iPhone / Android の機能（モデル表示・投擲・ギャラリー・撮影）を壊さない。

## 根本原因（確定）
1. **AR.js設定の二重適用**
   - `scene.blade.php` のサーバー描画時 `arjs` 設定と、`js-init.blade.php` の Android 再設定が重複。
   - 該当:
     - `resources/views/ARstampRally202605/scene.blade.php:11`
     - `resources/views/ARstampRally202605/js-init.blade.php:8`

2. **ズーム対策CSSの適用先不足**
   - `video` のみ `object-fit` 指定されており、AR最終描画（canvas）への制御が不十分。
   - 該当:
     - `resources/views/ARstampRally202605/head.blade.php:174`

3. **ピンチ機能と要件不一致**
   - 現状 `scene` でピンチによるモデル拡縮が有効だが、ユーザー要件は「ピンチ無効化」。
   - 該当:
     - `resources/views/ARstampRally202605/js-init.blade.php:391-414`

## 採用する解決案（案1）

### 変更A: AR.js設定を単一化
- `js-init.blade.php` 冒頭の Android 640x480 上書きブロックを削除。
- `scene.blade.php` の `arjs` 属性のみを正とする。

### 変更B: AR表示レイヤーのCSS補強
- `head.blade.php` の `video` ルールを残しつつ、`a-scene canvas` にも表示制御を追加。
- 目的:
  - 端末依存で `video` のみでは効かないケースを補完
  - AR合成出力の見かけ拡大を抑制

### 変更C: シーン内ピンチズームの無効化
- `js-init.blade.php` の `scene` に対する `touchstart/touchmove/touchend` ピンチ処理を削除。
- ホイール拡縮はPC操作のため維持（モバイル影響なし）。

## 変更対象ファイル
- `resources/views/ARstampRally202605/js-init.blade.php`
- `resources/views/ARstampRally202605/head.blade.php`

## 非変更（維持）
- `scene.blade.php` の Android向け 640x480 方針は維持。
- `lowres` 再試行ボタン（320x240）経路は維持。
- 投擲・ギャラリー・撮影・景品交換ロジックは維持。

## 実施手順
1. `js-init.blade.php` から Android再設定ブロックを削除。
2. `head.blade.php` に `a-scene canvas` 向けの表示制御を追加。
3. `js-init.blade.php` のピンチズームイベントを削除。
4. `php -l` で変更PHP/Bladeファイルの構文検証。
5. 影響範囲（最低限）を手動確認。

## リスクと対策
- リスク1: 一部端末で表示余白が出る可能性
  - 対策: `contain` 指定で意図的に視野優先。表示欠け（見切れ）を防ぐ。
- リスク2: ピンチ無効化による操作仕様変更
  - 対策: 要件に基づく明示変更であり、想定どおり。

## 検証観点
- Android (moto g64y): AR映像の見かけ拡大が解消されるか
- Android/iPhone 共通: マーカー検出・モデル表示・投擲・ギャラリー・撮影が継続するか
- UI操作: スタンプ帳・ガイド・景品交換のタップ操作が維持されるか

## 設計結果チェック
- 要件（最小変更・汎用対応・ピンチ無効化）に一致している。
- 変更範囲は `ARstampRally202605` 配下に限定されている。
