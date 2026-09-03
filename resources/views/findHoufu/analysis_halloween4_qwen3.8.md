# shooting3Dhalloween4 分析書（Qwen3.8）

> 分析対象ディレクトリ: `resources/views/shooting3Dhalloween4/`  
> 分析日: 2026-07-09

---

## 1. 概要

| 項目 | 内容 |
|------|------|
| タイトル | seieiVR Halloween4（ゾンビ撃退シューティング） |
| フレームワーク | A-Frame + Three.js (WebXR/VR) |
| ファイル構成 | **3 ファイル**（分割済み） |
| ゲーム内容 | 2 ステージ構成のシューティングゲーム。ゾンビを撃退し、ボス戦を経てスコアを競う。武器切替・コンボ・アモシステム・ランキングあり |

---

## 2. ファイル構成

```
shooting3Dhalloween4/
├── index.blade.php        (20行)  → HTML シェル & include
├── _components.blade.php  (1513行) → 全 JS ロジック & A-Frame コンポーネント
└── _scene.blade.php       (239行)  → A-Frame シーン定義 (HTML)
```

### 2.1 index.blade.php

```html
<head>
  - meta charset, csrf-token
  - script: aframe, particle, extras, axios (全て asset() でローカル)
  - @include('shooting3Dhalloween4._components')
</head>
<body>
  @include('shooting3Dhalloween4._scene')
</body>
```

**ポイント:**
- CSRF トークンを `<meta>` で設定（`findakaei` との大きな違い）
- 全て `asset()` でローカル読み込み（CDN 依存なし）
- `@include` による分割

---

## 3. _scene.blade.php 構造

### 3.1 `<a-scene>` 属性

```
renderer: antialias, colorManagement, sortObjects, physicallyCorrectLights, exposure, toneMapping: ACESFilmic
vr-mode-ui: enabled
auto-enter-vr
```

### 3.2 アセット (`<a-assets>`)

| カテゴリ | ID | 説明 |
|----------|-----|------|
| S1 ゾンビ | `model_s1_01` ~ `model_s1_05` | Stage1 通常敵 (5種) |
| S1 ボス | `model_boss_s1` | Stage1 ボス |
| S2 ゾンビ | `model_s2_01` ~ `model_s2_05` | Stage2 通常敵 (5種) |
| S2 ボス | `model_boss_s2` | Stage2 ボス |
| サウンド | `sound_hit`, `sound_bgm_s1/s2`, `sound_alert`, `sound_zombie_appear/die` | 効果音・BGM |
| 背景 | `sky_s1`, `sky_s2` | 360° 背景 |
| アイコン | `pokeball_icon_05/06` | 武器アイコン |

### 3.3 エンティティ構造

```
a-scene
├── a-assets
├── lighting (ambient + 3x directional)
├── mouseCursor
├── leftController (laser-controls, raycaster)
│   └── controllerGunModel (GLB)
├── rightController (laser-controls, raycaster)
│   └── controllerGunModel (GLB)
├── startMenu [start-menu]
│   ├── plane (clickable)
│   ├── texts (title, stage, weapon, hint, START)
│   └── weaponDisplay (clickable)
├── timerDisplay (timerText, currentScore)
├── debugDisplay (debugText)
├── resultMenu_s1 [result-menu]
│   ├── plane, texts, rankingDisplay_s1
│   └── nextStageButton (clickable)
├── resultMenu_s2 [result-menu]
│   ├── plane, texts, rankingDisplay_s2
│   └── gameOverButton (clickable collidable)
├── closeMessage
├── aSky
├── particles (normal, tier1~3, celebration)
└── my_camera [shoot]
    ├── ammoHud (gun1/gun2 ammo display)
    └── fadeOverlay
```

---

## 4. _components.blade.php 構造

### 4.1 グローバル設定

| 変数/オブジェクト | 用途 |
|-------------------|------|
| `window.DEBUG_MODE` / `debugLog` | デバッグ制御 |
| `window.gameStarted/Ended` | ゲーム状態 |
| `window.totalScore, comboCount, maxComboCount` | スコア管理 |
| `window.gameTimeLeft, gameTimer` | タイマー |
| `window.currentStage` | 現在ステージ (1 or 2) |
| `window.selectedGun` | 選択武器 (1 or 2) |
| `window.ammoByGun` | 武器別弾数 |
| `window.STAGE_CONFIG` | ステージ設定（時間・モデル・ボス・BGM等） |
| `window.GUN_CONFIG` | 武器設定（ボール・モデルパス） |
| `window.activeBalls` | 発射中ボール |
| `window.ballPoolByGun` | ボールプール（オブジェクトプーリング） |
| `window.activeTimers` | 追跡される timeout |
| `window.respawnModelGlobal` | 再生成関数 |

### 4.2 ユーティリティ関数

| 関数 | 用途 |
|------|------|
| `registerTimeout(cb, delay)` | 追跡可能な setTimeout（クリア可能） |
| `getAvailablePattern(patterns, modelId)` | 使用済みパターンを避けたランダム選択 |
| `updateAmmoDisplay()` | HUD 弾数更新 |
| `showAmmoPopup(gunNo, amount)` | 弾補充ポップアップ |
| `tryConsumeAmmo(gunNo)` | 弾消費 |
| `addAmmoToGun(gunNo, amount)` | 弾追加 |
| `spawnAmmoPickupToCamera(...)` | 敵撃破時弾ドロップ |
| `stopAllParticles()` | 全パーティクル停止 |
| `disposeEntityResources(entity)` | GPU リソース解放 |
| `releaseModelsFromScene(ids, sceneEl)` | モデル群解放 |
| `disposeAndRemoveEntity(entity)` | エンティティ破棄 & 削除 |
| `createBallEntity(gunNo)` | ボール作成 |
| `acquireBallEntity(gunNo, sceneEl)` | プールからボール取得 |
| `releaseBallEntity(ball, gunNo)` | ボールをプールに返却 |
| `fadeOutAndStopAudio(audioEl, dur, cb)` | BGM フェードアウト |
| `setupStage2MusicReload(audioEl)` | S2 BGM 終了時リロード |

### 4.3 A-Frame カスタムコンポーネント

| コンポーネント | 役割 |
|---------------|------|
| `enhance-materials` | モデルテクスチャの各方向异性フィルタリング |
| `face-camera` | エンティティをカメラ方向に向ける（50ms スロットリング） |
| `start-menu` | ゲーム開始、武器切替、敵スポーン、タイマー、リザルト表示 |
| `result-menu` | Next Stage / Game Over 遷移、フェード、クローズ |
| `shoot` | 射撃機構（ボール発射、移動、当たり判定） |
| `approach-camera` | 敵のカメラ方向への移動、リスポーン |
| `hit-box` | 当たり判定、スコア計算、コンボ、再生成 |
| `auto-enter-vr` | VR サポート検出 → 自動 VR 入場 |
| `vr-controller` | 空（`raycaster` 依存のみ） |

---

## 5. ゲームフロー

```
[ロード] → auto-enter-vr → VR 入場
  │
  ▼
START MENU (startMenu)
  │  ← START クリック / touch
  ▼
Stage 1 (100秒)
  ├─ 5体のゾンビが approach-camera で接近
  ├─ 撃破 → スコア + コンボ + 弾ドロップ(01のみ+5)
  ├─ ボスが残り15秒で出現 (15 hit)
  └─ タイムアップ or 全滅 → リザルト S1
       │
       ▼
  NEXT STAGE ボタン
       │  ← クリック
       ▼
Stage 2 (85秒)
  ├─ 5体のゾンビ (2 hit 必須, 01のみ+10)
  ├─ ボス (15 hit)
  └─ タイムアップ or 全滅 → リザルト S2
       │
       ▼
  GAME OVER ボタン
       │  ← クリック
       ▼
  フェードアウト → VR 退出 → location.reload()
```

---

## 6. スコアリング機構

| 要素 | 計算 |
|------|------|
| 基本スコア | `10.0` / 撃破 |
| 距離ボーナス | `distance > 8: ×1.6`, `> 4: ×1.0`, `else: ×0.6` |
| コンボ倍率 | `tier = min(combo, 5)`, ボーナスあり `×1.1` |
| ボスコア | 同様に 1 hit ずつ加算 |

---

## 7. パフォーマンス対策

| 対策 | 実装 |
|------|------|
| **オブジェクトプーリング** | `ballPoolByGun` (最大 4 発/gun) |
| **THREE.Color キャッシュ** | `cachedBallEmissiveColor` 等 |
| **Vector3 再利用** | `_cachedModelPos`, `_cachedCameraPos` |
| **tick スロットリング** | `face-camera` (50ms), `start-menu` (100ms) |
| **GPU リソース解放** | `disposeEntityResources()` (geometry, material, textures) |
| **タイムアウト追跡** | `registerTimeout` + `activeTimers` で一括クリア |
| **モデル dispose & remove** | `disposeAndRemoveEntity` |
| **`showLine: false`** | 左コントローラーのレーザー非表示（GPU 節約） |

---

## 8. 問題点・改善すべき点

### 🔴 重大 (Critical)

| # | 問題 | 説明 |
|---|------|------|
| C1 | `saveScoreToDatabase` で `name: 'noName'` ハードコード | ユーザー識別がない。ランキングが実質無意味 |
| C2 | `api/shooting-scores` の相対パス | `{{ env('MIX_ASSET_URL') }}` 不使用。`findakaei` と API ベース URL の扱いが不統一 |
| C3 | Stage2 BGM `ended` イベントで `reload()` | BGM が loop しないと動かない。脆弱な再プレイ機構 |

### 🟡 中程度 (Medium)

| # | 問題 | 説明 |
|---|------|------|
| M1 | `window.*` グローバル変数の乱用 | 1500 行の JS が全て `window.*` で共有。カプセル化なし、名前衝突リスク |
| M2 | `hit-box` コンポーネントが巨大 | 当たり判定・スコア・コンボ・アニメーション・再生成が 1 関数に集中（~200行） |
| M3 | `start-menu` がゲーム全体を管理 | ゲーム開始、タイマー、ボススポーン、リザルト、スコア保存、ランキング表示まで |
| M4 | `shoot` の当たり判定が手動 | A-Frame の built-in raycaster ではなく、`tick` 内で手計算（重力 2.45 込み） |
| M5 | `respawnModelGlobal` のシングルトン | 最初の `hit-box` init で設定。複数 hit-box がある場合の競合 |
| M6 | 複数 `addEventListener` の重複 | `click` / `touchstart` が複数回 bind される可能性（enter-vr / exit-vr 時の再 setup） |
| M7 | `fadeOverlay` が camera 内 | camera 子要素として `position: 0 0 -0.5`。VR モードでの表示不安定 |
| M8 | `STAGE_CONFIG` の `models` 配列が未使用 | `activateModels` で pattern をハードコード。`cfg.models` は参照されない |

### 🟢 軽微 (Minor)

| # | 問題 | 説明 |
|---|------|------|
| L1 | `DEBUG_MODE` が常に `false` | デバッグ用だが切り替える仕組みが UI ない |
| L2 | `closeMessage` エンティティ | 「このタブを閉じてください」— `visible=false` で固定、未使用 |
| L3 | `vr-controller` が空 | `init: function() {}` のみのプレースホルダ |
| L4 | `face-camera` の `lookAt` 後に `rotation.set(0, y, 0)` | X/Z ロールが常にリセットされる |
| L5 | `shoot` の `modelsList` がハードコード | 6 体の modelGroup を列挙。動的に生成されたモデルとの同期なし |
| L6 | `ammoHud` の右寄り配置 | 片眼 VR デバイスで視認性 |
| L7 | `setupStage2MusicReload` の `once: true` | 一度の BGM 終了で reload。再プレイ不可 |

---

## 9. findHoufu への移行時に参考になるパターン

### 9.1 ファイル分割構成（推奨）

```
findHoufu/
├── index.blade.php        → HTML シェル + include
├── _components.blade.php  → JS ロジック + A-Frame コンポーネント
└── _scene.blade.php       → A-Frame シーン定義
```

### 9.2 設定オブジェクトによるデータ駆動

```js
// findakaei の switch-case をこうする
const LOCATIONS = [
  { pos: '-2 0.5 1',  rot: '0 120 0',  scale: '2.1 2.1 2.1', sky: '#sky01', model: '#model_03' },
  { pos: '10 -0.88 -1.9', rot: '0 -90 0', scale: '2.7 2.7 2.7', sky: '#sky02', model: '#model_01' },
  // ...
];
```

### 9.3 ユーティリティ共通化

- `registerTimeout` — タイマー追跡
- `disposeEntityResources` — GPU メモリ解放
- `fadeOutAndStopAudio` — BGM フェード
- `getAvailablePattern` — ランダムパターン選択

### 9.4 API 呼び出しの統一

- CSRF トークン `<meta>` 設定（halloween4 方式）
- ベース URL は `{{ env('MIX_ASSET_URL') }}` で統一
- `name` / `userid` をセッションまたはクエリから取得

### 9.5 パフォーマンス

- オブジェクトプーリング（ボール・モデル）
- tick スロットリング
- `showLine: false` 等 GPU 節約
- `disposeEntityResources` で GPU メモリリーク防止

---

## 10. アセットパス一覧

| 用途 | パス |
|------|------|
| S1 ゾンビ 01 | `asset('cg/20260613/11_optimized.glb')` |
| S1 ゾンビ 02 | `asset('cg/20260613/12_optimized.glb')` |
| S1 ゾンビ 03 | `asset('cg/20260613/13_optimized.glb')` |
| S1 ゾンビ 04 | `asset('cg/20260613/14_optimized.glb')` |
| S1 ゾンビ 05 | `asset('cg/20260613/15_optimized.glb')` |
| S1 ボス | `asset('cg/20260613/boss101_optimized.glb')` |
| S2 ゾンビ 01 | `asset('cg/20260613/16_optimized.glb')` |
| S2 ゾンビ 02 | `asset('cg/20260613/17_optimized.glb')` |
| S2 ゾンビ 03 | `asset('cg/20260613/18_optimized.glb')` |
| S2 ゾンビ 04 | `asset('cg/20260613/19_optimized.glb')` |
| S2 ゾンビ 05 | `asset('cg/20260613/20_optimized.glb')` |
| S2 ボス | `asset('cg/20260613/boss102_optimized.glb')` |
| 武器 1 | `asset('cg/gun_01.glb')` |
| 武器 2 | `asset('cg/gun_02.glb')` |
| ボール 1 | `cg/poke_ball_05.glb` |
| ボール 2 | `cg/poke_ball_06.glb` |
| BGM S1 | `asset('cg/sound_bgm13.mp3')` |
| BGM S2 | `asset('cg/sound_bgm14.mp3')` |
| 撃破音 | `asset('cg/sound_hit01.mp3')` |
| アラート | `asset('cg/sound_alert.mp3')` |
| 出現音 | `asset('cg/sound_animal_appear.mp3')` |
| 死亡音 | `asset('cg/sound_animal_die.mp3')` |
| 背景 S1 | `asset('cg/202609/IMG_20260405_506.jpg')` |
| 背景 S2 | `asset('cg/202609/IMG_20260405_507.jpg')` |
| アイコン 1 | `asset('cg/pokeball_icon05.png')` |
| アイコン 2 | `asset('cg/pokeball_icon06.png')` |

---

## 11. findakaei.blade.php との比較

| 比較項目 | findakaei | halloween4 |
|----------|-----------|------------|
| ファイル構成 | 単一ファイル (412行) | 3ファイル分割 (1772行) |
| ライブラリ読み込み | CDN + asset 混合 | 全て asset() ローカル |
| CSRF | なし | `<meta>` で設定 |
| API ベース URL | `env('MIX_ASSET_URL')` | 相対パス `api/...` |
| ゲーム複雑度 | 低（クリック→位置移動） | 高（シューティング+コンボ+ボス） |
| パフォーマンス | 無対策 | プーリング・スロットリング・dispose |
| サウンド | なし | BGM + 効果音 6種 |
| UI 要素 | 最低限（text + button） | 充実（HUD, ranking, popup, fade） |
| VR 対応 | laser-controls | laser-controls + auto-enter-vr + gun model |
| タイマー | setInterval 10ms | setInterval 1000ms |
| 敵 AI | なし（固定位置） | approach-camera（カメラ追従+リスポーン） |
| スコア API | `POST /api/Scores` + `GET /api/Scores` | `POST api/shooting-scores` + `GET api/shooting-scores/top5` |

---

## 12. 総評

`shooting3Dhalloween4` は**構成・設計の面で `findakaei` を大幅に上回る**。3 ファイル分割、オブジェクトプーリング、GPU リソース管理、CSRF 対策、設定オブジェクトによるデータ駆動設計など、本番運用を意識した実装になっている。

`findHoufu` を作成する際は、**halloween4 のアーキテクチャ（3ファイル分割 + 設定オブジェクト + ユーティリティ関数）を土台**にし、findakaei の「探してクリックする」ゲームロジックを組み込む形が最も効率的である。