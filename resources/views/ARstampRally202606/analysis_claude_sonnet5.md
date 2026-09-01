# ARstampRally202606 コード分析レポート

- 分析対象: `resources/views/ARstampRally202606.blade.php` および `resources/views/ARstampRally202606/*.blade.php`
- 分析者: Claude Sonnet 5 (GitHub Copilot)
- 分析日: 2026-09-01

---

## 1. 概要

スマートフォンのブラウザで動作するWebARアプリ。AR.js（+ A-Frame + three.js）を用いてカメラ映像上にマーカー認識で3Dアニメーションモデル（動物）を表示し、画面タップでモンスターボールを投げて命中させると「捕獲」としてスタンプを記録する。20種類のスタンプのうち10個以上集めると景品交換コードが発行される、というスタンプラリー企画。

### システム構成

| レイヤー | 内容 |
|---|---|
| ビュー | `ARstampRally202606.blade.php` が `head` / `ui` / `scene` / `aframe-components` / `js-stamps` / `js-prize` / `js-throw` / `js-gallery` / `js-camera` / `js-init` の各partialを読み込む |
| ARエンジン | `public/js/ar-engine.min.js`（three.js等を含むバンドル, 約1.3MB）、`public/js/ar-tracking.min.js`（AR.js本体 + jsartoolkit5, 約1.6MB）。どちらも **npm/composerで管理されないベンダーファイルとして`public/js`に直置き**（`package.json`に依存として記載なし、`webpack.mix.cjs`でもビルド対象外） |
| 3Dモデル | `public/cg/202606/Model_00〜20.glb`（各マーカーに対応する動物モデル、`anime01`=待機ループ、`anime02`=ヒット時再生） |
| マーカー | `public/cg/202606/pattern-maker00〜20.patt`（AR.jsパターンマーカー、20+1個） |
| 状態管理 | `localStorage`（スタンプ・捕獲状態・ギャラリー選択）、`IndexedDB` + Cookie（ユーザーID永続化のフォールバック多重化） |
| サーバー連携 | `routes/api.php` → `MarkerScanController`, `PrizeExchangeController`（マーカー検出ログ・景品交換コード発行） |

### 主要ファイルの役割

- `head.blade.php`: グローバル状態、カメラ起動監視、`ensureCameraAccess`（フォールバック用getUserMedia）、ズーム/コンテキストメニュー抑止、CSS
- `scene.blade.php`: `<a-scene>` と `arjs` 属性（**sourceWidth/sourceHeight等をUser-Agentで出し分け**）、マーカー0〜20個のエンティティ定義
- `aframe-components.blade.php`: `pokeball-throwable`（投擲・当たり判定）, `hitbox`/`gallery-hitbox`（AABB判定）, `lazy-model`（マーカー消失時にモデルを破棄しメモリ解放）, `click-animation`（アニメーション制御・捕獲状態管理）
- `js-init.blade.php`: DOMContentLoaded後の初期化、ガイドモーダル、カメラ許可確認、モーダル各種、**ポートレート時のarjs再設定ロジック**
- `js-stamps.blade.php`: スタンプのlocalStorage永続化、UI描画
- `js-prize.blade.php`: フィンガープリント生成、マーカースキャンAPI送信、景品交換
- `js-throw.blade.php`: タップ/スワイプでのボール投擲
- `js-gallery.blade.php`: marker00で捕獲済みモデルを最大4体展示するギャラリー機能
- `js-camera.blade.php`: 写真・動画撮影（Canvas合成 + MediaRecorder）

---

## 2. 端末互換性分析

### 2.1 iPhone 7以降（iOS Safari）

iPhone 7は最終的にiOS 15.xまでアップデート可能。iOS 15のSafariを前提にすると、コード内で使われているAPIは概ね対応している。

| 使用API | 必要iOSバージョン目安 | 判定 |
|---|---|---|
| `getUserMedia` (facingMode指定) | iOS 11+ | ✅ |
| `IndexedDB` | iOS 10+ | ✅ |
| `fetch` / `Promise` / テンプレートリテラル / アロー関数 | iOS 10+ | ✅ |
| `Array.prototype.includes` / `String.prototype.padStart` | iOS 9〜10+ | ✅ |
| `classList` | 全面対応 | ✅ |
| `HTMLCanvasElement.captureStream()`（動画合成撮影） | iOS 11+ | ✅ |
| `MediaRecorder`（動画録画、`js-camera.blade.php`） | **iOS 14.3+** | ⚠️ iOS 14.2以前は`MediaRecorder`が未実装のためTypeErrorで例外発生。iPhone 7でも古いiOSバージョンのまま使われている場合は動画撮影機能が丸ごと落ちる |
| `navigator.share` + `canShare({files})`（保存/共有） | iOS 15+ | ⚠️ iOS 14以下では`canShare`自体が存在しないか、files共有非対応。コードは`if (navigator.share && navigator.canShare)`で分岐しダウンロードにフォールバックしているため**クラッシュはしないが機能低下は起こりうる** |
| WebGL / A-Frame / three.js描画 | iOS 10+ (WebGL1) | ✅ ただしiPhone 7のGPU(A10)は非力なため、20体のGLBモデル+アニメーションミキサーの同時ロードでは描画負荷に注意 |
| `gesturestart`/`gesturechange`（ピンチズーム抑止） | Safari独自イベント | ✅ 意図通りiOSのみで発火 |

**結論**: iOS側は致命的な非互換はないが、`MediaRecorder`（動画撮影）と`navigator.share`のfiles共有は**iOSバージョンに強く依存**する。iPhone 7実機でもiOSが古いまま（14.2以前）のケースがあり得るため、`MediaRecorder`未対応時に**事前チェックとUIの無効化（try/catchで落ちるだけでなく、ボタン自体を隠す）** が望ましい。現状 `startRecording()` は `try { ... new MediaRecorder(...) ... } catch (err) { alert(...) }` で捕捉されているため、アプリ全体がクラッシュすることはない点は良い設計。

### 2.2 Android 7以降（Chrome for Android想定）

Android 7 (Nougat, 2016)自体はGoogle Playの自動更新でChromeが最新版まで上がる端末が多く、Chrome自体のAPI対応は問題になりにくい。ただし以下は注意点。

- `MediaRecorder`, `navigator.share`, `IndexedDB`, `padStart`, `classList`: Chrome for Androidでは早期から対応済みで問題なし。
- **低スペック端末での実パフォーマンス**が最大の懸念。Android 7世代の端末はメモリ・GPUが弱く、20体のGLBモデル（`lazy-model`で遅延ロード/アンロードする設計自体は正しい）でも、`detectionMode: mono`かつ`maxDetectionRate: 30`はトラッキング負荷が高め。
- Android WebViewベースのアプリ内ブラウザ（LINE, Instagram等）経由でアクセスされた場合、`getUserMedia`や`MediaRecorder`が制限される既知の問題があるが、本コードはこれを検知・警告する仕組みを持たない。

### 2.3 【最重要】Androidでカメラがズームして正しく表示されない問題

これはコード上に明確な原因が特定できた。

#### 根本原因

`scene.blade.php` 冒頭:

```php
$_arjsIsAndroid = stripos(request()->header('User-Agent', ''), 'android') !== false;
$_srcW = $_arjsIsAndroid ? 640 : 1280;
$_srcH = $_arjsIsAndroid ? 480 : 720;
```

→ **Androidと判定された全端末（機種を問わず一律）** に対し、`arjs`属性の `sourceWidth: 640; sourceHeight: 480` を最初からサーバーサイドで焼き込んでいる。

一方、AR.js本体（`ar-tracking.min.js`）は内部でこの値をそのまま `getUserMedia` の **ideal（努力目標）制約** として使っている（実装を確認済み）:

```js
{ audio: false, video: { facingMode: "environment",
    width:  { ideal: sourceWidth  },   // ← ここに640が渡る
    height: { ideal: sourceHeight } } }
```

`ideal`は「できればこの解像度で」という弱い制約であり、Android端末のカメラHAL（特にSamsung/一部MediaTek系チップ搭載機種）は、低い解像度が要求されると **センサーの画角全体を縮小するのではなく、中央部分だけを切り出して要求解像度に合わせる（デジタルクロップ）実装になっているものがある**。これが実機ごとに「同じアプリなのに機種によってカメラがズームして見える／視野が狭くなる」というまさに今回報告されている症状の典型的な原因である。

つまり:
- iOS/デスクトップ → `1280x720`（16:9に近い、AVFoundationは概ねFOVを保持） → 問題が出にくい
- Android → 一律 `640x480`（4:3、低解像度） → 機種によってはHALが中央クロップ → **画角が狭くなり「ズームして見える」**

これを裏付ける実装上の傍証:
1. `js-init.blade.php` の `arjs-video-loaded` ハンドラでは、実際の `video.videoWidth/videoHeight` が **縦長（ポートレート）だった場合のみ** `sourceWidth/sourceHeight` を実測値に再設定する救済コードがある。しかし**横長（ランドスケープ）で解像度がズレているケース（本質的な原因）には一切対応していない**。
2. `scene.blade.php` 末尾には「Samsung Galaxy 縦向き対策」として **端末名を直接文字列マッチ**する場当たり的なパッチ（`samsung|SM-[A-Z]`）が追加されている。これは症状ごとに個別パッチを積み重ねている状態であり、**根本原因（低解像度ideal制約によるHAL側クロップ）に対処できていない**ことを示す状況証拠。
3. `head.blade.php` の `ensureCameraAccess()`（カメラ起動失敗時のフォールバック関数）は、逆に **1280x720 → 640x480 → 制約なし → true の順で高解像度を優先**しており、AR.js本体が使う初期解像度設定（Androidは640x480固定）と**方針が矛盾している**。

#### 推奨される修正方針

1. **Androidであることを理由に解像度を下げる現在のロジックを撤廃**し、iOSと同様に `sourceWidth: 1280, sourceHeight: 720` を既定値にする（低性能端末向けの解像度低減は、パフォーマンス計測に基づく別軸の最適化として分離する）。
2. どうしても低解像度が必要な低スペック端末向けには、`width`/`height`の`ideal`だけでなく **`aspectRatio`** 制約を併用し、クロップではなくスケーリングを促す（`aspectRatio: {ideal: 16/9}`等）。
3. 実際に取得された `video.videoWidth/videoHeight` を **縦横問わず**チェックし、要求値と異なればその場で `sourceWidth/sourceHeight` をAR.jsに再設定するロジックに一般化する（現状はポートレートのみの特別対応）。これにより機種名のハードコードパッチ（Samsung判定）が不要になる。
4. 可能であれば、起動直後に `navigator.mediaDevices.getSupportedConstraints()` / `track.getCapabilities()` を用いて実機の対応解像度レンジを取得し、極端な低解像度要求を避ける。
5. UA判定によるサーバーサイド分岐（PHPで`$_arjsIsAndroid`）自体は「iOSとAndroidでconstraint方針を変える」目的だが、**UA文字列は偽装・省略され得るため信頼性が低い**。クライアントサイドの`navigator.userAgent`判定と二重管理になっており、どちらか一方（クライアント側JSに統一）に寄せるべき。

---

## 3. その他の問題点

### 3.1 パフォーマンス

- `ar-engine.min.js`（約1.3MB）+ `ar-tracking.min.js`（約1.6MB）+ `aframe.min.js`（約1.3MB）が**非圧縮相当のサイズでキャッシュヘッダー等の言及もなく**都度ロードされる。低速回線利用者（イベント会場の混雑Wi-Fi等）では初期表示が非常に遅くなる可能性が高い。gzip/br圧縮配信・HTTPキャッシュ（`Cache-Control: immutable`等）の設定確認を推奨。
- `hitbox`/`gallery-hitbox`コンポーネントの`tick()`は`window.activeBalls > 0`の間、毎フレーム`updateBox()`（`getWorldPosition`/`getWorldScale`呼び出し）を実行しており、ボールを連投すると計算コストが線形に積み上がる。マーカー数（20個）× ボール数が同時に増えるケースでの負荷検証が必要。
- `maxDetectionRate: 30` はハイエンド端末では良いが、低性能Android機では発熱・フレーム落ちの原因になり得る。低スペック検出（`AR_FORCE_LOWRES`は「Android 7以下」でしか発動しない）をもう少し緩やかな基準（例: `navigator.hardwareConcurrency`や`deviceMemory`）で行う余地がある。

### 3.2 データ整合性・セキュリティ

- `PrizeExchangeController::exchange()` は、クライアントから送られた `stamps`（スタンプ配列）を **サーバー側で件数検証していない**。クライアント側`exchangePrize()`は`count < 10`ならAPIを呼ばずにalertするだけの**フロントエンドのみのガード**であり、悪意あるユーザーが`fetch('/api/exchange-prize', {...})`を直接叩けば、スタンプ0個でも景品コードが発行できてしまう。サーバー側で`stamps`の内容（あるいは既知のスタンプIDとの整合性）を検証すべき。
- `PrizeExchangeController::checkStatus()` は `$request->input('fingerprint')` を`validate()`せずそのまま`orWhere`に使っている。バリデーション欠如（型/必須チェックなし）。
- 「フィンガープリント」は `localStorage`+`Cookie`+`IndexedDB`の多重フォールバックだが、最終的にはブラウザのプライベートモードやストレージクリアで容易にリセットできるため、**同一人物による複数回の景品交換を完全には防げない**（`session_id`との`orWhere`も、UA/Cookie削除で回避可能）。景品の価値によっては運営側の想定と乖離するリスクを明記しておくべき。
- 各APIエンドポイント（`/api/record-marker-scan`など）にレート制限（Laravelの`throttle`ミドルウェア）が見当たらない。連打・自動化スクリプトによるDB肥大化の余地がある。

### 3.3 保守性・コード品質

- `ar-engine.min.js` / `ar-tracking.min.js` が**npm依存管理外でベンダリング**されており、バージョン番号もコメントも残っていない（`grep`で`version="0.3.0`が一箇所ヒットする程度）。将来のセキュリティパッチ適用や不具合修正時に「今どのAR.jsバージョンを使っているか」を追跡できない。`package.json`に取り込む、またはファイル冒頭にソースURLとバージョンをコメントで明記することを推奨。
- `scene.blade.php`のPHP側UA判定と、複数箇所のJS側`navigator.userAgent`判定（Samsung検出、iOS検出、旧Android検出）が**分散して存在**しており、今後端末別分岐が増えるほど整合性を保つのが難しくなる。デバイス判定・AR設定決定ロジックを1箇所（例えば`head.blade.php`内の1関数）に集約するリファクタリングが有効。
- `collectAndMarkWithRetry`/`collectStamp`/`markAnimalCaptured`などスタンプ確定処理が複数箇所（`aframe-components.blade.php`の`handleHit`, `click-animation`の`playHitAnimation`, `pokeball-throwable`の`tryAutoGet`）から**似た手順で重複して呼ばれている**。挙動は動くが、将来の仕様変更時にバグを埋め込みやすい構造。

### 3.4 UX上の懸念

- カメラ起動失敗時の`camera-error`表示までのタイムアウトは`monitorCameraStartup(7000)`＝7秒だが、iOSの`showIfNoAR`は`Math.max(delay, 10000)`で最低10秒待つなど、**待機時間の基準がAPIごとにバラバラ**で、ユーザーが「フリーズしている」と誤解しやすい。
- `#exchange-prize-button`のクールダウン・二重送信防止（連打対策）がクライアント側に見当たらない（サーバー側は`session_id`/`fingerprint`重複チェックがあるため実害は限定的だが、連打でAPIコールが重複発行される）。

---

## 4. 改善提案まとめ（優先度順）

| 優先度 | 内容 | 対象 |
|---|---|---|
| P0 | Android向け `sourceWidth/sourceHeight` の一律640x480固定をやめ、iOSと同じ高解像度（1280x720）をデフォルトにし、実測videoWidth/videoHeightとの差分を横縦問わず検出して再設定する仕組みに一般化する（Samsung個別パッチも撤去可能に） | `scene.blade.php`, `js-init.blade.php` |
| P0 | 景品交換API (`/api/exchange-prize`) にサーバー側でのスタンプ件数・整合性検証を追加 | `PrizeExchangeController` |
| P1 | `MediaRecorder`非対応環境（iOS 14.2以前等）では動画撮影ボタンを事前に非表示化するfeature detection | `js-camera.blade.php` |
| P1 | AR.js/three.jsバンドルのバージョン管理・圧縮配信・キャッシュ設定の見直し | `public/js/ar-engine.min.js`, `ar-tracking.min.js` |
| P1 | APIエンドポイントへのレート制限（`throttle`ミドルウェア）追加 | `routes/api.php` |
| P2 | UA判定ロジックの一元化（PHP/JS両方に分散した端末判定を1箇所に集約） | 全体 |
| P2 | 低スペック端末判定の精緻化（`deviceMemory`/`hardwareConcurrency`も加味） | `head.blade.php` |
| P2 | スタンプ確定処理の共通化（重複呼び出し箇所の整理） | `aframe-components.blade.php`, `js-stamps.blade.php` |

---

## 5. 総評

基本機能（マーカー認識→3D表示→投擲→捕獲→スタンプ→景品交換）は堅牢に作り込まれており、`lazy-model`によるメモリ解放や、カメラ起動失敗時の多段リトライなど**運用を意識した防御的な実装**が随所に見られる良質なコードベースである。

一方で、今回問題として挙げられた **「Android機種によってカメラがズームする」問題は、`scene.blade.php`でAndroid全般に対して低解像度(640x480)を`ideal`制約として一律に要求していることが根本原因である可能性が高い**。これは個別機種名のハードコードパッチ（Samsung判定など）で対症療法的に対応されている状態であり、解像度要求ロジックそのものを見直すことで、パッチを増やさずに問題を解消できる見込みが高い。

またAndroid/iOS双方とも致命的な非互換はないものの、`MediaRecorder`や`navigator.share(files)`など**OS/ブラウザのマイナーバージョンに依存する機能**があるため、実機（特にiPhone 7実機でiOSが古いまま更新されていない個体）での動作確認を推奨する。
