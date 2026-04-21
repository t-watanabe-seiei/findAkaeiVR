# 要件定義: /stamp202605 Androidズーム未解決問題（moto g64y 5G）

## 作成日時
2026-04-21

## 更新日時（第2フェーズ）
2026-04-21

## 目的（更新）
`/stamp202605` で Android（moto g64y 5G）において以下2問題を解消する。
1. **ページ全体がズームされる**（ピンチ操作で広がった状態になる）
2. **ボールが明後日の方向に飛ぶ**（キャラクターにまったく当たらない）

---

## ユーザー確認（第2フェーズ 2026-04-21）
- ズームの種類: ページ全体がズームされる（ピンチ操作で広がった状態）
- ボールの挙動: 飛ぶ方向がおかしい（明後日の方向に飛ぶ）
- 他端末: iPhoneは正常。Androidのみ問題あり
- 前回変更との関連: 不明（改善したかどうかは分からない）

---

## 根本原因の特定（コード解析結果）

### 根本原因A: ボール方向がおかしい

**原因**: `<a-entity camera>` に `look-controls` が自動付加される

A-Frame は `<a-entity camera>` を設定すると `look-controls` コンポーネントを自動付加する。  
`look-controls` は Android Chrome では **DeviceOrientationEvent**（ジャイロセンサー）を無条件に受信し、
物理的なデバイスの向きでカメラを回転させる。

- `scene.camera.quaternion` = デバイスの物理的な向き（AR追跡結果ではない）
- ボール投げ方向 = `camera.quaternion` から計算 → **物理方向に飛ぶ = 明後日の方向**
- iPhone は iOS 13以降 DeviceOrientationEvent に許可が必要 → 自動起動しない → 問題が出ない

**証拠**: `ar-tracking.min.js` を解析した結果、AR.js は look-controls を無効化していない。

### 根本原因B: ページがズームされる

**原因**: look-controls のタッチイベントハンドラが `e.stopPropagation()` を呼ぶ可能性が高い

- look-controls は 2本指タッチイベントを拾う（ピンチでカメラを動かすため）
- これにより document レベルの `touchmove` ハンドラ（ズーム防止）が発火しない
- `gesturestart/gesturechange/gestureend` は **Android Chromeで未対応**（iOSSafari専用）
- `user-scalable=no` は Android Chrome 65以降で**アクセシビリティ理由により無視される**
- 結果: ページレベルのピンチズームが止められない

**追加調査（ar-tracking.min.js 解析結果）**:  
AR.js は `copyElementSizeTo()` で縦持ち時に **4:3 固定比率** を使用する。  
moto g64y 5G（20:9 画面）では canvas が 1220px 幅にスタイリングされ、  
−404px marginLeft で画面外にはみ出す。この canvas のはみ出しが  
ブラウザのビューポート幅計算に影響し、ズーム状態を引き起こしている可能性がある。

---

## 制約
- 現在動作している iPhone SE などへの影響は最小限にとどめること
- コード変更は最小限かつ可逆的であること
- 第1フェーズで行った変更（arjs二重設定削除・ピンチズームハンドラ削除）は維持する

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

