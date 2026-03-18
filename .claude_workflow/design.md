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

