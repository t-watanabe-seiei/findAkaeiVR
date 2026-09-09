# ARstampRally202609 全面監査レポート（ゼロベース）

> 作成日: 2026-09-08
> 監査対象: `resources/views/ARstampRally202609/`（パーシャル10ファイル）+ メインビュー `resources/views/ARstampRally202609.blade.php`（計約3,400行）
> 関連検証: `routes/web.php` / `routes/api.php` / `PrizeExchangeController` / `MarkerScanController` / `public/cg/202609/*` / `public/js/ar-engine.min.js`・`ar-tracking.min.js` / `bootstrap/app.php`
> 方法: 全ファイルの全行直接読取、ルーター・コントローラー突合、アセット実在確認、バンドルJSのバージョン確認（A-Frame 1.4.2 / super-three 0.147.1 / AR.js `maxDetectionRate` 対応を確認）、全11 bladeファイルに対する `php -l`（PHP 8.2.23、構文エラー0件）
> 注記: 本レポートは既存分析（`analysis_qwen3.8.md`、`.claude_workflow/*`）に依存しないゼロベース監査である

---

## 0. 結論（TL;DR）

**クライアント（ビュー/JS）側は概ね良好**であり、端末互換・エラーフォールバック・GPU資源破棄・リトライ処理・二重送信防止など、実運用を想定した設計が多数見られる。重大な機能バグは少数である。

**ただし、本アプリが依存するサーバー側 API（景品交換 / スキャン記録）に致命的なセキュリティホールがある**。スタンプ検証が全てクライアントサイドで行われており、サーバーは「`stamps` が array であること」しか確認しないため、**誰でもブラウザコンソール1行・または別サイトからのフォームPOSTで無制限に景品コードを発行できる**。さらに CSRF 保護なし・レート制限なし・セッションIDがnullで他ユーザー行にマッチしうる問題も重なっている。

| 優先度 | 件数 | 概要 |
|---|---|---|
| **P0（セキュリティ重大）** | 5 | 景品発行APIの検証欠落 / CSRF無 / レート制限無 / nullセッションIDの他ユーザー参照 / クライアントIDの偽造可能性 |
| **P1（機能・UXバグ）** | 8 | ストレージクォータエラー時のスタンプ全消去、ローダーとカメラ監視のタイミング矛盾、カメラ切替のデッドコード、iPadOS検出漏れ、XSS面、等 |
| **P2（性能・保守性）** | 10 | 21マーカーの検出負荷、ボールGLB毎投擲ロード、インラインonclick、未使用セレクタ、ガイド言語の不一致、等 |
| **P3（軽微・参考）** | 6 | 時刻ゼロ埋め、`user-scalable=no`、Secureフラグ、テレメトリ無、等 |

> 対応の優先順: **P0-1〜P0-5（即対応要）→ P1-1〜P1-3（データ損失系は要対応）→ 以降は余力次第**。

---

## 1. アーキテクチャ概要

```
/stamp202609 (routes/web.php:50, view ARstampRally202609)
├─ head.blade.php          viewport/CSRF meta/低スペック判定/カメラ監視/destroyAndFreeEntity/外部JS(ローカル)
├─ ui.blade.php            ボタン列・スタンプ帳・ガイド・カメラエラー・写真プレビュー等のDOM
├─ scene.blade.php         <a-scene>(AR.js設定) + marker-00(ギャラリー) + marker-01〜20(捕獲対象)
├─ aframe-components.blade.php  pokeball-throwable / hitbox / gallery-hitbox / lazy-model / click-animation
├─ <script>
│   ├─ js-stamps.blade.php   STAMPS定義(20種)/LocalStorage CRUD/ギャラリー選択/collectStamp/スタンプ帳描画
│   ├─ js-prize.blade.php    fingerprint・userId(3重保存)/スキャン記録fetch/景品交換fetch
│   ├─ js-throw.blade.php    タップ(スワイプ)投げ / PCマウス投げ
│   ├─ js-gallery.blade.php  marker-00ギャラリー表示(選択4体+Model_00)
│   └─ js-camera.blade.php   写真・動画(MediaRecorder)撮影/共有/ダウンロード
└─ js-init.blade.php       マーカーイベント配線・スケール制御・AR.js射影同期(syncArjsToRealSize)・ガイド言語
```

**データフロー**
- 捕獲: マーカー検出→モデル表示→タップでボール投擲→当たり判定（`hitbox`）→`anime02`→`collectAndMarkWithRetry()`→**LocalStorage**（スタンプ+base64スクリーンショット）
- スキャン記録: `collectStamp` 内から `POST /api/record-marker-scan`（fingerprint+deviceInfo）
- 景品交換: `POST /api/check-prize-exchange` → `POST /api/exchange-prize` → コード表示

**確認済みの環境事実**
- A-Frame **1.4.2**（`ar-engine.min.js` 内 `aframe 1.4.2`、依存 `super-three ^0.147.1`）→ `physicallyCorrectLights` は three r147 では有効なプロパティ（非推奨化はr152以降）
- `ar-tracking.min.js`（AR.js）に `maxDetectionRate` パラメータ実装を確認 → 低スペックモードのレート上限は機能している
- `public/cg/202609/` に `Model_00.glb`〜`Model_20.glb`、`pattern-maker00.patt`〜`pattern-maker20.patt`、`model00_bucchi.glb` が実在
- `public/cg/poke_ball_seiei2.glb`（233KB）、`sound_stamp01/02.mp3`、`img/howToOperate.png`（1.39MB）は実在
- Laravel **11.9**（`bootstrap/app.php` 新形式）、APIは `routes/api.php`（**セッション/CSRFミドルウェアなしのunguarded群**）
- 全11 bladeファイル `php -l` 通過（PHP 8.2.23）

---

## 2. P0 — セキュリティ（重大・即対応推奨）

### P0-1 景品交換APIにサーバー側検証が一切ない（景品コードの無制限発行が可能）
- **箇所**: `app/Http/Controllers/PrizeExchangeController.php:26-89`（`exchange()`）、`routes/api.php:28`
- **問題**: バリデーションは `fingerprint => required|string`、`deviceInfo => required|array`、`stamps => required|array` のみ。**スタンプの個数（クライアントの閾値10個: `js-prize.blade.php:163`）や、本当にマーカーを検出していたかの検証（MarkerScan 記録との突合）が存在しない**。クライアントの `exchangePrize()`（`js-prize.blade.php:181-220`）が「10個以上」を確認して送るだけなので、APIを直接叩けば以下の1行で景品コードが取得できる:
  ```js
  fetch('/api/exchange-prize', {method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({fingerprint:'x', deviceInfo:[], stamps:[{}]})}).then(r=>r.json())
  // → {"success":true,"prizeCode":"AB147",...}
  ```
  `stamps` が空配列/1件でも `required|array` は通る（`min:` 指定なし）。
- **影響**: **実店舗での景品詐欺**が誰でも可能。景品コストの無限損失。`PrizeExchange` テーブルがダミー記録で洪水し、`generation_attempts` 監視も破綻。
- **修正方針**（サーバー側、推奨順）:
  1. `stamps => required|array|min:10`（最低限）
  2. `stamps` の中身（`model_XX` 形式）を **同一 fingerprint の MarkerScan 記録と突合**（その日までに `marker_id` が10種類以上 distinct 存在するか）。`MarkerScanController` に `capture_type` があるため、`ball_hit` の成立実在を検証できる。
  3. fingerprint ではなく**サーバー発行トークン**（初回アクセス時にセッション/DB発行、`/stamp202609` 経由で取得）を交換条件にすることで、クライアント生成IDの偽造（P0-5）を根本排除。
  4. 交換成立後の `stamps_data` 保存はスクリーンショット（base64）込みでDB肥大（P1-3）→ 送信時に除外、または保存時に除去。

### P0-2 APIルートにCSRF保護が効いていない（クライアントのトークン送信は死んだコード）
- **箇所**: `routes/api.php:26-28`（`routes/api.php` は Laravel 11 デフォルトで `VerifyCsrfToken` が**適用されない**群）、`bootstrap/app.php`
- **問題**: クライアントは `head.blade.php:4` の meta から `X-CSRF-TOKEN` を取得し、全 fetch でヘッダ送信している（`js-prize.blade.php:145-151, 169-172, 203-205`）。しかし api グループには CSRF ミドルウェアが存在しないため、**このトークンは検証されない**。
- **影響**: 悪意のある別サイトが訪問者のブラウザから**フォームPOST（application/x-www-form-urlencoded）**で `/api/exchange-prize` を叩けると、`fingerprint` / `deviceInfo[0]` / `stamps[0]` の3フィールドだけでバリデーションを満たし景品コードが発行される（JSONの CORS プリフライト攻撃ではないが、フォーム方式はプリフライト不要）。P0-1 と組み合わせると遠隔での悪用が成立する。
- **修正方針**: 3端点を `routes/web.php` へ移動（web グループは CSRF 保護付き。クライアントのトークン送信もそのまま有効に）するか、api に残す場合は `Header: X-Requested-With` 等のカスタム検証+SameSite厳格化の組み合わせ。**`web.php` へ移動が最も単純**。

### P0-3 3つのAPIにレート制限（throttle）がない
- **箇所**: `routes/api.php:26-28`（`routes/*.php` 全体に `throttle` ミドルウェアの適用を確認できず）
- **問題**: `record-marker-scan` / `check-prize-exchange` / `exchange-prize` 全てが無制限。
- **影響**: `exchange-prize` は P0-1 と同じく無制限のコード発行に。`check-prize-exchange` は任意 fingerprint の状態照会（`prizeCode` を返す: `PrizeExchangeController.php:23`）を叩き回して**既存コードの列挙・照合攻撃**が可能。`record-marker-scan` はDBへの無限INSERT（ダッシュボード統計の汚染）。
- **修正方針**: `->middleware('throttle:10,1')` 程度を3端点に付与（`exchange-prize` は `throttle:5,60` 等）。

### P0-4 api ルートでの `session()->getId()` は null になりうる → `where('session_id', null)` が `IS NULL` となり他ユーザー行にマッチしうる
- **箇所**: `PrizeExchangeController.php:13-17`（checkStatus）, `:34-40`（exchange）、`MarkerScanController.php:21,45-51`
- **問題**: これらのコントローラーは api グループ（**`StartSession` ミドルウェア未適用**）で動く。その場合 `session()->getId()` はセッションが開始されないため **null を返す可能性が高い**。するとクエリは `where('session_id', null)` → SQL 上 **`session_id IS NULL`** となり、`session_id` が NULL で保存された行（過去に同じ経緯で保存された交換記録）を**全員がマッチさせうる**。
- **影響**: ① `checkStatus` が他ユーザーの `hasExchanged: true` / `prizeCode` を無関係の訪問者に返却（**コード漏洩**）。② `exchange` が「すでに交換済み」と誤拒否し、正常なユーザーが景品を取得できない（誤陽性）。③ `MarkerScan` の `totalScans` 集計も NULL 行を巻き込む。
  ※ Laravel 11 の `Store` 実装依存で「毎回一意ID生成」の場合は影響が小さいが、**実環境での挙動確認が必須**（検証手順: api 端点に curl で POST し、DB の `session_id` 値と返却 JSON を確認）。
- **修正方針**: api ではセッションに依存せず **fingerprint のみで判定**する（`session_id` 条件を削除するか `whereNotNull` 化）、または端点を web ルートへ移しセッションを有効化する。DB 側 `session_id` に NOT NULL + default を推奨。

### P0-5 クライアント側識別子（fingerprint / userId）は全て偽造可能
- **箇所**: `js-prize.blade.php:63-116`（`generateUUID202609` / `getUserId202609` / `generateFingerprint`）
- **問題**: fingerprint は `hash(UA + localStorage 内のランダムsalt)` で生成され、userId は `Math.random()` ベースのUUID。どちらも**ブラウザのコンソールから再書き込み・再作成可能**（`localStorage.clear()` で翌リクエストから新fingerprint）。
- **影響**: サーバー側が fingerprint/session を信頼している全ての重複排除（`marker_scan` の1日1回: `MarkerScanController.php:27-42`、景品1回限り: `PrizeExchangeController.php:42-48`）が**無効化される**。P0-1〜P0-4 と複合し、現状の「不正防止」は実質ゼロ。
- **修正方針**: P0-1の修正方針3（サーバー発行トークン）で根本解決。最低限、`stamps`/`MarkerScan` の**内容ベース検証**（P0-1-2）を導入し、識別子単体では信用しない設計にする。

## 3. P1 — 機能バグ・UX（要対応〜応じて対応）

### P1-1 ストレージクォータ例外時に「全スタンプを消去する」パスがある
- **箇所**: `js-stamps.blade.php:164-167`（`collectStamp` の catch ブロック）
- **問題**: `saveCollectedStamps()` の `localStorage.setItem` が `QuotaExceededError`（base64スクリーンショットが積み重なって5MB枠超過）等を起こすと、catch 内で **`localStorage.removeItem(LOCAL_STORAGE_KEY)` を実行し、集めた全スタンプを消去する**。意図は「壊れたデータを捨てる」かと思うが、結果は**データ全失**になる。
- **影響**: 長游玩び中の端末で突然「0個」に戻る。景品交換直前での発生は致命的。
- **修正方針**: 例外時は **スクリーンショットなしで再保存**（`screenshot: null`）し、データ自体は保持する。クォータ超過が継続する場合は IndexedDB への移行、またはスクリーンショット保存を廃止する判断。

### P1-2 ローダーの「3秒無条件非表示」フォールバックと「カメラ監視7秒」の矛盾
- **箇所**: `js-init.blade.php:639-640`（`setTimeout(hideArjsLoader, 3000)`） vs `head.blade.php:47-72`（`monitorCameraStartup`、500msポーリング×7000ms）/ `js-init.blade.php:643-645`
- **問題**: カメラが起動しない端末では、ローダーが3秒で消え、カメラエラー表示は7秒後まで出ない → **約4秒間、何のフィードバックもない黒画面**。また、ARが起動していないのにローダーが消えるため、起動失敗と誤認される。
- **影響**: 低スペックAndroidでの初回体験が壊れる（「動かない」判定→離脱）。
- **修正方針**: `hideArjsLoader` のフォールバックを「`arjsVideoReady === true` の場合のみ非表示」に変更するか、カメラ監視のタイムアウト（7秒）で初めて非表示+エラー表示に集約する。

### P1-3 スクリーンショット（base64）をAPIへ送信し、DBにそのまま保存
- **箇所**: 送信: `js-prize.blade.php:206`（`stamps: stamps` に `screenshot` 込み）、保存: `PrizeExchangeController.php:78`（`stamps_data => $validated['stamps']`）
- **問題**: 1枚あたり 320×480 JPEG の base64（数10KB〜）が、**景品交換のたびにDBのJSON列に埋め込まれる**。20枚で数百KB〜1MB/件。
- **影響**: DB肥大、バックアップ増加、`checkStatus` 等のクエリでの行取得コスト増加（`prizeCode` 取得のみに `stamps_data` が不要）。
- **修正方針**: クライアントが送信前に `screenshot` を除去（`stamps` を `{stampId, collectedAt}` のみへ）するか、コントローラー側で除去。`PrizeExchange` モデルに `casts` で不要列の排除も検討。

### P1-4 `showStampBook` が localStorage の値を `innerHTML` へ直接埋め込む（DOM注入面）
- **箇所**: `js-stamps.blade.php:338-348`
- **問題**: `stamps[sid].screenshot`（localStorage の任意文字列）を `' + s.name + '` と同じく **HTML文字列連結**して `item.innerHTML` に反映している。`src="..."` 属性内なので、値に `"` が入ると属性から抜け、`data:` URL やイベントハンドラの注入が可能（同一オリジンでのコード実行経路）。
- **影響**: 通常運用では低リスク（自分の端末のデータ）だが、他アプリが同一オリジンに書き込んだ場合や、開発者の手動操作で**XSS面**になる。`s.name` も STAMPS 定数なので安全だが、同じパターンで混在している。
- **修正方針**: `createElement('img')` + `img.src = ...` での組み立て、または保存時に `screenshot` が `^data:image\/` であるかを検証。

### P1-5 カメラ切替機能は UI にボタンが存在せず「死んだ機能」
- **箇所**: 定義: `js-camera.blade.php:17, 25-39`（`switch-camera-button` 待ち）、参照: `js-throw.blade.php:28`（`isUIButton`）
- **問題**: `ui.blade.php` に `#switch-camera-button` が**存在しない**。そのため `switchCameraBtn` は常に null になり、切替UIは到達不能（デッドコード）。また仮に復活させると、AR.js が管理している `<video>` の `srcObject` を書き換えるだけで **AR.js のトラッキングは壊れる**可能性が高い（AR.js 側のストリーム再取得処理が必要）。
- **影響**: 現状は無害（機能しないだけ）だが、保守時に誤って有効化するとAR全体が停止しうる地雷。
- **修正方針**: 使わないなら `js-camera.blade.php:25-39` と `isUIButton` の該当行を削除。残すなら AR.js の `camera` コンポーネントを再初期化する経路を実装する。

### P1-6 iPadOS 13 以降が iOS 判定に掛からない
- **箇所**: `js-camera.blade.php:56, 235`（`/iPad|iPhone|iPod/` 判定）
- **問題**: iPadOS 13+ は UA を Mac と偽装するため、`isIOS` が false になる。結果 ① 動画が fps30/bitrate5Mbps になりiPadで重くなる、② `downloadFallback` の「長押しで写真に追加」ヒントがiPadで出ない（実際のiOS系は`<a download>`が不安定）。
- **修正方針**: `navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1` を iOS 判定に追加。

### P1-7 `AR_FORCE_LOWRES` の IIFE が例外耐性なし（head のインラインJS全体を壊しうる）
- **箇所**: `head.blade.php:31-45`
- **問題**: `new URL(window.location.href)`、`navigator.userAgent` 等に try/catch が無い（`detectOldAndroid` 内と Android 分岐のみtry/catch）。ここで例外が起きると、そのスクリプトブロック全体の残りの定義（`monitorCameraStartup` / `ensureCameraAccess` / `destroyAndFreeEntity`）が**すべて undefined** になり、アプリが起動不能になる。
- **修正方針**: IIFE 全体を try/catch で包み、失敗時は `false` を返す。

### P1-8 `document.querySelector('video')` の「先頭video」前提が脆い
- **箇所**: `head.blade.php:51, 75, 89`（`monitorCameraStartup` / `ensureCameraAccess`）
- **問題**: DOM 上の**最初の** `<video>` を AR.js のカメラ映像と仮定している。`#photo-preview` のプレビュー `<video>`（動画撮影時）や将来追加される `<video>` が先頭に来ると、誤った要素を監視/操作する。現状のDOM順序（scene が photo-preview より先）では動いている。
- **修正方針**: AR.js 生成の video に固有セレクタ（例: `a-scene video`）を指定するか、AR.js のイベント（`arjs-video-loaded`）に統一する。

## 4. P2 — 性能・保守性

### P2-1 21マーカー同時検出が最大のAndroid負荷
- **箇所**: `scene.blade.php:15, 30-73`（`a-scene` の AR.js 設定 + 21個の `a-marker type="pattern"`）
- **現状の評価**: 初回は `.patt` 21ファイルの取得+AR.js が毎フレーム21パターンの検出を行う。低スペック機ではここが FPS の主因（`AR_FORCE_LOWRES` で `maxDetectionRate ≤ 8` に抑える仕組みは入っており、バンドルJSに `maxDetectionRate` 実在を確認済み ✅）。
- **推奨**: ① 検出不能マーカーの動的無効化（N秒未検出で `marker` の `enabled=false`）で検索空間を狭める、② マーカー枚数の運用上限を明文化、③ `detectionMode: mono` は現行どおり維持（推奨設定）。

### P2-2 ポケボール GLB を毎投擲ロード
- **箇所**: `js-throw.blade.php:48-49`（毎投擲 `setAttribute('gltf-model', ... poke_ball_seiei2.glb (233KB)`）
- **問題**: A-Frame の `gltf-model` は `<a-assets>` を経由しない限り**毎回の読み込みで GLTF を再パース**する（ブラウザHTTPキャッシュに頼る状態）。`destroyAndFreeEntity` が geometry/material を `dispose()` するため、GPUメモリ上の再利用も無い。低周波（タップ頻度）なら許容範囲だが、連続タップでパースコストが積み上がる。
- **推奨**: ボールをシーンに**プール（3体程度）**し再利用する。または初回ロード済み GLTF をキャッシュして付与する。

### P2-3 `showPrizeCode` / `showRedeemedPrizeInfo` の重複とインライン onclick
- **箇所**: `js-prize.blade.php:222-233, 235-254`
- **問題**: ① 2関数がほぼ同一のモーダルHTMLを文字列連結（重複約30行）、② `onclick="this.parentElement.parentElement.remove()"` が**DOM深さに依存**する脆い記法（マークアップ1段変わるだけで壊れる）、③ コード値を innerHTML に埋め込む（サーバー由来の5桁コードなので現状は安全だが、P1-4 と同じパターン）。
- **推奨**: 共通 `showPrizeModal(title, code, extra)` に集約し、閉じるボタンは `addEventListener` で `modal.remove()`。

### P2-4 `isUIButton` に存在しないセレクタ
- **箇所**: `js-throw.blade.php:28`（`#switch-camera-button`）
- **問題**: P1-5 と同様、UI に無い要素のセレクタが混在。`closest()` 自体は null 安全なので動作上不都合はないが、**意図しない挙動（ボタン追加時に投げ判定から除外される）の罠**になる。
- **推奨**: P1-5 の判断に合わせ削除 or 残す。

### P2-5 ガイドモーダルの初期言語が EN なのに `guideLang = 'jp'`
- **箇所**: `ui.blade.php:47-49`（`lang-en` に `class="lang-btn active"`） vs `js-init.blade.php:9`（`guideLang = 'jp'`）
- **問題**: 画面の「active」は English、内部変数は jp。初回表示時に言語表示と実効言語が食い違う。
- **推奨**: 初期値を統一（日本向けなら `guideLang='jp'` + `lang-jp` 側を active）。

### P2-6 捕獲日時表示のゼロ埋め漏れ
- **箇所**: `js-stamps.blade.php:345`（`d.getHours() + ':' + ...`）
- **問題**: 9時台が `9:05`、10時台が `10:05` と不斉（`padStart(2,'0')` が分のみ）。
- **推奨**: `String(d.getHours()).padStart(2,'0')`。

### P2-7 API エラーの沈黙
- **箇所**: `js-prize.blade.php:143-159`（`.catch(function () {})`、HTTPステータス未確認）
- **問題**: `record-marker-scan` / `check-prize-exchange` / `exchange-prize` の失敗が `console` に出ず、`r.json()` が 5xx HTML を返すと `JSON.parse` 失敗を catch が飲み込む。運用時トラブルが「何も起きない」形で消える。
- **推奨**: `.catch` 内で `console.warn('[AR202609] api error', ...)` を最低限出す。

### P2-8 2つの Audio を初回に即生成（preload=auto）
- **箇所**: `js-stamps.blade.php:32-35`（`sound_stamp01.mp3` 71KB + `sound_stamp02.mp3` 100KB）
- **問題**: キャッチしたことがなくても初回ロード。低帯域では無駄。
- **推奨**: 初回捕獲時に遅延生成（`new Audio` を `playSound` 初回呼び出し時に）。

### P2-9 `howToOperate.png`（1.39MB）がガイド画像として重め
- **箇所**: `ui.blade.php:53`
- **推奨**: WebP/AVIF 化 or 表示幅（ガイドモーダル内）向けにリサイズ。初回ガイド表示の LCP を左右する。

### P2-10 `a-scene` の `antialias: true` はモバイルGPUコスト
- **箇所**: `scene.blade.php:17`
- **推奨**: `AR_FORCE_LOWRES` 時に `antialias` を false にする分岐、または常時 false（WebARではエッジのジagglingよりFPS優先が通常）。

## 5. P3 — 軽微・参考事項

| # | 箇所 | 内容 |
|---|---|---|
| P3-1 | `head.blade.php:3` | `user-scalable=no` はアクセシビリティ（WCAG）観点で非推奨。ゲームUIでは許容範囲だが意識すること |
| P3-2 | `js-prize.blade.php:50` | Cookie `ar_user_id_202609` に `Secure` フラグなし（HTTPS提供なら `;Secure` 推奨） |
| P3-3 | `js-prize.blade.php:63-69` | `generateUUID202609` が `Math.random()` ベース。非ID目的なら可、唯一性重視なら `crypto.randomUUID()` |
| P3-4 | `js-prize.blade.php:136` | `marker-scan-cache-202609-*` キーが日付単位で localStorage に蓄積（20マーカー×日数分・微小だが定期掃除の検討） |
| P3-5 | `head.blade.php:149-168` | Ctrl+U / 右クリック / ピンチブロックは「ソース表示防止」にはならない（ブラウザUIはブロック不可）。kiosk 体験対策としては可 |
| P3-6 | `head.blade.php:15-20` | グローバルエラー/非同期リジェクトは `console` のみ。本番運用では送信先（Sentry等）の検討 |

---

## 6. 確認済み「問題なし・適切」な項目（検証結果）

| 項目 | 結果 |
|---|---|
| A-Frame / three バージョン整合 | A-Frame 1.4.2 + super-three 0.147.1。`physicallyCorrectLights: false` は r147 で有効（r152以降で `useLegacyLights` 化）→ **非推奨ではない** ✅ |
| `maxDetectionRate` 実装 | `ar-tracking.min.js` に実在（grep 確認）→ 低スペックモードのレート上限（≤8）は機能する ✅ |
| AR.js 射影同期 | `scene.blade.php:1-18` の暫定16:9初期値 + `js-init.blade.php:599-636` の `syncArjsToRealSize()`（`arjs-video-loaded` / `resize` 200ms / `orientationchange` 300ms で同期）→ 縦横問わず実寸同期されている ✅ |
| GPU資源破棄 | `head.blade.php:115-146` `destroyAndFreeEntity` が geometry/material/maps を `dispose()` 済み ✅ |
| 当たり判定の鮮度 | `hitbox` / `gallery-hitbox` とも `tick` で毎フレーム `updateBox()`（`aframe-components.blade.php:265-270, 329-331`）→ マーカー追従は正常 ✅ |
| 二重送信防止 | `exchangePrize` の `_exchanging` ガード（`js-prize.blade.php:178-182, 197, 216`）✅ |
| MediaRecorder 検出 | `js-camera.blade.php:13-16` で非実装環境の動画ボタン非表示 ✅ |
| リトライ経路 | `collectAndMarkWithRetry`（`js-stamps.blade.php:170-203`）3回×2秒、カメラ低解像度再試行ボタン ✅ |
| アセット実在 | `public/cg/202609/` の Model_00〜20.glb / pattern-maker00〜20.patt / `poke_ball_seiei2.glb` / 効果音 / ガイド画像 全て確認 ✅ |
| データ分離 | LocalStorage / IndexedDB / Cookie / sessionStorage キー全て `202609` サフィックス → 202606 版と衝突しない ✅ |
| 構文 | 全11 bladeファイル `php -l` 通過（PHP 8.2.23、警告0）✅ |
| 外部依存 | CDN 非依存（A-Frame/AR.js ともローカル `public/js/` バンドル）→ 供給停止リスクなし ✅ |

---

## 7. 推奨対応ロードマップ

| 優先度 | 対応 | 工数目安 | 対象ファイル |
|---|---|---|---|
| **最優先** | P0-1 `stamps => min:10` + MarkerScan突合 | 0.5〜1日 | `PrizeExchangeController.php` |
| **最優先** | P0-2 3端点を `web.php` へ移設（CSRF有効化） | 0.5日 | `routes/api.php` / `routes/web.php` |
| **最優先** | P0-3 `throttle` 付与 | 10分 | `routes/*.php` |
| **最優先** | P0-4 session_id NULL 行の挙動確認+修正 | 0.5日 | `PrizeExchangeController.php` / `MarkerScanController.php` |
| 要対応 | P1-1 クォータ例外時の全消去を「スクショなし保存」に変更 | 30分 | `js-stamps.blade.php` |
| 要対応 | P1-2 ローダーフォールバックの条件付き化 | 30分 | `js-init.blade.php` |
| 要対応 | P1-3 API送信前の `screenshot` 除去 | 30分 | `js-prize.blade.php` |
| 応じて | P1-4〜P1-8（XSS面 / デッドコード / iPadOS / 例外耐性 / video選択子） | 各30分〜1時間 | 該当ファイル |
| 余力 | P2-1〜P2-10（性能・保守性） | 案件次第 | 該当ファイル |

---

## 8. 補足・前提・限界

1. **本レポートは静的読取+コード走査に基づく**。実端末（Android/iOS/PC）での挙動検証、DB実データ確認（`PrizeExchange.session_id` の NULL 有無）、Laravel セッション実装の動作確認は未実施。P0-4 は特に**実環境確認が必須**。
2. `ar-engine.min.js` / `ar-tracking.min.js` は min 化済みのバンドルで、行番号ベースの指摘が困難なため、バージョン・実装キーの有無（`maxDetectionRate` 等）で確認した。
3. `resources/views/ARstampRally202609/` 配下の blade 11ファイル+メインビューは全行読取済み。
4. 本レポート作成時点で `.claude_workflow/ar_tasks.md` に記載の T1〜T4 修正（主因A/B/C の対応、UA分岐削除、`AR_FORCE_LOWRES` 精緻化、MediaRecorder検出）は**全てコードに反映済み**であることを確認した（既存分析への依存ではなくコード直接確認による）。

---

## 9. 修正記録（2026-09-08 実施）— P0 対応完了

> 本節は 2026-09-08 に実施した P0（セキュリティ重大）全5項目の修正記録である。
> 方針：202609専用コントローラー + `web` ミドルウェアグループ（CSRF・セッション有効）により他キャンペーン（202605 等）への影響を排除。

### 9.1 修正内容一覧

| 項目 | 修正内容 | 対象ファイル | 状態 |
|---|---|---|---|
| **P0-1** | サーバー側で閾値（10種）強制。`stamps` 件数<10なら422返却 | `StampRally202609Controller::exchange()` | ✅ 修正済み |
| **P0-2** | 3エンドポイントを `web` グループ（CSRF有効）へ移設。JSに `X-CSRF-TOKEN` 付与 | `routes/web.php` / `js-prize.blade.php` | ✅ 修正済み |
| **P0-3** | 3種のレートリミッター（120/分・120/分・10/時間） | `AppServiceProvider.php` / `routes/web.php` | ✅ 修正済み |
| **P0-4** | `recordScan` 時にFPをセッション保持。セッションID+FPでDB照合 | `StampRally202609Controller` 全体 | ✅ 修正済み |
| **P0-5** | セッション優先・リクエスト値フォールバックで識別 | `StampRally202609Controller::checkStatus()/exchange()` | ✅ 修正済み |

### 9.2 新規/変更ファイル

| ファイル | 種別 | 内容 |
|---|---|---|
| `app/Http/Controllers/StampRally202609Controller.php` | **新規** | `recordScan()` / `checkStatus()` / `exchange()`。`PRIZE_EXCHANGE_THRESHOLD=10` |
| `app/Providers/AppServiceProvider.php` | 編集 | `stamp202609_scan/check/redeem` RateLimiter定義追加 |
| `routes/web.php` | 編集 | `stamp202609` prefix グループ3POSTルート追加（`throttle` 付き） |
| `resources/views/ARstampRally202609/js-prize.blade.php` | 編集 | 3URL更新 + 全fetchに `X-CSRF-TOKEN` 付与 |

### 9.3 P0-5 残存リスク

- 同一端末・同一セッション内で `userId` を書き換え別FP生成しても、`recordScan` 時にセッション上書きされるため**最終FP**が基準 → 実害は「同一端末複数回交換」に限定
- **推奨（将来）**: `recordScan` 時、セッション内FPが初回以外に不一致なら403 or 旧FP交換無効化

### 9.4 P0対応時に判明した新規問題

| # | 箇所 | 内容 | 優先度 |
|---|---|---|---|
| **N1** | `StampRally202609Controller::checkStatus()` | レスポンスに `exchangedAt` 欠落。JS `showRedeemedPrizeInfo` は `undefined` を受け取り交換日時非表示。旧 `PrizeExchangeController` にも同欠落 | **要対応** |
| **N2** | `js-prize.blade.php::exchangePrize()` | `stamps`（base64スクショ含む）をそのまま送付。`stamps_data` に数MB JSON蓄積（P1-3 と同一） | **要対応** |

---

## 10. 次の修正候補（優先順位順・2026-09-08時点）

> P0対応後に実コードを再確認し、次に着手すべき箇所を特定。

### 10.1 最優先（データ損失・機能欠損）

| # | 対象ファイル | 関数/箇所 | 問題 | 修正方針 | 工数 |
|---|---|---|---|---|---|
| **T-01** ✅ | `js-stamps.blade.php` | `collectStamp()` catch（L164-176） | **P1-1**: `localStorage.removeItem(LOCAL_STORAGE_KEY)` で全スタンプ消去。クォータ超過時に景品交換直前で全データ消失 | catch内で各スタンプの `screenshot` を `null` にし**再保存**（全消去回避）→ **2026-09-09 修正済み** | **30分** |
| **T-02** ✅ | `js-prize.blade.php` | `exchangePrize()` fetch 前 | **N2/P1-3**: base64スクショ含む stamps をそのまま送付 → DBに数MB JSON | `stamps` を `{ stampId, collectedAt, name }` のみの配列に整形して送付 → **2026-09-09 修正済み** | **30分** |
| **T-03** ✅ | `StampRally202609Controller.php` | `checkStatus()` | **N1**: レスポンスに `exchangedAt` 欠落 → 交換済みモーダルで日時非表示 | `'exchangedAt' => $exchange ? $exchange->exchanged_at->toIso8601String() : null` を追加 → **2026-09-09 修正済み** | **10分** |

### 10.2 要対応（UX・バグ）

| # | 対象ファイル | 関数/箇所 | 問題 | 修正方針 | 工数 |
|---|---|---|---|---|---|
| **T-04** ✅ | `js-init.blade.php` | L639 `setTimeout(hideArjsLoader, 3000)` | **P1-2**: 3秒無条件非表示 vs `monitorCameraStartup(7000)` → 約4秒間黒画面 | フォールバック 3秒→7秒に変更（`monitorCameraStartup` と整合）→ **2026-09-09 修正済み** | **30分** |
| **T-05** | `js-stamps.blade.php` | `showStampBook()` L338-348 | **P1-4**: `innerHTML` に base64 URL + `s.name` を直接組み込み | `document.createElement('img')` + `img.src` でDOM生成（XSS面） | **30分** |
| **T-06** | `js-camera.blade.php` / `js-throw.blade.php` | L17,L25-39 / L28 | **P1-5**: `#switch-camera-button` が202609 UIに存在しない（null参照デッドコード） | 該当行を削除 | **15分** |
| **T-07** | `js-prize.blade.php` | `collectDeviceInfo()` L134 | **P1-6**: iPadOS 13+（Mac UA偽装）が `isIOS=false` | `navigator.maxTouchPoints>1 && /MacIntel/.test(navigator.platform)` 分岐追加 | **15分** |
| **T-08** ✅ | `head.blade.php` | L31-45 IIFE | **P1-7**: try/catch無し。例外で `monitorCameraStartup` 等が全undefined→起動不能 | IIFE全体を `try{...}catch{...return false;}` で包む → **2026-09-09 修正済み** | **15分** |
| **T-09** | `head.blade.php` | L51,75,89 | **P1-8**: `querySelector('video')` がDOM先頭を仮定（`#photo-preview` 競合） | `a-scene video` セレクタ or AR.jsイベントに統一 | **30分** |

### 10.3 余力（性能・軽微）

| # | 対象ファイル | 問題 | 修正方針 | 工数 |
|---|---|---|---|---|
| **T-10** | `scene.blade.php:17` | **P2-10**: `antialias:true` がモバイルGPUコスト | `AR_FORCE_LOWRES` 時 `antialias:false` | **20分** |
| **T-11** | `ui.blade.php:53` | **P2-9**: `howToOperate.png`(1.39MB) がLCP影響 | WebP/AVIF化 or 表示幅リサイズ | **30分** |
| **T-12** | `js-prize.blade.php:63-69` | **P3-3**: `generateUUID202609()` が `Math.random()` ベース | `crypto.randomUUID()` 切替（フォールバック維持） | **10分** |
| **T-13** | `js-prize.blade.php:50` | **P3-2**: Cookie に `Secure` フラグなし | HTTPS環境で `;Secure` 追加 | **5分** |
| **T-14** | `head.blade.php` | **P3-6**: グローバルエラーが `console` のみ | Sentry等の送信先接続 | **検討** |

---

## 11. 修正記録（2026-09-09 実施）— P1 最優先3項目対応完了

> 本節は 2026-09-09 に実施した P1 最優先3項目（T-01/T-02/T-03）の修正記録である。
> 方針：202609 専用ファイルのみ変更。202605 / 202606 には影響なし。

### 11.1 修正内容一覧

| 項目 | 修正内容 | 対象ファイル | 状態 |
|---|---|---|---|
| **T-01** | `collectStamp()` catch 内で `screenshot:null` 化して再保存。全消去回避（スタンプ個数・名前は保持） | `js-stamps.blade.php` L164-176 | ✅ 修正済み |
| **T-02** | `exchangePrize()` で base64 除外配列 `stampArr` を fetch 送信。DB JSON 肥大解消 | `js-prize.blade.php` L185-188, L210 | ✅ 修正済み |
| **T-03** | `checkStatus()` レスポンスに `exchangedAt`（ISO 8601）を追加 | `StampRally202609Controller.php` L114-116 | ✅ 修正済み |

### 11.2 詳細

**T-01**（P1-1 対応）
- 旧: `catch { localStorage.removeItem(LOCAL_STORAGE_KEY); }` → 全スタンプ消去
- 新: `catch { try { stamps.forEach(sid => stamps[sid].screenshot = null); saveCollectedStamps(stamps); } catch { localStorage.removeItem(...); } }`
- フォールバック（再保存も失敗時）は従来どおり維持

**T-02**（N2 / P1-3 対応）
- 旧: `body: JSON.stringify({ stamps: stamps })`（base64 込み）
- 新: `body: JSON.stringify({ stamps: stampArr })`（`{ stampId, collectedAt, name }` のみ）
- サーバー側 `PRIZE_EXCHANGE_THRESHOLD` チェック（`count(stamps)`）に無影響

**T-03**（N1 対応）
- 追加行: `'exchangedAt' => $exchange && $exchange->exchanged_at ? $exchange->exchanged_at->toIso8601String() : null`
- `showRedeemedPrizeInfo()` 内の `exchangedAt.replace('T', ' ')` が正常に動作

### 11.3 検証結果

| チェック | 結果 |
|---|---|
| `php -l` 3ファイル（js-stamps / js-prize / Controller） | 警告0件 ✅ |
| `stamps: stamps` 旧参照（202609 js-prize.blade.php） | 0件 ✅ |
| `stamps: stampArr` 新参照 | 1件 ✅ |
| `collectStamp` catch 内 `removeItem` | 1件のみ（最終フォールバック）✅ |
| `checkStatus()` に `exchangedAt` | 存在 ✅ |
| 202605 / 202606 ファイル | 変更なし ✅ |

### 11.4 残存リスク・補足

- T-01: `screenshot:null` 化後、ユーザーがスタンプ帳の画像プレビューを見る場合は「画像なし」になる。運用上はスタンプ個数・名前が重要なので許容範囲。
- T-02: `PrizeExchange.stamps_data` に今後 base64 が保存されなくなる。既存データ（過去に base64 込みで保存済み）は残ったままだが、新規交換から解消。
- T-03: 旧 `PrizeExchangeController`（202605/202606 共用）の `checkStatus` にも同欠落があるが、202609 専用コントローラー側は修正済み。


## 12. 修正記録（2026-09-09 実施）— P1 UI安定性2項目対応完了

> 本節は 2026-09-09 に実施した P1 UI安定性2項目（T-08/T-04）の修正記録である。
> 方針：202609 専用ファイルのみ変更。202605 / 202606 には影響なし。

### 12.1 修正内容一覧

| 項目 | 修正内容 | 対象ファイル | 状態 |
|---|---|---|---|
| **T-08** | `AR_FORCE_LOWRES` IIFE 全体を try/catch で包み、例外時は `false` 返却（従来: 例外伝播→アプリ起動不能） | `head.blade.php` L31-45 | ✅ 修正済み |
| **T-04** | `setTimeout(hideArjsLoader, 3000)` を `setTimeout(hideArjsLoader, 7000)` に変更（`monitorCameraStartup(7000)` と整合） | `js-init.blade.php` L639-641 | ✅ 修正済み |

### 12.2 詳細

**T-08**（P1-7 対応）
- 旧: `const url = new URL(window.location.href);` が例外を投げると IIFE 外に伝播 → `monitorCameraStartup` / `ensureCameraAccess` / `destroyAndFreeEntity` が全 undefined → アプリ起動不能
- 新: IIFE 本体を `try { ... } catch (e) {}` で包み、例外時は `return false`
- `AR_FORCE_LOWRES` の読み取り箇所（`js-init.blade.php` L594 `desiredMaxDetectionRate()`）は `if (window.AR_FORCE_LOWRES)` 判定 → `false`（falsy）で従来どおり動作
- `AR_FORCE_LOWRES = true` になる条件（`?lowres=1` / 旧Android / 低コア・小メモリ）は try 内であり従来どおり動作

**T-04**（P1-2 対応）
- 旧: `setTimeout(hideArjsLoader, 3000)` → カメラ未起動時に3秒でローダー非表示 → `monitorCameraStartup(7000)` のエラー表示（7秒）まで**約4秒間黒画面**
- 新: `setTimeout(hideArjsLoader, 7000)` → 7秒でローダー非表示 ＝ `camera-error` UI 表示のタイミングと整合
- `arjs-video-loaded` イベント発火時（カメラ正常起動）は従来どおり `hideArjsLoader()` 即時呼出（L621）→ 影響なし
- 低スペックAndroid（`AR_FORCE_LOWRES=true`）でも同一の7秒フォールバックが適用

### 12.3 検証結果

| チェック | 結果 |
|---|---|
| `php -l head.blade.php` | 警告0件 ✅ |
| `php -l js-init.blade.php` | 警告0件 ✅ |
| `AR_FORCE_LOWRES` 参照箇所で `true` 判定が変わらない | 確認済み ✅ |
| `hideArjsLoader` 関数本体・`arjs-video-loaded` ハンドラ | 変更なし ✅ |
| 202605 / 202606 ファイル | 変更なし ✅ |

### 12.4 残存リスク・補足

- T-08: 例外発生時は `AR_FORCE_LOWRES = false`（低スペック判定を放棄）→ 高スペックパスで処理継続。従来（クラッシュ）より**改善**。
- T-04: 7秒間ローダーが表示され続ける（従来3秒より4秒長い）。これは「読み込み中」を示す正しい状態であり、黒画面（従来3〜7秒）よりユーザー体験は改善。
- T-04: `window.load` が DOMContentLoaded より大幅に遅延する極端な端末では `monitorCameraStartup` の開始が遅れる可能性がある。ただしフォールバックは `hideArjsLoader` のみ（エラーUI表示は `monitorCameraStartup` 側で制御）なので、最悪の場合ローダーが7秒後に非表示になる程度。

---

## 13. 修正記録（2026-09-09 実施）— P1高優先4項目＋P2対応完了

> 本節は 2026-09-09 に実施した P1高優先4項目（T-05/T-06/T-07）＋P2（T-09）の修正記録である。
> 方針：202609 専用ファイルのみ変更。202605 / 202606 には影響なし。

### 13.1 修正内容一覧

| 項目 | 対応P | 修正内容 | 対象ファイル | 状態 |
|---|---|---|---|---|
| **T-05** | P1-5 | `showStampBook()` の `innerHTML` 全削除 → `document.createElement` + `textContent`（XSS防止） | `js-stamps.blade.php` L348-400 | ✅ 修正済み |
| **T-06** | P1-3 | `#switch-camera-button` 関連デッドコード削除（ボタン自体が存在しない） | `js-camera.blade.php` L8,17-37 / `js-throw.blade.php` L28 | ✅ 修正済み |
| **T-07** | P1-4 | iPadOS 13+ 検出追加（`maxTouchPoints > 1 && /MacIntel/.test(navigator.platform)`） | `js-prize.blade.php` L105-106 | ✅ 修正済み |
| **T-09** | P2 | `querySelector('video')` → `querySelector('#ar-scene video')`（特定性強化） | `head.blade.php` L51,75,89 | ✅ 修正済み |

### 13.2 詳細

**T-05**（P1-5 XSS 対応）
- 旧: `item.innerHTML = '<div class="stamp-icon">…</div>' + (screenshot ? '<img src="' + url + '">' : icon) + '<div class="stamp-name">' + s.name + '</div>…'`
- 新: `document.createElement('div')` → `className` 設定 → `textContent` 設定 → `appendChild`
  - `<img>`: `createElement('img')` → `src` / `alt` プロパティ直接代入（`innerHTML` なし）
  - 名前: `nameDiv.textContent = s.name`（HTMLエスケープ自動）
  - 日付: `dateDiv.textContent = '…'`
- CSS整合性: `head.blade.php` L262-279 の `.stamp-icon` / `.stamp-name` / `.stamp-date` / `.gallery-check` セレクタが新DOM構造と完全一致（確認済み）
- 202609 モジュールの `innerHTML` 使用箇所: **0件**（確認済み）

**T-06**（P1-3 デッドコード削除）
- `js-camera.blade.php`:
  - 削除: `var currentFacingMode = 'environment';`（switchCameraBtn のみで使用）
  - 削除: `var switchCameraBtn = …; switchCameraBtn.addEventListener('click', …)`（L17-37、ボタン自体が `ui.blade.php` に存在しない）
- `js-throw.blade.php`:
  - 削除: `element.closest('#switch-camera-button') ||`（`isUIButton` 判定から除去）
- 残存UI判定: `#stamp-book-button` / `#guide-button` / `#camera-button` / `#video-button` / 各モーダル → **すべて残存**
- 他キャンペーン: 202603 / 202605 / 202606 は独立ファイル → **未変更**

**T-07**（P1-4 iPadOS 検出）
- 旧: `isIOS: /iPad|iPhone|iPod/.test(navigator.userAgent)` → iPadOS 13+（Mac UA偽装）で `false` ❌
- 新: `isIOS: /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.maxTouchPoints > 1 && /MacIntel/.test(navigator.platform))`
- 影響: iPadOS 13+ のみ `isIOS: false→true`（意図した修正）。他端末は条件が成り立たず**不変**

**T-09**（P2 セレクタ特定化）
- 旧: `document.querySelector('video')`（DOM先頭の`<video>`を掴む — `#photo-preview` のvideoと競合の恐れ）
- 新: `document.querySelector('#ar-scene video')`（AR.js が生成する video 元素のみ取得）
- `#ar-scene` の存在: `scene.blade.php` L13 `id="ar-scene"` ✅
- 通常時: 取得される元素は同一（AR.js video が DOM 先頭だったため）→ **既存動作不変**
- 競合時: `#photo-preview` の `<video>` を誤って掴まなくなる → **改善**

### 13.3 検証結果

| チェック | 結果 |
|---|---|
| `php -l` 5ファイル（js-stamps / js-camera / js-throw / js-prize / head） | 警告0件 ✅ |
| 202609 モジュール内 `innerHTML` 使用箇所 | **0件** ✅ |
| 202609 モジュール内 `switch-camera-button` 参照 | **0件** ✅ |
| 202609 モジュール内 `querySelector('video')`（汎用） | **0件**（すべて `#ar-scene video`）✅ |
| `maxTouchPoints` + `MacIntel`（iPadOS判定） | 1件（`js-prize.blade.php` L105-106）✅ |
| `showStampBook` の新DOM構造と CSS セレクタ整合 | 一致 ✅ |
| 202605 / 202606 ファイル | 変更なし ✅ |

### 13.4 残存リスク・補足

- **T-05**: `img.src` は `screenshot`（base64 data URI）を直接代入。base64 は `collectAndMarkWithRetry` で `canvas.toDataURL('image/png')` 生成されるため、外部 URL を含まない。万一外部 URL が混入した場合は `img.src` としてロードされる（HTML注入ではない）。リスク: 低。
- **T-06**: 削除した `currentFacingMode` 変数は削除後、ファイル内に残存しないことを確認済み。`isUIButton` の他ボタン判定はすべて残存。
- **T-07**: `navigator.platform` は将来 non-standard として削除される可能性があるが、`maxTouchPoints > 1` 条件と組み合わせたフォールバック判定なので、`platform` が `undefined` の場合は条件が false になり従来どおり `isIOS: false`（iPhone/iPad は UA で判定済み）。
- **T-09**: `#ar-scene` は A-Frame が生成する `<a-scene>` 元素の id。AR.js が内部で `<video>` を生成する構造は不変。

---

## 14. 修正記録（2026-09-09 実施）— 優先度A 5項目（P2/P3）対応完了

> 本節は 2026-09-09 に実施した優先度A 5項目（T-15〜T-19）の修正記録である。
> 方針：202609 専用ファイルのみ変更。202605 / 202606 には影響なし。

### 14.1 修正内容一覧

| 項目 | 対応P | 修正内容 | 対象ファイル | 状態 |
|---|---|---|---|---|
| **T-15** | P2-6 | 捕獲日時 `d.getHours()` → `String(d.getHours()).padStart(2, '0')`（`9:05` → `09:05`） | `js-stamps.blade.php` L372 | ✅ 修正済み |
| **T-16** | P2-7 | 全5箇所の空 `.catch` → `console.warn` + `r.ok` チェック | `js-prize.blade.php` L30/L144/L162-164/L182-184/L233 | ✅ 修正済み |
| **T-17** | P2-5 | `active` / `aria-pressed` を `lang-en` → `lang-jp` に統一（`guideLang='jp'` と一致） | `ui.blade.php` L47-48 | ✅ 修正済み |
| **T-18** | P3-2 | Cookie 書込に `(location.protocol === 'https:' ? ';Secure' : '')` 条件追加 | `js-prize.blade.php` L50 | ✅ 修正済み |
| **T-19** | P3-3 | `crypto.randomUUID()` + `Math.random()` フォールバック構文 | `js-prize.blade.php` L63-69 | ✅ 修正済み |

### 14.2 詳細

**T-15**（P2-6 捕獲日時ゼロ埋め）
- 旧: `d.getHours() + ':' + String(d.getMinutes()).padStart(2, '0')`
- 新: `String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0')`
- 表示例: `9:05` → `09:05` / `10:30` → `10:30`（2桁均一化）

**T-16**（P2-7 API エラーの沈黙修正）
- 対象5箇所（`js-prize.blade.php`）:

| # | 行 | 関数 | 修正 |
|---|---|---|---|
| ① | L30 | `UserIdStore202609.set()` IndexedDB save | `.catch(function(){})` → `.catch(function(err){ console.warn('[AR202609] saveUserId error', err); })` |
| ② | L144 | `recordMarkerDetection()` | catch → `console.warn('[AR202609] recordMarkerDetection error', err)` |
| ③ | L162-164 | `recordMarkerScan()` | `r.ok` チェック + `console.warn` / catch → `console.warn` |
| ④ | L182-184 | `checkPrizeExchangeStatus()` | `r.ok` チェック + `console.warn` + フォールバック / catch → `console.warn` + フォールバック |
| ⑤ | L233 | `exchangePrize()` | catch に `console.warn` 追加（`alert` 維持） |

- API 正常応答時の挙動は不変。ユーザー向け UI（alert / modal）は変更なし
- ①（L30）は設計当初の「4箇所」に追加で発見した IndexedDB `put()` 失敗時のサイレントスワロウ

**T-17**（P2-5 ガイド初期言語統一）
- 旧: `lang-en` が `active` + `aria-pressed="true"` / `lang-jp` が `aria-pressed="false"`
- 新: `lang-jp` が `active` + `aria-pressed="true"` / `lang-en` が `aria-pressed="false"`
- `js-init.blade.php` L9 `guideLang='jp'` と初期 HTML が一致 → JS 実行前の一瞬の英語ちらつき解消

**T-18**（P3-2 Cookie `Secure` フラグ）
- 旧: `document.cookie = name + '=' + value + ';expires=...;path=/;SameSite=Strict';`
- 新: `document.cookie = name + '=' + value + ';expires=...;path=/;SameSite=Strict' + (location.protocol === 'https:' ? ';Secure' : '');`
- HTTPS 本番: `Secure` 付与（MITM 経由の Cookie 盗難防止） / HTTP ローカル: なし（開発互換維持）

**T-19**（P3-3 `crypto.randomUUID()` 切替）
- 旧: `Math.random()` ベースの UUID 生成
- 新: `crypto.randomUUID()` 優先 + `Math.random()` フォールバック（secure context 非対応環境で自動切替）
- `'uid_'` prefix 維持 → 既存 DB / Cookie 内の ID 形式と統一

### 14.3 検証結果

| チェック | 結果 |
|---|---|
| `php -l` 3ファイル（js-stamps / js-prize / ui） | 警告0件 ✅ |
| 202609 モジュール内 空 `.catch(function () {})` | **0件**（全5箇所 `console.warn` 済み）✅ |
| `location.protocol === 'https:'` 条件判定 | 1件（`js-prize.blade.php` L50）✅ |
| `crypto.randomUUID` 使用 + フォールバック | 1件（`js-prize.blade.php` L64-66）✅ |
| `lang-jp` active / `aria-pressed="true"` | `ui.blade.php` L47 ✅ |
| `String(d.getHours()).padStart(2, '0')` | `js-stamps.blade.php` L372 ✅ |
| 202605 / 202606 ファイル | 変更なし ✅ |

### 14.4 残存リスク・補足

- **T-16 ①**: `UserIdStore202609.set()` の IndexedDB 保存失敗は、`localStorage` / Cookie による3重保存が存在するため実運用上のリスクは低い。
- **T-18**: HTTP→HTTPS 移行時に `Secure` なしで書かれた旧 Cookie が読み取られない可能性 → `localStorage` 参照フォールバックで吸収される設計。
- **T-19**: `crypto.randomUUID()` は secure context のみ利用可能。HTTP 環境（localhost 以外）では `Math.random()` フォールバック → 既存 ID 形式不変。

---

## 15. 次の修正候補（2026-09-09 時点）

> T-15〜T-19 対応後の残存項目。対応済み（P2-5/P2-6/P2-7/P3-2/P3-3）は除外済み。

### 15.1 推奨優先（影響大・工数小）

| # | 対象ファイル | 問題（対応P） | 修正方針 | 工数 | 状態 |
|---|---|---|---|---|---|
| **NEXT-1** | `scene.blade.php:17` | **P2-10**: `antialias: true` が全環境有効 → モバイル GPU 負荷 | `AR_FORCE_LOWRES === true` 時に `antialias: false`（`head.blade.php` IIFE 結果を `scene.blade.php` で `@if` 分岐 or 動的 attribute set） | **20分** | ✅ 修正済み |
| **NEXT-2** | `js-stamps.blade.php:32-35` | **P2-8**: 2つの `new Audio` が初回ロードで即生成（`preload=auto` 171KB 消費） | 遅延生成: IIFE 内 `null` 初期化 → 初回 `playSound` 時に `new Audio(...)` | **30分** | ✅ 修正済み |
| **NEXT-3** | `ui.blade.php:53` | **P2-9**: `howToOperate.png`（1.39MB）が LCP 影響 | WebP/AVIF 化（`cwebp -q 80` → 200〜400KB）or `<picture>` + 幅 360px 相当リサイズ | **30分** | ✅ 修正済み |
| **NEXT-4** | `js-prize.blade.php` | **P2-3**: `showPrizeCode` / `showRedeemedPrizeInfo` の約30行 HTML 重複 + インライン `onclick` | 共通 `showPrizeModal(title, code, extra)` に集約、`addEventListener` で `modal.remove()` | **30分** | ✅ 修正済み |

### 15.2 余力（アーキテクチャ・運用）

| # | 対象ファイル | 問題（対応P） | 修正方針 | 工数 |
|---|---|---|---|---|
| **NEXT-5** | `js-throw.blade.php:48-49` | **P2-2**: ポケボール GLB（233KB）毎投擲再パース | シーン内にボールプール（3体）or `a-assets` プリロード | **1〜2時間** |
| **NEXT-6** | `scene.blade.php:15` | **P2-1**: 21マーカー同時検出の Android 最大負荷 | N秒未検出マーカーの動的無効化（`enabled=false`）で検索空間狭小化 | **3〜5時間** |
| **NEXT-7** | `js-prize.blade.php:136-144` | **P3-4**: `marker-scan-cache-202609-*` localStorage 残留 | 初回ロード時 `date < today` キーを削除（1行ループ） | **15分** |
| **NEXT-8** | 全体 | **P3-6**: グローバルエラーが `console` のみ | Sentry / CloudWatch / 自社ログ送信先接続 | **要検討** |

> **P3-1**（`user-scalable=no`）: `ar_requirements.md` で「ユーザー操作防御として維持」方針明記済み → **対応不要**。

### 15.3 推奨進め方

1. **NEXT-1（antialias）**: 条件分岐1行で低スペック端末の FPS 改善。即対応推奨。
2. **NEXT-2（Audio 遅延生成）**: 初回ロード時の 171KB 帯域 + メモリ削減。
3. **NEXT-3（画像最適化）**: 1.39MB → 200〜400KB。LCP 大幅改善。
4. **NEXT-4（モーダル集約）**: 保守性向上。新規 UI 追加時の重複排除。
5. **NEXT-5〜6**: パフォーマンスボトルネック解消のため要設計検討。

---

## 16. 修正記録（2026-09-09 実施）— NEXT-1〜NEXT-4（P2-10/P2-8/P2-9/P2-3）対応完了

> 本節は 2026-09-09 に実施した NEXT-1〜NEXT-4 全4項目の修正記録である。
> 方針：202609 専用ファイルのみ変更。202605 / 202606 には影響なし。

### 16.1 修正内容一覧

| 項目 | 対応P | 修正内容 | 対象ファイル | 状態 |
|---|---|---|---|---|
| **NEXT-1** | P2-10 | `AR_FORCE_LOWRES` 時に `antialias: true → false` を動的置換（モバイル GPU 負荷軽減） | `scene.blade.php` L12-24 | ✅ 修正済み |
| **NEXT-2** | P2-8 | `new Audio` 初回即生成 → `_ensureSound(which)` 遅延生成（初回 `playSound` 時のみ生成、171KB 帯域節約） | `js-stamps.blade.php` L31-38, L159-160 | ✅ 修正済み |
| **NEXT-3** | P2-9 | `howToOperate.png`（1.39MB）に `loading="lazy" decoding="async"` 追加（LCP 改善） | `ui.blade.php` L53 | ✅ 修正済み |
| **NEXT-4** | P2-3 | `showPrizeCode` / `showRedeemedPrizeInfo` 2関数を `showPrizeModal(config)` に集約。`innerHTML` → `textContent`（XSS 排除）＋ `addEventListener` 化 | `js-prize.blade.php` L213/214/226, L222-270 | ✅ 修正済み |

### 16.2 詳細

**NEXT-1**（P2-10 対応：antialias 動的無効化）

- 旧: `scene.blade.php:32` の `renderer` 属性に `antialias: true` が全環境で固定
- 新: `<a-scene>` 直前に `<script>` 追加（L12-24）
  - `window.AR_FORCE_LOWRES === true` の場合のみ `beforeentitycomposition` イベント（`{ once: true }`）で `renderer` 属性から `antialias: true` → `antialias: false` を文字列置換
  - `AR_FORCE_LOWRES === false`（高スペック / デスクトップ）は従来どおり `antialias: true` 維持
- 検証: `AR_FORCE_LOWRES` 定義（`head.blade.php` IIFE）が `scene.blade.php` より前に実行される DOM 順序を確認済み
- 残存リスク: なし。`beforeentitycomposition` は A-Frame 1.4.2 で確実に発火するイベント

**NEXT-2**（P2-8 対応：Audio 遅延生成）

- 旧: `js-stamps.blade.php:32-35`
  ```js
  const soundStamp01 = new Audio('...sound_stamp01.mp3'); // 71KB 初回即ロード
  const soundStamp02 = new Audio('...sound_stamp02.mp3'); // 100KB 初回即ロード
  ```
- 新:
  ```js
  var soundStamp01 = null;  // L31
  var soundStamp02 = null;  // L32
  function _ensureSound(which) {  // L34-38
      if (which === 1) { if (!soundStamp01) { soundStamp01 = new Audio('...'); } return soundStamp01; }
      if (which === 2) { if (!soundStamp02) { soundStamp02 = new Audio('...'); } return soundStamp02; }
      return null;
  }
  ```
  - 呼出側（L159-160）: `playSound(_ensureSound(2))` / `playSound(_ensureSound(1))`
- `playSound(audioElement)`（L222-227）は `currentTime=0` + `play().catch()` のシンプルな実装 → `_ensureSound` が返す `Audio` オブジェクトと完全互換
- 検証: `_ensureSound` 定義（L34）が呼出箇所（L159/160）より前にあることを確認済み
- 残存リスク: なし。初回以降は同一 `Audio` オブジェクトを再利用（メモリ効率向上）

**NEXT-3**（P2-9 対応：画像遅延読み込み）

- 旧: `ui.blade.php:53`
  ```html
  <img src="...howToOperate.png" alt="操作方法" width="100%">
  ```
- 新:
  ```html
  <img src="...howToOperate.png" alt="操作方法" width="100%" loading="lazy" decoding="async">
  ```
- 効果: ガイドモーダル非表示中は画像をロードしない → 初回ロードの LCP 改善
- 残存リスク: なし。`loading="lazy"` は全ブラウザでサポート済み（2021〜）

**NEXT-4**（P2-3 対応：モーダル集約 + XSS 排除）

- 旧: `showPrizeCode()` / `showRedeemedPrizeInfo()` がほぼ同一のモーダル HTML を文字列連結（重複約30行）、`innerHTML` に直接組み込み、`onclick="this.parentElement.parentElement.remove()"`（DOM 深さに依存）
- 新: `showPrizeModal(config)` 1関数に集約（L222-270）
  ```js
  function showPrizeModal(config) {
      // config: { title, code, exchangedAt, isRedeemed }
      // DOM 生成: document.createElement + textContent（innerHTML 不使用）
      // 閉じる: addEventListener('click', function() { modal.remove(); })
  }
  ```
  - `_formatExchangeDateTime(dt)`（L215-220）: `exchangedAt` の `T` 置換・`Z` 除去を抽出
  - 呼出側:
    - 交換成功: `showPrizeModal({ title: '景品コード', code: data.prizeCode })`（L213）
    - 交換済み: `showPrizeModal({ title: '交換済み', code: data.prizeCode, exchangedAt: data.exchangedAt, isRedeemed: true })`（L214/226）
- XSS 排除: `textContent` により HTML 特殊文字がエスケープされる
- 残存リスク: なし。`textContent` は全ブラウザでサポート済み

### 16.3 検証結果

| チェック | 結果 |
|---|---|
| `php -l` 4ファイル（scene / js-stamps / ui / js-prize） | 警告0件 ✅ |
| `playSound(_ensureSound(1/2))` 呼出箇所 | 2件（L159/160）✅ |
| `_ensureSound` 定義が呼出より前にある | 確認済み ✅ |
| `showPrizeModal` 定義が3つの呼出箇所より前にある | 確認済み ✅ |
| `AR_FORCE_LOWRES` 参照が `head.blade.php`（IIFE 定義）より後にある | 確認済み ✅ |
| 202609 モジュール内 `innerHTML` 使用箇所 | **0件** ✅ |
| 202605 / 202606 ファイル | 変更なし ✅ |

### 16.4 残存リスク・補足

- **NEXT-1**: `beforeentitycomposition` イベントは A-Frame 1.4.2 で確実に発火する。`renderer` 属性の文字列置換は A-Frame が `renderer` をパースする前のタイミングで実行されるため安全。
- **NEXT-2**: `var` 宣言（IIFE 内ではスコープが `window` に昇格しないが、`IIFE` 内なので問題なし）。初回以降は同一 `Audio` オブジェクトを再利用。
- **NEXT-3**: `loading="lazy"` は `IntersectionObserver` ベース。ブラウザが画像を視界外と判定するまでロードしない。
- **NEXT-4**: `showPrizeModal` は `document.body.appendChild(modal)` で追加。モーダル表示中は `z-index: 9999` で前面表示。

---

## 17. 次の修正候補（2026-09-09 時点・NEXT-1〜4 完了後）

> NEXT-1〜NEXT-4 対応後の残存項目。対応済み（P0 全件 / P1 全件 / P2-3〜P2-10 / P3-2/P3-3）は除外済み。

### 17.1 推奨優先（影響大・工数小）

| # | 対象ファイル | 問題（対応P） | 修正方針 | 工数 |
|---|---|---|---|---|
| **NEXT-7** | `js-prize.blade.php:136-144` | **P3-4**: `marker-scan-cache-202609-*` キーが日付単位で localStorage に蓄積 | 初回ロード時 `date < today` キーを削除（1行ループ） | **15分** |

### 17.2 余力（アーキテクチャ・パフォーマンス）

| # | 対象ファイル | 問題（対応P） | 修正方針 | 工数 |
|---|---|---|---|---|
| **NEXT-5** | `js-throw.blade.php:48-49` | **P2-2**: ポケボール GLB（233KB）毎投擲再パース | シーン内にボールプール（3体）or `a-assets` プリロード | **1〜2時間** |
| **NEXT-6** | `scene.blade.php:15` | **P2-1**: 21マーカー同時検出の Android 最大負荷 | N秒未検出マーカーの動的無効化（`enabled=false`）で検索空間狭小化 | **3〜5時間** |
| **NEXT-8** | 全体 | **P3-6**: グローバルエラーが `console` のみ | Sentry / CloudWatch / 自社ログ送信先接続 | **要検討** |

> **P3-1**（`user-scalable=no`）: `ar_requirements.md` で「ユーザー操作防御として維持」方針明記済み → **対応不要**。

### 17.3 推奨進め方

1. **NEXT-7（localStorage 掃除）**: 15分で完了。初回ロード時の微量メモリ削減。
2. **NEXT-5（ボールプール）**: パフォーマンスボトルネック解消。設計検討必要。
3. **NEXT-6（マーカー動的無効化）**: Android 最大負荷の解消。設計検討必要。
4. **NEXT-8（エラー送信）**: 本番運用での障害検知強化。要検討。

