# 要件定義: /stamp202605 Androidズーム未解決問題（moto g64y 5G）

## 作成日時
2026-04-21

## 参照
`.claude_workflow/complete.md` を参照済み

## 目的
`http://127.0.0.1:8000/stamp202605` で、Android（moto g64y 5G）において「モデルは表示されるが画面がズームされて使いにくい」問題を解消する。

## 調査対象（全読了）
- `routes/web.php`
- `resources/views/ARstampRally202605.blade.php`
- `resources/views/ARstampRally202605/head.blade.php`
- `resources/views/ARstampRally202605/scene.blade.php`
- `resources/views/ARstampRally202605/ui.blade.php`
- `resources/views/ARstampRally202605/aframe-components.blade.php`
- `resources/views/ARstampRally202605/js-init.blade.php`
- `resources/views/ARstampRally202605/js-stamps.blade.php`
- `resources/views/ARstampRally202605/js-prize.blade.php`
- `resources/views/ARstampRally202605/js-throw.blade.php`
- `resources/views/ARstampRally202605/js-gallery.blade.php`
- `resources/views/ARstampRally202605/js-camera.blade.php`

## 現状把握（原因候補）

### 原因候補1: AR.js設定の二重定義
- `scene.blade.php` でサーバーサイドに `arjs` を定義している。
- `js-init.blade.php` で Android 時に再度 `setAttribute('arjs', ...)` している。
- 結果として、初期化順序とAR.js内部状態によって挙動が不安定化する可能性がある。

### 原因候補2: ズーム対策CSSの適用対象が不十分
- `head.blade.php` では `video { object-fit: contain !important; }` のみ適用。
- AR.js/A-Frame の最終描画は `canvas` であるため、`video` のみ対象では期待どおりに効かない端末がある。

### 原因候補3: Android解像度が固定（640x480）
- `scene.blade.php` と `js-init.blade.php` の双方で Android を 4:3 固定にしている。
- moto g64y の縦長画面（ポートレート）との比率差により、見かけ上「拡大される」ように見える可能性がある。

### 原因候補4: ユーザー操作としてのピンチとブラウザズーム防止の競合
- `js-init.blade.php` ではシーン内ピンチでモデル拡大縮小を許可している。
- `head.blade.php` ではブラウザズーム防止イベントを登録している。
- 端末実装依存でイベント競合し、意図しない表示スケーリングになる可能性がある。

## 変更方針の制約
- 既存の正常端末（iPhone含む）の動作を壊さない。
- 既存機能（マーカー検出、投擲、ギャラリー、撮影、景品交換）を維持する。
- 変更は最小限かつ段階的に行う。
- 原因切り分け可能な順序で適用する。

## 成功基準
- moto g64y 5G で `/stamp202605` 表示時に、操作に支障のあるズーム状態が解消される。
- モデル表示・マーカー追従・投擲・ギャラリーが継続動作する。
- iPhone など既存正常端末の挙動を維持する。

## 設計前確認の回答（ユーザー確定）
1. ズーム種別: 「ARカメラ映像だけ拡大される」
2. ピンチ操作: 「無効化する」
3. 端末特化対応: 「できれば避ける（汎用対応優先）」
4. 修正範囲: 「ARstampRally202605 配下のみ」

