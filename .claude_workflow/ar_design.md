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
