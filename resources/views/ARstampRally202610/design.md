# ARstampRally202610 設計書

## 1. アーキテクチャ
ARstampRally202609 と同一構成のクローン（単一モジュール追加のみ、共通コードに依存しない）。

- 表示: Laravel Blade（エントリ + パーシャル10個）
- クライアント: A-Frame + AR.js / Three.js（`public/js/ar-engine.min.js` 等を共有）
- ユーザー識別: LocalStorage → IndexedDB（`ARStampRallyDB202610`）→ Cookie（`ar_user_id_202610`）
- サーバー: `StampRally202610Controller`（recordScan / checkStatus / exchange）
- 制限: `AppServiceProvider` の RateLimiter 3種
- 管理: `AdminController::dashboard202610` + `admin/dashboard202610.blade.php`

## 2. ファイル構成
| ファイル | 役割 | 新規/変更 |
|---|---|---|
| `resources/views/ARstampRally202610.blade.php` | エントリ（パーシャル include） | 新規 |
| `resources/views/ARstampRally202610/head.blade.php` | head / meta / スクリプト読み込み | 新規 |
| `resources/views/ARstampRally202610/ui.blade.php` | UI（集計・景品ボタン等） | 新規 |
| `resources/views/ARstampRally202610/scene.blade.php` | A-Frame シーン（マーカー・モデル） | 新規 |
| `resources/views/ARstampRally202610/aframe-components.blade.php` | A-Frame カスタムコンポーネント | 新規 |
| `resources/views/ARstampRally202610/js-stamps.blade.php` | スタンプ定義・集計ロジック | 新規 |
| `resources/views/ARstampRally202610/js-prize.blade.php` | 景品交換API連携 | 新規 |
| `resources/views/ARstampRally202610/js-throw.blade.php` | ボール投擲ロジック | 新規 |
| `resources/views/ARstampRally202610/js-gallery.blade.php` | ギャラリー表示 | 新規 |
| `resources/views/ARstampRally202610/js-camera.blade.php` | カメラ制御 | 新規 |
| `resources/views/ARstampRally202610/js-init.blade.php` | 初期化・イベント接続 | 新規 |
| `app/Http/Controllers/StampRally202610Controller.php` | 景品交換API | 新規 |
| `resources/views/admin/dashboard202610.blade.php` | 管理ダッシュボード | 新規 |
| `routes/web.php` | /stamp202610 + API3本 + admin ルート1本 | 変更（追記のみ） |
| `app/Providers/AppServiceProvider.php` | レートリミッター3種 | 変更（追記のみ） |
| `app/Http/Controllers/AdminController.php` | dashboard202610() | 変更（追記のみ） |

## 3. 202610 固有識別子（分離のための置換一覧）
202609 → 202610 に置換する項目：
- ページタイトル: `AR Stamp Rally 202610`
- LocalStorage: `ar-stamp-rally-202610` / `ar-captured-animals-202610` / `ar-gallery-selection-202610` / `ar-user-id-202610` / `ar-camera-reload-202610`
- IndexedDB: `ARStampRallyDB202610`
- Cookie: `ar_user_id_202610`
- スキャン日次キャッシュ: `marker-scan-cache-202610-{markerId}-{YYYY-MM-DD}`
- consoleプレフィックス: `[AR202610]`
- JS関数・変数名: `UserIdDB202610` / `CookieHelper202610` / `generateUUID202610` / `getUserId202610` / `getCapturedAnimals202610` / `saveCapturedAnimals202610`
- API URL: `/stamp202610/{record-scan,check-prize,exchange-prize}`
- レートリミッター: `stamp202610_scan` / `stamp202610_check` / `stamp202610_redeem`
- ルート名: `stamp202610.index` / `admin.dashboard202610`
- PHP: `StampRally202610Controller` / `AdminController::dashboard202610`

**置換しない項目（アセット再利用）**:
- `scene.blade.php` の `asset('cg/202609/pattern-makerNN.patt')` / `asset('cg/202609/Model_NN.glb')`
- `js-stamps.blade.php` の `STAMPS[].model = '202609/Model_NN.glb'`（js-gallery が `cg/` を付与）

## 4. API 設計（202609 と同一仕様）
| エンドポイント | 用途 | レートリミット |
|---|---|---|
| `POST /stamp202610/record-scan` | スキャン記録、セッションフィンガープリント更新 | 分120回 |
| `POST /stamp202610/check-prize` | 交換状態確認 | 分120回 |
| `POST /stamp202610/exchange-prize` | 景品コード発行（閾値10種・サーバー強制） | 時10回 |

- 共通: web ミドルウェアグループ（CSRF + セッション）適用。
- レートリミッターキー: セッションの `ar_fingerprint`、未設定時は IP。
- 使用テーブル: `marker_scans` / `prize_exchanges`（既存キャンペーンと共有）。

## 5. 管理ダッシュボード
- ルート: `GET /admin/dashboard202610`（auth + admin ミドルウェア）
- 統計期間: **2026-09-17 00:00:00 〜 2026-10-31 23:59:59（JST、UTC変換してクエリ）**
- 集計内容: 動物別スキャン数（marker_scan / ball_hit）・個別ユーザー数・最近のスキャン・日別統計・景品交換実績
- 動物リスト: `model_01`〜`model_20`（202609 の管理画面と同一。model_20 = ぶっちー）
- 集計項目: スキャン数（capture_type: marker_scan / ball_hit）・個別ユーザー数（fingerprint）・最近のスキャン履歴・日別統計・景品交換実績（exchanged_at）

## 6. リスク・注意
- `prize_exchanges` / セッションが共用のため、202609 で交換済みのフィンガープリントは 202610 でも「使用済み」になる（202605/202606/202609 と同一の既存仕様・許容）。
- アセットは `cg/202609/` に依存するため、202609 版アセットの削除時は 202610 にも影響する（運用上の注意）。

## 7. iOS カメラ起動遅延時の誤表示解消 設計（2026-09-11 追加）

### 現状のフロー（202609 クローン由来・不具合がある）
1. `head.blade.php` / `monitorCameraStartup(timeoutMs)`: `#ar-scene video` を 500ms 間隔でポーリングし、
   `readyState >= 2 || currentTime > 0 || !paused` なら成功（オーバーレイ非表示）。
   タイムアウト（7000ms）到達で `setInterval` を**解除**し `#camera-error` を表示する → **以降の再チェックが一切無い**。
2. `head.blade.php` / `ensureCameraAccess()`: 上記「ready 判定」を満たさないと 4 制約セットで
   `getUserMedia` を再要求 → 全失敗で再び `#camera-error` を表示。
3. `js-init.blade.php`: `arjs-video-loaded` ハンドラは `.arjs-loader` と `#camera-help-modal` を非表示にするが
   `#camera-error` は対象外。`window.load` 時に `monitorCameraStartup(7000)` を開始、
   7000ms 後にローダー強制非表示（フォールバック）。

### 不具合メカニズム（iOS: iPhone7 / SE3）
- 低スペック機では AR.js（`ar-tracking.min.js`）の `getUserMedia` → 初フレームが 7 秒を超える。
  タイムアウトで監視が永久停止 → 実際にはカメラが起動しても（マーカー・モデル動作）エラー画面が残り続ける。
- リトライ時: AR.js が既にストリームを保持（`video.srcObject` あり）→ `ensureCameraAccess` が 2 度目の
  `getUserMedia` を発行 → iOS で「カメラ使用中」等の失敗 → エラー再表示（無限ループ）。

### 改修設計（202610 パーシャルのみ）
1. `monitorCameraStartup(timeoutMs)`（head.blade.php）:
   - タイムアウトで `#camera-error`（と低解像度リトライボタン）は従来どおり表示するが、
     **`setInterval` は解除しない**（video が存在する間は監視継続）。
   - video が ready になった瞬間、`#camera-error` / `#camera-help-modal` / `.arjs-loader` を非表示にし
     `window.arjsVideoReady = true` を設定してのみ監視を停止する（誤表示の自動解消）。
   - `window.guideModalOpen` 中の処理（`_pendingCameraError` を保持）は従来通り。
2. `ensureCameraAccess()`（head.blade.php）:
   - **先行判定**: `video.srcObject` が既に存在する場合、新たな `getUserMedia` は行わず、
     `video.play().catch(...)` を試行して即戻り（タップgesture内で自動復帰を狙う。失敗でも監視継続で回復）。
   - `srcObject` が無い場合のみ従来の 4 制約セット再要求ロジックを維持。
3. `js-init.blade.php`:
   - `arjs-video-loaded` ハンドラで `#camera-error` も非表示にする（二重防御）。
   - `window.load` の監視開始とローダーフォールバックのタイムアウトを UA 判定で
     **iOS=`15000`ms / 他=`7000`ms** に変更（低スペック機の遅いコールドスタートを吸収）。
   - 監視開始前に既存 `video` の `play()` を1回試行する（iOS autoplay ブロック時）
4. `ui.blade.php`:
   - `#camera-error` の文言を「起動に時間がかかる場合があります／画面をタップすると再開されることがあります」
     等、誤表示時に正しい導線を与える表現に更新（要素ID・構造は維持）。

### 影響範囲
- 202610: `head.blade.php` / `js-init.blade.php` / `ui.blade.php` の3ファイルのみ。
- 202609 / 202605 / 202606 / 202603 / 共有JS（`public/js/ar-engine.min.js` 等）は変更しない。
- Android（Chrome・WebXR）経路の挙動はタイムアウト値以外不変（監視継続は Android でも「起動遅延の自動復帰」として有効）。
