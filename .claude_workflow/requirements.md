# 要件定義: /stamp202605 投擲時自動GET化（ズーム継続時の運用回避）

---

# 要件定義: /stamp202603 UI改修・自動GET実装（2026-04-22）

## 作成日時
2026-04-22

## 前段階ファイル読込
前段階のmdファイルを読み込みました（既存 `requirements.md`, `design.md`, `tasks.md`）。

## 背景
- `/stamp202603` は `/stamp202605` に先行するバージョン。
- `/stamp202605` で導入した以下の改善を `/stamp202603` にも反映したい。

## 目的・仕様（ユーザー確定済み）

### 1. ガイド初期非表示
- `window.guideModalOpen = true` → `false` に変更し、起動時にガイドモーダルを自動表示しない。
- ガイドボタンは残し、手動で開けるようにする。

### 2. Android Zoom修正
- 案B採用: 202605 の CSS・イベント一式をポート。
  - `video { object-fit: contain !important; }`
  - `a-scene canvas { object-fit: contain !important; width: 100% !important; height: 100% !important; }`
  - `gesturestart/change/end` preventDefault イベント追加
  - `touchmove` 2本指防止、`touchend` ダブルタップ防止イベント追加
  - `<a-entity camera look-controls="enabled: false">` 追加

### 3. UI左上集約
- 案A採用: `#top-left-buttons` コンテナを追加し、以下4ボタンを横並びに集約。
  - `#stamp-book-button`, `#guide-button`, `#camera-button`, `#video-button`
- 各ボタンの個別 `position: fixed` CSS は削除し、コンテナ内のスタイルに統一。
- `#throw-button` は現状位置のまま維持。

### 4. カメラ切替ボタン削除
- HTML から `<button id="switch-camera-button">` を削除。
- CSS から `#switch-camera-button` スタイルを削除。
- JS から `switchCameraButton.addEventListener(...)` イベントハンドラを削除。
- `isUIButton()` 内の `switch-camera-button` 参照を削除。

### 5. 投擲演出維持（案C: 両方残す）
- `#throw-button` → `throwPokeballToCenter(speed)` は従来どおり維持。
- 新規: `document.addEventListener('touchstart/touchend')` でどこでもタップで方向投げを追加。
- 新規: `document.addEventListener('mousedown/mouseup')` で PC マウスでも投げられるように追加。

### 6. マーカー検出中の自動GET（投擲1秒後）
- `pokeball-throwable` コンポーネントに `schema: { autoGetStampId, autoGetDelayMs }` 追加。
- `throw()` 実行時に `autoGetStampId` が設定されていれば、`autoGetDelayMs`（1000ms）後に `tryAutoGet()` を呼ぶ。
- `tryAutoGet()` は `collectAndMarkWithRetry(stampId, ...)` を呼ぶ。
- 実ヒットが先に発生した場合（`handleHit()` 内）は `autoGetTimer` をキャンセルして二重処理を防止。
- `getActiveVisibleStampId()` 関数を追加し、可視ヒットボックスから stampId を取得。
- 投擲時（ボタン・タップどちらも）に `getActiveVisibleStampId()` を取得して pokeball に渡す。

## 現状把握
- 対象ファイル: `resources/views/ARstampRally202603.blade.php`（7291行の単一ファイル）
- 参考ファイル: `resources/views/ARstampRally202605/` 配下の各サブファイル
- `pokeball-throwable` は line 349 付近。schema なし。
- `throwPokeballToCenter()` は line 4659 付近。
- `#switch-camera-button` CSS は line 1165 付近、イベントハンドラは line 6515 付近。
- ガイド初期フラグは line 14。
- UI ボタン HTML は line 2166 付近。

## 成功基準
1. ガイドモーダルが起動時に自動表示されない。ガイドボタンで手動表示は可能。
2. Android でのカメラ映像ズームが抑制される（`object-fit: contain !important` + look-controls 無効）。
3. UIボタン4個が左上にまとまって表示される。カメラ切替ボタンが消える。
4. 画面どこでもタップでも、下部ボタンでも投げられる。演出（ボール飛行）が維持される。
5. マーカー検出中に投げたとき、1秒後に自動GETが発動する。
6. 実ヒットが先に発生した場合、自動GETはキャンセルされる。
7. `php -l` で構文エラーがない。

## 制約
- 変更は `ARstampRally202603.blade.php` のみ。
- 既存の命中判定（tick/handleHit）ロジックを壊さない。
- iPhone・PC などの既存正常端末の動作を維持する。

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

