# 設計 — ARstampRally202609 問題点修正

> 作成日: 2026-09-02
> 前提: `.claude_workflow/ar_requirements.md`（要件定義、スコープ確定済み）
> 方針: CLAUDE.md 準拠 — 変更を最小限に・既存挙動を保存・段階的に進める

---

## 1. 全体アプローチ

2つの分析レポートの推奨（qwen3.8 §6 の Fix-1/2/3、claude_sonnet5 §2.3・§4）を統合した4つの設計軸:

| 軸 | 内容 | 対応要件 |
|----|------|----------|
| ① 射影パラメータを実態に同期 | `arjs-video-loaded`（動画読み込み完了）時に **全方向** で `source=動画実寸 / display=実画面` に再設定し、`resize` / `orientationchange` でも追従 | 主因A、claude P0、要件#1#2 |
| ② 初期値の統一・Samsungパッチ削除 | UA分岐を廃止し全端末 `1280×720` 暫定値に（低解像度 `ideal` 制約を外す＝HALクロップ対策）。Samsung上書きブロックを削除（①が代替） | 主因A、claude P0、要件#3 |
| ③ 二重スケール解消 | `applyCurrentScaleTo` はルートのスケール1回分のみ。`traverse` は `frustumCulled=false` の設定に限定 | 主因B、要件#4 |
| ④ 補助修正 | 低解像度リトライ時の display 更新（主因C）／MediaRecorder feature detection／交換ボタンの二重送信ガード／低スペック判定の精緻化（`AR_FORCE_LOWRES` を検出レート制御に実効化）／CSSコメント修正 | 主因C、claude P1/P2、要件#5-#9 |

**変更しないもの**: 3Dモデル・マーカーアセット（`public/cg/202606/`）、202606 のファイル、`aframe-components.blade.php`、`js-gallery.blade.php`、`js-throw.blade.php`、`js-stamps.blade.php`、`ui.blade.php`、サーバー側（Controller/routes）、バンドル配信設定、待機時間の基準（7s/10s）。

## 2. ファイル別設計（変更前 → 変更後）

### 2.1 `scene.blade.php`

**(a) L1-7 UA分岐 → 全端末 1280×720 暫定値**

変更前:
```php
@php
    $_arjsIsAndroid = stripos(request()->header('User-Agent', ''), 'android') !== false;
    $_srcW = $_arjsIsAndroid ? 640 : 1280;
    $_srcH = $_arjsIsAndroid ? 480 : 720;
    $_dispW = $_arjsIsAndroid ? 640 : 1280;
    $_dispH = $_arjsIsAndroid ? 480 : 720;
@endphp
```
変更後:
```php
@php
    // AR.js 射影パラメータの初期値（暫定値・16:9）。
    // 動画読み込み完了後、js-init.blade.php の syncArjsToRealSize() が
    // source=動画実寸 / display=実画面 に縦横問わず同期するため、
    // UAによる解像度出し分けは行わない（低解像度 ideal 制約による
    // Android HAL のデジタルクロップ回避）。
    $_srcW = 1280;
    $_srcH = 720;
    $_dispW = 1280;
    $_dispH = 720;
@endphp
```
※ L11 の `arjs` 属性の書式は不変（`$_srcW` 等をそのまま参照）。

**(b) L73-92 Samsung 縦型上書きブロック（末尾 `<script>` 全体）を削除**
- 理由: ①の全方向同期が代替。1280×720 要求に切り替わったため、4:3 の 480×640 要求による縦型ストリーム返却の前提自体がなくなる。
- リスク: 読み込み完了前の短い窓で Samsung 縦型が認識しにくい可能性（①は旧来と同じ `arjs-video-loaded` 経路で即時同期するため、従来の縦型対策と同等のタイミングで解決）。実機検証項目として tasks に残す。

### 2.2 `js-init.blade.php`

**(a) 主因B: `applyCurrentScaleTo`（L31-56）— メッシュスケール設定を削除**

変更後（構造・ガードは既存の防御的スタイルを維持）:
```js
function applyCurrentScaleTo(el) {
    if (!el) return;
    try {
        var base = parseFloat(el.dataset.baseScale || 1);
        var clamped = Math.max(1, Math.min(3, currentScale));
        var v = base * clamped;
        el.setAttribute('scale', v + ' ' + v + ' ' + v);   // スケールはルートの1回分のみ
        try {
            // メッシュへは frustumCulled=false の設定のみ（scale.set は二重スケール(base²)の原因のため削除）
            if (el.object3D && el.object3D.children) {
                el.object3D.traverse(function (node) {
                    if (node.isMesh) node.frustumCulled = false;
                });
            }
        } catch (err) {
            console.debug('applyCurrentScaleTo: three.js traversal failed', err);
        }
    } catch (e) {
        console.warn('applyCurrentScaleTo failed', el, e);
    }
}
```
※ 呼出箇所 L353 / L378・`setBaseScaleIfMissing`・`resetActiveModel` は不変。

**(b) 主因A: `syncArjsToRealSize()` を新設し、`arjs-video-loaded`（L585-606）で全方向に呼出**

`if (scene)` ブロック内（`hideArjsLoader` 定義の直後）に新設:
```js
// 検出レートの確定: 現行値を維持しつつ、低スペックモードでは最大8
function desiredMaxDetectionRate() {
    var base = 30;
    try {
        var a = scene.getAttribute && scene.getAttribute('arjs');
        if (a && typeof a.maxDetectionRate === 'number' && a.maxDetectionRate > 0) base = a.maxDetectionRate;
    } catch (e) {}
    if (window.AR_FORCE_LOWRES) base = Math.min(base, 8);
    return base;
}

// AR.js 射影パラメータを実態に同期（source=動画実寸 / display=実画面・縦横問わず）
function syncArjsToRealSize() {
    try {
        var vid = document.querySelector('video');
        if (!vid) return;
        var sw = vid.videoWidth || 0;
        var sh = vid.videoHeight || 0;
        if (sw < 2 || sh < 2) return;   // 動画が確定する前は同期しない
        var dw = window.innerWidth || sw;
        var dh = window.innerHeight || sh;
        scene.setAttribute('arjs',
            'sourceWidth: ' + sw + '; sourceHeight: ' + sh + ';' +
            ' displayWidth: ' + dw + '; displayHeight: ' + dh + ';' +
            ' trackingMethod: best; sourceType: webcam; debugUIEnabled: false;' +
            ' detectionMode: mono; maxDetectionRate: ' + desiredMaxDetectionRate() + ';'
        );
        console.warn('[AR202609] arjs synced (source ' + sw + 'x' + sh + ' / display ' + dw + 'x' + dh + ' / rate ' + desiredMaxDetectionRate() + ').');
    } catch (e) {}
}
```

`arjs-video-loaded` ハンドラ変更: 既存の `hideArjsLoader()` / helpモーダル閉じは維持し、**縦型限定ブロック（L591-604）を `syncArjsToRealSize()` の呼出に置換**。

追加（同ブロック内、`arjs-video-loaded` リスナの直後）:
```js
// 画面回転・ウィンドウリサイズで実表示が変化したら再同期（デバウンス）
var _syncArjsTimer = null;
window.addEventListener('resize', function () {
    clearTimeout(_syncArjsTimer);
    _syncArjsTimer = setTimeout(syncArjsToRealSize, 200);
});
window.addEventListener('orientationchange', function () {
    setTimeout(syncArjsToRealSize, 300);
});
```

**(c) 主因C: 低解像度リトライ（L523-535）— `display` を実画面に更新**

変更後（`setAttribute('arjs', ...)` 行のみ修正、他は不変）:
```js
if (scene) {
    var dw = window.innerWidth || 320;
    var dh = window.innerHeight || 240;
    scene.setAttribute('arjs', 'sourceType: webcam; debugUIEnabled: false; sourceWidth: 320; sourceHeight: 240; displayWidth: ' + dw + '; displayHeight: ' + dh + '; detectionMode: mono; maxDetectionRate: 8;');
    scene.setAttribute('renderer', 'antialias: false; alpha: true; precision: lowp;');
}
```

### 2.3 `head.blade.php`

**(a) CSSコメント修正（L172-173）— 実態に合わせる（見た目の `object-fit: cover` 自体は維持）**
```css
/* ========== レターボックス防止（見た目専用） ========== */
/* 注: AR.js の射影(sourceWidth/displayWidth等)は js-init.blade.php の syncArjsToRealSize() により実寸に同期される */
```

**(b) 低スペック判定の精緻化（L31-35）— `AR_FORCE_LOWRES` に Android のコア数/メモリを加味**
```js
window.AR_FORCE_LOWRES = (function() {
    const url = new URL(window.location.href);
    if (url.searchParams.get('lowres') === '1') return true;
    if (detectOldAndroid()) return true;
    // 低スペックAndroidの精緻化: 少数コアまたは小メモリなら低検出レートモード
    // (navigator が非対応(0/undefined)の場合は判定しない=従来挙動を維持)
    try {
        if (/Android/i.test(navigator.userAgent || '')) {
            const cores = navigator.hardwareConcurrency || 0;
            const mem   = navigator.deviceMemory || 0;
            if ((cores > 0 && cores <= 2) || (mem > 0 && mem <= 2)) return true;
        }
    } catch (e) {}
    return false;
})();
```
※ 202609 ではこのフラグの読み取り箇所が存在しない（単体版 `ARstampRally.blade.php` L4474 の初期適用ロジックはスプリット時に脱落）ため、**js-init の `desiredMaxDetectionRate()` が `AR_FORCE_LOWRES` を `maxDetectionRate`（30→8）に反映する形で実効化する**（claude §3.1 の性能指摘に対応）。カメラ解像度の自動低減は行わない（低解像度リトライボタンの手動経路を維持）。

### 2.4 `js-camera.blade.php` — MediaRecorder feature detection

IIFE 内（`videoButton` 取得直後）に追加:
```js
// MediaRecorder 未実装環境（iOS 14.2以前等）では動画ボタンを非表示にする
if (typeof MediaRecorder === 'undefined' && videoButton) {
    videoButton.style.display = 'none';
}
```
※ 動画撮影本体（`startRecording`）の try/catch 構造は不変。

### 2.5 `js-prize.blade.php` — 景品交換の二重送信防止

`exchangePrize()`（L178-214）にフラッグガードを追加（ボタンの disabled 操作は行わず `updatePrizeButton()` との状態衝突を回避 — ボタン状態の管理は既存のまま）:
- `var _exchanging = false;` を `exchangePrize` 定義の直前に追加
- 関数先頭: `if (_exchanging) return;`
- csrf チェック通過後（`checkPrizeExchangeStatus()` 呼出直前）: `_exchanging = true;`
- **全終端パスで `_exchanging = false;` を復元**:
  1. `isRedeemed` → `showRedeemedPrizeInfo(...)` 後
  2. `hasExchanged` → `showPrizeCode(...)` 後
  3. fetch の `data` 処理 if/else 後（成功・失敗両方）
  4. 最外层 `.catch(function () { alert('通信エラーが発生しました'); _exchanging = false; })`

### 2.6 `README.md` — 機能ドキュメント追記（CLAUDE.md ルール6）

- 202609 の修正内容（射影同期・スケール修正・feature detection 等）を既存のキャンペーン記述（L304-353 周辺）の流れに沿って追記

## 3. 依存関係と実行順序

1. `js-init.blade.php`（2.2a→2.2b→2.2c: 独立した3箇所を1タスクとして実施）
2. `scene.blade.php`（2.1: 2.2b の全方向同期が導入済みであることを前提に Samsung ブロック削除）
3. `head.blade.php`（2.3: desiredMaxDetectionRate が存在することを確認済み）
4. `js-camera.blade.php`（2.4）
5. `js-prize.blade.php`（2.5）
6. `README.md`（2.6）
7. 検証（`php -l` × 全変更ファイル、静的grep確認、動作確認）

## 4. エッジケース・リスクと対策

| # | リスク | 対策 |
|---|--------|------|
| R1 | 動画読み込み前の短い窓で暫定値(16:9)と実ストリームが乖離し Samsung 縦型で認識しにくい（パッチ削除の副作用） | ①は旧来の縦型対策と同一の `arjs-video-loaded` 経路で即時同期する（旧パッチは「初期値が縦型」だったのに対し、今は「読み込み後に全方向同期」）。tasks の実機検証項目に明記 |
| R2 | `setAttribute('arjs', ...)` が A-Frame 初期化前で効かない端末 | 既存コードが同パターン（旧 L597、低解像度リトライ L530）で運用実績あり。`hideArjsLoader` / 3秒フォールバックも維持 |
| R3 | `scene.getAttribute('arjs').maxDetectionRate` が undefined（schema未パース） | try/catch + 既定 30 で安全側。低スペック時は `Math.min(base, 8)` で常に8以下 |
| R4 | 低解像度リトライ後の `arjs-video-loaded` 再発火でレート/解像度が元に戻る | `desiredMaxDetectionRate()` が現行値を基準に `min(…, 8)` を維持。source は「実ストリーム実寸」に戻るため射影は整合したまま（意図どおり） |
| R5 | `_exchanging` が例外で復元されず交換が事実上無効化される | 全4終端パスで復元（Promise チェーンの then/catch が必ず片方を通る）。alert 後に直ちに復元する順序 |
| R6 | `navigator.hardwareConcurrency` / `deviceMemory` 非対応（旧Safari等）で 0 になり誤検出 | `> 0` ガードで非対応時は判定しない（従来挙動維持） |
| R7 | 202606（旧キャンペーン）への変更混入 | diff 対象を 202609 のファイル+README に限定。編集前に各ファイル現行内容を再読取 |

## 5. 検証計画

1. **静的検証**: 変更した全 blade ファイルで `php -l`（警告0件）。`grep` で「202609 配下に samsung/SM- 判定がない」「`node.scale.set` が `applyCurrentScaleTo` に残っていない」「`vid.videoWidth < vid.videoHeight` の縦型限定ガードが置換されている」ことを確認。
2. **動作確認（可能範囲）**: PC/WebView でページ起動→カメラ起動→`syncArjsToRealSize` の console 出力（source/display が実寸になるか）→マーカー認識時のモデルスケール（`base²` でないか）→wheel ズーム2倍でモデルも2倍か→低解像度リトライ経路→交換ボタンの連打で `/api/exchange-prize` が1回のみ。
3. **実機チェックリスト（開発者実行）**: Android（縦長/横長）でのカメラ比率・モデル位置、iOS 回帰、低スペック機（または `?lowres=1`）での検出レート、`MediaRecorder` 非対応環境で動画ボタン非表示。

---

# 設計 — ARstampRally202609 景品交換APIのサーバー側強化（202609専用エンドポイント）

> 作成日: 2026-09-08
> 前提: `.claude_workflow/ar_requirements.md` §7-11（P0-1/2/3、スコープ確定済み）
> 方針: 共有API（`/api/...`）は 202605 / 202606 が利用中のため触らず、**202609専用の新コントローラー+新ルート**を `web` グループに新設する

## 全体アプローチ

P0 の3件を「202609専用エンドポイント」に分離して解消。他キャンペーンの閾値差（202605=5 / 202606=10 / 202609=10）を壊さないため、共有コントローラーは修正しない。

| 軸 | 内容 | 対応 |
|----|------|------|
| ① サーバー側閾値強制 | 新コントローラー `exchange()` で `count(stamps) >= 10` を検証し、未満は 422 | P0-1 |
| ② CSRF + セッション | 3ルートを `routes/web.php`（`web`グループ）に配置。フィンガープリントをセッションに保持 | P0-2 |
| ③ レート制限 | `AppServiceProvider::boot()` で `stamp202609_scan/check/redeem` の命名リミッター3種を定義し各ルートに適用 | P0-3 |
| ④ クライアント差し替え | `js-prize.blade.php` の3 fetch を新URLへ変更（CSRFヘッダ維持） | 結合 |

## ファイル別設計

### 新設 `app/Http/Controllers/StampRally202609Controller.php`
- `recordScan()`: 既存 `MarkerScanController::record` と同ロジック＋ `session(['ar_fingerprint' => ...])` でセッションに紐付け
- `checkStatus()`: `session('ar_fingerprint') ?: $request->input('fingerprint')` で参照（既存の `orWhere` 挙動を維持）
- `exchange()`: バリデーション＋ `PRIZE_EXCHANGE_THRESHOLD = 10` の件数検証（未満は 422）→ 既存 `PrizeExchangeController::exchange()` と同じコード生成/保存ロジック

### `routes/web.php`
```php
Route::post('/stamp202609/record-scan', [StampRally202609Controller::class, 'recordScan'])->middleware('throttle:stamp202609_scan');
Route::post('/stamp202609/check-prize', [StampRally202609Controller::class, 'checkStatus'])->middleware('throttle:stamp202609_check');
Route::post('/stamp202609/exchange-prize', [StampRally202609Controller::class, 'exchange'])->middleware('throttle:stamp202609_redeem');
```

### `app/Providers/AppServiceProvider.php`（`boot()`）
```php
$key = fn (Request $r) => $r->session()->get('ar_fingerprint') ?: $r->ip();
RateLimiter::for('stamp202609_scan',   fn ($r) => Limit::perMinute(120)->by($key($r)));
RateLimiter::for('stamp202609_check',  fn ($r) => Limit::perMinute(120)->by($key($r)));
RateLimiter::for('stamp202609_redeem', fn ($r) => Limit::perHour(10)->by($key($r)));
```
※ 交換は1時間に10回まで（閾値10と整合。意図的な連打・Bot 防御）。キーはフィンガープリント優先→IPフォールバック。

### `resources/views/ARstampRally202609/js-prize.blade.php`
- 3 fetch の URL を `/stamp202609/...` へ変更（L149 / L170 / L203）
- `X-CSRF-TOKEN` ヘッダは維持（`web` グループで検証される）

## エッジケース・リスク

| # | リスク | 対策 |
|---|--------|------|
| S1 | 202605 / 202606 への影響 | 共有API・共有コントローラー・他キャンペーンの `js-prize` を一切変更しない |
| S2 | セッション未設定での `checkStatus` / `exchange` | `session('ar_fingerprint') ?: $request->input('fingerprint')` でフォールバック（既存挙動を維持） |
| S3 | レートリミッターのキー | フィンガープリント優先 → IPフォールバック（`recordScan` でセッションに保持済みの値を参照） |
| S4 | 202609 の既存 UI 挙動 | 新エンドポイントは応答形状を既存と同一に（`success` / `prizeCode` / `hasExchanged` 等） |

## 検証計画
1. `php -l`（`StampRally202609Controller.php` / `AppServiceProvider.php` / `web.php`）
2. `php artisan route:list --path=stamp202609` で3新ルートが Controller に解決
3. 10未満交換が 422 / 10以上でコード発行（要実機・要セッション）
4. 別サイトからのクロスサイトフォームPOSTが CSRF 419 で拒否（要実機）
5. 202605 が閾値5のまま `/api/...` で動作（回帰、要実機）

---

# 設計 — ARstampRally202609 最優先バグ修正（T-01 / T-02 / T-03）

> 作成日: 2026-09-09
> 前提: `.claude_workflow/ar_requirements.md` §12-15（要件定義・スコープ確定済み）
> 方針: 変更を最小限に・既存挙動を完全保存・各関数は1箇所のみ修正

## 全体アプローチ

3項目とも「**該当関数内の1ブロックのみ**」を変更する。他関数・他ファイルのロジックには一切触れない。

| # | 変更箇所 | 変更内容 | 影響範囲 |
|---|----------|----------|----------|
| T-01 | `js-stamps.blade.php` `collectStamp()` catch | `removeItem` → `screenshot:null` 化再保存（フォールバック維持） | `collectStamp()` 内のみ |
| T-02 | `js-prize.blade.php` `exchangePrize()` | fetch 前に `stamps` → `stampArr`（screenshot除外）に整形 | `exchangePrize()` 内のみ |
| T-03 | `StampRally202609Controller::checkStatus()` | レスポンス配列に `exchangedAt` を追加 | `checkStatus()` 内のみ |

## ファイル別設計

### T-01: `js-stamps.blade.php` — `collectStamp()` catch（L164-167）

**変更前:**
```js
} catch (err) {
    try { localStorage.removeItem(LOCAL_STORAGE_KEY); } catch (e) {}
    return false;
}
```

**変更後:**
```js
} catch (err) {
    // クォータ超過等の例外時は、screenshotをnull化して再保存（スタンプ個数・名前は保持）
    try {
        if (typeof stamps !== 'undefined' && stamps) {
            Object.keys(stamps).forEach(function (sid) { stamps[sid].screenshot = null; });
            saveCollectedStamps(stamps);
        }
    } catch (e) {
        // 再保存も失敗した場合の最終フォールバック
        try { localStorage.removeItem(LOCAL_STORAGE_KEY); } catch (e2) {}
    }
    return false;
}
```

**設計判断:**
- `var stamps` は `try` 内で宣言されているが、JS の `var` は関数スコープのため `catch` 内から参照可能
- `typeof stamps !== 'undefined' && stamps` ガードで、`getCollectedStamps()` 自体が例外を投げた場合の安全性を確保
- 内側 catch（`e`）で再保存失敗時、従来の `removeItem` をフォールバックとして維持
- `return false` は不変 → 呼び出し側 `collectAndMarkWithRetry` の分岐に影响なし

### T-02: `js-prize.blade.php` — `exchangePrize()` の fetch body

**変更前:**
```js
var stamps = getCollectedStamps();

fetch('/stamp202609/exchange-prize', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken202609() },
    body: JSON.stringify({ fingerprint: fp, deviceInfo: deviceInfo, stamps: stamps })
})
```

**変更後:**
```js
var stamps = getCollectedStamps();
// base64スクリーンショットを除外して送付（DB容量・ネットワーク帯域の節約）
var stampArr = Object.keys(stamps).map(function (sid) {
    return { stampId: sid, collectedAt: stamps[sid].collectedAt || '', name: stamps[sid].name || '' };
});

fetch('/stamp202609/exchange-prize', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken202609() },
    body: JSON.stringify({ fingerprint: fp, deviceInfo: deviceInfo, stamps: stampArr })
})
```

**設計判断:**
- `stampArr` は `{ stampId, collectedAt, name }` の**配列**（既存のオブジェクト `{ stampId: {...} }` と形状が変わる）
- サーバー側 `count($validated['stamps'])` は PHP で配列・オブジェクト両対応 → 閾値チェック正常
- `stamps_data` 列の保存形式が変わるが、**読み取りコードが存在しない**（管理画面なし）
- `recordMarkerScan()` は対象外（サーバー側は `count()` のみ使用・DB保存なし）

### T-03: `StampRally202609Controller::checkStatus()` — レスポンス追加

**変更前:**
```php
return response()->json([
    'hasExchanged' => $exchange !== null,
    'isRedeemed' => $exchange ? $exchange->is_redeemed : false,
    'prizeCode' => $exchange ? $exchange->prize_code : null
]);
```

**変更後:**
```php
return response()->json([
    'hasExchanged' => $exchange !== null,
    'isRedeemed' => $exchange ? $exchange->is_redeemed : false,
    'prizeCode' => $exchange ? $exchange->prize_code : null,
    'exchangedAt' => $exchange && $exchange->exchanged_at
        ? $exchange->exchanged_at->toIso8601String()
        : null
]);
```

**設計判断:**
- `$exchange && $exchange->exchanged_at` の二重ガードで NULL 安全（レコード存在時でも `exchanged_at` が NULL の場合対策）
- `toIso8601String()` は Carbon 標準メソッド（`2026-09-09T12:00:00+09:00` 形式）
- 追加フィールドのみで既存3フィールド（`hasExchanged`/`isRedeemed`/`prizeCode`）は不変 → 後方互換

## リスク・回避策

| # | リスク | 回避策 |
|---|--------|--------|
| R1 | T-01: `stamps` 変数が `catch` 内で `undefined`（`getCollectedStamps()` 自体が例外） | `typeof stamps !== 'undefined' && stamps` ガード |
| R2 | T-01: スクショ除去後もクォータ超過（極端にスタンプ数が多い場合） | 内側 catch で従来の `removeItem` を最終フォールバックとして維持 |
| R3 | T-02: `stamps` の形状変化でサーバー側バリデーション失敗 | PHP `count()` は配列・オブジェクト両対応。`required|array` バリデーションも配列で通る |
| R4 | T-03: `exchanged_at` が NULL（旧データ） | `&& $exchange->exchanged_at` ガードで NULL 返却 |

## 検証計画

1. `php -l resources/views/ARstampRally202609/js-stamps.blade.php` → 警告0件
2. `php -l resources/views/ARstampRally202609/js-prize.blade.php` → 警告0件
3. `php -l app/Http/Controllers/StampRally202609Controller.php` → 警告0件
4. `php artisan route:list --path=stamp202609` → 3ルートが Controller に解決
5. `grep` で `exchangePrize` 内に `stamps: stamps`（旧参照）が0件、`stamps: stampArr`（新参照）が1件
6. `grep` で `collectStamp` の catch 内に `removeItem` が**1件**（最終フォールバックのみ）
7. 202605 / 202606 のファイルに変更がない

---

## 設計: T-08（P1-7）IIFE 例外耐性 — 2026-09-09

### 変更対象
`resources/views/ARstampRally202609/head.blade.php` L31-45（`AR_FORCE_LOWRES` IIFE）

### 現状
```js
window.AR_FORCE_LOWRES = (function() {
    const url = new URL(window.location.href);          // ← 例外耐性なし
    if (url.searchParams.get('lowres') === '1') return true;  // ← 例外耐性なし
    if (detectOldAndroid()) return true;
    try { /* Android cores/memory */ } catch (e) {}
    return false;
})();
```

### 設計方針
IIFE 本体を try/catch で包み、例外時は `false` を返す。
**理由**: `AR_FORCE_LOWRES` は「低スペックなら true、それ以外は false」の2値。例外時は「低スペックと判定できない」= `false` が安全な既定値。

### 変更後コード
```js
window.AR_FORCE_LOWRES = (function() {
    try {
        const url = new URL(window.location.href);
        if (url.searchParams.get('lowres') === '1') return true;
        if (detectOldAndroid()) return true;
        if (/Android/i.test(navigator.userAgent || '')) {
            const cores = navigator.hardwareConcurrency || 0;
            const mem   = navigator.deviceMemory || 0;
            if ((cores > 0 && cores <= 2) || (mem > 0 && mem <= 2)) return true;
        }
    } catch (e) {}
    return false;
})();
```

### 影響
| 項目 | 影響 |
|---|---|
| `?lowres=1` URL パラメータ | 従来どおり `true`（try 内） |
| 旧Android（≤7）検出 | 従来どおり `true`（try 内） |
| 低コア/小メモリAndroid | 従来どおり `true`（try 内） |
| 例外発生時 | `false`（従来: 例外伝播 → アプリ起動不能） |
| 202605 / 202606 | 影響なし（専用ファイル） |

---

## 設計: T-04（P1-2）ローダーフォールバックタイミング — 2026-09-09

### 変更対象
`resources/views/ARstampRally202609/js-init.blade.php` L639-640

### 現状
```js
// フォールバック: 3秒後にローダーを強制非表示（arjs-video-loaded が発火しない端末向け）
setTimeout(hideArjsLoader, 3000);
```

### 設計方針
**3秒 → 7秒** に変更し、`monitorCameraStartup(7000)` のタイムアウトと整合させる。

**理由**:
- `monitorCameraStartup(7000)` は `window.load` イベント発火後に開始（L643-645）
- 7秒時点でカメラ未起動 → `camera-error` + `retry-camera-lowres` が表示される
- 3秒フォールバックがあると、カメラ未起動時にローダーが消えるがエラーUIがまだ出ない → **3〜7秒は黒画面**
- 7秒に揃えることで「ローダー消える = エラーUIが表示される」の整合が取れる

### 変更後コード
```js
// フォールバック: 7秒後にローダーを強制非表示（arjs-video-loaded が発火しない端末向け）
// monitorCameraStartup(7000) のタイムアウトと整合: ローダー非表示時 = camera-error が表示されるタイミング
setTimeout(hideArjsLoader, 7000);
```

### シーケンス（修正後）
```
t=0s    DOMContentLoaded → hideArjsLoader 定義
t=0s    window.load → monitorCameraStartup(7000) 開始
t=0.5s  1回目の video.readyState チェック（ポーリング）
        ...（カメラ起動したら arjs-video-loaded → hideArjsLoader() 即時非表示）
t=7s    タイムアウト:
        ├─ monitorCameraStartup: camera-error + retry 表示
        └─ hideArjsLoader: ローダー非表示（カメラエラーUIが確認できる）
```

### 影響
| 項目 | 影響 |
|---|---|
| カメラ正常起動（通常端末） | `arjs-video-loaded` が即発火 → 従来どおり即時非表示（影響なし） |
| カメラ未起動（低スペックAndroid） | 7秒でローダー非表示 + camera-error 表示（従来: 3秒で黒画面） |
| `?lowres=1` モード | 同一の7秒フォールバックが適用 |
| 202605 / 202606 | 影響なし（専用ファイル） |

### リスク
| # | リスク | 対応 |
|---|---|---|
| R1 | 7秒間ローダーが表示され続ける（従来より4秒長い） | 許容範囲。ローダーは「読み込み中」を示す正しい状態。黒画面よりマシ |
| R2 | `window.load` が遅延する端末で `monitorCameraStartup` の開始が遅れ、7秒フォールバックと不一致 | 低確率（`window.load` は通常 DOMContentLoaded 後数ms）。フォールバックは `hideArjsLoader` のみ（エラーUI表示は monitorCameraStartup 側で制御） |


---

## 設計: T-05（P1-4）XSS 修正 — 2026-09-09

### 変更対象
`resources/views/ARstampRally202609/js-stamps.blade.php` L347-370（`showStampBook()` 内）

### 設計方針
`innerHTML` 文字列結合を**完全に排除**し、`document.createElement` + `textContent` / `img.src` でDOMを構築する。
- `s.name` → `textContent` に代入（HTMLエスケープ自動）
- `stamps[sid].screenshot` → `img.src` に代入（属性値として安全）
- 静的文字列（`'🐾'` / `'？？？'` / `'✓'`）→ `textContent`

### 影響
| 項目 | 影響 |
|---|---|
| スタンプ捕獲・表示UI | 挙動不変（icon / name / date / check / click すべて維持） |
| XSS リスク | `s.name` に `<script>` が含まれても実行されない |
| 202605 / 202606 | 影響なし（専用ファイル） |

---

## 設計: T-06（P1-5）デッドコード削除 — 2026-09-09

### 変更対象
`resources/views/ARstampRally202609/js-camera.blade.php`（L8, L17-37）
`resources/views/ARstampRally202609/js-throw.blade.php`（L28）

### 設計方針
`#switch-camera-button` が `ui.blade.php` に存在しないことを確認済み。
- `js-camera.blade.php`: `currentFacingMode` 変数 + `switchCameraBtn` 宣言 + 全イベントハンドラ（L17-37）を削除
- `js-throw.blade.php`: `isUIButton` 内の `#switch-camera-button` チェック行のみ削除（他ボタンチェックは維持）

### 影響
| 項目 | 影響 |
|---|---|
| 動画撮影 / 写真撮影 | 影響なし（他ボタンのロジックは不変） |
| `isUIButton` 投げ判定 | 他ボタン（`#stamp-book-button` 等）のチェックは維持 |
| 202605 / 202606 | 影響なし |

---

## 設計: T-07（P1-6）iPadOS 13+ 検出 — 2026-09-09

### 変更対象
`resources/views/ARstampRally202609/js-prize.blade.php` L105（`collectDeviceInfo()` 内）

### 設計方針
```js
// 変更前
isIOS: /iPad|iPhone|iPod/.test(navigator.userAgent),

// 変更後
isIOS: /iPad|iPhone|iPod/.test(navigator.userAgent)
    || (navigator.maxTouchPoints > 1 && /MacIntel/.test(navigator.platform)),
```

**判定根拠**:
- iPadOS 13+ は `navigator.userAgent` が `MacIntel` を含み、`navigator.maxTouchPoints >= 5`
- Mac desktop は `maxTouchPoints = 0`（タッチ非対応）または 1（touch-equipped MacBook）→ **誤検出なし**
- Android / PC は `MacIntel` を含まない → **誤検出なし**

### 影響
| 項目 | 影響 |
|---|---|
| iPadOS 13+ | `isIOS: true`（従来: `false` → 端末情報が正確に記録） |
| iPhone / iPod | `isIOS: true`（従来どおり） |
| Mac desktop | `isIOS: false`（`maxTouchPoints=0` で除外） |
| 202605 / 202606 | 影響なし |

---

## 設計: T-09（P1-8）video セレクタ特定化 — 2026-09-09

### 変更対象
`resources/views/ARstampRally202609/head.blade.php` L51, L75, L89

### 設計方針
```js
// 変更前
document.querySelector('video')

// 変更後
document.querySelector('#ar-scene video')
```

**理由**:
- AR.js のカメラ `<video>` は `<a-scene id="ar-scene">` の子要素として生成される
- `#photo-preview` のプレビュー `<video>` は `a-scene` の**外側**（`body` 直下）に存在
- `#ar-scene video` で AR.js 管理の `<video>` だけが絞り込まれる
- 将来 `<video>` 要素が追加されても、`a-scene` 外のものを選ばない

### 影響
| 項目 | 影響 |
|---|---|
| `monitorCameraStartup` | 従来どおり AR.js のカメラのみをポーリング |
| `ensureCameraAccess` | 従来どおり AR.js のカメラのみを操作 |
| `#photo-preview` 動画プレビュー | 影響なし（`a-scene` 外） |
| 202605 / 202606 | 影響なし |

---

## 設計: T-15（P2-6）捕獲日時ゼロ埋め — 2026-09-09

### 変更対象
`resources/views/ARstampRally202609/js-stamps.blade.php` L372（`showStampBook()` 内）

### 設計方針
```js
// 変更前
dateDiv.textContent = (d.getMonth() + 1) + '/' + d.getDate() + ' ' + d.getHours() + ':' + String(d.getMinutes()).padStart(2, '0');

// 変更後
dateDiv.textContent = (d.getMonth() + 1) + '/' + d.getDate() + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
```

**理由**: 既存の分（minutes）の `padStart(2, '0')` パターンを時間（hours）にも適用するのみ。日付（M/D）のフォーマットは変更しない。

### 影響
| 項目 | 影響 |
|---|---|
| スタンプブックの日時表示 | `9:05` → `09:05`（時刻が常に2桁） |
| 他UI / 他ファイル | 影響なし |
| 202605 / 202606 | 影響なし |

---

## 設計: T-16（P2-7）API エラーの沈黙修正 — 2026-09-09

### 変更対象
`resources/views/ARstampRally202609/js-prize.blade.php`（4箇所）

### 設計方針

**① `recordMarkerDetection` L141** — catch 内で `console.warn` を出す
```js
// 変更前
.catch(function () {});

// 変更後
.catch(function (err) { console.warn('[AR202609] recordMarkerDetection error', err); });
```

**② `recordMarkerScan` L158** — `r.ok` チェック + `console.warn`
```js
// 変更前
}).then(function (r) { return r.json(); }).catch(function () {});

// 変更後
}).then(function (r) {
    if (!r.ok) { console.warn('[AR202609] recordMarkerScan HTTP ' + r.status); return null; }
    return r.json();
}).catch(function (err) { console.warn('[AR202609] recordMarkerScan error', err); });
```

**③ `checkPrizeExchangeStatus` L175** — `r.ok` チェック + `console.warn`（フォールバック値維持）
```js
// 変更前
}).then(function (r) { return r.json(); }).catch(function () { return { hasExchanged: false }; });

// 変更後
}).then(function (r) {
    if (!r.ok) { console.warn('[AR202609] checkPrize HTTP ' + r.status); return { hasExchanged: false }; }
    return r.json();
}).catch(function (err) { console.warn('[AR202609] checkPrize error', err); return { hasExchanged: false }; });
```

**④ `exchangePrize` L224** — catch 内で `console.warn` を追加（`alert` は維持）
```js
// 変更前
.catch(function () { alert('通信エラーが発生しました'); _exchanging = false; });

// 変更後
.catch(function (err) { console.warn('[AR202609] exchangePrize error', err); alert('通信エラーが発生しました'); _exchanging = false; });
```

### 影響
| 項目 | 影響 |
|---|---|
| API 正常応答 | 挙動不変（`r.ok === true` で `r.json()` を呼ぶ） |
| API 5xx / ネットワークエラー | `console.warn` でログ出力（従来: 沈黙） |
| ユーザー向け UI（alert / modal） | 変更なし |
| 202605 / 202606 | 影響なし |

---

## 設計: T-17（P2-5）ガイド初期言語統一 — 2026-09-09

### 変更対象
`resources/views/ARstampRally202609/ui.blade.php` L47-48

### 設計方針
```html
<!-- 変更前 -->
<button id="lang-jp" class="lang-btn" aria-pressed="false">日本語</button>
<button id="lang-en" class="lang-btn active" aria-pressed="true">English</button>

<!-- 変更後 -->
<button id="lang-jp" class="lang-btn active" aria-pressed="true">日本語</button>
<button id="lang-en" class="lang-btn" aria-pressed="false">English</button>
```

**理由**: `js-init.blade.php` L9 で `guideLang = 'jp'` としており、L195 で `setGuideLanguage(guideLang)` が DOMContentLoaded 時に呼ばれる。HTML の初期状態を JS の初期状態（日本語）に合わせることで、JS 実行前のちらつきを解消する。

### 影響
| 項目 | 影響 |
|---|---|
| ガイドモーダル初回表示 | 日本語 active（従来: 一瞬英語 active → JSで日本語に切替） |
| 言語切替機能（JP ↔ EN） | 影響なし（`setGuideLanguage` は従来どおり動作） |
| 202605 / 202606 | 影響なし |

---

## 設計: T-18（P3-2）Cookie `Secure` フラグ追加 — 2026-09-09

### 変更対象
`resources/views/ARstampRally202609/js-prize.blade.php` L50（`CookieHelper202609.set()` 内）

### 設計方針
```js
// 変更前
document.cookie = name + '=' + value + ';expires=' + exp.toUTCString() + ';path=/;SameSite=Strict';

// 変更後
document.cookie = name + '=' + value + ';expires=' + exp.toUTCString() + ';path=/;SameSite=Strict' + (location.protocol === 'https:' ? ';Secure' : '');
```

**理由**: `Secure` フラグは HTTPS 環境でのみ有効。HTTP 環境（ローカル開発 `php artisan serve`）で `Secure` を付与すると Cookie が読み書きできなくなるため、`location.protocol === 'https:'` で条件付き付与する。

### 影響
| 項目 | 影響 |
|---|---|
| HTTPS 本番環境 | Cookie に `Secure` フラグ付与（MITM 対策） |
| HTTP ローカル開発 | `Secure` なし（従来どおり動作） |
| `getUserId202609()` のフロー | 影響なし（Cookie API の変更有り） |
| 202605 / 202606 | 影響なし（専用ファイル） |

---

## 設計: T-19（P3-3）`crypto.randomUUID()` 切替 — 2026-09-09

### 変更対象
`resources/views/ARstampRally202609/js-prize.blade.php` L63-69（`generateUUID202609()` 内）

### 設計方針
```js
// 変更前
function generateUUID202609() {
    return 'uid_' + 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
        var r = Math.random() * 16 | 0;
        var v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

// 変更後
function generateUUID202609() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return 'uid_' + crypto.randomUUID();
    }
    return 'uid_' + 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
        var r = Math.random() * 16 | 0;
        var v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}
```

**理由**:
- `crypto.randomUUID()` は暗号学的に安全な乱数で、UUID v4 を生成する
- `typeof crypto !== 'undefined' && crypto.randomUUID` で feature detection（旧ブラウザ対応）
- `'uid_'` prefix は維持（既存の `localStorage` / Cookie 値との互換性）
- フォールバックで従来の `Math.random()` 方式を維持

### 影響
| 項目 | 影響 |
|---|---|
| モダンブラウザ（Chrome 92+ / Firefox 95+ / Safari 15.4+） | `crypto.randomUUID()` を使用（より安全なUUID） |
| 旧版ブラウザ | 従来どおり `Math.random()` ベース（機能低下なし） |
| 既存ユーザーの ID | 影響なし（新規ID生成時のみ） |
| 202605 / 202606 | 影響なし（専用ファイル） |

---

## 設計: NEXT-1（P2-10）`antialias` 条件分岐 — 2026-09-09

### 変更対象
`resources/views/ARstampRally202609/scene.blade.php`（`<a-scene>` 直前に `<script>` 追加）

### 設計方針
`AR_FORCE_LOWRES` は `head.blade.php` の JS IIFE で設定される `window` プロパティのため、Blade `@if` では使えない。`<a-scene>` 直前の `<script>` で A-Frame の `beforeentitycomposition` イベントをListen し、`renderer` 属性をパッチする。

```html
<script>
    if (window.AR_FORCE_LOWRES) {
        document.addEventListener('beforeentitycomposition', function(e) {
            var el = e.target;
            if (el && el.id === 'ar-scene') {
                var r = el.getAttribute('renderer') || '';
                if (r.indexOf('antialias: true') !== -1) {
                    el.setAttribute('renderer', r.replace('antialias: true', 'antialias: false'));
                }
            }
        }, { once: true });
    }
</script>
```

**理由**:
- `beforeentitycomposition` は A-Frame がレンダラーを作成する直前に発火
- `{ once: true }` で1回限り（リッスンリーク回避）
- `AR_FORCE_LOWRES === false` の高スペック端末では何も行わない（従来挙動維持）

### 影響
| 項目 | 影響 |
|---|---|
| 低スペック端末（`AR_FORCE_LOWRES=true`） | `antialias: false`（GPU 負荷軽減） |
| 高スペック端末（`AR_FORCE_LOWRES=false`） | 従来どおり `antialias: true` |
| 202605 / 202606 | 影響なし |

---

## 設計: NEXT-2（P2-8）Audio 遅延生成 — 2026-09-09

### 変更対象
`resources/views/ARstampRally202609/js-stamps.blade.php` L31-35（宣言）、L153-154（呼出）

### 設計方針
`null` 初期化 + `_ensureSound(which)` ヘルパーで初回使用時のみ `new Audio(...)` を実行。

```js
// 変更後
var soundStamp01 = null;
var soundStamp02 = null;
function _ensureSound(which) {
    if (which === 1) {
        if (!soundStamp01) soundStamp01 = new Audio("{{ asset('cg/sound_stamp01.mp3') }}");
        return soundStamp01;
    }
    if (!soundStamp02) soundStamp02 = new Audio("{{ asset('cg/sound_stamp02.mp3') }}");
    return soundStamp02;
}
// 呼出側
if (isComplete) { playSound(_ensureSound(2)); showCompleteParticles(); }
else            { playSound(_ensureSound(1)); showNormalParticles(); }
```

**理由**:
- `null` 初期化で初回ロード時の帯域171KBを節約
- 初回捕獲時に Audio が生成される（1フレームの遅延は体感しにくい）
- `preload` 属性は不要（初回 `play()` でブラウザが自動 fetch）

### 影響
| 項目 | 影響 |
|---|---|
| 初回ロード | Audio 171KB の帯域消費がなくなる |
| 初回捕獲時 | Audio が遅延生成される |
| 202605 / 202606 | 影響なし |

---

## 設計: NEXT-3（P2-9）`howToOperate.png` LCP 低減 — 2026-09-09

### 変更対象
`resources/views/ARstampRally202609/ui.blade.php` L53

### 設計方針
```html
<!-- 変更後 -->
<img class="howto-main" src="{{ asset('img/howToOperate.png') }}" alt="操作ガイド" loading="lazy" decoding="async" />
```

**理由**:
- `loading="lazy"`: ビューポート外ではブラウザが読み込みを延期（LCP 改善）
- `decoding="async"`: 非ブロッキングデコード（主スレッドのブロック解消）
- 本番環境での更なる改善: `cwebp -q 80 howToOperate.png -o howToOperate.webp`（運用時に実施）

### 影響
| 項目 | 影響 |
|---|---|
| LCP | 改善（非同期デコード + 遅延読み込み） |
| 202605 / 202606 | 影響なし |

---

## 設計: NEXT-4（P2-3）景品モーダル集約 — 2026-09-09

### 変更対象
`resources/views/ARstampRally202609/js-prize.blade.php` L213-214・L226（呼出）、L236-268（関数定義）

### 設計方針
共通関数 `showPrizeModal(config)` + `_formatExchangeDateTime()` に集約。`textContent` でコード値を設定（XSS 経路排除）。`addEventListener` で閉じるボタン。

**新設関数**:
- `_formatExchangeDateTime(exchangedAt)` — 日時文字列を返す（従来 `showRedeemedPrizeInfo` 内のロジックを抽出）
- `showPrizeModal(config)` — `config` オブジェクト（`title` / `titleColor` / `subtitle` / `code` / `codeFontSize` / `label` / `dateTimeStr` / `buttonBg`）でモーダル生成

**呼出箇所変更**（3箇所）:
- L213: `showRedeemedPrizeInfo(code, exchangedAt)` → `showPrizeModal({ title:'✅ すでに景品と交換済みです', titleColor:'#999', code:..., codeFontSize:'28px', label:'景品コード', dateTimeStr:_formatExchangeDateTime(...), buttonBg:'#999' })`
- L214: `showPrizeCode(code)` → `showPrizeModal({ title:'🎉 景品交換完了！ 🎉', titleColor:'#4CAF50', subtitle:'以下のコードを受付でお見せください', code:..., codeFontSize:'32px', buttonBg:'#4CAF50' })`
- L226: `showPrizeCode(data.prizeCode)` → 同上

**理由**:
- 重複約30行を1関数に集約（保守性向上）
- `textContent` でコード値を設定（`innerHTML` 経由の XSS 経路を排除）
- `addEventListener` で DOM 深さに依存しない閉じる処理
- `_formatExchangeDateTime` で日時フォーマット統一

### 影響
| 項目 | 影響 |
|---|---|
| 景品交換成功モーダル | 見た目不変 |
| 交換済みモーダル | 見た目不変 + 日時表示維持 |
| XSS 耐性 | `textContent` でコード値がエスケープされる |
| DOM 深さ依存の onclick | 解消（`addEventListener` 使用） |
| 202605 / 202606 | 影響なし（専用ファイル） |
