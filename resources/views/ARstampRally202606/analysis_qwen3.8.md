# AR (WebAR) Android カメラズーム / モデルスケール不具合 — 根本原因と修正方針

> 対象: `ARstampRally202606` (AR.js + A-Frame / ARToolKit)
> 状態: 診断完了・修正未実施（本ドキュメントは原因と修正案の設計）
> 更新: 2026-06

---

## 📋 1. 概要

**一言でいうと**: Android では AR.js の射影計算が使う
`sourceWidth / sourceHeight / displayWidth / displayHeight` が
**4:3 (640×480) にハードコード**されているため、実際のカメラストリーム比率・
実際の画面比率と一致せず、AR 座標系（= カメラの「ズーム・歪み」）がズレる。
その結果、カメラ映像が「ズームイン気味」に見え、マーカー上の 3D モデルの
スケール・位置が本来の意図と異なる。

**併発する第 2 のバグ**: `applyCurrentScaleTo()` が
「エンティティルート」のスケールと「各メッシュ」のスケールの**両方**を
同じ値 `v` に設定しているため、モデルが `base` 倍ではなく **`base²` 倍**
で描画される。これはマーカー検出のたびに（全端末で）発火する。

| # | 不具合 | 主因 | 影響 |
|---|--------|------|------|
| A | Android でカメラがズーム・モデルがズレる | 射影寸法を 4:3 にハードコード | Android 全面 |
| B | モデルが意図より大きい/小さい（`base²`） | 二重スケール適用 | 全端末（`markerFound` 毎） |
| C | 低解像度リトライ後、スケールが更にはずれる | `displayWidth/Height` 未更新 | 低解像度経路のみ |

**iOS が「正常に見える」理由**（詳細は §5）:
iOS 側は 1280×720 (16:9) をハードコードしており、iPhone のカメラストリーム
(16:9)・画面比率と**たまたま一致**するため、AR.js の想定カバークロップが
実態とほぼ一致する。Android は 4:3 固定が両方とも一致しないためズレが
最大になる。

---

## 🎯 2. 症状（観測）

- **Android（WebView / Chrome）**:
  - カメラ映像が「実際より近め（ズームイン）」に見える。
  - マーカー検出は成功するが、マーカー上に乗る 3D モデルの**サイズ・位置**が
    意図と合わない（大きく見える / ずれる）。
  - 縦向き・横向きでズレの大きさが変わる（縦向きが特に酷い端末あり）。
- **iOS (Safari / WebView)**: 上記が顕在化しにくい（§5）。
- **PC (wheel zoom 使用時)**: モデルが 2 倍にしたいのに 4 倍相当に見える（B の
  `v²` 効果が `currentScale` と相乗）。

---

## 🧱 3. 前提：AR.js は「CSS/DOM のサイズ」ではなく「射影パラメータ」で計算する

これは本件を理解する上で最重要です。

AR.js (`ar-tracking.min.js`) は、次の 4 つのパラメータを**射影計算に使う**
（DOM 上の `<video>` を `object-fit` でどう見せても、この計算は変わらない）：

- `sourceWidth / sourceHeight` … **カメラのネイティブ解像度**（ストリーム実寸）
- `displayWidth / displayHeight` … **画面上にその映像が表示されるサイズ**

AR.js はこの 4 値から
1. 「ソース画像 → 表示領域」への **cover (videofill) クロップ**を計算し、
2. 検出マーカーのピクセルを**理想カメラ座標系へ逆変換**して
   ARToolKit が 3D 射影する、
を行います。

つまり：
```
実ストリームが 16:9 なのに  source = 4:3 (640x480) と申告
実画面が  縦長 (≈2:1) なのに display = 4:3 (640x480) と申告
        ↓
想定カバークロップ ≠ 実カバークロップ
        ↓
AR 座標系がズレる → 「カメラズーム」に見える + マーカー/モデルがズレる
```

**重要**: 既存の `head.blade.php` の
```css
video            { object-fit: cover !important; }
a-scene canvas   { object-fit: cover !important; width:100%!important; height:100%!important; }
```
（`head.blade.php` L172-181）は**見た目のレターボックス防止（見た目）専用**です。
AR.js に「実表示サイズ」を伝えていないため、**射影のズレは解消しません**。
「Android カメラズーム防止」というコメントこそ付いていますが、
実際には DOM の見た目だけを直しており、本質（射影パラメータ）には触れていない、
というのが現在の状態です。


---

## 🔍 4. 根本原因と証拠（ファイル:行）

### 4-1. 【主因 A】射影寸法が 4:3 に固定されている

`scene.blade.php` — UA 判定で Android / その他に分岐し、
**Android 側を 4:3 (640×480)** に固定している：

- `scene.blade.php` L1-7 : UA 判定で `$_arjsIsAndroid` を決定し
  Android → `640/480`、それ以外 (iOS 等) → `1280/720`。
- `scene.blade.php` L11  : `arjs="sourceWidth:640; sourceHeight:480; displayWidth:640; displayHeight:480; trackingMethod: best; sourceType: webcam; debugUIEnabled: false; detectionMode: mono; maxDetectionRate: 30;"`
  （iOS 側は 1280/720）
- `scene.blade.php` L78-91 : Samsung 縦型のみ `arjs` を
  `sourceWidth:480; sourceHeight:640; displayWidth:480; displayHeight:640`
  に上書きする（4:3 を 4:3 の縦に置き換え、**横長画面はカバーしていない**）。

→ 実カメラが 16:9 / 実画面が縦長でも、AR.js は「4:3→4:3」でクロップ計算するため
§3 のズレが発生する。**Samsung 縦型の上書きも「4:3 → 縦4:3」への置き換えにすぎず、
実ストリーム・実画面の比率には追従していない。**

### 4-2. 【主因 A の補足】動画読み込み後の修正が「縦型のみ」で、横長は未修正

`js-init.blade.php` L585-605 : `arjs-video-loaded` ハンドラが
**`videoWidth < videoHeight`（縦型）のときだけ**
`source = display = video の実寸` に再設定する。
**横長ストリーム・横長画面はそのまま 4:3 のまま**残る。

### 4-3. 【主因 A の補足】カメラ解像度のフォールバックも 4:3 寄り

`head.blade.php` L64-103 : `ensureCameraAccess` のフォールバックチェーン
`1280×720 → 640×480 → facingMode のみ → video:true`。
- `head.blade.php` L31-35 : `AR_FORCE_LOWRES` が Android の低解像度強制に関与する
  （※ これは**カメラ入力解像度**の話で、射影パラメータ自体は §4-1 の固定値のまま）。
- `js-init.blade.php` L523-535 : 低解像度リトライ時に
  `arjs` の `sourceWidth:320; sourceHeight:240` を差し替えるが、
  **`displayWidth/displayHeight` を更新しない**（主因 C）。

### 4-4. 【主因 B】`applyCurrentScaleTo()` の二重スケール（全端末・毎 `markerFound`）

`js-init.blade.php` L31-56（実体）：
```js
function applyCurrentScaleTo(el) {
    var base = parseFloat(el.dataset.baseScale || 1);   // L34  (0.6 / 1.1 等を取得)
    var clamped = Math.max(1, Math.min(3, currentScale)); // L35
    var v = base * clamped;                              // L36
    el.setAttribute('scale', v + ' ' + v + ' ' + v);      // L37 → ルート = v
    if (el.object3D && el.object3D.scale)
        el.object3D.scale.set(v, v, v);                   // L40 → ルート = v (重複)
    if (el.object3D && el.object3D.children)
        el.object3D.traverse(function (node) {
            if (node.isMesh) {
                if (node.scale) node.scale.set(v, v, v);   // L45 → 各メッシュも = v
                node.frustumCulled = false;                // L46
            }
        });
}
```
- 世界座標スケール = `ルート(v) × … × メッシュ(v) = v²`。
  （中間 group は `traverse` が `isMesh` だけ触るためスケール 1 のまま → 相乗する）
- 呼出箇所:
  - `js-init.blade.php` L350-353 : **`markerFound` のたびに呼ばれる（全端末・モバイル含む）**。
  - `js-init.blade.php` L373-381 : PC 用 `wheel` ズームのみが `currentScale` を変える
    （`currentScale` は L8 で初期値 1、**モバイルでは常に 1**）。
- 影響:
  - モバイル: `v = base`（0.6 or 1.1）だが描画は `base²`
    → maker00 が `0.6²=0.36`（約 60% 縮小）、maker01-20 が `1.1²=1.21`（約 10% 拡大）。
  - PC wheel: `currentScale=2` → `v=2base`、描画は `(2base)²=4base²` → 2 倍なのに 4 倍相当。
- `setBaseScaleIfMissing`（`js-init.blade.php` L11-28）は `scene.blade.php` の
  初期スケール（maker00 = `0.6` (L41)、maker01-20 = `1.1` (L63)）を
  `dataset.baseScale` として正しく取得している。**問題は取得ではなく
  「ルートの v」と「各メッシュの v」の二重適用**である。

### 4-5. 正常な箇所（念のため）
- `scene.blade.php` L22-23 : `look-controls="enabled: false"` + `<a-entity camera>`。
  マーカーベース AR として正しい（DeviceOrientation 頼みではない）。
- `head.blade.php` L3 / L150-166 : ビューポートの `user-scalable=no`、
  多点タップ・コンテキストメニューの preventDefault は**ユーザー操作による
  ページズーム防止**であり、本不具合とは別物（維持するべき正当な処理）。

---

## 📱 5. なぜ iOS では正常に見えるか

1. `scene.blade.php` L1-7/L11 で iOS 側は **1280×720 (16:9)** を固定。
2. iPhone のカメラストリームは概ね **16:9**、表示も 16:9 寄りのため、
   AR.js が想定する「ソース 16:9 → 表示 16:9」のクロップが**実態とほぼ一致**。
3. 加えて `js-init.blade.php` L585-605 の縦型修正が、
   iPhone の縦長ストリームを `source = display = 実寸` に補正してくれる。

Android は (1) が **4:3** なので、実ストリーム (16:9)・実画面 (縦長) の
**どちらとも不一致** → §3 のズレが最大になる、という構図です。
（※ B の二重スケール自体は iOS でも起きているが、16:9 の相性が良いため
  体感されにくい・または既存の見た目で相殺されている。）



---

## 🛠 6. 修正方針（優先度順）

> 方針: 「見た目の CSS」ではなく **AR.js が射影計算に使う 4 値を実態に合わせる**こと。
> 既存の縦型補正（`js-init.blade.php` L585-605）は正しい方向だが**縦型限定**なので、
> **全方向 + リサイズ/画面回転時に常に同期する**ように一般化する。

### Fix-1（主因 A、最重要・高優先度）: 動画読み込み後に実寸で射影を再設定 + リサイズ追従

`js-init.blade.php` L585-605 の縦型限定ガードを撤廃し、全方向で実行する。
`source` は動画の実寸 (`videoWidth/Height`)、`display` は実画面
(`window.innerWidth/innerHeight`) にする。

```js
function syncArjsToRealSize() {
    var video = document.getElementById('arjs-video');
    if (!video) return;
    var sw = video.videoWidth  || 640;   // カメラネイティブ実寸
    var sh = video.videoHeight || 480;
    var dw = window.innerWidth   || 640; // 実表示サイズ（CSS px）
    var dh = window.innerHeight  || 480;
    if (sw < 2 || sh < 2) return;
    var scene = document.getElementById('ar-scene');
    if (!scene) return;
    scene.setAttribute('arjs',
        'sourceWidth: '  + sw + ';' +
        'sourceHeight: ' + sh + ';' +
        'displayWidth: ' + dw + ';' +
        'displayHeight:' + dh + ';' +
        'trackingMethod: best; sourceType: webcam; debugUIEnabled: false;' +
        'detectionMode: mono; maxDetectionRate: 30;'
    );
}

// 動画が読み込まれたら（縦/横問わず）1 回
document.addEventListener('arjs-video-loaded', function () {
    syncArjsToRealSize();
    if (loaderEl && loaderEl.classList) loaderEl.classList.add('hidden');
});
// 画面回転・ウィンドウリサイズで実表示が変化したとき
var _rt;
window.addEventListener('resize', function () {
    clearTimeout(_rt); _rt = setTimeout(syncArjsToRealSize, 200);
});
window.addEventListener('orientationchange', function () {
    setTimeout(syncArjsToRealSize, 300);
});
```
- 効果: 縦・横とも、AR.js の想定クロップが実態と一致し「カメラズーム」感と
  マーカー/モデルのズレが解消する。
- `scene.blade.php` L11 の初期値 640×480 は**起動までの暫定値**として残してよい
  （読み込み後即時に上書きされる）。ただし Samsung 縦型上書き
  (`scene.blade.php` L78-91) は Fix-1 に取って代わるため**削除候補**。

### Fix-2（主因 B、全端末・毎 `markerFound`）: 二重スケールを解消

`js-init.blade.php` L37-48 を、**「ルートで 1 回だけスケールし、
traverse は `frustumCulled=false` の設定だけに使う」**形に直す。

```js
function applyCurrentScaleTo(el) {
    if (!el) return;
    var base = parseFloat(el.dataset.baseScale || 1);
    var clamped = Math.max(1, Math.min(3, currentScale));
    var v = base * clamped;
    el.setAttribute('scale', v + ' ' + v + ' ' + v);   // スケールは「ルートの 1 回」だけ
    // 以下は「メッシュの frustumCulled 解除」のためだけに回す（scale.set は削除）
    if (el.object3D && el.object3D.children) {
        el.object3D.traverse(function (node) {
            if (node.isMesh) node.frustumCulled = false;
        });
    }
}
```
- 効果: 描画が `base²` → `base` に復元。PC の wheel も `2 倍 → 2 倍` で正常化。
- `frustumCulled=false`（AR でモデルが切り落とされないようにする正当な目的）は
  維持する。

### Fix-3（主因 C）: 低解像度リトライ時も `display` を更新

`js-init.blade.php` L523-535 のリトライで `sourceWidth:320; sourceHeight:240`
を差し替える箇所は、**`displayWidth/displayHeight` も現画面サイズに合わせて
再設定する**（Fix-1 の `syncArjsToRealSize()` をそのまま呼べばよい）。
`display` を 4:3 のままにすると、リトライ後にスケールが更にずれる。

### Fix-4（任意・堅牢性）: `scene.blade.php` の初期値を実画面に近づける
起動までのちらつきを減らすため、`<a-scene>` 生成前に
`window.innerWidth/innerHeight` を `display` 初期値にする（`source` は
動画読み込みまで待って実寸で確定）。Fix-1 が本質なので任意。

---

## ✅ 7. 検証手順

**対象デバイス**
- Android: 縦長 (19.5:9 系) / 横長 (16:9 系) 各 1 台以上。Chrome と WebView 両方。
- iOS: iPhone（回帰確認）。
- PC: wheel zoom を使う回帰確認（Fix-2）。

**手順**
1. DevTools (Chrome) で確認:
   - `video.videoWidth / videoHeight`（実ストリーム）
   - `window.innerWidth / innerHeight`（実表示）
   - 読み込み後の `#ar-scene` の `arjs` 属性（Fix-1 適用で 4 値が実態に一致しているか）
2. マーカーを認識させ、モデルの**見た目のサイズ**が意図 (maker00=0.6 / maker01-20=1.1)
   になっているか確認（Fix-2 で `base²` が消える）。
3. 縦↔横回転、ウィンドウリサイズで再確認（Fix-1 の追従）。
4. 低解像度経路（旧 Android / 帯域制限）でモデルがはじかないか確認（Fix-3）。
5. PC で wheel を 2 倍まで回し、モデルが 2 倍（4 倍でない）か確認（Fix-2）。

**判定基準**
- カメラ映像の「ズーム込み」感が iOS と同程度に収まる。
- マーカーとモデルの位置・サイズが端末・向きを問わず安定する。

---

## 📎 8. 関連ファイルマップ

| ファイル | 行 | 内容 |
|----------|----|------|
| `scene.blade.php` | L1-7 | UA 判定、Android=640/480 / iOS=1280/720 |
| `scene.blade.php` | L11 | `arjs` 射影 4 値のハードコード（**主因 A**） |
| `scene.blade.php` | L41 / L63 | モデル初期スケール 0.6 / 1.1 |
| `scene.blade.php` | L78-91 | Samsung 縦型上書き（Fix-1 に取って代わる削除候補） |
| `js-init.blade.php` | L8 | `currentScale = 1`（モバイルでは常に 1） |
| `js-init.blade.php` | L11-28 | `setBaseScaleIfMissing`（base 取得、正常） |
| `js-init.blade.php` | L31-56 | `applyCurrentScaleTo`（**主因 B・二重スケール**） |
| `js-init.blade.php` | L350-353 | `markerFound` で `applyCurrentScaleTo` 呼出 |
| `js-init.blade.php` | L373-381 | PC wheel zoom（`currentScale` 変更の唯一箇所） |
| `js-init.blade.php` | L523-535 | 低解像度リトライ（**主因 C**・display 未更新） |
| `js-init.blade.php` | L585-605 | `arjs-video-loaded`（**縦型限定**の補正） |
| `head.blade.php` | L3 | `user-scalable=no`（ページズーム防止、正当） |
| `head.blade.php` | L22-35 | `detectOldAndroid` / `ARFORCE_LOWRES` |
| `head.blade.php` | L64-103 | `ensureCameraAccess` フォールバック |
| `head.blade.php` | L172-181 | `object-fit: cover`（**見た目専用**、射影には無効） |

---

## ⚠️ 9. 注意点 / 想定副作用

- **Fix-1 は「`source` = 動画実寸、`display` = 実画面」で統一する**のが鍵。
  `display` を動画実寸にすると縦長画面でレターボックス的クリップが再発しうる。
- 4 値は**動画読み込み後**でないと確定しない（起動直後は `scene.blade.php` の
  暫定値）。その間にマーカーが認識されると、一時的に旧比率で固定される可能性
  がある → Fix-1 の `arjs-video-loaded` 即時適用が必須。
- Fix-2 で `node.scale.set(...)` を削除しても、GLB 自体の内部スケールは
  `dataset.baseScale`（= `scene.blade.php` の初期スケール）で制御されるため、
  意図した 0.6 / 1.1 が正しく反映される。
- `ARFORCE_LOWRES` / `ensureCameraAccess` のカメラ解像度は**入力品質**の話であり、
  本不具合（射影）の直接原因ではない。混同しないこと。
- Samsung 縦型上書き (`scene.blade.php` L78-91) を削除する場合、
  Fix-1 が必ず発火することを先に確認すること（順序依存）。

---

## 🔜 10. 次アクション（実装チェックリスト）

- [ ] **Fix-2**（`applyCurrentScaleTo` 二重スケール解消）— 最小・全端末受益・まず実施
- [ ] **Fix-1**（`arjs-video-loaded` 全方向 + resize/orientation 追従）— 主因 A 解消
- [ ] **Fix-3**（低解像度リトライ時の `display` 更新）
- [ ] `scene.blade.php` L78-91 Samsung 上書きの削除判断（Fix-1 確認後）
- [ ] §7 の検証を Android 縦/横・iOS・PC で実施し、結果を本ドキュメントに追記

