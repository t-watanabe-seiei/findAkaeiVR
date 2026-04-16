# タスク化: ARstampRally202605 新規作成

## 作成日時
2026年4月16日

## 前提
`.claude_workflow/design.md` を読み込み済み

---

## タスク一覧

### Task 1: サブディレクトリ作成 & エントリポイント作成
- `resources/views/ARstampRally202605/` ディレクトリ作成
- `resources/views/ARstampRally202605.blade.php` 作成（@includeエントリポイント）
- **ステータス**: ⬜ 未着手

### Task 2: head.blade.php 作成
- `<head>` + CSS + グローバルJS（カメラ監視・エラーハンドラ等）
- `ar-engine.min.js` / `ar-tracking.min.js` の読み込み
- Android `video` ズーム防止CSS（`object-fit: contain`）
- **ステータス**: ⬜ 未着手

### Task 3: aframe-components.blade.php 作成
- `pokeball-throwable` コンポーネント（202603から流用）
- `hitbox` コンポーネント（202603から流用）
- `lazy-model` コンポーネント（202603から流用）
- `click-animation` コンポーネント（anime03対応を追加）
- **ステータス**: ⬜ 未着手

### Task 4: scene.blade.php 作成
- `<a-scene>` 全体
- maker00（ギャラリーマーカー・モデルなし）
- maker01〜maker10（Bladeループで生成）
- **ステータス**: ⬜ 未着手

### Task 5: ui.blade.php 作成
- スタンプ帳ボタン・モーダル
- ガイドボタン・モーダル
- 写真・動画・カメラ切り替えボタン
- 確認ダイアログ
- カメラエラーUI
- **ステータス**: ⬜ 未着手

### Task 6: js-stamps.blade.php 作成
- `STAMPS` オブジェクト定義（model_01〜model_10）
- LocalStorage管理関数（getCollectedStamps, saveCollectedStamps等）
- スタンプ帳表示（showStampBook）
- バッジ更新
- パーティクルエフェクト
- collectStamp, collectAndMarkWithRetry
- **ステータス**: ⬜ 未着手

### Task 7: js-prize.blade.php 作成
- UUID生成・ユーザーID管理（IndexedDB, Cookie, LocalStorage）
- `generateFingerprint()`, `collectDeviceInfo()`
- `recordMarkerScan()`, `recordMarkerDetection()`
- `exchangePrize()`（閾値6個）
- `updatePrizeButton()`（閾値6個）
- `showPrizeCode()`, `showRedeemedPrizeInfo()`
- **ステータス**: ⬜ 未着手

### Task 8: js-throw.blade.php 作成
- タップ投げロジック（touchstart/touchend）
- `isUIButton()` 関数
- `throwPokeballToCenter()` 関数
- PC用 mousedown/mouseup 対応
- **ステータス**: ⬜ 未着手

### Task 9: js-gallery.blade.php 作成
- maker00 markerFound/markerLost イベントハンドラ
- 捕獲済みモデルを動的に `<a-entity>` として追加
- Y軸1.0間隔で縦並び（X=0, Z=0）
- anime03ループ再生
- galleryEntitiesのmixerをsceneのtickで更新
- **ステータス**: ⬜ 未着手

### Task 10: js-camera.blade.php 作成
- 写真撮影機能（cameraButton）
- 動画撮影機能（videoButton）
- カメラ切り替え（switchCameraButton）
- 写真プレビュー・ダウンロード
- **ステータス**: ⬜ 未着手

### Task 11: js-init.blade.php 作成
- DOMContentLoaded ハンドラ
- Android AR.js 解像度オーバーライド
- マーカーイベントハンドラ（maker01〜10の markerFound/markerLost）
- スタンプ帳モーダルのopen/close
- ガイドモーダルのopen/close（日英切り替え）
- カメラ権限チェック（`checkCameraPermissions()`）
- ピンチズーム（拡大縮小）
- ヒットエフェクト（`showHitEffect()`）
- resumeCamera()
- 初期化（バッジ更新、guideModal初期表示）
- scene tick ハンドラ（galleryEntitiesのmixerを更新）
- **ステータス**: ⬜ 未着手

### Task 12: routes/web.php にルート追加
- `/stamp202605` → `ARstampRally202605` ビュー
- `admin` グループ内に `/dashboard202605` ルート
- **ステータス**: ⬜ 未着手

### Task 13: AdminController に dashboard202605() 追加
- dashboard202603() をベースに日付範囲・animals配列・return viewを変更
- **ステータス**: ⬜ 未着手

### Task 14: admin/dashboard202605.blade.php 作成
- dashboard202603.blade.php をコピーし、202605用に調整（タイトル・日付範囲表示等）
- **ステータス**: ⬜ 未着手

### Task 15: PHP構文チェック & エラー修正
- `php -l` で全変更PHPファイルをチェック
- **ステータス**: ⬜ 未着手

### Task 16: README.md 追記
- ARstampRally202605 の機能概要を追記
- **ステータス**: ⬜ 未着手

---

# タスク化: shooting3Dterrer3 VRゴーグル処理落ち修正

## 作成日時
2026年3月18日

## 前提
`.claude_workflow/design.md` を読み込み済み

---

## タスク一覧

### Task 1: aframe-physics-system スクリプトタグ削除
**目的**: 未使用の物理エンジンを読み込まなくし、毎フレームのCPU/GPU負荷を除去  
**対象ファイル**: `resources/views/shooting3Dterrer3.blade.php`  
**変更**: line 11 の `<script src="{{ asset('js/aframe-physics-system.min.js') }}"></script>` を1行削除  
**ステータス**: ✅ 完了

### Task 2: `<a-scene>` の physics 属性削除
**目的**: 物理エンジン属性を除去してシーン初期化負荷を排除  
**対象ファイル**: `resources/views/shooting3Dterrer3.blade.php`  
**変更**: line 3255 の `physics="gravity: -9.8"` 行を削除  
**ステータス**: ✅ 完了

### Task 3: restartGame のシーン全体 dispose ブロック削除
**目的**: リスタート時にスカイボックス・ライト・UIなど全オブジェクトを破棄してしまう箇所を削除し、WebGLクラッシュを防止  
**対象ファイル**: `resources/views/shooting3Dterrer3.blade.php`  
**変更**: lines 1223〜1283 の `// 🚀🚀 強化: THREE.jsの完全なGPUリソース解放` コメント〜`if (sceneEl && sceneEl.renderer) { ... }` ブロック全体を削除  
**ステータス**: ✅ 完了

### Task 4: anisotropy を 16 → 2 に変更（4箇所）
**目的**: モバイルGPU（Snapdragon XR2）に適正な値に変更し、テクスチャフィルタリングの過負荷を除去  
**対象ファイル**: `resources/views/shooting3Dterrer3.blade.php`  
**変更**: enhance-materials コンポーネント内の `anisotropy = 16` を全4箇所 `anisotropy = 2` に変更  
**ステータス**: ✅ 完了

### Task 5: restartGame の allModels traverse+dispose ブロック削除
**目的**: リスタート時の各モデル削除で共有GLBリソースを dispose しないようにする  
**対象ファイル**: `resources/views/shooting3Dterrer3.blade.php`  
**変更**: `// THREE.jsレベルのクリーンアップ` コメント〜`if (model.object3D) { ... }` ブロックを削除  
**ステータス**: ✅ 完了

### Task 6: ボールヒット時の traverse+dispose ブロック削除
**目的**: ヒット時のアニメーション終了後にボールGLBを dispose しないようにする  
**対象ファイル**: `resources/views/shooting3Dterrer3.blade.php`  
**変更**: registerTimeout コールバック内の `// 🚀 メモリ解放` ブロック（lines 1855〜1872）を削除  
**ステータス**: ✅ 完了

### Task 7: ボール落下/タイムアウト時の traverse+dispose ブロック削除
**目的**: ボールが落下・射程外になった時の dispose を削除  
**対象ファイル**: `resources/views/shooting3Dterrer3.blade.php`  
**変更**: `// 🚀 メモリ解放` ブロック（lines 1911〜1927）を削除  
**ステータス**: ✅ 完了

### Task 8: despawnAndRespawn の traverse+dispose ブロック削除
**目的**: ゾンビデスポーン時の共有GLBリソース dispose を削除  
**対象ファイル**: `resources/views/shooting3Dterrer3.blade.php`  
**変更**: `// 🚀 改善: 削除前にメモリを解放` ブロック（lines 2354〜2374）を削除  
**ステータス**: ✅ 完了

---

## 実行順序
Task 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8（すべて独立。同時適用可）

---

# タスク化: ARstampRally202603 Android (moto g64y) バグ修正

## 作成日時
2026年3月18日

## 前提
`.claude_workflow/design.md` を読み込み済み

---

## タスク一覧

### Task 1: throwBall() の `loaded` → `model-loaded` + フォールバック修正
**目的**: Android でボールが表示されない・投げられない問題を解消  
**対象ファイル**: `resources/views/ARstampRally202603.blade.php`  
**対象行**: 7184 付近（`newBall.addEventListener('loaded', () => {` の部分）

**変更前**:
```js
// コンポーネントが初期化されたら投げる
newBall.addEventListener('loaded', () => {
    // マテリアル調整（既存コードと同様）
    const model = newBall.getObject3D('mesh');
    if (model) {
        model.traverse(function(node) {
            if (node.isMesh) {
                if (node.geometry) node.geometry.computeVertexNormals();
                if (node.material) {
                    const materials = Array.isArray(node.material) ? node.material : [node.material];
                    materials.forEach(mat => {
                        mat.side = THREE.DoubleSide;
                        mat.depthWrite = true;
                        mat.depthTest = true;
                        mat.flatShading = false;
                        mat.transparent = false;
                        mat.opacity = 1.0;
                        mat.needsUpdate = true;
                    });
                }
                node.frustumCulled = false;
            }
        });
    }
    
    // 投げる処理を実行（当たり判定はコンポーネント内で行うため、ここでのsetIntervalは削除）
    newBall.components['pokeball-throwable'].throw(direction, speed);
});
```

**変更後**:
```js
// コンポーネントが初期化されたら投げる
// model-loaded を使用（GLB が確実にロードされたタイミング）
// Android ではキャッシュ済みの場合 loaded より前に発火するためフォールバック追加
let throwCalled = false;
function doThrowSetup() {
    if (throwCalled) return;
    throwCalled = true;
    // マテリアル調整
    const model = newBall.getObject3D('mesh');
    if (model) {
        model.traverse(function(node) {
            if (node.isMesh) {
                if (node.geometry) node.geometry.computeVertexNormals();
                if (node.material) {
                    const materials = Array.isArray(node.material) ? node.material : [node.material];
                    materials.forEach(mat => {
                        mat.side = THREE.DoubleSide;
                        mat.depthWrite = true;
                        mat.depthTest = true;
                        mat.flatShading = false;
                        mat.transparent = false;
                        mat.opacity = 1.0;
                        mat.needsUpdate = true;
                    });
                }
                node.frustumCulled = false;
            }
        });
    }
    // 投げる処理を実行
    newBall.components['pokeball-throwable'].throw(direction, speed);
}
newBall.addEventListener('model-loaded', doThrowSetup);
// フォールバック: model-loaded が来なかった場合 (Android キャッシュ済みモデル等)
setTimeout(doThrowSetup, 500);
```

**完了条件**: `php -l` エラーなし

---

### Task 2: Android 向け AR.js カメラ解像度動的調整
**目的**: moto g64y ポートレートモードのカメラズーム問題を解消  
**対象ファイル**: `resources/views/ARstampRally202603.blade.php`  
**対象行**: 3699 の `DOMContentLoaded` コールバック冒頭 (行 3700: `const scene = ...` の直前)

**追加するコード**:
```js
// Android 判定: カメラ解像度を 640×480 (4:3) に変更してポートレート表示のズームを防止
(function() {
    if (!/Android/i.test(navigator.userAgent)) return;
    const _scene = document.querySelector('a-scene');
    if (!_scene) return;
    _scene.setAttribute('arjs',
        'sourceType: webcam; debugUIEnabled: false; sourceWidth: 640; sourceHeight: 480; detectionMode: mono; maxDetectionRate: 15;'
    );
    console.log('[Android fix] AR.js source size overridden to 640x480');
})();
```

**挿入位置**: `document.addEventListener('DOMContentLoaded', function() {` の次の行（`const scene = ...` の前）

**完了条件**: `php -l` エラーなし

---

### Task 3: PHP lint 確認
**目的**: 修正によって PHP 構文エラーが発生していないことを確認  
**コマンド**: `php -l resources/views/ARstampRally202603.blade.php`  
**完了条件**: `No syntax errors detected` が表示される

---

## 実行順序
1. Task 1 (throwBall 修正)
2. Task 2 (Android カメラ解像度)
3. Task 3 (PHP lint)

## ステータス
- [x] Task 1: loaded → model-loaded + フォールバック
- [x] Task 2: Android カメラ解像度調整
- [x] Task 3: PHP lint 確認

---

# タスクリスト: ARstampRally202603 HUDポケボール DOM オーバーレイ化（パターン2）

## 作成日時
2026年4月6日

## 前提
`.claude_workflow/design.md` を読み込み済み

---

## Task 1: CSS追加 — `#hud-pokeball` スタイル
**ファイル**: `resources/views/ARstampRally202603.blade.php`  
**対象行**: `</style>` タグ直前（~2003行目）  
**内容**: `#hud-pokeball` および `#hud-pokeball img` のスタイル定義を追加  
**完了条件**: CSS が追加されている  
- [ ] Task 1 完了

---

## Task 2: HTML追加 — `<div id="hud-pokeball">` 挿入
**ファイル**: `resources/views/ARstampRally202603.blade.php`  
**対象行**: `<button id="throw-button">` の閉じタグ直後（~2172行目）  
**内容**: `<div id="hud-pokeball"><img src="{{ asset('cg/pokeball_image.png') }}" alt="pokeball"></div>` を追加  
**完了条件**: DOM要素が追加されている  
- [ ] Task 2 完了

---

## Task 3: HTML変更 — `#holding-pokeball` を非表示化
**ファイル**: `resources/views/ARstampRally202603.blade.php`  
**対象行**: ~2201行目  
**内容**: `visible="true"` → `visible="false"`  
**完了条件**: A-Frame エンティティが非表示設定になっている  
- [ ] Task 3 完了

---

## Task 4: JS変更 — `captureModelScreenshot()` 非表示処理
**ファイル**: `resources/views/ARstampRally202603.blade.php`  
**対象行**: ~3371〜3376行目（hide部分）、~3400,3415,3421行目（restore部分×3）  
**内容**: `holding-pokeball` の `display` 操作 → `hud-pokeball` の `visibility` 操作に変更  
**完了条件**: スクリーンショット時にHUDボールが非表示になる  
- [ ] Task 4 完了

---

## Task 5: JS変更 — IIFE先頭 `hudEl` 変数追加・`applyHoldingBallFix` 削除
**ファイル**: `resources/views/ARstampRally202603.blade.php`  
**対象行**: ~7028〜7074行目  
**内容**:  
1. `let hudEl = document.getElementById('hud-pokeball');` を `ballEntity` 宣言の直後に追加  
2. `applyHoldingBallFix()` 関数定義・`addEventListener`・`setTimeout` × 2 を全て削除  
**完了条件**: `hudEl` が宣言されており、`applyHoldingBallFix` 関連コードがすべて存在しない  
- [ ] Task 5 完了

---

## Task 6: JS変更 — タッチ/マウスイベント ハンドラ書き換え
**ファイル**: `resources/views/ARstampRally202603.blade.php`  
**対象箇所（6か所）**:  
- touchstart ~7096: `ballEntity.setAttribute('position',...)` → `hudEl.style.transform`  
- touchend ~7131: `ballEntity.setAttribute('visible','false')` 等 → `hudEl.style.visibility='hidden'`  
- touchcancel ~7139: reset position → `hudEl.style.transform + visibility` リセット  
- mousedown ~7155: touchstart と同様  
- mouseup ~7169: touchend と同様  
**完了条件**: `ballEntity.setAttribute` によるHUD操作がすべて `hudEl.style.*` に置換されている  
- [ ] Task 6 完了

---

## Task 7: JS変更 — `throwBall()` 初期位置計算を代替式に変更
**ファイル**: `resources/views/ARstampRally202603.blade.php`  
**対象行**: ~7183〜7185行目  
**内容**: `ballEntity.object3D.getWorldPosition(worldPos)` → カメラ位置 + quaternion offset  
**完了条件**: `getWorldPosition` の呼び出しがなくなり、camera位置ベースの計算になっている  
- [ ] Task 7 完了

---

## Task 8: JS変更 — `restoreBall()` 復元処理を `hudEl.style.*` に変更
**ファイル**: `resources/views/ARstampRally202603.blade.php`  
**対象行**: ~7255〜7259行目  
**内容**: `ballEntity.setAttribute('visible','true')` → `hudEl.style.visibility='visible'` + transform リセット  
**完了条件**: `restoreBall` が `hudEl` を復元するようになっている  
- [ ] Task 8 完了

---

## Task 9: PHP lint 確認
**コマンド**: `php -l resources/views/ARstampRally202603.blade.php`  
**完了条件**: `No syntax errors detected` が表示される  
- [ ] Task 9 完了

---

## 実行順序
Task 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9（順番に実施。エラーが出たら解決してから次へ）

## ステータス
- [x] Task 1: CSS追加
- [x] Task 2: HTML追加
- [x] Task 3: `#holding-pokeball` 非表示化
- [x] Task 4: captureModelScreenshot 修正
- [x] Task 5: hudEl追加 + applyHoldingBallFix削除
- [x] Task 6: タッチ/マウスイベント書き換え
- [x] Task 7: throwBall 初期位置計算変更
- [x] Task 8: restoreBall 復元処理変更
- [x] Task 9: PHP lint 確認

---

# タスク化10: ギャラリーマーカー（maker00）iPhone SEフリーズ修正

## 前段階のmdファイルを読み込みました
`.claude_workflow/design.md` の設計10を参照

## タスク一覧

- [x] Task 1: js-gallery.blade.php を全面書き換え（キャッシュ＋デバウンス＋逐次ロード）
- [x] Task 2: PHP lint 確認
- [x] Task 3: 動作確認（ブラウザでアクセス可能か確認）
