# 要件定義: /stamp202605 投擲時自動GET化（ズーム継続時の運用回避）

## 作成日時
2026-04-21

## 前段階ファイル読込
前段階のmdファイルを読み込みました（`.claude_workflow/complete.md` / 既存 `requirements.md`）。

## 背景
- Android（moto g64y 5G）でズーム問題が未解消。
- ボールが飛ぶこと自体は見えるが、ズームにより着弾位置が把握しづらく捕獲しにくい。
- 既存端末を壊さないため、最小限変更で回避策を入れたい。

## 目的
- 画面タップでボールを投げた際、**マーカー検出中の対象を自動GET** できるようにする。
- 既存の投擲演出（ボールが飛ぶ表示）は維持する。

## ユーザー確認（2026-04-21）
- 適用範囲: **全端末（Android / iPhone / PC）**
- 自動GET条件: **マーカー検出中のみ（activeModel がある時だけ）**
- 演出: **ボールは従来どおり飛ばしつつ、同時に自動GET**

## 現状実装の確認結果
- 投擲入力は `js-throw.blade.php` の `touchend` / `mouseup` で処理。
- 実際の当たり判定・GETは `aframe-components.blade.php` の `pokeball-throwable` 内 `tick()` と `handleHit()` で処理。
- GET確定処理は `collectAndMarkWithRetry(stampId, ...)`（`js-stamps.blade.php`）で一元化済み。

## 成功基準
1. タップ投擲時、`activeModel` が存在する場合は当たり判定なしで該当 `stampId` をGETできる。
2. `activeModel` がない時は従来どおり（何もGETしない）。
3. 既存の命中時処理（パーティクル、通知、スタンプ帳反映）との整合性が崩れない。
4. `php -l` で変更ファイルの構文エラーがない。

## 制約
- 最小変更を最優先（既存ロジックの大規模改修は行わない）。
- 既存端末で動作中の機能を壊さない。
- 仕様追加は `/stamp202605` のみ。

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

