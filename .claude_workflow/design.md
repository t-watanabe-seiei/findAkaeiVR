# 設計: marker-00にModel_00.glb追加（捕獲数連動アニメーション）

## 作成日時
2026年4月18日

## 前提
`.claude_workflow/requirements.md` を読み込み済み

---

## アプローチ

### 変更方針
既存のjs-gallery.blade.phpのIIFE内にModel_00専用のロジックを追加する。
ギャラリーのmarkerFound/markerLostイベントに相乗りし、Model_00を管理する。

### 変更ファイル

#### 1. `resources/views/ARstampRally202605/scene.blade.php`
- marker-00内に`Model_00.glb`用の`<a-entity>`を追加
- `id="model-00"`, `position="1 2 0.5"`, `scale="1.1 1.1 1.1"`, `rotation="-90 0 0"`
- `visible="false"`（JSで制御）
- 既存のシリンダー+球体はそのまま残す

#### 2. `resources/views/ARstampRally202605/js-gallery.blade.php`
- `onMarkerConfirmed()`内の先頭でModel_00のアニメーション切替処理を追加
- 捕獲数を取得し、適切なアニメーションクリップを選択:
  - 0〜4個 → 'anime01'
  - 5〜9個 → 'anime02' 
  - 10個 → 'anime03'
- Model_00のmixer/actionをキャッシュし、捕獲数変化時のみ切替
- `hideGallery()`でModel_00も非表示にする
- markerFound時にModel_00をvisible=trueにする

### 設計詳細

```
Model_00管理変数:
  model00Entity  = document.getElementById('model-00')
  model00Mixer   = null  (model-loaded後に生成)
  model00CurrentClip = null  (現在再生中のクリップ名)

処理フロー:
  1. markerFound → onMarkerConfirmed()
  2. model00Entity.visible = true
  3. capturedCount = Object.keys(getCapturedAnimals202605()).filter(v => v === true).length
  4. clipName = capturedCount >= 10 ? 'anime03' : capturedCount >= 5 ? 'anime02' : 'anime01'
  5. clipNameが前回と異なれば、現在のactionを停止→新clipを再生
  6. markerLost → model00Entity.visible = false
```

### 問題点・考慮事項
- Model_00はscene.blade.phpで静的に配置するため、lazy-modelは不要（marker-00内の子要素はmarker検出時にまとめて表示される）
- ただしgltf-modelの読み込みタイミングでmodel-loadedイベントを使ってmixer初期化が必要
- galleryMixersにModel_00のmixerも追加し、既存のtickGalleryMixers()で更新されるようにする
    ├── scene.blade.php            ← <a-scene>全体（マーカー・モデルのHTML）
    ├── ui.blade.php               ← UI HTML（モーダル・ボタン等）
    ├── js-stamps.blade.php        ← STAMPS定数・LocalStorage管理・スタンプ帳ロジック
    ├── js-prize.blade.php         ← 景品交換ロジック・IndexedDB・UUID
    ├── js-throw.blade.php         ← ポケボール投擲ロジック（タップ投げ）
    ├── js-gallery.blade.php       ← maker00ギャラリー機能
    ├── js-camera.blade.php        ← 写真撮影・動画録画・カメラ切り替え
    └── js-init.blade.php          ← DOMContentLoaded 初期化（マーカーイベント등）
```

**行数目安（合計 ≈ 900行）**:
| ファイル | 予想行数 |
|---------|---------|
| ARstampRally202605.blade.php | 20行 |
| head.blade.php | 100行 |
| aframe-components.blade.php | 250行 |
| scene.blade.php | 120行 |
| ui.blade.php | 150行 |
| js-stamps.blade.php | 200行 |
| js-prize.blade.php | 150行 |
| js-throw.blade.php | 100行 |
| js-gallery.blade.php | 80行 |
| js-camera.blade.php | 200行 |
| js-init.blade.php | 180行 |

---

## 各ファイルの設計詳細

### ARstampRally202605.blade.php（エントリポイント）
```php
<!DOCTYPE html>
<html lang="ja">
@include('ARstampRally202605.head')
<body>
@include('ARstampRally202605.ui')
@include('ARstampRally202605.scene')
<script>
@include('ARstampRally202605.js-stamps')
@include('ARstampRally202605.js-prize')
@include('ARstampRally202605.js-throw')
@include('ARstampRally202605.js-gallery')
@include('ARstampRally202605.js-camera')
@include('ARstampRally202605.js-init')
</script>
</body>
</html>
```
※ `<script>`ブロック内にBladeを@includeすることで、アセットURLの`{{ asset(...) }}`が正常に展開される。

### head.blade.php
- `<head>` + `<meta>` + `<title>` + CSRF
- グローバル変数（`window.activeBalls`, `window.allHitboxes`, `window.guideModalOpen`）
- `detectOldAndroid()`, `AR_FORCE_LOWRES`
- `monitorCameraStartup()`, `ensureCameraAccess()`
- `destroyAndFreeEntity()`
- ピンチ/ズーム防止、キーボードイベントブロック（Ctrl+U等）
- `<script src="ar-engine.min.js">` / `<script src="ar-tracking.min.js">`
- CSS（全スタイル）

### aframe-components.blade.php
- `AFRAME.registerComponent('pokeball-throwable', {...})`
- `AFRAME.registerComponent('hitbox', {...})`
- `AFRAME.registerComponent('lazy-model', {...})`
- `AFRAME.registerComponent('click-animation', {...})`  
  ※ anime03対応を追加（ギャラリーモード用）

### scene.blade.php
```html
@php
    $_arjsIsAndroid = stripos(request()->header('User-Agent',''),'android') !== false;
@endphp
<a-scene embedded arjs="..." ...>
    <a-entity camera>
        <!-- 手持ちポケボール（202603から流用・HUDは残すが非表示） -->
    </a-entity>
    <a-light ...>

    <!-- maker00: ギャラリーマーカー（モデルなし、JSで動的追加） -->
    <a-marker type="pattern" url="{{ asset('cg/202605/pattern-maker00.patt') }}" id="marker-00">
    </a-marker>

    <!-- maker01 ～ maker10: 捕獲マーカー -->
    @for ($i = 1; $i <= 10; $i++)
    @php $id = str_pad($i, 2, '0', STR_PAD_LEFT); @endphp
    <a-marker type="pattern" url="{{ asset('cg/202605/pattern-maker'.$id.'.patt') }}" id="marker-{{ $id }}">
        <a-entity
            id="model-{{ $id }}"
            lazy-model="src: {{ asset('cg/202605/Model_'.sprintf('%02d',$i).'.glb') }}"
            position="0 0 0.5"
            scale="1.1 1.1 1.1"
            rotation="-90 0 0"
            click-animation="clip: anime01"
            hitbox="stampId: model_{{ $id }}; width: 1.6; height: 3.2; depth: 1.6">
        </a-entity>
    </a-marker>
    @endfor
</a-scene>
```

### js-gallery.blade.php（新機能）
```javascript
// maker00 markerFound: 捕獲済みモデルをギャラリー表示
const marker00 = document.getElementById('marker-00');
if (marker00) {
    let galleryEntities = [];

    marker00.addEventListener('markerFound', function() {
        // 既存ギャラリーエンティティを削除
        galleryEntities.forEach(e => { try { if(e.parentNode) e.parentNode.removeChild(e); } catch(ex){} });
        galleryEntities = [];

        const captured = getCapturedAnimals202605(); // LocalStorageから取得
        const ids = Object.keys(captured).filter(id => captured[id] === true);

        ids.forEach((stampId, index) => {
            const entity = document.createElement('a-entity');
            const modelIdx = parseInt(stampId.replace('model_', ''), 10);
            entity.setAttribute('gltf-model', `{{ asset('cg/202605/Model_') }}${String(modelIdx).padStart(2,'0')}.glb`);
            entity.setAttribute('position', `0 ${index * 1.0} 0`);
            entity.setAttribute('scale', '1.1 1.1 1.1');
            entity.setAttribute('rotation', '-90 0 0');

            // anime03 ループ再生
            entity.addEventListener('model-loaded', function() {
                const model = entity.getObject3D('mesh');
                if (!model || !model.animations || !model.animations.length) return;
                model.traverse(n => { if (n.isMesh) n.frustumCulled = false; });
                const mixer = new THREE.AnimationMixer(model);
                entity._galleryMixer = mixer;
                let clip = THREE.AnimationClip.findByName(model.animations, 'anime03');
                if (!clip) clip = model.animations[Math.min(2, model.animations.length - 1)];
                const action = mixer.clipAction(clip);
                action.setLoop(THREE.LoopRepeat, Infinity);
                action.play();
            });

            marker00.appendChild(entity);
            galleryEntities.push(entity);
        });
    });

    marker00.addEventListener('markerLost', function() {
        galleryEntities.forEach(e => { try { if(e.parentNode) e.parentNode.removeChild(e); } catch(ex){} });
        galleryEntities = [];
    });
}
```
**注意**: ギャラリーエンティティのアニメーションmixerのtick（更新）は `js-init.blade.php` のシーンtickイベントで一括処理。

### js-throw.blade.php（タップ投げ）
202603版のHUDスワイプ方式を廃止し、シンプルなタップ投げに変更。

```javascript
// 画面タップで即投げ（UIボタン上は除外）
document.addEventListener('touchstart', (e) => {
    const touch = e.touches[0];
    if (isUIButton(document.elementFromPoint(touch.clientX, touch.clientY))) return;
    tapThrowStart = { x: touch.clientX, y: touch.clientY, t: Date.now() };
}, { passive: false });

document.addEventListener('touchend', (e) => {
    if (!tapThrowStart) return;
    const touch = e.changedTouches[0];
    const dx = touch.clientX - tapThrowStart.x;
    const dy = touch.clientY - tapThrowStart.y;
    tapThrowStart = null;

    const scene = document.querySelector('a-scene');
    const camera = scene && scene.camera;
    if (!camera) return;

    // カメラ前方 + スワイプ補正
    const dir = new THREE.Vector3(0, 0, -1);
    dir.x += dx * 0.002;
    dir.applyQuaternion(camera.quaternion).normalize();

    throwPokeballToCenter(dir, 15); // 固定速度15
}, { passive: true });
```
※ PC用（mousedown/mouseup）も同様に対応。

### Androidカメラズーム修正
`head.blade.php` のCSSに以下を追加:
```css
/* Androidカメラズーム防止 */
video {
    object-fit: contain !important; /* coverではなくcontainで歪みなし */
    width: 100% !important;
    height: 100% !important;
}
```
`DOMContentLoaded` 内のAndroid判定で `sourceWidth: 640; sourceHeight: 480` を保持（202603と同様）。

### 景品交換修正（6個以上）
`js-prize.blade.php` の `exchangePrize()` 内:
```javascript
if (collectedCount < 6) {  // 10 → 6 に変更
    alert('6匹以上捕まえると景品と交換できるよ！');
    return;
}
```
`updatePrizeButton()` も同様に閾値6に変更。

---

## ルート追加

### routes/web.php に追加
```php
Route::match(['get', 'head'], '/stamp202605', function () {
    return view('ARstampRally202605');
})->name('stamp202605.index');

// admin グループ内に追加
Route::get('/dashboard202605', [AdminController::class, 'dashboard202605'])->name('admin.dashboard202605');
```

---

## AdminController::dashboard202605() 設計

202603版の `dashboard202603()` メソッドをコピーし、以下を変更:
- 日付範囲: 2026年5月（`2026-05-01 00:00:00` ～ `2026-05-31 23:59:59`）
- animals配列: `model_01` ～ `model_10`（各モデルのIDと表示名）
- return view: `admin.dashboard202605`

---

## 問題点・注意事項

1. **Bladeの@includeとJavaScript**: `<script>`ブロック内の `@include` は正常にレンダリングされる。ただし `{{ asset(...) }}` はBladeで処理されるため問題なし。

2. **ギャラリーモデルのmixerのtick**: `scene.addEventListener('tick', ...)` でgalleryEntitiesのmixerを更新する必要あり。または `click-animation` コンポーネントの拡張として実装可能。

3. **maker00のギャラリーで既存ヒットボックスと衝突しない**: ギャラリーエンティティには `hitbox` コンポーネントを付与しない。

4. **Androidカメラズーム**: `object-fit: contain` を video に適用すると、スクリーン端に黒帯が生じる場合がある。AR.jsがビデオ要素を直接スタイリングする競合が起きうるため、`!important` で強制適用。

---

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

---

# 設計: ARstampRally202603 HUDポケボール DOM オーバーレイ化（パターン2）

## 作成日時
2026年4月6日

## 前提
`.claude_workflow/requirements.md` を読み込み済み

---

## 対象ファイル
`resources/views/ARstampRally202603.blade.php`

---

## 変更1: CSS追加（`</style>` 直前）

HUDポケボール画像を画面下中央に固定表示するスタイル。  
`touch-action: none` でタッチイベントを妨害しない設定も含む。

```css
/* HUDポケボール画像オーバーレイ */
#hud-pokeball {
    position: fixed;
    bottom: 12%;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 80px;
    z-index: 500;
    pointer-events: none;
    touch-action: none;
    transition: transform 0.1s ease;
}
#hud-pokeball img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}
```

---

## 変更2: HTML追加（`throw-button` 直後）

```html
<!-- HUDポケボール画像オーバーレイ -->
<div id="hud-pokeball">
    <img src="{{ asset('cg/pokeball_image.png') }}" alt="pokeball">
</div>
```

---

## 変更3: `#holding-pokeball` 非表示化

```html
<!-- 変更前 -->
visible="true"

<!-- 変更後 -->
visible="false"
```

---

## 変更4a: `captureModelScreenshot()` HUD非表示

変更前（`holding-pokeball` の display を操作）：
```javascript
const holding = document.getElementById('holding-pokeball');
if (holding) {
    holding._prevDisplay = holding.style.display || '';
    holding.style.display = 'none';
    _tmpHiddenEls.push(holding);
}
```

変更後（`hud-pokeball` の visibility を操作）：
```javascript
const hudPokeball = document.getElementById('hud-pokeball');
if (hudPokeball) {
    hudPokeball._prevVisibility = hudPokeball.style.visibility || '';
    hudPokeball.style.visibility = 'hidden';
    _tmpHiddenEls.push(hudPokeball);
}
```

---

## 変更4b: `captureModelScreenshot()` 復元コード × 3か所

変更前（same pattern in 3 places）：
```javascript
if (el.id === 'holding-pokeball') { el.style.display = el._prevDisplay || ''; delete el._prevDisplay; }
```

変更後：
```javascript
if (el.id === 'hud-pokeball') { el.style.visibility = el._prevVisibility || ''; delete el._prevVisibility; }
```

---

## 変更5: IIFE 先頭 — `hudEl` 追加 + `applyHoldingBallFix` 全削除

変更前（抜粋）：
```javascript
let ballEntity = document.querySelector('#holding-pokeball');
let canThrow = true;

function applyHoldingBallFix() {
    // ... ~42行 ...
}
if (ballEntity) { ballEntity.addEventListener('model-loaded', applyHoldingBallFix); ... }
setTimeout(applyHoldingBallFix, 500);
setTimeout(applyHoldingBallFix, 2000);
```

変更後：
```javascript
let ballEntity = document.querySelector('#holding-pokeball');
let hudEl = document.getElementById('hud-pokeball');
let canThrow = true;
// applyHoldingBallFix() は DOM overlayが担うため削除
```

---

## 変更6a: touchstart — 持ち上げ演出

変更前：
```javascript
ballEntity.setAttribute('position', '0 -0.23 -0.5');
```

変更後：
```javascript
if (hudEl) hudEl.style.transform = 'translateX(-50%) translateY(-3px)';
```

---

## 変更6b: touchend — ボール隠し

変更前：
```javascript
ballEntity.setAttribute('visible', 'false');
ballEntity.setAttribute('position', '0 -0.24 -0.5');
canThrow = false;
```

変更後：
```javascript
if (hudEl) hudEl.style.visibility = 'hidden';
canThrow = false;
```

---

## 変更6c: touchcancel — 復元

変更前：
```javascript
ballEntity.setAttribute('position', '0 -0.24 -0.5');
```

変更後（transform リセット＋visibility 復元）：
```javascript
if (hudEl) { hudEl.style.transform = 'translateX(-50%)'; hudEl.style.visibility = 'visible'; }
```

---

## 変更6d: mousedown — 持ち上げ演出

変更前：
```javascript
ballEntity.setAttribute('position', '0 -0.23 -0.5');
```

変更後：
```javascript
if (hudEl) hudEl.style.transform = 'translateX(-50%) translateY(-3px)';
```

---

## 変更6e: mouseup — ボール隠し

変更前：
```javascript
ballEntity.setAttribute('visible', 'false');
ballEntity.setAttribute('position', '0 -0.24 -0.5');
```

変更後：
```javascript
if (hudEl) hudEl.style.visibility = 'hidden';
```

---

## 変更7: `throwBall()` — 初期位置計算

AR.js がカメラのワールドマトリクスを直接書き換えるため `getWorldPosition()` が信頼できない。
代わりにカメラのワールド行列からカメラ位置と前方向を取得してオフセット計算する。

変更前：
```javascript
const worldPos = new THREE.Vector3();
ballEntity.object3D.getWorldPosition(worldPos);
```

変更後：
```javascript
const cam = document.querySelector('[camera]').object3D;
const worldPos = new THREE.Vector3();
cam.getWorldPosition(worldPos);
const forward = new THREE.Vector3(0, -0.24, -0.5).applyQuaternion(cam.quaternion);
worldPos.add(forward);
```

---

## 変更8: `restoreBall()` — ボール復元

変更前：
```javascript
setTimeout(() => {
    if (ballEntity) {
        ballEntity.setAttribute('visible', 'true');
        canThrow = true;
    }
}, 500);
```

変更後：
```javascript
setTimeout(() => {
    if (hudEl) {
        hudEl.style.visibility = 'visible';
        hudEl.style.transform = 'translateX(-50%)';
    }
    canThrow = true;
}, 500);
```

---

## 変更箇所まとめ

| # | 変更種別 | ファイル内位置（概算行） | 内容 |
|---|----------|--------------------------|------|
| 1 | CSS追加 | ~2003 `</style>` 直前 | `#hud-pokeball` スタイル |
| 2 | HTML追加 | ~2172 `throw-button` 直後 | `<div id="hud-pokeball">` |
| 3 | HTML変更 | ~2201 `#holding-pokeball` | `visible="true"` → `"false"` |
| 4a | JS変更 | ~3371 screenshot hide | `holding-pokeball` → `hud-pokeball` |
| 4b | JS変更 | ~3400,3415,3421 restore×3 | id + property 変更 |
| 5 | JS変更 | ~7028〜7074 IIFE先頭 | `hudEl` 追加、`applyHoldingBallFix` 削除 |
| 6a | JS変更 | ~7096 touchstart | `setAttribute` → `hudEl.style.transform` |
| 6b | JS変更 | ~7131 touchend | `setAttribute` → `hudEl.style.visibility` |
| 6c | JS変更 | ~7139 touchcancel | `setAttribute` → `hudEl.style.*` |
| 6d | JS変更 | ~7155 mousedown | `setAttribute` → `hudEl.style.transform` |
| 6e | JS変更 | ~7169 mouseup | `setAttribute` → `hudEl.style.visibility` |
| 7 | JS変更 | ~7183 throwBall初期位置 | `getWorldPosition` → camera+offset |
| 8 | JS変更 | ~7255 restoreBall | `setAttribute` → `hudEl.style.*` |

## 注意事項
- PHP lint（`php -l`）を実装後に必ず実行
- `ballEntity` 変数は `throwBall()` 内で 3D アニメーション用に引き続き使用するため削除しない
- `restoreBall()` 内の `ballEntity` 参照はすべて `hudEl` に置き換える

---

# 設計10: ギャラリーマーカー（maker00）iPhone SEフリーズ修正

## 作成日時
2026年4月17日

## 前段階のmdファイルを読み込みました
`.claude_workflow/requirements.md` の要件定義10を参照

## 設計方針

### 改修対象ファイル
`resources/views/ARstampRally202605/js-gallery.blade.php` のみ

### 現在の問題点（コード上）
1. `markerFound` → 全捕獲モデルを `forEach` で同時に `gltf-model` セット → メモリスパイク
2. `markerLost` → `clearGallery()` で全エンティティを `removeChild` → 次の `markerFound` で再度1からロード
3. `galleryMixers` を `window.galleryMixers` に公開しているが、どのtick()からも `mixer.update()` が呼ばれていない

### 設計A: キャッシュ＋デバウンス

**markerFoundハンドラ:**
- デバウンスタイマー（300ms）を導入。300ms以内の再発火を無視
- 既にキャッシュ済みエンティティがあれば、`visible=true` にするだけ（再ロードしない）
- 新たに捕獲された動物がある場合のみ、差分エンティティを追加生成

**markerLostハンドラ:**
- `clearGallery()` を廃止
- 代わりに全ギャラリーエンティティを `visible=false` にするだけ
- AnimationMixerは停止しない（tickでvisible=falseなら自然にスキップ）

**キャッシュ管理:**
- `galleryCache` オブジェクト: `{ stampId: entityElement }` 形式
- 新規捕獲時のみエンティティを追加（既存キャッシュと比較）

### 設計B: 逐次ロード

**初回ロード時:**
- 捕獲済みリストを取得
- `galleryCache` にない分だけ、500ms間隔のキュー方式で1体ずつ生成
- `setTimeout` チェーンで実装（再帰呼び出し）
- ロード中にmarkerLostが発生した場合、キューを中断

**再表示時（キャッシュ済み）:**
- 即座に `visible=true`（遅延なし）

### AnimationMixer修正
- ギャラリーエンティティ専用のtickループは作らない
- `model-loaded` で `mixer.clipAction().play()` → Three.jsの内部clockで再生される
- ただし `mixer.update(dt)` がないとアニメーションは進まないため、`requestAnimationFrame` ベースの軽量ループを1つだけ追加

## 変更箇所一覧

| # | 変更内容 | 詳細 |
|---|---------|------|
| 1 | galleryEntities/galleryMixers → galleryCache | キャッシュ用オブジェクトに変更 |
| 2 | markerFoundハンドラ書き換え | デバウンス300ms + キャッシュ判定 + 逐次ロード |
| 3 | markerLostハンドラ書き換え | removeChild → visible=false + キュー中断 |
| 4 | clearGallery廃止 | hideGallery（visible切替のみ）に置換 |
| 5 | 逐次ロードキュー | loadNextModel再帰関数 |
| 6 | AnimationMixerの更新ループ | requestAnimationFrameで全mixer一括update |

