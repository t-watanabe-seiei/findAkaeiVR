# findakaei.blade.php 分析書（Qwen3.8）

> 分析対象ファイル: `resources/views/findakaei.blade.php`  
> 分析日: 2026-07-09

---

## 1. 概要

| 項目 | 内容 |
|------|------|
| タイトル | seieiVR（「あかい」を探すゲーム） |
| フレームワーク | A-Frame 1.2.0 (WebVR/AR) |
| ファイル構成 | **単一ファイル**（HTML + JS 約 412 行） |
| ゲーム内容 | 6 つの 360° 背景で「あかい」（ウツボ 3D モデル）を見つけ、クリックして次々と移動させ、全 6 箇所を制圧するとクリア。クリア後は動画再生 & TOP5 ランキング表示 |
| API | `POST /api/Scores`（スコア送信）、`GET /api/Scores`（TOP5 取得） |

---

## 2. 依存ライブラリ

| ライブラリ | 読み込み方法 | 用途 |
|-----------|------------|------|
| A-Frame 1.2.0 | CDN (`aframe.io`) | VR シーン構築 |
| aframe-particle-system-component | `asset('js/...')` | パーティクル（星） |
| aframe-extras 7.2.0 | CDN (`cdn.jsdelivr.net`) | 補足コンポーネント |
| axios | CDN (`unpkg.com`) | **未使用**（実装は native `fetch`） |

---

## 3. アセット（`<a-assets>`）

| ID | 種類 | 説明 |
|----|------|------|
| `akaeiModel_01` | GLB | あかい idle アニメーション |
| `akaeiModel_02` | GLB | あかい TrunToRunning アニメーション |
| `akaeiModel_03` | GLB | あかい HouseDancing アニメーション |
| `sky01` ~ `sky06` | JPG | 360° 背景画像（6 種） |
| `video` | MP4 | クリア後の紹介動画 |

> ⚠️ 古いアセット群がコメントアウト（`<!-- ... -->`）されたまま残っている（349-355 行目）。

---

## 4. 主要 JS ロジック

### 4.1 グローバル変数

```js
let PassSec;     // タイマー秒数（初回 case1 で 0 に初期化）
let mytext;      // テキスト要素リファレンス
let PassageID;   // setInterval ID
```

### 4.2 タイマー

```js
showPassage()  // setInterval で 10ms ごとに PassSec += 0.01
stopShowing()  // clearInterval
```

### 4.3 A-Frame カスタムコンポーネント

| コンポーネント名 | 役割 |
|----------------|------|
| `hit-box` | 当たり判定 & ゲーム進行制御（位置移動・モデル切替・スコア・動画） |
| `vr-controller` | 空（`raycaster` 依存のみのプレースホルダ） |

### 4.4 ゲームフロー

```
[ロード]
  │
  ▼
case 0 (初期表示: sky01, 位置1, scale 2.1)
  │  ← クリック（6回目）
  │
  ▼
case 1: タイマー開始 → sky02, 位置2, scale 2.7
  │  ← クリック
  ▼
case 2: sky03, 位置3, scale 1.4
  │  ← クリック
  ▼
case 3: sky04, 位置4, scale 2.1
  │  ← クリック
  ▼
case 4: sky05, 位置5, scale 1.0
  │  ← クリック
  ▼
case 5: sky06, 位置6, scale 1.9
  │  ← クリック（6回目）
  ▼
case 0 (hitCount%6==0):
  ├─ タイマー停止
  ├─ モデル → akaeiModel_03 (HouseDancing)
  ├─ スコア POST
  ├─ TOP5 GET & 表示
  ├─ Particle 表示
  ├─ 15秒後に playMovie()
  │    ├─ 背景・モデル・Particle 非表示
  │    ├─ 動画再生
  │    └─ 49秒後に stopMovie()
  │         └─ 全リソース復元 → 初回状態に戻る
```

---

## 5. 構造図

```
findakaei.blade.php (単一ファイル)
├── <head>
│   ├── script: A-Frame, particle, extras, axios
│   └── <script>
│       ├── グローバル変数 (PassSec, mytext, PassageID)
│       ├── showPassage() / stopShowing()
│       ├── AFRAME.registerComponent('hit-box', { ... })
│       │   ├── click handler
│       │   ├── playMovie()
│       │   ├── stopMovie()
│       │   └── rePaintModel() ← switch(hitCount % 6)
│       └── AFRAME.registerComponent('vr-controller', { ... })
├── <body>
│   └── <a-scene>
│       ├── <a-assets> (models, skies, video)
│       ├── mouseCursor
│       ├── leftController / rightController
│       ├── akaeiGroup
│       │   └── target3DModel
│       │       └── hit-boxed → hit-box-cylinder
│       ├── aSky
│       ├── videosphere
│       ├── particle
│       └── my_camera
│           ├── start ボタン (input)
│           ├── my_text
│           └── ranking1 ~ ranking5
```

---

## 6. 問題点・改善すべき点

### 🔴 重大 (Critical)

| # | 問題 | 行 | 説明 |
|---|------|----|------|
| C1 | `OnStartButtonClick()` が未定義 | 400 | `<input ... onClick="OnStartButtonClick();">` を呼んでいるが、JS 内にこの関数が存在しない。開始ボタンが動作しない |
| C2 | `PassSec` の未初期化 | 13 | `let PassSec;` は `undefined`。case1 まで `0` にならない。万一 case1 前に `showPassage()` が呼ばれると `NaN` |
| C3 | `setInterval('showPassage()', 10)` | 227 | 文字列引数（`eval` 的に解釈される）。セキュリティ & パフォーマンス上の悪慣習 |
| C4 | CORS / CSRF | 178,188 | `fetch` 呼び出しに CSRF トークンも認証ヘッダもない。`userid: '2'` はハードコード |

### 🟡 中程度 (Medium)

| # | 問題 | 説明 |
|---|------|------|
| M1 | axios を読み込んでいるが未使用 | `unpkg.com/axios` をロードしているが、実際は native `fetch` を使用。不要なバンドル増 |
| M2 | 単一ファイルに全てを記述 | 412 行のファイルに HTML・JS が混在。メンテ困難 |
| M3 | `hit-box` コンポーネントが全てを担う | 位置移動、モデル切替、スコア、動画再生、タイマー等が 1 コンポーネントに集中。SRP 違反 |
| M4 | 変数スコープの曖昧さ | `mySky`, `model1`, `model2`, `myRank1~5` 等は `let` で宣言後に `document.getElementById` で再代入。単にリファレンスの再利用で混乱 |
| M5 | コメントアウト済みアセットの残骸 | 349-355 行の古い sky/video がコメントで残存 |
| M6 | `userid: '2'` がハードコード | ユーザー識別が固定値。複数ユーザー対応不可 |
| M7 | タイマー精度 | `setInterval` 10ms + `0.01` 足し算はドリフトが累積。`Date.now()` ベースが望ましい |

### 🟢 軽微 (Minor)

| # | 問題 | 説明 |
|---|------|------|
| L1 | `vr-controller` が空 | `init: function() {}` のみのプレースホルダ |
| L2 | 未使用変数 `myRank1~5` | 宣言されているが `myRankElement` のみ使用 |
| L3 | 未使用変数 `model1`（init 内） | 宣言後に `document.getElementById('akaeiGroup')` で再代入 |
| L4 | `<input type="button">` が `<a-camera>` 内 | WebXR では DOM 要素の表示が不安定 |
| L5 | `PassageID` のスペル | `Passage`（通路）ではなく `Passage` → `PassageID` ではなく `PassageID` だが意図は `Passage ID`。一貫性の問題 |

---

## 7. `findHoufu` への移行時に参考になるパターン

1. **6 箇所の位置情報**（position / rotation / scale / sky / model）をオブジェクト配列で管理
   - 現行: `switch` 内のハードコード
   - 改善: `const LOCATIONS = [{pos, rot, scale, sky, model}, ...]`

2. **スコア API** は `POST` → `GET` の流れを共通化

3. **動画再生** は `playMovie` / `stopMovie` を独立したモジュールに

4. **Timer** は `Date.now()` ベース + `requestAnimationFrame` 推奨

---

## 8. アセットパス一覧

| 用途 | パス |
|------|------|
| あかい idle | `asset('cg/akaei_oldMan_idle.glb')` |
| あかい run | `asset('cg/akaei_TrunToRunning.glb')` |
| あかい dance | `asset('cg/akaei_HouseDancing.glb')` |
| 背景 1 | `asset('cg/R0010095.JPG')` |
| 背景 2 | `asset('cg/R0010109.JPG')` |
| 背景 3 | `asset('cg/R0010111.JPG')` |
| 背景 4 | `asset('cg/R0010114.JPG')` |
| 背景 5 | `asset('cg/R0010131.JPG')` |
| 背景 6 | `asset('cg/R0010143.JPG')` |
| 動画 | `asset('cg/R0010149_st.MP4')` |

---

## 9. 総評

「あかいを探せ」ゲームとして機能するが、**単一ファイルに全てが詰め込まれており、保守性が低い**。`OnStartButtonClick()` 未定義という致命的なバグを含む。`shooting3Dhalloween4` のように **`index` / `_components` / `_scene`** に分割し、ゲームロジックをコンポーネント単位で分離する構成を推奨する。