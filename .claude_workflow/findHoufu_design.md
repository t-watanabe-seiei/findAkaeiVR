# findHoufu 設計書

> 作成日: 2026-07-09  
> 最終更新: 2026-09-03  
> 前提: `.claude_workflow/findHoufu_requirements.md` を読み込み済み

---

## 0. 修正設計（2026-09-03）

shooting3Dhalloween4 を参照し、`_components.blade.php` の以下4箇所を修正する。

### 0.1 auto-enter-vr 修正

**問題:** `init()` 内で即座に `navigator.xr.isSessionSupported()` を呼び、`loaded` イベントを待たない。VRゴーグル（Pico等）ではシーン初期化完了前に `enterVR()` が呼ばれ失敗する。

**修正方針:** shooting3Dhalloween4 と同じく `loaded` イベントを待ってから WebXR 確認 → 1秒待機 → `enterVR()`。

```js
AFRAME.registerComponent('auto-enter-vr', {
    init: function () {
        const sceneEl = this.el;
        sceneEl.addEventListener('loaded', function () {
            if (navigator.xr) {
                navigator.xr.isSessionSupported('immersive-vr').then(function (supported) {
                    if (supported) {
                        registerTimeout(function () {
                            if (sceneEl.sessionMode !== 'vr') sceneEl.enterVR();
                        }, 1000);
                    }
                }).catch(function () {});
            }
        });
    }
});
```

### 0.2 start-menu clickBlocked 修正

**問題:** schema の `clickBlocked` デフォルト値が `true`、`init()` でも `this.data.clickBlocked`（= `true`）をそのまま使用。`handleClick()` 内の `if (this.clickBlocked) return;` で永久にブロックされる。

**修正方針:** shooting3Dhalloween4 と同じく `init()` 内で `this.clickBlocked = false` で初期化。`startGame()` 内で `true` に変更（再クリック防止）。

```js
// schema の default を false に変更
schema: { clickBlocked: { type: 'boolean', default: false }, ... },

// init() で明示的に false
init: function () {
    this.clickBlocked = false;  // ← 修正
    ...
},

// handleClick() は既存ロジックのまま（clickBlocked チェックは有効）
// startGame() で clickBlocked = true を設定（既存）
```

### 0.3 shoot triggerdown リスナー修正

**問題:** `this.el.addEventListener('triggerdown', triggerFn)` の `this.el` は `<a-camera>`。VR では `triggerdown` がコントローラー实体（`#leftController` / `#rightController`）で発火するため、カメラには届かない。

**修正方針:** shooting3Dhalloween4 と同じくコントローラー实体に `triggerdown` リスナーを付与。PC は `document mousedown` を維持。

```js
init: function () {
    ...
    const vrTriggerFn = function (e) {
        if (window.gameStarted && !window.gameEnded) this.shoot(e);
    }.bind(this);

    // VR: コントローラーにリスナー
    const lc = document.getElementById('leftController');
    const rc = document.getElementById('rightController');
    if (lc) lc.addEventListener('triggerdown', vrTriggerFn);
    if (rc) rc.addEventListener('triggerdown', vrTriggerFn);

    // PC: document にリスナー（mousedown / touchstart）
    const pcFn = function (e) {
        if (window.gameStarted && !window.gameEnded) {
            e.preventDefault();
            this.shoot(e);
        }
    }.bind(this);
    document.addEventListener('mousedown', pcFn);
    document.addEventListener('touchstart', pcFn, { passive: false });

    this._vrTriggerFn = vrTriggerFn;
    this._pcFn = pcFn;
},
remove: function () {
    const lc = document.getElementById('leftController');
    const rc = document.getElementById('rightController');
    if (lc) lc.removeEventListener('triggerdown', this._vrTriggerFn);
    if (rc) rc.removeEventListener('triggerdown', this._vrTriggerFn);
    document.removeEventListener('mousedown', this._pcFn);
    document.removeEventListener('touchstart', this._pcFn);
    releaseAllBalls();
},
```

### 0.4 vr-controller 登録追加

**問題:** `_scene.blade.php` のコントローラーで `vr-controller` 属性を使用しているが、`AFRAME.registerComponent('vr-controller', ...)` が存在しない。A-Frame が警告を出力し、シーン初期化に悪影響を及ぼす可能性がある。

**修正方針:** shooting3Dhalloween4 と同じ空コンポーネントを登録。

```js
AFRAME.registerComponent('vr-controller', {
    dependencies: ['raycaster'],
    init: function () {}
});
```

### 0.5 変更対象ファイル

| ファイル | 変更内容 |
|---------|---------|
| `resources/views/findHoufu/_components.blade.php` | 上記4箇所（auto-enter-vr / start-menu / shoot / vr-controller） |

> `_scene.blade.php` / `index.blade.php` / `vr-mode-ui` は変更不要。

---

## 1. アーキテクチャ概要

```
┌─────────────────────────────────────────────────────┐
│  index.blade.php (HTML シェル)                       │
│  ├── <head>: scripts + @include(_components)        │
│  └── <body>: @include(_scene)                       │
├─────────────────────────────────────────────────────┤
│  _components.blade.php (JS)                         │
│  ├── グローバル設定 (STAGE_CONFIG, LOCATIONS, ...)   │
│  ├── ユーティリティ関数 (pool, dispose, timeout...)  │
│  └── A-Frame コンポーネント                          │
│      ├── start-menu                                 │
│      ├── shoot                                      │
│      ├── hit-box                                    │
│      ├── auto-enter-vr                              │
│      └── vr-controller                              │
├─────────────────────────────────────────────────────┤
│  _scene.blade.php (A-Frame HTML)                    │
│  ├── a-assets (model, gun, ball, bgm, sky, sound)   │
│  ├── lighting                                       │
│  ├── controllers (left/right + gun model)           │
│  ├── startMenu                                      │
│  ├── timerDisplay (HUD)                             │
│  ├── resultMenu                                     │
│  ├── aSky                                           │
│  ├── particles                                      │
│  └── camera (shoot)                                 │
└─────────────────────────────────────────────────────┘
```

## 2. データ構造

### 2.1 STAGE_CONFIG

```js
window.STAGE_CONFIG = {
  1: { timeLimit: 10, skyId: 'sky01', bgmId: 'bgm_s1', isResult: false },
  2: { timeLimit: 12, skyId: 'sky02', bgmId: 'bgm_s1', isResult: false },
  3: { timeLimit: 12, skyId: 'sky03', bgmId: 'bgm_s2', isResult: false },
  4: { timeLimit: 12, skyId: 'sky04', bgmId: 'bgm_s2', isResult: false },
  5: { timeLimit: 12, skyId: 'sky05', bgmId: 'bgm_s3', isResult: false },
  6: { timeLimit: 12, skyId: 'sky06', bgmId: 'bgm_s3', isResult: false },
  7: { timeLimit: 12, skyId: 'sky01', bgmId: 'bgm_s4', isResult: true  },
};
```

### 2.2 LOCATIONS

```js
window.LOCATIONS = [
  { pos: '-2 -0.6 1',      rot: '0 120 0',  scale: '1.4 1.4 1.4' },
  { pos: '10 -0.88 -1.9',  rot: '0 -90 0',  scale: '2.7 2.7 2.7' },
  { pos: '-1.325 1.0 4.00',rot: '0 150 0',  scale: '1.4 1.4 1.4' },
  { pos: '6.0 0 0.13',     rot: '0 -120 0', scale: '2.1 2.1 2.1' },
  { pos: '-0.5 0 -0.5',    rot: '0 0 0',    scale: '1 1 1' },
  { pos: '-4.5 0.9 4.6',   rot: '0 130 0',  scale: '1.9 1.9 1.9' },
];
```

### 2.3 グローバル状態

```js
window.gameStarted   = false;
window.gameEnded     = false;
window.currentStage  = 1;
window.totalScore    = 0;
window.comboCount    = 0;
window.maxComboCount = 0;
window.hitCount      = 0;
window.modelAppearTime = 0;
window.modelActive   = false;
window.gameTimer     = null;
window.gameTimeLeft  = 0;
window.activeBalls   = [];
window.ballPool      = [];
window.activeTimers  = [];
window._cachedPos    = new THREE.Vector3();
window._cachedDir    = new THREE.Vector3();
window._cachedBallColor = null;
```

## 3. コンポーネント設計

### 3.1 start-menu

| 項目 | 内容 |
|------|------|
| 役割 | ゲーム開始、ステージ進行、タイマー、リザルト |
| 発火 | START ボタン click/touchstart |

**メソッド:** `handleClick`, `startGame`, `startTimer`, `spawnModel(locIdx)`, `advanceStage`, `showResult`, `saveScore`, `fetchRankings`, `tick`

### 3.2 shoot

| 項目 | 内容 |
|------|------|
| 役割 | ボール発射・移動・当たり判定 |
| 発火 | triggerdown / mouse click |
| 同時 | 最大 2 発 |

**メソッド:** `shoot(e)`, `tick`, `updateBallPosition(bd)`, `checkHit(ballPos)`

**ボール物理:** `pos = start + vel*t + 0.5*g*t²`, `g=(0,-2.45,0)`, `vel=dir*20`

### 3.3 hit-box

| 項目 | 内容 |
|------|------|
| 役割 | ヒット検出→アニメ切替→フェード→スコア→再出現 |
| 発火 | `ball-hit` イベント |

**フロー:** ball-hit → anime02 → ヒット音 → スコア計算 → コンボ+1 → 0.5s フェード → dispose → 0.5s 後再出現

### 3.4 auto-enter-vr / vr-controller

halloween4 と同じ。WebXR 検出→1s 後 enterVR / 空コンポーネント。

## 4. ユーティリティ関数

| 関数 | 説明 |
|------|------|
| `registerTimeout(cb, delay)` | 追跡可能な setTimeout |
| `clearAllTimers()` | 全 activeTimers クリア |
| `disposeEntityResources(entity)` | GPU リソース解放 |
| `disposeAndRemoveEntity(entity)` | dispose + DOM 削除 |
| `createBallEntity()` | ボール生成 |
| `acquireBall(sceneEl)` | プール取得 or 新規 |
| `releaseBall(ball)` | プール返却（max 4） |
| `playSound(id)` | 効果音再生 |
| `fadeOutAndStopAudio(el, dur, cb)` | BGM フェード |
| `preloadNextStage(n)` | 次ステージプリロード |
| `updateHUD()` | HUD 更新 |
| `showScorePopup(pos, score, combo)` | 得点ポップアップ |

## 5. シーン構造（_scene.blade.php）

```
a-scene
├── a-assets (model, gun, ball, bgm×4, sound×2, sky×6, icon)
├── lighting (ambient + 2x directional)
├── mouseCursor
├── leftController (laser showLine:false)
├── rightController (laser showLine:true + gun)
├── startMenu [start-menu] (plane + texts + START)
├── timerDisplay (score, combo, time)
├── resultMenu (plane + texts + ranking + message)
├── aSky
├── particles (celebration)
└── my_camera [shoot] → fadeOverlay
```

## 6. 状態遷移

```
[LOADED] → [START_MENU] → START → [STAGE_1]
  STAGE_N: spawn → hit → fade → respawn (loop until timeout)
  timeout → STAGE_N+1
  STAGE_6 timeout → STAGE_7 (Result)
  STAGE_7: model + score + ranking + 12s → FADE → VR_EXIT → RELOAD
```

## 7. パフォーマンス設計

| 項目 | 実装 |
|------|------|
| ボールプーリング | max 4 発 |
| Color キャッシュ | `window._cachedBallColor` |
| Vector3 再利用 | `_cachedPos`, `_cachedDir` |
| GPU 解放 | フェード後 `disposeAndRemoveEntity()` |
| タイムアウト追跡 | `registerTimeout` + `clearAllTimers` |
| laser | 左: false / 右: true |
| tick スロットル | start-menu: 100ms |
| プリロード | a-assets + BGM preload="auto" |

## 8. findakaei 問題点への対策

| 問題 | 対策 |
|------|------|
| `OnStartButtonClick()` 未定義 | start-menu で event listener |
| `PassSec` 未初期化 | `Date.now()` ベース |
| `setInterval` 文字列引数 | 関数リファレンス |
| CSRF なし | `<meta>` + `X-CSRF-TOKEN` |
| `userid` ハードコード | `name: 'noName'` |
| 単一ファイル | 3 ファイル分割 |
| axios 未使用 | 読み込まない（native fetch） |
| コメント残骸 | clean な定義 |
---

## 9. 追加設計（2026-09-04）

前提: `.claude_workflow/findHoufu_requirements.md` §10 を読み込み済み

### 9.1 変更対象ファイル

| ファイル | 変更内容 |
|---------|---------|
| `resources/views/findHoufu/_components.blade.php` | 全変更（STAGE_CONFIG / LOCATIONS / BGM / ボール / タイマー） |

> `_scene.blade.php` / `index.blade.php` は変更不要。

### 9.2 STAGE_CONFIG 変更

```js
// 変更前
1: { timeLimit: 12, skyId: 'sky01', bgmId: 'bgm_s1', isResult: false },
2: { timeLimit: 12, skyId: 'sky02', bgmId: 'bgm_s1', isResult: false },
3: { timeLimit: 12, skyId: 'sky03', bgmId: 'bgm_s2', isResult: false },
4: { timeLimit: 12, skyId: 'sky04', bgmId: 'bgm_s2', isResult: false },
5: { timeLimit: 12, skyId: 'sky05', bgmId: 'bgm_s3', isResult: false },
6: { timeLimit: 12, skyId: 'sky06', bgmId: 'bgm_s3', isResult: false },
7: { timeLimit: 12, skyId: 'sky01', bgmId: 'bgm_s4', isResult: true  },

// 変更後
1: { timeLimit: 16, skyId: 'sky01', bgmId: 'bgm_s1', isResult: false },
2: { timeLimit: 16, skyId: 'sky02', bgmId: 'bgm_s1', isResult: false },
3: { timeLimit: 16, skyId: 'sky03', bgmId: 'bgm_s1', isResult: false },
4: { timeLimit: 16, skyId: 'sky04', bgmId: 'bgm_s1', isResult: false },
5: { timeLimit: 16, skyId: 'sky05', bgmId: 'bgm_s1', isResult: false },
6: { timeLimit: 16, skyId: 'sky06', bgmId: 'bgm_s1', isResult: false },
7: { timeLimit: 16, skyId: 'sky01', bgmId: 'bgm_s4', isResult: true  },
```

**ポイント:**
- 全ステージ `timeLimit: 16`
- Stage1〜6: すべて `bgmId: 'bgm_s1'`（同一BGM）
- Stage7: `bgm_s4`（リザルト用）

### 9.3 LOCATIONS（モデルサイズ50%）

```js
// 変更前（現在値）
{ pos: '-2 -0.6 1',       rot: '0 120 0',  scale: '0.7 0.7 0.7' },
{ pos: '10 -0.88 -1.9',   rot: '0 -90 0',  scale: '1.35 1.35 1.35' },
{ pos: '-1.325 1.0 4.00', rot: '0 150 0',  scale: '0.7 0.7 0.7' },
{ pos: '6.0 0 0.13',      rot: '0 -120 0', scale: '1.05 1.05 1.05' },
{ pos: '-0.5 0 -0.5',     rot: '0 0 0',    scale: '0.5 0.5 0.5' },
{ pos: '-4.5 0.9 4.6',    rot: '0 130 0',  scale: '0.95 0.95 0.95' },

// 変更後（50%）
{ pos: '-2 -0.6 1',       rot: '0 120 0',  scale: '0.35 0.35 0.35' },
{ pos: '10 -0.88 -1.9',   rot: '0 -90 0',  scale: '0.675 0.675 0.675' },
{ pos: '-1.325 1.0 4.00', rot: '0 150 0',  scale: '0.35 0.35 0.35' },
{ pos: '6.0 0 0.13',      rot: '0 -120 0', scale: '0.525 0.525 0.525' },
{ pos: '-0.5 0 -0.5',     rot: '0 0 0',    scale: '0.25 0.25 0.25' },
{ pos: '-4.5 0.9 4.6',    rot: '0 130 0',  scale: '0.475 0.475 0.475' },
```

### 9.4 ボール速度 50%

```js
// 変更前
this.speed = 30;

// 変更後
this.speed = 15;
```

### 9.5 ボールヒット時：跳ね返り+フェードアウト

**変更箇所:** `shoot` コンポーネントの `tick` 内、ヒット判定ブロック

**変更前:**
```js
if (dist < 1.5) {
    bd.hit = true;
    toRemove.push(bd);
    this.el.sceneEl.dispatchEvent(new CustomEvent('ball-hit'));
    return;
}
```

**変更後:**
```js
if (dist < 1.5) {
    bd.hit = true;
    this.el.sceneEl.dispatchEvent(new CustomEvent('ball-hit'));
    // ボール跳ね返り + フェードアウト（shooting3Dhalloween4 同等）
    var ballEl = bd.el;
    var mesh = ballEl.getObject3D('mesh');
    if (mesh && window._cachedHitEmissive) {
        mesh.traverse(function(n) {
            if (n.isMesh && n.material) {
                n.material.emissive = window._cachedHitEmissive;
                n.material.emissiveIntensity = 1.5;
            }
        });
    }
    ballEl.setAttribute('animation__fade', { property: 'scale', to: '0 0 0', dur: 300, easing: 'easeInQuad' });
    var idx = window.activeBalls.indexOf(bd);
    if (idx !== -1) window.activeBalls.splice(idx, 1);
    registerTimeout(function() { releaseBall(ballEl); }, 300);
    return;
}
```

**グローバル追加:**
```js
window._cachedHitEmissive = new THREE.Color(0xFFFFFF);
```

**動作フロー:**
1. ヒット判定 → `ball-hit` イベント発火（hit-boxコンポーネントがスコア計算）
2. ボール mesh の emissive を白(0xFFFFFF) intensity 1.5 に設定（フラッシュ）
3. `animation__fade` で scale 0→0（300ms, easeInQuad）で縮小
4. `activeBalls` から即除去（同時進行ボール数に影響しない）
5. 300ms後に `releaseBall()` でプール返却（hidden + pool push）

### 9.6 BGM ループ再生（Stage1〜6）

**変更箇所:** `start-menu` コンポーネントの `advanceStage` 内 BGM ブロック

**変更前（crossfade 方式）:**
```js
var self = this;
const newBgmEl = document.getElementById(cfg.bgmId);
if (this.bgmAudio && this.bgmAudio !== newBgmEl) {
    fadeOutAndStopAudio(this.bgmAudio, 1500, function () {
        if (newBgmEl) {
            newBgmEl.volume = 0.5;
            newBgmEl.currentTime = 0;
            newBgmEl.play().catch(function () {});
        }
        self.bgmAudio = newBgmEl;
    });
} else if (newBgmEl) {
    newBgmEl.volume = 0.5;
    newBgmEl.currentTime = 0;
    newBgmEl.play().catch(function () {});
    this.bgmAudio = newBgmEl;
}
```

**変更後（同一BGMループ方式）:**
```js
var self = this;
// BGM: Stage1〜6 同一BGMループ（ステージ切替で停止しない）
//       Stage7 は showResult が BGM 切替を処理
if (!cfg.isResult) {
    const newBgmEl = document.getElementById(cfg.bgmId);
    if (this.bgmAudio !== newBgmEl && newBgmEl) {
        if (this.bgmAudio) {
            fadeOutAndStopAudio(this.bgmAudio, 1500, function () {
                newBgmEl.volume = 0.5;
                newBgmEl.currentTime = 0;
                newBgmEl.play().catch(function () {});
                self.bgmAudio = newBgmEl;
            });
        } else {
            newBgmEl.volume = 0.5;
            newBgmEl.currentTime = 0;
            newBgmEl.play().catch(function () {});
            this.bgmAudio = newBgmEl;
        }
    }
    // this.bgmAudio === newBgmEl → 同一BGMループ継続（何もしない）
}
```

**動作フロー:**
- Stage1開始: `this.bgmAudio === null` → `bgm_s1` 開始（loop属性で自動ループ）
- Stage1→2: `this.bgmAudio === bgm_s1 === newBgmEl` → 何もしない（ループ継続）
- Stage2→3〜6: 同上（すべて `bgm_s1` なので何もしない）
- Stage6→7: `cfg.isResult === true` → BGMブロックスキップ → `showResult()` が `bgm_s1` 停止 + `bgm_s4` 開始

### 9.7 Stage7: 16秒後にVR解除

**変更箇所:** `showResult` 内の `resultTimeLeft`

```js
// 変更前
var resultTimeLeft = 12;

// 変更後
var resultTimeLeft = 16;
```

VR解除ロジック（`exitToStart`）は既存のまま維持。

### 9.8 影響範囲サマリー

| 機能 | 影響 | 備考 |
|------|------|------|
| ボール速度 | 50%減 | 射撃テンポが低下 |
| モデルサイズ | 50%減 | ヒット判定 radius 0.8 は維持（相対的に大きくなる） |
| ステージ時間 | 12s→16s | 全ステージ + 結果画面 |
| ボールヒット | 視覚効果追加 | emissive + scale animation |
| BGM | Stage1-6 統一 | `bgm_s1` のみ。Stage7 で `bgm_s4` |
| VR解除 | 12s→16s | Stage7 リザルト表示時間 |
