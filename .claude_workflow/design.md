# 設計: shooting3Dterrer4 ゲーム性拡張（2026-06-09）

## 前段階ファイル読込
`requirements.md` を読み込みました。

## 変更ファイル
- `resources/views/shooting3Dterrer4/_components.blade.php`
- `resources/views/shooting3Dterrer4/_scene.blade.php`
- `README.md`

## 設計方針
- 既存構造（`start-menu` / `shoot` / `hit-box` / `result-menu`）を維持し、最小変更で機能を追加する。
- 新規状態は `window` 変数へ追加し、既存更新タイミング（開始時、射撃時、撃破時、遷移時）にフックする。

## 変更設計

### 1) ゲーム中の武器切替
- `start-menu` の `switchGun()` から `window.gameStarted` による禁止条件を除去。
- `gripdown/abuttondown/bbuttondown` のハンドラもゲーム中に動作するよう変更。
- 切替後に右コントローラモデルとUI（武器表示・残弾表示）を即時更新。

### 2) Stage2 GameOver 時の VR 解除
- `result-menu.handleGameOver()` は「終了」ではなく VR 解除専用へ変更。
- 既存の `performClose()` は、`scene.exitVR()` 実行後にフェードを戻して画面維持。
- 連打防止フラグは残すが、ページ終了処理（close/blank）は削除。

### 3) ステージ時間変更
- `STAGE_CONFIG.timeLimit` を
  - Stage1: 100
  - Stage2: 80
  に変更。
- ボス出現条件 `gameTimeLeft === 15` は既存維持。
- `_scene` の初期タイマー文言は Stage1 に合わせ `TIME: 100s` に変更。

### 4) 残弾システム
- `window.ammoByGun = { 1: 20, 2: 20 }` を追加。
- 共通関数:
  - `window.updateAmmoDisplay()`
  - `window.tryConsumeAmmo(gunNo)`
  - `window.addAmmoToInactiveGun(amount)`
- `shoot.shoot()` 開始時に `tryConsumeAmmo(selectedGun)` を実行し、失敗なら発射中断。
- 切替可否は残弾に関係なく許可（要求仕様）。

### 5) 残弾 HUD（常時表示）
- `_scene` の `<a-camera>` 配下に HUD を追加し、視線追従で常時視認可能にする。
- アイコンは
  - `public/cg/pokeball_icon05.png`
  - `public/cg/pokeball_icon06.png`
  を `<a-assets><img>` として登録して利用。

### 6) 特定ゾンビ撃破時の補給演出
- `hit-box` の撃破確定時に `gltfSrc` を判定:
  - `#model_s1_01` なら +5
  - `#model_s2_01` なら +10
- 補給ボール演出は撃破位置からカメラ付近へ移動アニメーション。
- `animationcomplete__toCamera` を受けて到達とみなし、未使用側武器へ加算。

## 影響範囲と安全策
- スコア、ボス出現、敵リスポーン、ランキング保存ロジックには手を入れない。
- 新規処理は null チェックを必須化し、DOM未生成時でも落ちないようにする。

## 検証計画
1. `php -l resources/views/shooting3Dterrer4/_components.blade.php`
2. `php -l resources/views/shooting3Dterrer4/_scene.blade.php`
3. 手動確認
   - ゲーム中に Grip/A/B で武器切替
   - Stage1=100秒, Stage2=80秒, ボス残15秒
   - 残弾0で発射不可、切替は可能
   - 01/06 撃破で補給ボール到達後に未使用側へ加算
   - Stage2 GameOver 押下で VR 解除されページは維持

---

# 設計: admin/dashboard202606 景品交換セクションを最上部へ移動（2026-05-31）

## 前段階ファイル読込
`requirements.md` を読み込みました。

## 変更ファイル
- `resources/views/admin/dashboard202606.blade.php` のみ

## 変更概要
`<!-- 【新規追加】景品交換セクション -->` の `prizes-grid` div ブロック全体を、  
ページ末尾からヘッダー `</div>` 直後へ移動する。

## 変更手順
1. `<!-- 【新規追加】景品交換セクション -->` ～ prizes-grid 閉じ `</div>` までを末尾から削除
2. ヘッダー `</div>` の直後に同ブロックを挿入

## 問題点
- なし（HTMLの順序変更のみ）

## 検証方法
- ブラウザで `admin/dashboard202606` を開き、景品交換セクションが先頭に表示されることを目視確認

---

# 設計: admin/dashboard202606 動物別統計の動物名表示修正（2026-05-31）

## 前段階ファイル読込
`requirements.md` を読み込みました。

## 変更ファイル
- `app/Http/Controllers/AdminController.php` のみ

## 変更箇所
`dashboard202606()` メソッド内の `$animals` 配列の**値のみ**を変更する。  
キー名・配列構造・ロジック・Bladeテンプレートは一切変更しない。

## 修正内容
| キー | 現在 | 修正後 |
|---|---|---|
| model_01 | キャラクター01 | シマウマ |
| model_02 | キャラクター02 | シカ |
| model_03 | キャラクター03 | とら |
| model_04 | キャラクター04 | とり |
| model_05 | キャラクター05 | ぶた |
| model_06 | キャラクター06 | ビーバー |
| model_07 | キャラクター07 | レッサーパンダ |
| model_08 | キャラクター08 | きりん |
| model_09 | キャラクター09 | いぬ |
| model_10 | キャラクター10 | リス |
| model_11 | キャラクター11 | あらいぐま |
| model_12 | キャラクター12 | チーター |
| model_13 | キャラクター13 | きつね |
| model_14 | キャラクター14 | パンダ |
| model_15 | キャラクター15 | ぞう |
| model_16 | キャラクター16 | カタツムリ1 |
| model_17 | キャラクター17 | カタツムリ2 |
| model_18 | キャラクター18 | カタツムリ3 |
| model_19 | キャラクター19 | カタツムリ4 |
| model_20 | キャラクター20 | ぶっちー |

## 問題点
- なし（単純な値の置き換えのみ）

## 検証方法
- `php -l app/Http/Controllers/AdminController.php` で構文チェック

---

# 設計: ARstampRally202606 オフライン GLB 表示対応（2026-05-30）

## 前段階ファイル読込
`requirements.md` を読み込みました。

## 変更ファイル
- `resources/views/ARstampRally202606/aframe-components.blade.php` のみ

## 変更箇所
`lazy-model` コンポーネントの `init()` 関数内、既存コードの末尾に1行追加：

```diff
  init: function () {
      this.timer = null;
      this.isLoaded = false;

      this.el.sceneEl.addEventListener('markerFound', function (e) {
          if (e.target === this.el.parentElement) this.onMarkerFound();
      }.bind(this));

      this.el.sceneEl.addEventListener('markerLost', function (e) {
          if (e.target === this.el.parentElement) this.onMarkerLost();
      }.bind(this));
+
+     // バックグラウンド pre-fetch: ブラウザ HTTP キャッシュに GLB を登録
+     // オンラインで一度ページを開くと、以降オフラインでもモデルが表示される
+     if (this.data.src) { fetch(this.data.src, { cache: 'default' }).catch(function () {}); }
  },
```

## 設計判断

### なぜ `fetch(url, { cache: 'default' })` か
- `cache: 'default'` = ブラウザ標準キャッシュ動作。既にキャッシュあれば再 fetch しない
- `cache: 'force-cache'` は古いキャッシュを使い続けるためモデル更新時に困る
- `cache: 'no-cache'` は毎回再検証が入り通信量が増える
- `default` が最適：初回だけ fetch・2回目以降はキャッシュ利用

### なぜ init() か
- `lazy-model` は全マーカーの `<a-entity>` に付与されており、ページロード直後に全エンティティの `init()` が呼ばれる
- つまり Model_00〜Model_20 の 21 本が自動的に並列バックグラウンド fetch される

### 影響範囲
- `onMarkerFound` / `onMarkerLost` の動作に変化なし
- レスポンスボディは `.catch()` だけで捨てるため、余分なメモリ消費なし
- fetch 失敗（オフライン起動時など）は `.catch(function(){})` で無視

---

# 設計: ARstampRally202606 Android パフォーマンス改善（2026-05-28）

## 前段階ファイル読込
`requirements.md` を読み込みました。

## 変更ファイル
- `resources/views/ARstampRally202606/js-gallery.blade.php` のみ

## 変更箇所
`loadNextModel()` 内の以下1行：
```diff
- entity.setAttribute('scale', '0.6 0.6 0.6');
+ entity.setAttribute('scale', '0.48 0.48 0.48');
```

## 影響範囲
- 新規ロード時のみスケールが設定されるため、キャッシュ済みエンティティの再表示（`onMarkerConfirmed`）には影響なし
  → ただしキャッシュ済みエンティティは既にスケールが `0.6` で生成済みのため、**セッション中に初めてロードされる際に `0.48` が適用される**（LocalStorageがリセットされた初回ロード時にも同様）
- `scene.blade.php` の `#model-00`（Model_00.glb）は変更なし
- アニメーション・hitbox・配置位置への影響なし
- ピンチズーム（`setBaseScaleIfMissing`）はギャラリーエンティティには適用されないため影響なし

---

# 設計: ARstampRally202606 新規作成（2026-05-20）

## 前段階ファイル読込
`requirements.md` を読み込みました。

---

## 1. ファイル別設計

### 1-1. ARstampRally202606.blade.php（エントリポイント）
202605と同構造。`@include` でサブファイルを組み込む。
```php
// 変更点: 全 202605 → 202606
// @include('ARstampRally202606.xxx') に変更
```

---

### 1-2. head.blade.php
202605 の head.blade.php をベースにコピーして以下を変更：
- タイトル: `AR Stamp Rally 202606`
- `ar-camera-reload-202605` キー → `ar-camera-reload-202606`
- CSS は変更なし

---

### 1-3. ui.blade.php
202605 の ui.blade.php をベースにコピーして以下を変更：
- `total-slots` の初期値: 10 → 20（JS で上書きされるが念のため）
- 「コイを逃がす」ボタンテキスト → 「キャラクターを逃がす」
- ガイドの景品交換説明: 「6種類以上」 → 「11種類以上」（10+1でOK、実際は10以上）

---

### 1-4. scene.blade.php
202605 との差分：
- CGパス: `cg/202605/` → `cg/202606/`
- maker00: ギャラリー専用マーカー
  - **`Model_00` エンティティをシーンに定義（position="0 0 0"）**
  - `click-animation` + `hitbox` はなし（ギャラリー用のため js-gallery で管理）
  - `lazy-model` でロード
- maker01〜maker20: `@for ($i = 1; $i <= 20)` にループ拡大
  - `click-animation="clip: anime01"` + `hitbox` コンポーネント付き
  - position/scale/rotation は 202605 と同じ

---

### 1-5. aframe-components.blade.php
202605 からの変更点：
- `pokeball-throwable` の `tick()` に **ギャラリーhitboxチェック** を追加
  - `window.allGalleryHitboxes` を別配列で管理
  - ヒット時: `playGalleryHitAnimation(entity)` を呼ぶ（スタンプ取得なし）
- **`gallery-hitbox` コンポーネント** を新規登録（シンプルなBBox当たり判定）
  - `window.allGalleryHitboxes` に自身を登録/除去
  - `entity` プロパティで対象エンティティを参照

**gallery-hitbox スキーマ:**
```javascript
schema: {
    width:  { type: 'number', default: 1 },
    height: { type: 'number', default: 1 },
    depth:  { type: 'number', default: 1 }
}
```

**pokeball-throwable のtick()追加ロジック（ギャラリー用）:**
```
for each ghb in allGalleryHitboxes:
    if ghb.el.object3D.visible AND parentVisible:
        if collision detected:
            playGalleryHitAnimation(ghb.el)
            break
```

**playGalleryHitAnimation(entity):**
1. `_galleryActions.anime01` を stop
2. `_galleryActions.anime02` を reset → play（LoopOnce）
3. mixer.addEventListener('finished') で anime01.reset().play() に戻す
   - タイムアウト保険（clip.duration + 120ms）

- `click-animation` コンポーネント: 変更なし（202605流用）
- `lazy-model` / `hitbox` コンポーネント: 変更なし（202605流用）

---

### 1-6. js-stamps.blade.php
202605 との差分：
```javascript
// STAMPSを20種に拡張
const STAMPS = {
    'model_01': { name: 'キャラクター01', icon: '🐾', model: '202606/Model_01.glb' },
    ...
    'model_20': { name: 'キャラクター20', icon: '🐾', model: '202606/Model_20.glb' }
};
const TOTAL_STAMP_SLOTS = 20;
const GALLERY_MAX_DISPLAY = 4;   // 202605の5→4に変更
const LOCAL_STORAGE_KEY = 'ar-stamp-rally-202606';
const CAPTURED_KEY = 'ar-captured-animals-202606';
const GALLERY_SELECTION_KEY = 'ar-gallery-selection-202606';
```
- `getCapturedAnimals202606()` / `saveCapturedAnimals202606()` に関数名変更
- `recordMarkerScan()` 内の URL は変更なし（共通API流用）

---

### 1-7. js-prize.blade.php
202605 との差分：
```javascript
const PRIZE_EXCHANGE_THRESHOLD = 10;  // 5 → 10
```
- `UserIdDB202606`: dbName/storeName を `202606` サフィックスに
- `CookieHelper202606`: クッキー名を `202606` サフィックスに
- `generateUUID202606()`, `getUserId202606()`: 関数名変更
- `CAPTURED_KEY` は `ar-prize-exchanged-202606`, `ar-prize-code-202606`
- localStorage キーを `202606` サフィックスに変更

---

### 1-8. js-throw.blade.php
変更なし（202605と完全同一でよい）。
※ `getActiveVisibleStampId()` は `allHitboxes` を参照するため、
  ギャラリー内モデルのhitboxは `allGalleryHitboxes` で管理するため競合しない。

---

### 1-9. js-gallery.blade.php
202605 との大幅差分：
```
【202605】
  - ギャラリーモデルを Y軸方向に並べて表示（anime03ループ）
  - Model_00 は捕獲数に応じて anime01/02/03 を切り替え
  - hitbox なし

【202606】
  - Model_00: 常に (0,0,0) Y=0 に固定表示
    - anime01ループ再生
    - gallery-hitbox コンポーネント付き（anime02→anime01）
  - 選択4体: (0,0,1),(0,0,-1),(1,0,0),(-1,0,0) Y=0 に動的配置
    - anime01ループ再生
    - gallery-hitbox コンポーネント付き（anime02→anime01）
```

**ギャラリーエンティティの positions 定義:**
```javascript
var GALLERY_POSITIONS = [
    { x: 0, y: 0, z: 1 },
    { x: 0, y: 0, z: -1 },
    { x: 1, y: 0, z: 0 },
    { x: -1, y: 0, z: 0 }
];
```

**Model_00の扱い:**
- `scene.blade.php` で `id="model-00"` として `lazy-model` で定義
- js-gallery.blade.php で `model-loaded` イベントを受けてanime01再生 + gallery-hitbox付与
- markerFound/Lost で visible 切替

**選択4体モデルの逐次ロード:**
- 202605のキャッシュ＋逐次ロード方式を継承
- `LOAD_INTERVAL_MS = 500ms` で1体ずつロード
- ロード後に `gallery-hitbox` コンポーネントを付与

**アニメーション管理:**
- 各ギャラリーエンティティに `_galleryMixer`, `_galleryAction01`, `_galleryAction02` を保持
- `model-loaded` 時に anime01/anime02 を準備して anime01 を再生
- `playGalleryHitAnimation(entity)` を `window.playGalleryHitAnimation` として公開

**galleryMixers の更新:**
- 202605と同じく `window.galleryMixers` + `startGalleryMixerLoop/stopGalleryMixerLoop` で管理

---

### 1-10. js-camera.blade.php
変更なし（202605と完全同一）。

---

### 1-11. js-init.blade.php
202605 との差分：
```javascript
// marker イベント登録ループ
@for ($i = 1; $i <= 20; $i++)  // 10 → 20

// リセット処理
for (var i = 1; i <= 20; i++) { ... }  // 10 → 20

// LocalStorage クリア
localStorage.removeItem('ar-stamp-rally-202606');
localStorage.removeItem('ar-captured-animals-202606');

// ガイドテキスト（景品交換）
'10種類以上のキャラクターを捕まえると景品と交換できます。'
```

---

## 2. 管理ダッシュボード設計

### AdminController.php
`dashboard202606()` メソッドを追加：
- 期間: `2026-05-20 00:00:00` 〜 `2026-06-10 23:59:59` (JST→UTC)
- `$animals`: model_01〜model_20 の20種
- 202605の `dashboard202605()` を参考にほぼ同一実装

### dashboard202606.blade.php
202605の `dashboard202605.blade.php` をベースにコピーして以下を変更：
- タイトル: `管理ダッシュボード202606`
- 「ARスタンプラリー202606」に変更
- 動物リスト部分: 20種に変更

### routes/web.php への追加
```php
// public route
Route::match(['get', 'head'], '/stamp202606', function () {
    return view('ARstampRally202606');
})->name('stamp202606.index');

// admin middleware group 内
Route::get('/dashboard202606', [AdminController::class, 'dashboard202606'])
    ->name('admin.dashboard202606');
```

---

## 3. アーキテクチャ上の重要判断

### gallery-hitbox vs hitbox の分離理由
- `hitbox` コンポーネントは `window.allHitboxes` に登録 → `pokeball-throwable` の `handleHit` でスタンプ取得が発動
- ギャラリーモデルでスタンプ取得が発動すると不整合（既に捕獲済みのため）
- → `gallery-hitbox` コンポーネントは `window.allGalleryHitboxes` に登録し、`handleHit` と分離
- `pokeball-throwable.tick()` でギャラリー hitbox を別ループでチェック

### Model_00 のscene.blade.php内定義
- 202605 では `model-00` エンティティが `scene.blade.php` に定義済み
- 202606 でも同様に `<a-entity id="model-00" lazy-model="..." position="0 0 0">` として定義
- js-gallery.blade.php から `document.getElementById('model-00')` で参照

---

## 4. 成功基準
1. `/stamp202606` でAR正常起動
2. maker01〜maker20 でモデルが表示され、ボールヒットでスタンプ取得
3. maker00 でギャラリー表示（Model_00固定 + 選択4体）
4. ギャラリーモデルにボールをヒットしてもスタンプ取得なし（anime02→anime01のみ）
5. 10匹以上で景品交換ボタン有効化
6. `/admin/dashboard202606` で統計データ表示
7. `php -l` でPHPファイルの構文エラーなし

---

# 旧設計: /stamp202605 投擲時自動GET化（ズーム継続時の運用回避）

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

---

# 設計: shooting3Dterrer4 VRシューティングゲーム 2ステージ構成（2026-06-09）

## ファイル構成

```
resources/views/shooting3Dterrer4/
├── index.blade.php        (~60行)  エントリーポイント
├── _components.blade.php  (~900行) A-Frameコンポーネント定義（JS）
└── _scene.blade.php       (~700行) a-scene内HTMLエンティティ
```

ルート追加:
```
routes/web.php: /terrer4 → shooting3Dterrer4.index
```

---

## グローバル状態変数（window.*）

### 追加変数（terrer3にない）
```js
window.currentStage    // 現在のステージ番号（1 or 2）
window.selectedGun     // 選択中の武器（1 or 2）
window.stageTotalScore // ステージ別スコア（{1: 0, 2: 0}）
```

### 変更変数
```js
window.gameTimeLeft    // ステージ1: 90秒、ステージ2: 90秒
```

### 削除変数
```js
window.gameLevel       // Level選択なし → 削除
window.currentLevel    // Level概念なし → currentStageに置換
```

---

## ステージ設定テーブル（STAGE_CONFIG）

```js
window.STAGE_CONFIG = {
    1: {
        timeLimit: 90,
        bgm: 'sound_bgm_s1',         // #sound_bgm_s1 → sound_bgm08.mp3
        sky: '#sky_s1',               // R0010143a.JPG
        models: [                     // 通常ゾンビ5体
            { id: 'modelGroup_01', gltf: '#model_s1_01' },
            { id: 'modelGroup_02', gltf: '#model_s1_02' },
            { id: 'modelGroup_03', gltf: '#model_s1_03' },
            { id: 'modelGroup_04', gltf: '#model_s1_04' },
            { id: 'modelGroup_05', gltf: '#model_s1_05' },
        ],
        bossModel: '#model_boss_s1',  // zombie_morishige4.glb
        requiredHits: 1,              // 通常: 1ヒット
        bossRequiredHits: 15,
        gameMode: 'terrer4_s1',
        resultTitle: 'STAGE 1 CLEAR',
        nextButton: { label: 'Next Stage', color: '#00FF00', action: 'next_stage' }
    },
    2: {
        timeLimit: 90,
        bgm: 'sound_bgm_s2',         // #sound_bgm_s2 → sound_bgm06.mp3
        sky: '#sky_s2',               // R0010131a.JPG
        models: [
            { id: 'modelGroup_01', gltf: '#model_s2_01' },
            { id: 'modelGroup_02', gltf: '#model_s2_02' },
            { id: 'modelGroup_03', gltf: '#model_s2_03' },
            { id: 'modelGroup_04', gltf: '#model_s2_04' },
            { id: 'modelGroup_05', gltf: '#model_s2_05' },
        ],
        bossModel: '#model_boss_s2',  // zombie_fujii.glb
        requiredHits: 2,              // 通常: 2ヒット
        bossRequiredHits: 15,
        gameMode: 'terrer4_s2',
        resultTitle: 'GAME OVER',
        nextButton: { label: 'Game Over', color: '#FF0000', action: 'game_over' }
    }
};
```

---

## 武器設定テーブル（GUN_CONFIG）

```js
window.GUN_CONFIG = {
    1: { model: 'cg/gun_01.glb', ball: 'cg/poke_ball_05.glb' },
    2: { model: 'cg/gun_02.glb', ball: 'cg/poke_ball_seieiv.glb' }
};
```

---

## コンポーネント設計

### start-menu コンポーネント（変更）

#### init()
- gun切り替えイベントリスナー設定（VRコントローラー: gripdown/abuttondown/bbuttondown）
- 非VRモード: メニュー内のweaponDisplay要素クリックで切り替え

#### switchGun()
- window.selectedGun をトグル（1↔2）
- 右コントローラーのgltf-modelを更新
- メニューのWEAPON表示テキストを更新

#### startGame()
- Level選択分岐を削除
- currentStage=1固定で開始
- モデルID: modelGroup_01〜05（5体のみ）
- 必要ヒット数を STAGE_CONFIG[1].requiredHits から読む

#### startTimer()
- ステージ終了後: showResult() → ステージ別resultMenuを表示

#### spawnBoss()
- bossModelを STAGE_CONFIG[window.currentStage].bossModel から取得

### result-menu コンポーネント（変更）

#### init()
- nextStageButton と gameOverButton 両方にイベント設定
- ステージに応じてどちらを表示するかはHTML側で管理

#### handleNextStage()
- フェードアウト演出（黒overlay, 1秒）
- ステージ2初期化
- 既存モデル全削除
- 背景・BGM切り替え
- ステージ2モデル作成
- フェードイン演出（1秒）
- gameStarted=true でゲーム開始（スタートメニューなし）

#### handleGameOver()
- フェードアウト演出（黒overlay, 1秒）
- 5秒待機
- window.close() 試行
- 失敗時: 「このタブを閉じてください」テキスト表示

### shoot コンポーネント（変更）

#### shoot()
- `ball.setAttribute('gltf-model', window.GUN_CONFIG[window.selectedGun].ball)`

#### updateBallPosition()
- modelsList を5体（modelGroup_01〜05 + boss）に変更

### hit-box コンポーネント（変更）

#### 必要ヒット数判定
```js
// terrer3: const requiredHits = isBoss ? 15 : (window.currentLevel === 2 ? 2 : 1);
// terrer4:
const stageConfig = window.STAGE_CONFIG[window.currentStage];
const requiredHits = isBoss ? stageConfig.bossRequiredHits : stageConfig.requiredHits;
```

#### saveScoreToDatabase() → result-menuに移動
- game_mode を STAGE_CONFIG[window.currentStage].gameMode から読む

#### fetchAndDisplayRankings()
- game_mode を STAGE_CONFIG[window.currentStage].gameMode から読む

### approach-camera コンポーネント（変更なし）
- そのまま流用

---

## ステージ遷移フロー詳細

### ステージ1 → ステージ2 遷移

```
1. Next Stageボタンクリック
   ↓
2. 黒overlay（a-plane）をフェードイン（opacity 0→1, 1秒）
   ↓
3. BGMフェードアウト・停止
   ↓
4. 全モデル削除（allModels=01〜05+boss）
   ↓
5. currentStage = 2
6. gameStarted = false / gameEnded = false / スコアリセット
   ↓
7. 背景切り替え: a-sky の src を #sky_s2 に変更
8. BGM切り替え: sound_bgm_s2 を再生
   ↓
9. ステージ2モデルを recreateInitialModels() で作成（STAGE_CONFIG[2]のgltf使用）
   ↓
10. 黒overlay フェードアウト（opacity 1→0, 1秒）
    ↓
11. gameStarted = true（直接ゲーム開始、スタートメニューなし）
12. startTimer() 呼び出し
```

### ステージ2終了後 → Game Over

```
1. Game Overボタンクリック
   ↓
2. 黒overlay フェードイン（1秒）
   ↓
3. 5秒待機
   ↓
4. window.close() 試行
   ↓ (失敗時)
5. 「このタブを閉じてください」テキスト表示
```

---

## プリロード設計

### a-assets宣言（_scene.blade.php）

```html
<a-assets>
  <!-- ステージ1 ゾンビ -->
  <a-asset-item id="model_s1_01" src="{{ asset('cg/20260613/01.glb') }}"></a-asset-item>
  ...（s1: 01〜05, boss_s1: zombie_morishige4.glb）
  
  <!-- ステージ2 ゾンビ（preload属性付き: 全アセットを初期にロード） -->
  <a-asset-item id="model_s2_01" src="{{ asset('cg/20260613/06.glb') }}"></a-asset-item>
  ...（s2: 06〜10, boss_s2: zombie_fujii.glb）
  
  <!-- 武器・弾 -->
  <a-asset-item id="gun_model_1" src="{{ asset('cg/gun_01.glb') }}"></a-asset-item>
  <a-asset-item id="gun_model_2" src="{{ asset('cg/gun_02.glb') }}"></a-asset-item>
  
  <!-- サウンド（全ステージ分） -->
  <audio id="sound_bgm_s1" src="{{ asset('cg/sound_bgm08.mp3') }}" preload="auto"></audio>
  <audio id="sound_bgm_s2" src="{{ asset('cg/sound_bgm06.mp3') }}" preload="auto"></audio>
  
  <!-- 背景（全ステージ分） -->
  <img id="sky_s1" src="{{ asset('cg/R0010143a.JPG') }}" crossorigin="anonymous">
  <img id="sky_s2" src="{{ asset('cg/R0010131a.JPG') }}" crossorigin="anonymous">
</a-assets>
```

→ 全アセットをページロード時に一括取得。遷移時にロード待ちが発生しない。

---

## HTML構成（_scene.blade.php）

```
a-scene
  a-assets
    (全アセット)
  
  <!-- ライト -->
  <!-- カーソル・コントローラー（右コントローラーはgun_01初期） -->
  
  <!-- スタートメニュー（ステージ1開始時のみ） -->
  #startMenu
    タイトル: "seieiVR SHOOTING GAME"
    サブタイトル: "Stage 1"
    武器表示: "WEAPON: Gun 1"
    切替案内: "Grip/A/B: Switch Weapon"
    STARTボタン（水色）
  
  <!-- タイマー・スコア（ゲーム中） -->
  #timerDisplay
  
  <!-- リザルト画面ステージ1 -->
  #resultMenu_s1 (visible=false)
    "STAGE 1 CLEAR"
    スコア / レベル / コンボ / コメント
    ランキング表示エリア #rankingDisplay_s1
    Next Stageボタン（緑） #nextStageButton
  
  <!-- リザルト画面ステージ2 -->
  #resultMenu_s2 (visible=false)
    "GAME OVER"
    スコア / レベル / コンボ / コメント
    ランキング表示エリア #rankingDisplay_s2
    Game Overボタン（赤） #gameOverButton
  
  <!-- 黒フェードoverlay（ステージ遷移用） -->
  #fadeOverlay (visible=false)
    a-plane（黒、全画面を覆う位置）
  
  <!-- ゾンビモデル × 5 (初期非表示) -->
  #modelGroup_01 〜 #modelGroup_05
  
  <!-- 背景 -->
  #aSky (src="#sky_s1" 初期)
  
  <!-- パーティクル -->
  <!-- カメラ + shoot component -->
  #my_camera
```

---

## 黒フェードoverlay設計

```html
<!-- カメラの子要素として配置（カメラと共に動く） -->
<a-camera id="my_camera" shoot>
  <a-entity id="fadeOverlay" visible="false">
    <a-plane
      width="100" height="100"
      position="0 0 -0.5"
      color="#000000"
      opacity="0"
      material="transparent: true; depthTest: false; side: double">
    </a-plane>
  </a-entity>
</a-camera>
```

→ カメラの子要素にすることで常に視野内に表示される

---

## 武器切り替え設計

### VRモード（ゲーム開始前のみ有効）
```
左コントローラー: gripdown → switchGun()
右コントローラー: gripdown → switchGun()
左コントローラー: abuttondown / bbuttondown → switchGun()
```

### 非VRモード（ゲーム開始前のみ有効）
```
#weaponDisplay (a-plane, clickable) クリック → switchGun()
```

### switchGun() 処理
```js
window.selectedGun = (window.selectedGun === 1) ? 2 : 1;
// 右コントローラーのモデルを差し替え
const rightController = document.getElementById('rightController');
const gunEntity = rightController.querySelector('#controllerGunModel');
gunEntity.setAttribute('gltf-model', window.GUN_CONFIG[window.selectedGun].model);
// メニュー表示更新
const weaponText = document.getElementById('weaponText');
weaponText.setAttribute('value', `WEAPON: Gun ${window.selectedGun}`);
```

---

## ルーティング

```php
// routes/web.php 追加箇所（terrer3ルートの直後）
Route::match(['get', 'head'], '/terrer4', function () {
    return view('shooting3Dterrer4.index');
})->name('terrer4.index');
```

---

## 行数見積もり

| ファイル | 見積もり行数 |
|------|------|
| index.blade.php | ~60行 |
| _components.blade.php | ~900行 |
| _scene.blade.php | ~700行 |
| 合計 | ~1660行 |

（各ファイルが1000行以内であることを確認）

---

## 注意事項・リスク

1. **GLBアニメーション名**: 20260613/01〜10.glbのアニメーション名が `anime01` / `anime02` であることを前提としている。実際にアニメーションが存在しない場合は animation-mixer をスキップ。
2. **window.close()制限**: ユーザーが直接URLを入力して開いた場合はwindow.close()が失敗するブラウザあり。メッセージ表示でフォールバック。
3. **respawnModelGlobal**: ステージ2ではモデルが差し替わるため、respawnModelGlobalを毎ステージ再登録する。
4. **shootコンポーネントのキャッシュ**: ステージ遷移時にmodelsList / modelsCache / hitBoxCacheをクリアして再構築。
5. **ボスモデルID**: ステージ共通の `modelGroup_boss` を使用。ステージ遷移時に削除・再生成。

