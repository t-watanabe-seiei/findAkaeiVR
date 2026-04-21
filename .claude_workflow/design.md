# 設計: /stamp202605 Androidズーム未解決問題（案1: 最小修正）

## 前段階ファイル読込
前段階のmdファイルを読み込みました（`.claude_workflow/requirements.md`）。

## 設計方針
- 変更は `resources/views/ARstampRally202605/` 配下のみに限定する。
- 端末特化（moto g64y 固有分岐）は行わず、汎用的に効く最小修正を採用する。
- 既存の iPhone / Android の機能（モデル表示・投擲・ギャラリー・撮影）を壊さない。

---

## 第1フェーズ 根本原因（解決済み 2026-04-21）
1. AR.js設定の二重適用 → `js-init.blade.php` から Android 再設定ブロックを削除済み
2. ズーム対策CSSの適用先不足 → `head.blade.php` に `a-scene canvas` ルール追加済み
3. ピンチズームハンドラが残存 → `js-init.blade.php` の touchstart/touchmove/touchend を削除済み

---

## 第2フェーズ 根本原因（新規確定 2026-04-21）

### 根本原因A（ボール方向）: `look-controls` の自動付加
- A-Frame は `<a-entity camera>` に `look-controls` を自動付加する
- Android Chrome では DeviceOrientationEvent（ジャイロ）を許可なく受信
- `scene.camera.quaternion` がジャイロ値で上書きされ、ボール投げ方向がARと無関係になる
- iPhone は iOS13以降 DeviceOrientationEvent に許可が必要 → 未起動 → 問題なし

### 根本原因B（ページズーム）: `look-controls` のタッチハンドラが伝搬を遮断
- `look-controls` の touchstart/touchmove が document への伝搬を止める
- `gesturestart/gesturechange/gestureend` は Android Chrome 非対応（iOS Safari 専用）
- `user-scalable=no` は Android Chrome 65以降でアクセシビリティ理由により**無視される**
- document レベルのズーム防止ハンドラに 2本指タッチが届かず、ページズームが発生

### 結論: 両問題の原因は `look-controls` の自動付加 → 1行変更で両方解消

---

## 採用する解決案（案1: ユーザー選択）

### 変更内容
**ファイル**: `resources/views/ARstampRally202605/scene.blade.php`  
**変更量**: 1行のみ（属性を1つ追加）

```html
<!-- 変更前 -->
<a-entity camera></a-entity>

<!-- 変更後 -->
<a-entity camera look-controls="enabled: false"></a-entity>
```

### 効果
- `look-controls` が無効化され DeviceOrientationEvent 受信が停止
- `camera.quaternion` が AR.js のマーカー追跡値のみで制御される
- ボールが正しく AR キャラクターの方向に飛ぶ
- look-controls のタッチハンドラが消え、ピンチズーム防止ハンドラが document に届く

### 副次確認（追加変更なし）
- 前回追加した `a-scene canvas { object-fit: contain }` は canvas に object-fit は効かないため**削除しない**（最小変更の原則）
- ただし視覚的なズレが今後問題になれば案2として対応可能

## 変更対象ファイル
- `resources/views/ARstampRally202605/scene.blade.php` （1行のみ）

## 非変更（維持）
- `head.blade.php`: 前回追加のCSS維持
- `js-init.blade.php`: 前回の削除内容維持
- `scene.blade.php`: Android向け 640x480 設定、arjs属性の内容は維持

## リスクと対策
- リスク: look-controls 無効化でカメラ回転制御が完全に AR.js 依存になる
  - 対策: AR.js はもともとマーカー追跡でカメラを制御するため問題なし
- リスク: PC での操作に影響する可能性
  - 対策: PCでは元々 look-controls を使っていない（mousedown でドラッグ操作していない）

## 検証観点
- Android (moto g64y): ページズームが出なくなるか
- Android (moto g64y): ボールがキャラクター方向に飛ぶか
- iPhone SE: マーカー検出・モデル表示・投擲が継続するか
- PC: マーカー検出・モデル表示・投擲が継続するか
