# 設計: shooting3Dterrer3 VRゴーグル処理落ち修正

## 作成日時
2026年3月18日

## 前提
`.claude_workflow/requirements.md` を読み込み済み

---

## 対象ファイル
`resources/views/shooting3Dterrer3.blade.php`

---

## 修正1: aframe-physics-system を削除

### 変更箇所A（line 11）- スクリプトタグ削除
```html
// 削除対象
<script src="{{ asset('js/aframe-physics-system.min.js') }}"></script>
```
→ 1行丸ごと削除

### 変更箇所B（line 3255）- `<a-scene>` の physics 属性削除
```html
// 変更前
<a-scene 
    physics="gravity: -9.8"
    renderer="antialias: true; 
```
```html
// 変更後
<a-scene 
    renderer="antialias: true; 
```
→ `physics="gravity: -9.8"` の行を削除

---

## 修正2: restartGame のシーン全体 dispose を削除

### 変更箇所（lines 1223〜1280）- `if (sceneEl && sceneEl.renderer)` ブロック全体を削除
```js
// 削除対象（この全ブロックを削除）
// 🚀🚀 強化: THREE.jsの完全なGPUリソース解放（カクツキ対策）
const sceneEl = document.querySelector('a-scene');
if (sceneEl && sceneEl.renderer) {
    const renderer = sceneEl.renderer;
    // 1〜4. renderLists.dispose() など（安全なもの）
    // 5. sceneEl.object3D.traverse(...) ← ブラウザクラッシュの原因
    ...
    window.debugLog('🚀 THREE.js GPU resource cleanup done (safe mode)');
}
```
→ ブロック全体（`// 🚀🚀 強化` コメントから closing `}` まで）を削除。  
  A-Frame が WebGL コンテキストを管理しているため、外部から全オブジェクトを dispose するのは禁止。

---

## 修正3: anisotropy を 16 → 2 に変更

### 変更箇所（lines 76, 97, 100, 103）- enhance-materials コンポーネント内
4箇所すべて `anisotropy = 16` → `anisotropy = 2`

```js
// 変更前（4箇所）
node.material.map.anisotropy = 16;
node.material.metalnessMap.anisotropy = 16;
node.material.roughnessMap.anisotropy = 16;
node.material.normalMap.anisotropy = 16;

// 変更後（4箇所）
node.material.map.anisotropy = 2;
node.material.metalnessMap.anisotropy = 2;
node.material.roughnessMap.anisotropy = 2;
node.material.normalMap.anisotropy = 2;
```
→ Snapdragon XR2 での適正値。テクスチャ品質の大幅な劣化なし。

---

## 修正4: traverse+dispose ブロックを全箇所削除

GLBモデルは `<a-assets>` に登録され、ロード後は共有キャッシュとして使われる。
dispose すると他インスタンスのレンダリングも破壊される。
**DOM から `removeChild` するだけでよく、dispose は不要。**

### 変更箇所A（lines 1321〜1340）- restartGame の allModels.forEach 内

削除対象:
```js
// THREE.jsレベルのクリーンアップ
if (model.object3D) {
    model.object3D.traverse((node) => {
        if (node.geometry) { node.geometry.dispose(); }
        if (node.material) {
            if (Array.isArray(node.material)) { node.material.forEach(mat => mat.dispose()); }
            else { node.material.dispose(); }
        }
        // 🚀 追加: テクスチャも破棄
        if (node.material && node.material.map) { node.material.map.dispose(); }
    });
}
```
→ このブロックを削除（直後の `removeChild` は残す）

### 変更箇所B（lines 1855〜1872）- ボールヒット時のタイムアウト内

削除対象:
```js
// 🚀 メモリ解放: THREE.jsオブジェクトを破棄
if (ball.object3D) {
    ball.object3D.traverse((node) => {
        if (node.geometry) node.geometry.dispose();
        if (node.material) { ... node.material.dispose(); }
    });
}
```
→ このブロックを削除（直後の `removeChild` は残す）

### 変更箇所C（lines 1911〜1927）- ボール落下/タイムアウト時

削除対象:
```js
// 🚀 メモリ解放: THREE.jsオブジェクトを破棄
if (ball.object3D) {
    ball.object3D.traverse((node) => {
        if (node.geometry) node.geometry.dispose();
        if (node.material) { ... node.material.dispose(); }
    });
}
```
→ このブロックを削除（直後の `removeChild` は残す）

### 変更箇所D（lines 2354〜2374）- despawnAndRespawn のタイムアウト内

削除対象:
```js
// 🚀 改善: 削除前にメモリを解放
if (modelGroup.object3D) {
    modelGroup.object3D.traverse((node) => {
        if (node.geometry) { node.geometry.dispose(); }
        if (node.material) { ... node.material.dispose(); }
    });
}
```
→ このブロックを削除（直後の `usedPatterns` クリアと `removeChild` は残す）

---

## 変更箇所まとめ

| # | 場所 | 変更内容 |
|---|------|---------|
| 1a | line 11 | `<script>` タグ1行削除 |
| 1b | line 3255 | `physics="gravity: -9.8"` 行削除 |
| 2 | lines 1223-1280 | `if (sceneEl && sceneEl.renderer)` ブロック全体削除 |
| 3 | lines 76,97,100,103 | `anisotropy = 16` → `anisotropy = 2` (4箇所) |
| 4a | lines 1321-1340 | allModels traverse+dispose ブロック削除 |
| 4b | lines 1855-1872 | ボールヒット traverse+dispose ブロック削除 |
| 4c | lines 1911-1927 | ボール落下 traverse+dispose ブロック削除 |
| 4d | lines 2354-2374 | despawnAndRespawn traverse+dispose ブロック削除 |

合計: 8箇所の変更。削除のみ（新規追加なし）。

---

# 設計: ARstampRally202603 Android (moto g64y) バグ修正

## 作成日時
2026年3月18日

## 前提
`.claude_workflow/requirements.md` を読み込み済み

---

## 修正A : throwBall() の `loaded` → `model-loaded` + フォールバック

### 問題箇所
`resources/views/ARstampRally202603.blade.php` 行 7186 付近

現在のコード:
```js
newBall.addEventListener('loaded', () => {
    const model = newBall.getObject3D('mesh');
    if (model) {
        model.traverse(function(node) {
            if (node.isMesh) {
                // ... frustumCulled = false 等 ...
            }
        });
    }
    newBall.components['pokeball-throwable'].throw(direction, speed);
});
```

### 問題の理由
- `loaded` = A-Frame エンティティの component 初期化完了。GLB ロードは非同期なので
  この時点では `getObject3D('mesh')` が null → frustumCulled が設定されない
- Android でキャッシュ済み GLB の場合、`loaded` が appendChild 前に発火することがある
  → リスナーが登録される前にイベントが過ぎてしまい throw() が呼ばれない

### 修正方針
1. `loaded` を `model-loaded` に変更（mesh が確実に存在するタイミング）
2. `model-loaded` が来ない場合のフォールバック: 500ms 後に throw() を強制実行
3. 二重実行防止フラグ `throwCalled` を使用

### 修正後コード（イメージ）
```js
let throwCalled = false;
function doThrowSetup() {
    if (throwCalled) return;
    throwCalled = true;
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
    newBall.components['pokeball-throwable'].throw(direction, speed);
}
newBall.addEventListener('model-loaded', doThrowSetup);
// フォールバック: model-loaded が来なかった場合 (Android キャッシュ等)
setTimeout(doThrowSetup, 500);
```

### iPhone への影響
- `model-loaded` は A-Frame の標準イベント。iPhone でも同様に発火する
- フォールバックの setTimeout(500) は model-loaded が先に発火した場合は `throwCalled=true` により無効化
- **影響なし**

---

## 修正B : AR.js カメラ解像度の Android 動的調整

### 問題箇所
`<a-scene>` の `arjs` 属性（HTML側、行 2184 付近）:
```html
arjs="sourceType: webcam; debugUIEnabled: false; sourceWidth: 1280; sourceHeight: 720; detectionMode: mono; maxDetectionRate: 15;"
```

### 問題の理由
- `sourceWidth: 1280; sourceHeight: 720` は 16:9 横向き
- moto g64y ポートレートモードでは getUserMedia が実際には
  別の解像度でストリームを返し、AR.js が映像を引き延ばす
- AR_FORCE_LOWRES (Android ≤7 用) は moto g64y (Android 14) では発動しない

### 修正方針
`DOMContentLoaded` の早い段階（scene の loaded イベント前）で Android を検出し、
`<a-scene>` の `arjs` 属性を動的に書き換える。

```js
// DOMContentLoaded の冒頭、または arjs-video-loaded コールバックより前に実行
(function() {
    const isAndroid = /Android/i.test(navigator.userAgent);
    if (!isAndroid) return; // iPhone/Desktop は変更しない
    const scene = document.querySelector('a-scene');
    if (!scene) return;
    // Android 向け: 4:3 標準解像度に変更（ポートレート表示との相性が良い）
    scene.setAttribute('arjs',
        'sourceType: webcam; debugUIEnabled: false; sourceWidth: 640; sourceHeight: 480; detectionMode: mono; maxDetectionRate: 15;'
    );
    console.log('[Android fix] AR.js source size set to 640x480');
})();
```

### 配置場所
- `DOMContentLoaded` コールバックの最初の処理として追加
- AR.js は scene の `loaded` イベント後に arjs 属性を読み取るため、
  DOMContentLoaded 内での変更は有効（a-scene の初期化前）

### iPhone への影響
- `isAndroid` 判定ガードにより非 Android は一切変更なし
- **影響なし**

---

## 修正対象まとめ

| # | 問題 | 修正内容 | 変更箇所 |
|---|------|----------|----------|
| A | ボール非表示・投球不能 | `loaded`→`model-loaded` + 500ms フォールバック | throwBall() 内 |
| B | カメラズーム | Android 時 arjs属性を 640×480 に動的変更 | DOMContentLoaded 冒頭 |

## 変更ファイル
- `resources/views/ARstampRally202603.blade.php` のみ（2箇所修正）

## 注意事項
- 修正 A は新しいポケボール操作ロジック IIFE 内 `throwBall()` 関数を編集
- 修正 B は DOMContentLoaded の先頭ブロックに Android 判定コードを追加
- 既存の `applyHoldingBallFix()` は変更不要（既に正しく実装済み）
- PHP lint は修正後に必ず実行

