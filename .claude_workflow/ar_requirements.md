# 要件定義 — ARstampRally202609 問題点修正

> 作成日: 2026-09-02
> 根拠: `resources/views/ARstampRally202609/analysis_qwen3.8.md`（検証レポート）、
> `resources/views/ARstampRally202606/analysis_qwen3.8.md`（主因A/B/C）、
> `resources/views/ARstampRally202606/analysis_claude_sonnet5.md`（全体分析）
> 対象: `resources/views/ARstampRally202609.blade.php` および `resources/views/ARstampRally202609/*.blade.php`（11ファイル）
> 制約（CLAUDE.md）: 日本語で説明・コード変更を最小に・カレントディレクトリ配下のみ・既存挙動の保存・実装前に php -l / テスト確認・指示にない機能は追加しない

---

## 1. 目的

ARstampRally202606 の分析で特定され、ARstampRally202609 でも**未改善のまま残っている**（検証レポート §0/§3/§4）問題点を修正し、
Android / iOS / PC の全端末で「カメラ映像の比率・マRecognizerとの3Dモデルの位置・スケール」が意図通り安定して動作するWebARアプリにする。

## 2. 現状把握（202609 の現状コード）

| # | 現状 | 所在（202609 の file:line） |
|---|------|------------------------------|
| 1 | Android 判定で `sourceWidth/Height` を 640×480（iOS=1280×720）にサーバーサイド固定。AR.js はこれを `getUserMedia` の `ideal` 制約兼射影パラメータに使用 → 実ストリーム・実画面比率と一致せず「カメラズーム・モデルズレ」 | `scene.blade.php` L1-7, L11 |
| 2 | `arjs-video-loaded` 時の arjs 再設定が**縦型ストリームのみ**（`videoWidth < videoHeight` 限定）。横長ストリーム/横長画面は 4:3 固定のまま。`resize`/`orientationchange` 追従もなし | `js-init.blade.php` L586-605 |
| 3 | Samsung 縦型のみ `480×640` に上書きする端末名ハードコードパッチ（場当たり対応） | `scene.blade.php` L73-92 |
| 4 | `applyCurrentScaleTo()` が「ルートスケール」「各メッシュスケール」の**両方**を同じ値 `v` に設定 → 世界スケールが `base²` 倍（maker00=0.36、maker01-20=1.21、PC wheel 2倍→4倍相当）。`markerFound` 毎に全端末で発火 | `js-init.blade.php` L31-56（呼出 L353, L378） |
| 5 | 「低解像度で再試行」で `sourceWidth:320; sourceHeight:240` に差し替えるが `displayWidth/Height` を更新しない → スケールがさらにずれる | `js-init.blade.php` L523-535 |
| 6 | `object-fit: cover !important` は見た目専用で射影パラメータには無効（コメント「Androidカメラズーム防止」は不正確） | `head.blade.php` L172-181 |
| 7 | 動画撮影: `MediaRecorder` 未実装環境（iOS 14.2 以前等）は alert しか出ず、ボタン自体は常時表示（機能低下） | `js-camera.blade.php` L93-100, `ui.blade.php` L16 |
| 8 | 景品交換 `exchangePrize()` に fetch 実行中の `disabled`/クールダウンガードなし（連打で二重POST） | `js-prize.blade.php` L178-214 |
| 9 | 低スペック判定が「Android 7以下」の UA 判定のみ（`deviceMemory`/`hardwareConcurrency` 未加味） | `head.blade.php` L22-35 |

## 3. 成功基準

1. **カメラ比率（主因A）**: Android（縦/横長画面）で、動画読み込み完了後、`a-scene` の `arjs` 属性の 4 値が「source=動画実寸（videoWidth/Height）、display=実画面（innerWidth/Height）」になっている。`markerFound` 時にカメラ映像の「ズーム込み」感が iOS と同程度になる。
2. **回転・リサイズ追従**: 画面を縦↔横に回転し、PC でウィンドウをリサイズしても、上記4値が実態に追従する（タイムアウト/デバウンス付き）。
3. **モデルスケール（主因B）**: マーカー認識時に、モデルの描画スケールが `dataset.baseScale`（maker00=0.6、maker01-20=1.1）× `currentScale`（1〜3）の**1回分**になる（`base²` でない）。PC の wheel を 2 倍にするとモデルも 2 倍（4倍でない）。
4. **低解像度経路（主因C）**: 「低解像度で再試行」後の再認識でも、モデルのスケール・位置が3.と同様に安定する。
5. **iOS 回帰**: iPhone（16:9ストリーム・縦型ストリーム両方）で上記1-3の挙動が劣化しない。
6. **動画撮影**: `MediaRecorder` 未実装環境では動画ボタンが非表示（クラッシュしない・誤タップしない）。対応環境では従来どおり撮影できる。
7. **景品交換**: 交換中の連打で `/api/exchange-prize` が二重に POST されない（UI のみで、サーバー挙動は変更しない）。
8. **既存機能の保存**: マーカー認識→3D表示→投擲→捕獲→スタンプ→景品交換→ギャラリー→写真撮影・カメラフォールバックの既存挙動がすべて維持される。
9. `php -l` が全変更 blade ファイルで警告・エラーなし。

## 4. スコープ

### In-Scope（今回の修正対象）
- `scene.blade.php`: UA 分岐の解消、`arjs` 初期値の整理、Samsung 上書きの処置
- `js-init.blade.php`: `applyCurrentScaleTo` 修正、`arjs-video-loaded` 全方向同期＋resize/orientation追従、低解像度リトライ時の display 更新
- `head.blade.php`: CSS コメントの修正（必要なら低スペック判定の精緻化）
- `js-camera.blade.php` / `ui.blade.php`: 動画ボタンの feature detection（必要なら）
- `js-prize.blade.php`: 交換ボタンの二重送信防止（必要なら）

### Out-of-Scope（`ARstampRally202609` 関連ファイルの範囲外・今回は実施しない）
- **P0 サーバー側**: `PrizeExchangeController` のスタンプ検証・`checkStatus()` バリデーション・API レート制限（`app/`, `routes/api.php`）
- **P1 インフラ系**: `ar-engine.min.js` / `ar-tracking.min.js` のバージョン管理・gzip/br 圧縮・`Cache-Control` 設定（`public/js`, web server 設定, `package.json`）
- 3Dモデル・マーカーアセット（`public/cg/202606/`）は流用したまま変更しない
- ARstampRally202606（旧キャンペーン）のファイルは変更しない

## 5. 前提・留意事項

- `ar-tracking.min.js` は `sourceWidth/Height` を `getUserMedia` の `ideal` 制約に、`displayWidth/Height` を射影計算に使用している（202606分析 §3/§4-1）。
- 4 値は動画読み込み完了後でないと確定しない → 起動直後の暫定値（`scene.blade.php`）は「読み込み後に即時上書きされる前提」で維持する。
- `frustumCulled = false`（AR でモデルが切り落とされないための正当な目的）は維持する。
- `object-fit: cover`（レターボックス防止）は見た目のため維持し、コメントのみ実態に修正する。
- `user-scalable=no` / ズーム抑止 / コンテキストメニューブロックはユーザー操作防御として維持する。
- Samsung 上書き（#3）を削除する場合、全方向同期（#2 の修正）が確実に発火することの順序依存に注意（`scene.blade.php` 内の script は A-Frame 初期化前に実行されるため、初期解像度が Samsung の実ストリームと大きく乖離すると認識不能になる懸念）。

## 6. 未確定事項（設計前に確認）

- ~~Q1: 修正スコープは In-Scope 全部（#1-#9）か、カメラ/スケール系（#1-#6）のみにするか~~
  → **決定（2026-09-02）: 全修正（#1-#9 すべて）**
- ~~Q2: `scene.blade.php` の Samsung 上書き（#3）は削除するか、暫定維持（実機確認後に削除）するか~~
  → **決定（2026-09-02）: 全方向同期（Fix-1）と同時に変更し、上書きブロックを削除**
- ~~Q3: 低スペック判定の精緻化（#9: `deviceMemory`/`hardwareConcurrency` 加味）は今回の対象にするか~~
  → **決定（2026-09-02）: 今回の対象とする**

※ 「待機時間の基準不一致（7s vs 10s）」はスコープ外として今回変更しない（指示にない機能追加を避けるため）。

---

# 要件定義 — ARstampRally202609 景品交換APIのサーバー側強化（202609専用エンドポイント）

> 作成日: 2026-09-08
> 根拠: `resources/views/ARstampRally202609/analysis_qwen3.8_20260908.md` の P0-1 / P0-2 / P0-3
> 制約（CLAUDE.md）: 日本語で説明・変更を最小に・既存挙動の保存・実装前に `php -l` 確認・**202605 / 202606（旧キャンペーン）への影響を完全に排除**
> 重要: `routes/api.php` の `/api/record-marker-scan`・`/api/check-prize-exchange`・`/api/exchange-prize` は **202605 / 202606 が継続利用するため変更しない**

## 7. 目的

202609 の景品交換まわりの3つの P0 脆弱性を、**202609専用のサーバー側エンドポイント**として新設し、他キャンペーン（202605=閾値5 / 202606=閾値10）の閾値差を壊さず修正する。

## 8. 現状（202609 の脆弱性）

| # | 問題 | 箇所 |
|---|------|------|
| P0-1 | 景品交換API（`/api/exchange-prize`）がスタンプ件数・整合性を検証しない。クライアントの「10個以上」ガードは JS 内のみで、APIを直接叩けばスタンプ0個でもコード発行できる | `PrizeExchangeController::exchange()`・`routes/api.php:28` |
| P0-2 | 3端点が `routes/api.php`（CSRF 非適用グループ）にあり、`head.blade.php` の CSRF トークンは検証されない。クロスサイトフォームPOSTで景品コード発行可能 | `routes/api.php:26-28` |
| P0-3 | 3 API にレート制限（throttle）が無く、連打・自動化で DB 汚染・コード発行量暴走 | `routes/api.php:26-28` |

## 9. 成功基準

1. **P0-1**: `/stamp202609/exchange-prize` が `stamps` 件数10未満のとき **422** を返し、景品コードを発行しない（サーバー側強制）。
2. **P0-2**: 3端点すべてが `web` ミドルウェアグループ（CSRF + Session）を強制しセッションに紐づく。別サイトからのフォームPOSTは CSRF で拒否。
3. **P0-3**: スキャン記録・照会・交換それぞれに `throttle` を適用し、1分間 / 1時間 単位で上限を設ける。
4. **他キャンペーン影響なし**: 202605（閾値5）・202606（閾値10）は従来どおり `/api/...` を使用し、本変更の影響を受けない。
5. **既存機能保存**: マーカー認識→捕獲→スタンプ→交換→コード表示の既存UI挙動は維持（応答形状も同一）。
6. `php -l` が変更した PHP ファイルで警告・エラーなし。`php artisan route:list` で3新ルートが `StampRally202609Controller` に解決される。

## 10. スコープ

### In-Scope（今回実施）
- 新規 `app/Http/Controllers/StampRally202609Controller.php`（`recordScan` / `checkStatus` / `exchange`、閾値10をサーバー側で強制）
- `routes/web.php`: 202609 専用の3ルート（`web`グループ + `throttle`）
- `app/Providers/AppServiceProvider.php`: 202609 専用の命名レートリミッター3種
- `resources/views/ARstampRally202609/js-prize.blade.php`: 3つの fetch を新エンドポイントへ差し替え

### Out-of-Scope（変更しない）
- `routes/api.php` の `/api/record-marker-scan`・`/api/check-prize-exchange`・`/api/exchange-prize`（202605 / 202606 が継続利用）
- `app/Http/Controllers/MarkerScanController.php`・`PrizeExchangeController.php`（他キャンペーン向け）
- 202605 / 202606 の `js-prize.blade.php`（各閾値は従来どおり JS 内ガードのまま）

## 11. 前提・留意事項

- `SESSION_DRIVER=database`（`env` 確認済み）→ `web` グループでセッション/CSRF が機能する前提。
- 閾値10は 202609 の `js-prize.blade.php` の `PRIZE_EXCHANGE_THRESHOLD = 10` と一致させる。
- フィンガープリントは `recordScan` 時にセッションへ保持し、`checkStatus` / `exchange` でセッション優先・リクエスト値フォールバックに読み替える（既存の `orWhere` 挙動を維持しつつセッションと紐付け）。
- コード生成ロジック（ランダム5桁＋衝突チェック）は既存 `PrizeExchangeController::exchange()` と同一。
---

# 要件定義 — ARstampRally202609 最優先バグ修正（T-01 / T-02 / T-03）

> 作成日: 2026-09-09
> 根拠: `resources/views/ARstampRally202609/analysis_qwen3.8_20260908.md` §10.1（T-01/T-02/T-03）、§9.4（N1/N2）
> 制約（CLAUDE.md）: 変更を最小に・既存挙動保存・`php -l` 必須・他キャンペーン影響排除

## 12. 目的

P0 対応後に判明した**最優先3項目**（データ損失・大規模データ送信・機能欠損）を修正する。

| # | 問題 | 現状 | 修正目標 |
|---|------|------|----------|
| **T-01** | `collectStamp()` の catch が `localStorage.removeItem()` で**全スタンプ消去** | base64スクショ蓄積→クォータ超過時、景品交換直前で全データ消失 | catch 内で `screenshot: null` 化して**再保存**（スタンプ個数・名前は保持） |
| **T-02** | `exchangePrize()` が base64 スクショを含む `stamps` をそのまま POST | `stamps_data` 列に数MB JSON が蓄積（20体×1MB超） | 送付前に `screenshot` を除外し `{ stampId, collectedAt, name }` 配列のみ送付 |
| **T-03** | `checkStatus()` のレスポンスに `exchangedAt` 欠落 | 交換済みモーダルで交換日時が `undefined`（非表示） | `'exchangedAt' => $exchange->exchanged_at->toIso8601String()` を追加 |

## 13. 成功基準

1. **T-01**: `localStorage.setItem` が `QuotaExceededError` を投げても、`getCollectedStamps()` から `screenshot` を除外したデータが再保存され、スタンプ個数・名前が保持される。
2. **T-02**: `exchangePrize()` の POST body に `screenshot` フィールドが存在しない（DevTools Network で確認）。サーバー側 `stamps_data` 列に `screenshot` 文字列が含まれない。
3. **T-03**: `checkStatus()` のレスポンス JSON に `exchangedAt` フィールドが含まれる（ISO 8601 文字列 or `null`）。
4. **既存機能保存**: スタンプ捕獲・交換・コード表示・交換済み表示の既存UI挙動が維持される。
5. **他キャンペーン影響なし**: 202605 / 202606 のファイルに一切変更がない。
6. `php -l` が変更した全 PHP ファイルで警告・エラーなし。

## 14. スコープ

### In-Scope（今回変更）
- `resources/views/ARstampRally202609/js-stamps.blade.php` — `collectStamp()` の catch ブロック（L164-167）
- `resources/views/ARstampRally202609/js-prize.blade.php` — `exchangePrize()` の fetch body 整形
- `app/Http/Controllers/StampRally202609Controller.php` — `checkStatus()` のレスポンス

### Out-of-Scope
- `js-stamps.blade.php` の他関数（`collectAndMarkWithRetry` 等）
- `js-prize.blade.php` の他関数（`recordMarkerScan` / `checkPrizeExchangeStatus`）
- `PrizeExchangeController.php`（他キャンペーン共用、変更しない）
- DB スキーマ変更（`stamps_data` 列は `text`/`json` のまま）
- IndexedDB 移行・スクリーンショット保存の完全廃止（将来の選択肢）

## 15. 前提・留意事項

- T-01 の catch 内再保存もクォータ超過する可能性（極端な場合）は残る → その場合は従来の `removeItem` を最終フォールバックとして残す。
- T-02 で送付する `stamps` の形状がオブジェクト（`{ stampId: {...} }`）→ 配列（`[{ stampId, collectedAt, name }]`）に変わるが、サーバー側 `count($validated['stamps'])` は両方で動作する（PHP の `count` は配列・オブジェクト両対応）。
- T-03 の `exchanged_at` は `PrizeExchange` テーブルの `timestamp` 列（`exchanged_at`）で、`$casts` で `datetime` にキャスト済み（or `created_at` と同じ形式）。`toIso8601String()` で安全に文字列化できる。

---

## 16. T-08（P1-7）: `head.blade.php` IIFE の例外耐性不足

### 目的
`AR_FORCE_LOWRES` の IIFE（L31-45）内 L32 `new URL(window.location.href)` が例外を投げた場合、同一 `<script>` ブロックの `monitorCameraStartup` / `ensureCameraAccess` / `destroyAndFreeEntity` が全て未定義となり**アプリが起動不能**になる問題を解消する。

### 現状（head.blade.php L31-45）
```js
window.AR_FORCE_LOWRES = (function() {
    const url = new URL(window.location.href);  // ← 例外耐性なし
    if (url.searchParams.get('lowres') === '1') return true;
    if (detectOldAndroid()) return true;
    try { /* Android cores/memory チェック */ } catch (e) {}
    return false;
})();
```
- L32 の `new URL()` / L33 の `searchParams.get()` が例外を投げると IIFE 外に伝播
- 同一 `<script>` の L47 以降（`monitorCameraStartup` 等）は**実行されない**

### 成功基準
1. IIFE 内の全ステートメントが try/catch 内で評価される
2. 例外発生時も `window.AR_FORCE_LOWRES = false` が確定（`undefined` にならない）
3. 同一 `<script>` の残りの関数定義（L47+）は通常どおり実行される
4. `AR_FORCE_LOWRES=true` になる条件（`?lowres=1` / 旧Android / 低コア/小メモリ）は従来どおり動作

### Out-of-Scope
- `detectOldAndroid()` 自体のロジック変更（既に try/catch あり）
- `navigator.hardwareConcurrency` / `navigator.deviceMemory` の対応

---

## 17. T-04（P1-2）: ローダー3秒無条件非表示 vs カメラ監視7秒の矛盾

### 目的
低スペックAndroidで「ローダー消えたがカメラエラーも出ない」状態の約4秒間黒画面を解消し、ユーザーに明確なフィードバック（AR起動 or エラー表示）を7秒以内で提供する。

### 現状（js-init.blade.php L639-645）
```js
setTimeout(hideArjsLoader, 3000);  // 3秒で無条件非表示
window.addEventListener('load', function () {
    if (typeof monitorCameraStartup === 'function') monitorCameraStartup(7000);  // 7秒監視
});
```
- `monitorCameraStartup(7000)`: 500msポーリング×7秒、未起動時 `camera-error` + `retry-camera-lowres` を表示
- 3秒フォールバック: カメラ未起動でもローダーが消える → **3〜7秒は黒画面**

### 成功基準
1. フォールバックタイムアウトが **7秒**（`monitorCameraStartup` のタイムアウトと一致）
2. 7秒時点でローダーが非表示になり、`camera-error` UI が確実に表示される
3. `arjs-video-loaded` イベントが正常に発火する端末では従来どおり即時非表示（影響なし）
4. `AR_FORCE_LOWRES` が true の端末でも同一の7秒フォールバックが適用される

### Out-of-Scope
- `monitorCameraStartup` 自体のポーリング間隔（500ms）やタイムアウト値（7000ms）の変更
- カメラエラーUI の文言・デザインの改善
- `ensureCameraAccess` のリトライロジック


---

## 18. T-05（P1-4）: `showStampBook()` の XSS 脆弱性修正

### 目的
`showStampBook()`（`js-stamps.blade.php` L347-370）が `innerHTML` に `s.name`（スタンプ名）と `stamps[sid].screenshot`（base64 URL）を直接埋め込んでいる。スタンプ名がユーザー制御（API POST）であるため、`<script>` タグ等の HTML がそのまま DOM に注入される XSS リスクを解消する。

### 現状
```js
var iconContent = collected && stamps[sid].screenshot
    ? '<img src="' + stamps[sid].screenshot + '" alt="' + s.name + '" ...>'
    : (collected ? s.icon : '🐾');
var nameText = collected ? s.name : '？？？';
item.innerHTML = '<div class="stamp-icon">' + iconContent + '</div><div class="stamp-name">' + nameText + '</div>' + dateText + checkMark;
```

### 成功基準
1. `showStampBook()` 内に `innerHTML` の使用が0件
2. `document.createElement` + `textContent` / `img.src` でDOMを構築（XSS安全）
3. 既存UI（icon / name / date / check / click handler）の挙動が完全に維持される
4. `php -l` 警告0件

### Out-of-Scope
- `collectStamp()` / `showCapturedMessage()` 等他関数の `innerHTML`
- スタンプ名のサーバー側サニタイズ（将来の選択肢）

---

## 19. T-06（P1-5）: `#switch-camera-button` デッドコード削除

### 目的
202609 の `ui.blade.php` に `#switch-camera-button` が存在しないにもかかわらず、`js-camera.blade.php`（L8, L17-37）と `js-throw.blade.php`（L28）に当該ボタンの参照が残存する。将来誤ってボタンを追加した際に AR.js のトラッキングを破壊する地雷を除去する。

### 現状
- `js-camera.blade.php` L8: `var currentFacingMode = 'environment';`（switchCameraBtn のみで使用）
- `js-camera.blade.php` L17-37: `switchCameraBtn` 宣言 + `addEventListener('click', ...)` 全体
- `js-throw.blade.php` L28: `element.closest('#switch-camera-button') ||`（`isUIButton` 内）

### 成功基準
1. 202609 配下 blade ファイルに `switch-camera-button` が0件
2. `currentFacingMode` 変数が残っていない
3. `isUIButton` の他ボタンチェック（`#stamp-book-button` 等）は維持
4. `php -l` 警告0件
5. 202605 / 202606 に影響なし

---

## 20. T-07（P1-6）: iPadOS 13+ の `isIOS` 検出漏れ修正

### 目的
iPadOS 13 以降は User-Agent が `MacIntel` に偽装されるため、`/iPad|iPhone|iPod/.test(navigator.userAgent)` が `false` となり、`isIOS: false` がサーバーに送付される。景品交換時の端末情報が正確に記録されるよう修正する。

### 現状（js-prize.blade.php `collectDeviceInfo()`）
```js
isIOS: /iPad|iPhone|iPod/.test(navigator.userAgent),
```

### 成功基準
1. iPadOS 13+（Mac UA偽装）でも `isIOS: true` が送付される
2. 判定式: `/iPad|iPhone|iPod/.test(ua) || (navigator.maxTouchPoints > 1 && /MacIntel/.test(navigator.platform))`
3. 非iOS端末（Mac desktop / Android / PC）で `isIOS: false` が維持される
4. `php -l` 警告0件

### Out-of-Scope
- `collectDeviceInfo()` の他フィールド
- 202605 / 202606 の同関数（専用ファイル）

---

## 21. T-09（P1-8）: `document.querySelector('video')` の特定化

### 目的
`head.blade.php` の `monitorCameraStartup`（L51）・`ensureCameraAccess`（L75, L89）が `document.querySelector('video')` で DOM 上の**最初の** `<video>` を取得している。`#photo-preview` のプレビュー `<video>` や将来追加される `<video>` が先頭に来ると誤った要素を操作するリスクがある。AR.js のカメラ映像のみを対象にする。

### 現状
```js
const v = document.querySelector('video');  // L51, L75
var videoEl = document.querySelector('video');  // L89
```

### 成功基準
1. 全3箇所が `document.querySelector('#ar-scene video')` に置換される
2. 202609 配下 blade に `document.querySelector('video')` が0件
3. `monitorCameraStartup` / `ensureCameraAccess` の機能（ポーリング・フォールバック・リトライ）が完全に維持される
4. `php -l` 警告0件
5. 202605 / 202606 に影響なし


---

## 22. T-15（P2-6）: 捕獲日時表示のゼロ埋め漏れ修正

### 目的
`showStampBook()`（`js-stamps.blade.php` L372）が捕獲日時を表示する際、`d.getHours()` を直接文字列結合しているため、9時台が `9:05`、10時台が `10:05` と不揃いに表示される。分（`String(d.getMinutes()).padStart(2, '0')`）にはゼロ埋めがあるが、時間にない。

### 現状
```js
dateDiv.textContent = (d.getMonth() + 1) + '/' + d.getDate() + ' ' + d.getHours() + ':' + String(d.getMinutes()).padStart(2, '0');
```

### 成功基準
1. 時刻の表示が `HH:MM` 形式（例: `09:05` / `10:30`）で常に2桁
2. 日付部分（`M/D`）の表示は変えない
3. `php -l` 警告0件
4. 202605 / 202606 に影響なし

### Out-of-Scope
- 日付表示のフォーマット変更（`M/D` → `YYYY-MM-DD` 等）
- 月 / 日のゼロ埋め

---

## 23. T-16（P2-7）: API エラーの沈黙修正（`console.warn` + `r.ok` チェック）

### 目的
`js-prize.blade.php` の `recordMarkerDetection`（L141）・`recordMarkerScan`（L158）・`checkPrizeExchangeStatus`（L175）・`exchangePrize`（L224）が `.catch(function () {})` で API エラーを完全に無視している。`r.json()` が 5xx HTML を返すと `JSON.parse` 失敗も catch が飲み込み、運用時トラブルが「何も起きない」形で消える。

### 現状
```js
// L141
.catch(function () {});

// L158
.then(function (r) { return r.json(); }).catch(function () {});

// L175
.then(function (r) { return r.json(); }).catch(function () { return { hasExchanged: false }; });

// L224
.catch(function () { alert('通信エラーが発生しました'); _exchanging = false; });
```

### 成功基準
1. 全4箇所の `.catch` に `console.warn('[AR202609] ...', err)` を追加
2. `recordMarkerScan` / `checkPrizeExchangeStatus` / `exchangePrize` の `r.json()` 前に `r.ok` チェックを追加（非2xx時は `console.warn` + 安全なフォールバック値を返す）
3. `exchangePrize` の `alert` 表示は維持（ユーザー向けフィードバックは変更しない）
4. `php -l` 警告0件
5. 202605 / 202606 に影響なし

### Out-of-Scope
- Sentry / 外部エラー送信先への接続
- API のレート制限・リトライロジックの追加

---

## 24. T-17（P2-5）: ガイドモーダルの初期言語統一

### 目的
`ui.blade.php` L47-48 の初期状態が `lang-en` に `active` / `aria-pressed="true"` になっている一方、`js-init.blade.php` L9 の `guideLang = 'jp'` は日本語。初回表示時に画面の「active」ボタン（English）と実効言語（日本語）が食い違う。`setGuideLanguage('jp')`（L195）が DOMContentLoaded で即座に修正するが、その前に英語が active に見える一瞬のちらつきがある。

### 現状
```html
<!-- ui.blade.php L47-48 -->
<button id="lang-jp" class="lang-btn" aria-pressed="false">日本語</button>
<button id="lang-en" class="lang-btn active" aria-pressed="true">English</button>
```

### 成功基準
1. `ui.blade.php` の初期 HTML が `lang-jp` に `active` / `aria-pressed="true"`、`lang-en` に `aria-pressed="false"`（`js-init.blade.php` L9 の `guideLang = 'jp'` と一致）
2. DOMContentLoaded 後の `setGuideLanguage('jp')` による再描画で挙動変化なし（no-op になる）
3. 言語切替ボタン（JP ↔ EN）の切替機能は従来どおり動作する
4. `php -l` 警告0件
5. 202605 / 202606 に影響なし

### Out-of-Scope
- `guideLang` 初期値そのものの変更（`'jp'` → `'en'` 等）
- ガイド文言の翻訳・内容変更

---

## 25. T-18（P3-2）: Cookie `ar_user_id_202609` に `Secure` フラグ追加

### 目的
`js-prize.blade.php` L50 の `CookieHelper202609.set()` が設定する Cookie `ar_user_id_202609` に `Secure` フラグがない。HTTPS 提供環境では、HTTPS 経由で渡されたユーザー識別子が HTTP リダイレクトや MITM 経由で漏洩するリスクがある。

### 現状
```js
document.cookie = name + '=' + value + ';expires=' + exp.toUTCString() + ';path=/;SameSite=Strict';
```

### 成功基準
1. HTTPS 環境（`location.protocol === 'https:'`）で `;Secure` が付与される
2. HTTP 環境（ローカル開発 `php artisan serve`）では `Secure` を付与しない（Cookie が読めなくなる問題回避）
3. 既存の `expires` / `path=/` / `SameSite=Strict` は維持
4. `getUserId202609()` の Cookie 読込・書込フローは従来どおり動作する
5. `php -l` 警告0件
6. 202605 / 202606 に影響なし

### Out-of-Scope
- `CookieHelper202609` の API 変更（`get` / `set` シグネチャは不変）
- `__Host-` prefix への移行

---

## 26. T-19（P3-3）: UUID 生成を `crypto.randomUUID()` に切替（フォールバック維持）

### 目的
`js-prize.blade.php` L63-69 の `generateUUID202609()` が `Math.random()` ベースのUUIDを生成している。`Math.random()` は暗号学的に安全でない乱数であり、ユーザー識別子として使用する場合、予測可能な ID が生成されるリスクがある。モダンブラウザ（Chrome 92+ / Firefox 95+ / Safari 15.4+）は `crypto.randomUUID()` をサポートしている。

### 現状
```js
function generateUUID202609() {
    return 'uid_' + 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
        var r = Math.random() * 16 | 0;
        var v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}
```

### 成功基準
1. `crypto.randomUUID` が利用可能な環境では `crypto.randomUUID()` を使用（`'uid_'` prefix 維持）
2. 非対応ブラウザ（旧版等）では従来の `Math.random()` 方式にフォールバック
3. 生成される ID のフォーマット（`uid_` + UUID文字列）は不変
4. `getUserId202609()` / `generateFingerprint()` の動作は従来どおり
5. `php -l` 警告0件
6. 202605 / 202606 に影響なし

### Out-of-Scope
- サーバー側での ID 発行・検証の導入
- 既存ユーザーの ID のマイグレーション

---

## 27. NEXT-1 (P2-10): `antialias` 条件分岐

### 目的
`scene.blade.php:17` の `antialias: true` が全端末で有効になり、モバイル GPU 負荷の原因になっている。`AR_FORCE_LOWRES === true`（低スペック判定）の端末では `antialias: false` にする。

### 現状
```
<!-- scene.blade.php L17 -->
renderer="... antialias: true;"
```

### 成功基準
1. `AR_FORCE_LOWRES === true` の環境で、`a-scene` の `renderer` 属性が `antialias: false` に設定される
2. `AR_FORCE_LOWRES === false`（高スペック端末）では従来どおり `antialias: true` が維持される
3. モデル描画・マーカー認識に悪影響がない
4. `php -l` 警告0件
5. 202605 / 202606 に影響なし

### Out-of-Scope
- `antialias` 以外の renderer 設定の変更
- A-Frame のバージョン変更

---

## 28. NEXT-2 (P2-8): Audio 遅延生成

### 目的
`js-stamps.blade.php:32-35` の2つの `new Audio` が初回ロードで即生成され、`preload='auto'` で 171KB（71KB + 100KB）を消費する。捕獲が1回も起きなくても初回帯域が発生する無駄を解消する。

### 現状
```js
const soundStamp01 = new Audio("{{ asset('cg/sound_stamp01.mp3') }}");
const soundStamp02 = new Audio("{{ asset('cg/sound_stamp02.mp3') }}");
soundStamp01.preload = 'auto';
soundStamp02.preload = 'auto';
```

### 成功基準
1. 初回ロード時に Audio オブジェクトが生成されない（`null` 初期化）
2. 初回 `playSound` 呼び出し時にのみ Audio オブジェクトが生成される
3. 2回目以降はキャッシュ済みオブジェクトが再利用される
4. サウンド再生機能（音量・タイミング）は従来どおり
5. `php -l` 警告0件
6. 202605 / 202606 に影響なし

### Out-of-Scope
- サウンドファイル自体の圧縮・形式変更
- `js-throw.blade.php` の `playSound` 関数のシグネチャ変更

---

## 29. NEXT-3 (P2-9): `howToOperate.png` LCP 影響低減

### 目的
`ui.blade.php:53` の `howToOperate.png`（1.39MB）がガイドモーダル表示時の LCP に影響している。

### 現状
```html
<img class="howto-main" src="{{ asset('img/howToOperate.png') }}" alt="操作ガイド" />
```

### 成功基準
1. `loading="lazy"` が追加され、ビューポート外では読み込みが Deferred される
2. `decoding="async"` が追加され、非ブロッキングデコードが行われる
3. ガイドモーダルの表示・閉じる機能は従来どおり
4. `php -l` 警告0件
5. 202605 / 202606 に影響なし

### 備考
- 本番環境での更なる改善: `cwebp -q 80 howToOperate.png -o howToOperate.webp` で 1.39MB → 約200〜400KB（運用時に実施）
- 開発環境（`cwebp` 未インストール）では `loading="lazy"` + `decoding="async"` のみ適用

### Out-of-Scope
- 画像ファイル自体の WebP/AVIF 変換（外部ツール `cwebp` 必要）
- 画像の再撮影・設計変更

---

## 30. NEXT-4 (P2-3): 景品モーダル HTML 重複解消

### 目的
`js-prize.blade.php` の `showPrizeCode` / `showRedeemedPrizeInfo` がほぼ同一のモーダル HTML を `innerHTML` 文字列連結（約30行重複）しており、保守性が低い。また、インライン `onclick="this.parentElement.parentElement.remove()"` は DOM 深さに依存する脆い記法。

### 現状
```js
// showPrizeCode: innerHTML 文字列連結 + インライン onclick
// showRedeemedPrizeInfo: 同様の HTML + 日時表示 + インライン onclick
```

### 成功基準
1. 共通関数 `showPrizeModal(config)` に集約
2. インライン `onclick` が `addEventListener` に置換
3. `code` 値が `textContent` で設定（`innerHTML` 経由の XSS 経路を排除）
4. 従来と同じ見た目のモーダルが表示される（交換成功・交換済みの両方）
5. `php -l` 警告0件
6. 202605 / 202606 に影響なし

### Out-of-Scope
- `showPrizeModal` を他ファイルから呼べるようにする（グローバル公開）
- モーダルのアニメーション追加

---

## 31. NEXT-7 (P3-4): `marker-scan-cache-202609-*` localStorage 蓄積クリーンアップ

### 目的
`js-prize.blade.php:140` の `recordMarkerDetection()` が `marker-scan-cache-202609-{markerId}-{YYYY-MM-DD}` キーを localStorage に書き込む。キャンペーン期間（例: 8月25日〜9月10日）で20マーカー×約2週間分＝約280キーが蓄積する。初回ロード時に「今日より古い」キーを1回ループで削除し、ストレージの肥大化を抑制する。

### 現状
```js
// js-prize.blade.php L138-146
function recordMarkerDetection(markerId, markerName) {
    var today    = new Date().toISOString().split('T')[0];
    var cacheKey = 'marker-scan-cache-202609-' + markerId + '-' + today;
    if (localStorage.getItem(cacheKey)) return;
    recordMarkerScan(markerId, markerName, 'marker_scan').then(function () {
        localStorage.setItem(cacheKey, JSON.stringify({ scanned: true, timestamp: new Date().toISOString() }));
    }).catch(function (err) { console.warn('[AR202609] recordMarkerDetection error', err); });
}
```

### 成功基準
1. 初回ロード時に `marker-scan-cache-202609-*` プレフィックスのキーのうち、日付部分（`YYYY-MM-DD`）が今日より前のキーのみが削除される
2. 当日分のキーは削除されない（`recordMarkerDetection` の重複防止機能が維持される）
3. クリーンアップ処理が `try/catch` で囲まれ、`localStorage` アクセスエラー時は `console.warn` でログ出力・例外を投げない
4. 他キャンペーン（202605 / 202606）の localStorage キー（`marker-scan-cache-202603-*` / `marker-scan-cache-202606-*`）には影響しない
5. 既存の `recordMarkerDetection` / `recordMarkerScan` / `checkPrizeExchangeStatus` / `exchangePrize` の動作は従来どおり
6. `php -l` 警告0件
7. 202605 / 202606 に影響なし

### Out-of-Scope
- IndexedDB のクリーンアップ（`ARStampRallyDB202609`）
- 他キャンペーン（202603 / 202605 / 202606）の localStorage キーの削除
- `ar-stamp-rally-202609` / `ar-captured-animals-202609` 等のスタンプデータ
- サーバー側のログ削除

---

## 32. NEXT-5: ポケボール GLB プリロード（毎投擲再パース回避）

### 背景

`throwPokeballInDirection()`（`js-throw.blade.php:48-49`）が**毎投擲** `setAttribute('gltf-model', ...)` で `poke_ball_seinei2.glb`（233KB）を A-Frame の `gltf` コンポーネントに読み込ませる。A-Frame は `<a-assets>` 経由以外は**毎回の読み込みで GLTF を再パース**する（JSON + Binary Buffer デコード + Three.js オブジェクト生成）。さらに `destroyAndFreeEntity` が geometry/material を `dispose()` するため GPU メモリ上の再利用も無い。連続タップ時パースコストが積み上がり、Android（特に低スペック端末）でフレームドロップ / GC スパイキが発生する。

### 目的

GLB を**初回のみパース**し、テンプレートとして保持。以降の投擲では `geometry.clone()` + `material.clone()` による軽量な深さ複製のみを行い、パースコストを排除する。

### Scope

**対象**:
- `js-throw.blade.php`（プリロード IIFE + `createPokeballFromPool()` + `throwPokeballInDirection()` 修正）
- `js-init.blade.php`（`initPokeballPool()` 呼出追加）

**非対象**:
- `aframe-components.blade.php`（`pokeball-throwable` コンポーネント本体は変更しない）
- `scene.blade.php` / `head.blade.php` / `js-stamps.blade.php` / `js-prize.blade.php`
- 202605 / 202606 モジュール

### 成功基準

1. 初回投擲時: プリロードが完了していれば GLB パースなし（`setObject3D` による即座のマッシュ注入）でボールが生成される
2. 投擲後の破壊（`destroyAndFreeEntity`）がテンプレート本体の geometry/material に影響しない（`clone()` 済み）
3. プリロード未完了時は従来どおり `gltf-model` 設定によるロード（フォールバック）
4. 投擲速度・当たり判定・スタンプ取得フローに挙動変更有りなし
5. 202605 / 202606 影響なし
6. `php -l` 警告 0 件

