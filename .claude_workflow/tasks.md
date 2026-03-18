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
