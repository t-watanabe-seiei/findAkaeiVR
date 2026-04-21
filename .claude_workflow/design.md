# 設計: /stamp202605 投擲時自動GET化（ズーム継続時の運用回避）

---

# 設計: /stamp202603 UI改修・自動GET実装（2026-04-22）

## 前段階ファイル読込
前段階のmdファイルを読み込みました（`.claude_workflow/requirements.md`）。

## 対象ファイル
- `resources/views/ARstampRally202603.blade.php`（7291行・単一ファイル）
- 参考: `resources/views/ARstampRally202605/` 配下各サブファイル

## 現状の投擲メカニズムの整理（重要）

202603 には **2つの投擲経路** が存在する:

1. **IIFE 経路**（現在アクティブ）
   - `#hud-pokeball` ボールをタッチして離す → `throwBall(dx, dy, distance)` → `newBall.setAttribute('pokeball-throwable', '')` → `.throw()`
   - document-level `touchstart / touchend / mousedown / mouseup` で処理

2. **`#throw-button` 経路**（現在 `display: none !important` で非表示）
   - `#throw-button pointerdown/up` → `throwPokeballToCenter(speed)` → `pokeball.setAttribute('pokeball-throwable', '')` → `.throw()`

案C: 両方維持するため、`#throw-button` の `display: none !important` を削除して有効化する。

---

## 変更箇所設計（全6ブロック）

---

### Block 1: ガイド初期非表示

**変更箇所 1-1**: head script block 内フラグ（line 14）
```diff
- window.guideModalOpen = true;
+ window.guideModalOpen = false;
```

**変更箇所 1-2**: DOMContentLoaded 内スタートアップ表示ブロック（line ~3970-3975）
```diff
- // Show guide modal on startup
- const startupGuideModal = document.getElementById('guide-modal');
- if (startupGuideModal) {
-     startupGuideModal.style.display = 'block';
-     startupGuideModal.setAttribute('aria-hidden', 'false');
- }
```
(削除 — 手動で開くボタンは残す)

---

### Block 2: Android Zoom修正（202605方式ポート）

**変更箇所 2-1**: head script block 末尾（contextmenu handler の直前、line ~235 付近）
追加するイベント:
```javascript
document.addEventListener('gesturestart', function(e) { e.preventDefault(); }, { passive: false });
document.addEventListener('gesturechange', function(e) { e.preventDefault(); }, { passive: false });
document.addEventListener('gestureend', function(e) { e.preventDefault(); }, { passive: false });
var _lastTouchEnd = 0;
document.addEventListener('touchend', function(e) {
    var now = Date.now();
    if (now - _lastTouchEnd <= 300) e.preventDefault();
    _lastTouchEnd = now;
}, { passive: false });
document.addEventListener('touchmove', function(e) {
    if (e.touches && e.touches.length > 1) e.preventDefault();
}, { passive: false });
```
※ `contextmenu` は既存のものを維持（重複追加しない）

**変更箇所 2-2**: `<style>` ブロック内 `a-scene` 直下の CSS 追記（line ~1115 付近）
```css
/* ========== Androidカメラズーム防止 ========== */
video {
    object-fit: contain !important;
}
a-scene canvas {
    object-fit: contain !important;
    width: 100% !important;
    height: 100% !important;
}
```

**変更箇所 2-3**: `<a-entity camera>` に look-controls 無効化（scene 内 line ~2260 付近）
```diff
- <a-entity camera="near: 0.2; far: 800;">
+ <a-entity camera="near: 0.2; far: 800;" look-controls="enabled: false">
```

---

### Block 3: UI 左上集約 + カメラ切替削除

#### CSS 削除
- `#stamp-book-button { position: fixed; top: 30px; right: 30px; ... }` 全体（line ~1340-1382）
- `#guide-button { position: fixed; top: 30px; right: 100px; ... }` 全体（line ~1386-1406）
- `#camera-button { position: fixed; bottom: 30px; right: 30px; ... }` 全体（line ~1138-1162）
- `#video-button { position: fixed; bottom: 110px; right: 30px; ... }` 全体（line ~1193-1217）
- `#switch-camera-button { ... }` 全体（line ~1165-1191）
- `#throw-button` の `display: none !important;` 行のみ削除（他のスタイルは維持）

#### CSS 追加（上記削除ルールの後に挿入）
```css
/* ========== 左上ボタン列 ========== */
#top-left-buttons {
    position: fixed; top: 12px; left: 12px;
    display: flex; flex-direction: row; gap: 8px;
    z-index: 1000;
}
#top-left-buttons button {
    width: 56px; height: 56px;
    background-color: rgba(255,255,255,0.9); border: 2px solid #333; border-radius: 50%;
    cursor: pointer; display: flex; justify-content: center; align-items: center;
    font-size: 24px; box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    transition: transform 0.1s, background-color 0.2s;
    position: relative;
}
#top-left-buttons button:active { transform: scale(0.9); background-color: rgba(200,200,200,0.9); }
```
※ `#stamp-book-button .badge`、`#video-button.recording`、関連 `@keyframes` は維持。

#### HTML 変更
削除:
```html
<button id="switch-camera-button" type="button" title="カメラを切り替え">🔄</button>
```

変更（4ボタンをコンテナで囲む）:
```html
<div id="top-left-buttons">
    <button id="stamp-book-button" type="button" title="コレクションを見る">
        <div class="icon">🎁</div>
        <span class="badge">0</span>
    </button>
    <button id="guide-button" type="button" title="操作説明" aria-label="操作説明">
        <div class="icon">❓</div>
    </button>
    <button id="camera-button" type="button" title="写真を撮る">📷</button>
    <button id="video-button" type="button" title="動画を撮る">📹</button>
</div>
```

---

### Block 4: カメラ切替 JS 削除

削除対象:
1. `const switchCameraButton = document.getElementById('switch-camera-button');`（line ~3755）
2. `let currentFacingMode = 'environment';`（line ~3716）※ `switchCameraButton` のみで使用している変数
   → switchCameraButton handler 内でのみ使用なので合わせて削除
3. `switchCameraButton.addEventListener('click', async function(e) { ... });` ブロック（line ~6515-6557）
4. `isUIButton()` 内の switch-camera-button チェック 2行（line 4534, 4541）

---

### Block 5: pokeball-throwable コンポーネント改修

**変更箇所 5-1**: `schema` 追加（line ~349 の AFRAME.registerComponent 直後）
```javascript
schema: {
    autoGetStampId: { type: 'string', default: '' },
    autoGetDelayMs: { type: 'number', default: 1000 }
},
```

**変更箇所 5-2**: `init()` にタイマー変数追加
```javascript
this.autoGetTimer = null;
this.autoGetTriggered = false;
```

**変更箇所 5-3**: `remove()` にタイマークリア追加
```javascript
if (this.autoGetTimer) { clearTimeout(this.autoGetTimer); this.autoGetTimer = null; }
```

**変更箇所 5-4**: `throw()` メソッドにタイマーロジック追加（既存 `this.prevPosition.copy(...)` の直後）
```javascript
if (this.autoGetTimer) { clearTimeout(this.autoGetTimer); this.autoGetTimer = null; }
this.autoGetTriggered = false;
if (this.data.autoGetStampId) {
    var self = this;
    var delay = Math.max(0, parseInt(this.data.autoGetDelayMs, 10) || 1000);
    this.autoGetTimer = setTimeout(function() {
        self.autoGetTimer = null;
        self.tryAutoGet();
    }, delay);
}
```

**変更箇所 5-5**: `tryAutoGet()` メソッドを `throw()` の後に追加
```javascript
tryAutoGet: function() {
    if (this.autoGetTriggered) return;
    this.autoGetTriggered = true;
    var stampId = this.data.autoGetStampId;
    if (!stampId) return;
    try {
        if (typeof collectAndMarkWithRetry === 'function') collectAndMarkWithRetry(stampId, null, 3, 2000);
        var modelId = stampId.replace('model_', 'model-');
        var hitModel = document.getElementById(modelId);
        if (hitModel) {
            try { hitModel.setAttribute('visible', 'false'); } catch(e){}
            try { if (typeof hitModel.setCapturedState === 'function') hitModel.setCapturedState(true); } catch(e){}
        }
        try { if (typeof showCapturedMessage === 'function') showCapturedMessage(stampId); } catch(e){}
    } catch(e){}
},
```

**変更箇所 5-6**: `handleHit()` の先頭にタイマーキャンセル追加
```javascript
if (this.autoGetTimer) { clearTimeout(this.autoGetTimer); this.autoGetTimer = null; }
this.autoGetTriggered = true;
```

---

### Block 6: autoGetStampId を各投擲経路に接続

**変更箇所 6-1**: `getActiveVisibleStampId()` 関数を追加
場所: DOMContentLoaded 内、IIFE の直前（`// ========== 新しいポケボール操作ロジック ==========` の前）
```javascript
function getActiveVisibleStampId() {
    if (!window.allHitboxes || !Array.isArray(window.allHitboxes)) return '';
    for (var i = 0; i < window.allHitboxes.length; i++) {
        var hb = window.allHitboxes[i];
        if (!hb || !hb.el || !hb.el.object3D) continue;
        if (!hb.el.object3D.visible) continue;
        if (hb.el.parentElement && hb.el.parentElement.object3D && !hb.el.parentElement.object3D.visible) continue;
        if (hb.data && hb.data.stampId) return hb.data.stampId;
    }
    return '';
}
```

**変更箇所 6-2**: IIFE 内 `throwBall()` の `newBall.setAttribute('pokeball-throwable', '')` を修正
```diff
- newBall.setAttribute('pokeball-throwable', '');
+ var _autoGetId = getActiveVisibleStampId();
+ var _throwableConf = 'autoGetDelayMs: 1000';
+ if (_autoGetId) _throwableConf += '; autoGetStampId: ' + _autoGetId;
+ newBall.setAttribute('pokeball-throwable', _throwableConf);
```

**変更箇所 6-3**: `throwPokeballToCenter(speed)` の `pokeball.setAttribute('pokeball-throwable', '')` を修正
```diff
- pokeball.setAttribute('pokeball-throwable', '');
+ var _autoGetId = getActiveVisibleStampId();
+ var _throwableConf = 'autoGetDelayMs: 1000';
+ if (_autoGetId) _throwableConf += '; autoGetStampId: ' + _autoGetId;
+ pokeball.setAttribute('pokeball-throwable', _throwableConf);
```

---

## 変更順序（依存関係）
1. Block 1（ガイド非表示）→ 独立
2. Block 2（Zoom修正）→ 独立
3. Block 3（UI CSS + HTML）→ 独立
4. Block 4（camera switch JS）→ Block 3 の HTML 変更後
5. Block 5（pokeball-throwable）→ 独立
6. Block 6（autoGet接続）→ Block 5 完了後

## リスクと対策
| リスク | 対策 |
|---|---|
| `touchend` ダブルタップ防止が既存 IIFE の `touchend` と干渉 | IIFE の `touchend` は `isHoldingBall` フラグチェックが先行するため実質干渉なし |
| `guideModalOpen = false` でカメラ起動フォールバックが変わる | `monitorCameraStartup` は既に `guideModalOpen` 非依存で動作する（12s フォールバックも独立） |
| `tryAutoGet` の stampId が古い hitbox ID と一致しない | 202603 の stampId は `'tomato'` 等の文字列。`document.getElementById(modelId)` で `model-` 変換を適用 |
| 二重 GET | `autoGetTriggered` フラグ + `handleHit` でのタイマーキャンセルで防止 |

## 前段階ファイル読込
前段階のmdファイルを読み込みました（`.claude_workflow/requirements.md`）。

## 設計方針
- 最小変更で「投げたらGET」を実現する。
- 既存の投擲演出（ボール飛行）を維持する。
- 全端末（Android / iPhone / PC）で同一仕様にする。
- マーカー検出中（`activeModel` 存在時）のみ自動GETを有効化する。

## 現状ロジック整理
- 投擲入力: `resources/views/ARstampRally202605/js-throw.blade.php`
  - `touchend` / `mouseup` で方向計算して `throwPokeballInDirection()` 実行
- 命中判定: `resources/views/ARstampRally202605/aframe-components.blade.php`
  - `pokeball-throwable.tick()` で `window.allHitboxes` へレイ判定
- GET確定: `resources/views/ARstampRally202605/js-stamps.blade.php`
  - `collectAndMarkWithRetry(stampId, ...)` が通知・保存・捕獲表示まで一元処理

## 解決案（ユーザー選択用）

### 案A: 投擲直後に `activeModel` を即GET（最小変更・推奨）
- 変更箇所: `js-throw.blade.php` の `touchend` / `mouseup` の末尾
- 方法:
  1. ボール投擲（既存）をそのまま実行
  2. `activeModel` と `currentMarkerStampId` が存在する場合だけ `collectAndMarkWithRetry(currentMarkerStampId, null, 3, 2000)` を呼ぶ
  3. 二重実行防止として短時間クールダウン（例: 500ms）を追加
- メリット:
  - 変更範囲が小さい（1ファイル）
  - 現在の命中判定ロジックを壊さない
  - 失敗時も既存の命中判定が保険として残る
- デメリット:
  - 物理命中前にGETが成立するため、厳密な命中感は弱まる

### 案B: `pokeball-throwable` に自動GETモード追加（中変更）
- 変更箇所: `aframe-components.blade.php`（`throw` 時に対象stampIdを保持し、`tick` 冒頭で即GET）
- メリット:
  - 投擲コンポーネントにロジック集約できる
  - 入力経路（タッチ/マウス）を問わず共通化される
- デメリット:
  - 既存の当たり判定ロジックへ触るためリスク増
  - 影響範囲が広く、最小変更要件にやや反する

### 案C: グローバルフラグ方式（低変更だが保守性低）
- 変更箇所: `js-throw.blade.php` で `window._forceCaptureStampId` を設定し、`aframe-components.blade.php` 側で参照
- メリット:
  - 実装速度が速い
- デメリット:
  - グローバル状態依存が増え、将来不具合の温床になりやすい
  - 推奨しない

## 推奨案
- **案A**（最小変更・低リスク・要件適合）

## 変更対象（案A採用時）
- `resources/views/ARstampRally202605/js-throw.blade.php` のみ

## 実施ステップ（案A）
1. `touchend` の投擲処理末尾に「`activeModel` がある時だけ自動GET」処理を追加
2. `mouseup` の投擲処理末尾にも同等処理を追加
3. 二重GET防止のクールダウン変数を追加（短時間）
4. `php -l resources/views/ARstampRally202605/js-throw.blade.php` を実行

## リスクと対策（案A）
- リスク: 連打時に同一対象へGET処理が複数回走る可能性
  - 対策: クールダウンと既存 `collectStamp` の重複防止に依存
- リスク: マーカー見失い直前の境界タイミング
  - 対策: `activeModel` と `currentMarkerStampId` の両方存在チェック

## 検証観点（案A）
- Android: ズームが残っていても、マーカー検出中に投げれば捕獲できること
- iPhone / PC: 従来の投擲演出が維持され、捕獲処理が壊れないこと
- 重複防止: 同一対象へ連打してもデータ破損しないこと
