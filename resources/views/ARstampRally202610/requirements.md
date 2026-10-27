# ARstampRally202610 要件定義

## 1. 目的
2026年10月（2026-09-17 開始）開催の月次 AR スタンプラリーキャンペーン。
ARstampRally202609 と同一のアーキテクチャ（Bladeパーシャル + クライアントJS + サーバー側景品交換API）を複製し、
既存の 3D アセットをそのまま再利用する。既存キャンペーン（202603 / 202605 / 202606 / 202609）への影響はゼロとする。

## 2. 機能要件
| # | 要件 |
|---|---|
| FR-1 | `GET /stamp202610` で AR スタンプラリーページを表示（202609 と同一のUI・操作） |
| FR-2 | マーカー走査・ボール投擲によるスタンプ集め（クライアント側の LocalStorage / IndexedDB に保持） |
| FR-3 | スキャン実績を `POST /stamp202610/record-scan` でサーバー記録 |
| FR-4 | 10種以上を集めると `POST /stamp202610/exchange-prize` で景品コード交換可能（サーバー側で閾値10種を強制） |
| FR-5 | `POST /stamp202610/check-prize` で交換済み状態を確認 |
| FR-6 | レートリミッターで不正連打防止（scan / check: 分120回、redeem: 時10回） |
| FR-7 | 管理ダッシュボード `admin/dashboard202610`（期間 2026-09-17 00:00:00 〜 2026-10-31 23:59:59 JST）でスキャン統計・景品交換実績を表示 |

## 3. 非機能要件・制約
- **アセット再利用**: 3Dモデル（Model_00〜20.glb）・マーカー（pattern-maker00〜20.patt）は `public/cg/202609/` をそのまま参照する（コピーしない）。
- **分離性**: 202609 等の既存コードは一切変更しない。クライアントの保存キー（LocalStorage / IndexedDB / Cookie）・ルート・レートリミッターはすべて 202610 専用にする。
- **DB**: 既存の `marker_scans` / `prize_exchanges` テーブルを再利用（新規マイグレーションなし）。
- **プラットフォーム**: Android は Chrome（WebXR）、iOS は Safari（AR.js / Three.js 実装）で 202609 と同等に動作すること。
- **PSR-12** / Laravel 11 規約に準拠。

## 5. 改修: iOS（iPhone7 / iPhone SE3）での「カメラが起動できません」誤表示の解消（2026-09-11 追加）

### 現象
- 「AR を起動中...」→「カメラが起動できません」エラー画面が固定表示される。
- 再試行・低解像度で再試行を繰り返しても同様のエラーが続く。
- しかし、**実際にはマーカー認識・モデル表示は正常**（エラーメッセージの裏にARが見えている）。
- 対象: iPhone7 / iPhone SE3（iOS Safari、AR.js/Three.js フォールバック経路）。

### 根本原因（調査結論）
1. **カメラ監視が 7 秒で永久放棄**（`head.blade.php` / `monitorCameraStartup`）:
   7 秒以内に video が ready にならなかった瞬間に `setInterval` を解除し、以降の再チェックを行わない。
   低スペック機（A9 等）ではコールドスタートで 7 秒を超えるため、その後カメラが起動しても
   「カメラが起動できません」オーバーレイ（半透明黒・z-index 9999）が**自動で消えない**。
   `arjs-video-loaded` ハンドラもローダーとヘルプモーダルのみを非表示にし、エラー画面は非表示にしていない。
2. **リトライ処理が二重キャプチャ要求になる**（`head.blade.php` / `ensureCameraAccess`）:
   AR.js が既にストリームを確保済み（`video.srcObject` あり）でも、まだ最初のフレームが
   readyState に反映されていない状態だと「未準備」と判定され、2度目の `getUserMedia` を発行する。
   iOS では既存ストリームとの競合（NotAllowedError 等）で全制約セットが失敗し、エラー画面が再表示される
   （「同じことの繰り返し」）。ユーザーのタップという操作gestureが `video.play()` に活かされていない。

### 機能要件（追加）
| # | 要件 |
|---|---|
| FR-8 | iOS ではカメラ監視タイムアウトを 15 秒に延長し、低スペック機の遅いコールドスタートを吸収する |
| FR-9 | カメラ監視はタイムアウト後も継続し、video が ready になった時点でエラー画面・ヘルプモーダル・ローダーを**自動非表示**にする（誤表示の自動解消） |
| FR-10 | リトライ時は既存ストリーム（`srcObject`）が既に存在する場合は新たな `getUserMedia` を要求せず、タップgesture内で `video.play()` を試行して自動復帰させる（二重キャプチャ防止） |
| FR-11 | 誤表示時にユーザーに正しい導線（タップで再開/権限確認）を提示するエラー文言にする |
| FR-12 | 修正対象は 202610 のパーシャルのみとし、202609 等他のキャンペーンおよび共有JS（`public/js/`）への変更は行わない |

## 6. 根本原因修正（video セレクタ誤り）と要件の再定義（2026-09-11 追記・最終）

> **要約**: §5 で設定した FR-8〜FR-12（監視継続 / 二重 getUserMedia 防止 / タイムアウト拡張）を
> 適用しても旧 iPhone で症状が解消しなかった。その真因は、カメラ監視・確保処理が
> video 要素を**誤ったセレクタ**で取得していたことである。本節が最終的な根本原因と修正要件。

### 根本原因（§5 の診断に対する再定義）
- AR.js（`public/js/ar-tracking.min.js`）はカメラ `<video>` を `<a-scene>` 配下ではなく
  **`document.body` 直下**に `appendChild` する（ライブラリ本体で確認）。
- そのため `document.querySelector('#ar-scene video')` は**常に `null`** を返し、
  カメラが実動作していても ready 判定（`readyState >= 2 || ...`）に到達しない
  →「カメラを起動できません」が固定表示される。
- 正常系（`ARstampRally202605` / `ARstampRally202609@e0c369c`）は `document.querySelector('video')`
  を使い、同様の症状は現れていない。

### 機能要件（追加・最優先）
| # | 要件 |
|---|---|
| FR-13 | カメラ video の取得セレクタを `document.querySelector('video')` とする（`#ar-scene video` は使わない）。`monitorCameraStartup` / `ensureCameraAccess` / `getUserMedia` 成功ハンドラの **3 箇所すべて**で適用する |
| FR-14 | 修正対象は `ARstampRally202610/head.blade.php` のみ。202609 等の他キャンペーン・共有JS（`public/js/`）は変更しない（分離原則）。現行 202609 が同バグを持つ場合は `analysis20260911.md` の「要対応」に留め、本タスクでは修正しない |

## 7. 再交換リセット（方式②・保存キー名変更）と期間変更（2026-09-30 追記）

### 背景
- 2026-10（イベント本番）に向けて、過去に景品交換済みのユーザーが **再交換できるようにする**（テスト/プレビュー期間の交換済みレコードでブロックされることを解消）。
- 併せて、管理ダッシュボードの集計期間を実イベント期間に合わせる。

### 機能要件（追加）
| # | 要件 |
|---|---|
| FR-15 | 202610 関連のクライアント保存キー（LocalStorage / IndexedDB / Cookie）のキー名にサフィックス `-r2` を付与し、**新userId → 新fingerprint** を生成して `prize_exchanges` の交換済み判定（`session_id` / `fingerprint`）と切り離す。これにより交換済みユーザーも再交換可能にする |
| FR-16 | 同時にスタンプ収集状態（`ar-stamp-rally-*` / `ar-captured-animals-*` / `ar-gallery-selection-*` / `ar-prize-exchanged-*` / `ar-prize-code-*`）も新キー化し、**全面リセット**で 10/24 に改めて 10 種を集めてから交換させる |
| FR-17 | 過去交換済みレコード（`prize_exchanges`）の削除・更新は行わない（履歴保持）。変更はクライアント側のキー文字列のみ（API・DB スキーマ変更なし） |
| FR-18 | 管理ダッシュボード `admin/dashboard202610` の集計期間を **2026-10-01 00:00:00 〜 2026-10-24 23:59:59（JST）** に変更 |

## 8. オフライン対応（Service Worker・2026-10 追加）

### 背景
- 会場（屋外・地下・山間部等）では通信が不安定・不通になり、初回ロード以降 AR が開けなくなる可能性がある。
- 202610 は 3D モデル 21 体（`public/cg/202610/` 計 59MB）とマーカー 21 枚（`.patt`）を HTTP 取得しており、
  通信断時に再取得できないことが最大の課題。
- スタンプ収集状態は LocalStorage に保持済みのため、AR 表示そのものは通信不要で継続できる。

### 機能要件（追加）
| # | 要件 |
|---|---|
| FR-19 | 202610 専用 Service Worker `public/sw-stamp202610.js` を導入し、`/stamp202610` ページで登録する（既存キャンペーンの SW 登録・`public/service-worker.js` は変更しない） |
| FR-20 | インストール時に「最小必須セット」を事前キャッシュする: ページ HTML・オフラインページ・共有JS（ar-engine / ar-tracking / geo-location / loaders）・`css/stampRally.css`・マーカー 21 枚（`cg/202610/pattern-maker00..20.patt`）・ロード中モデル `Model_00.glb`・ボール（`objects/ball.obj` `ball.mtl`）・`sounds/loading.ogg`・`img/howToOperate.png` |
| FR-21 | 21 体すべての GLB（59MB）はインストール時に事前キャッシュしない。初回オンライン取得時にキャッシュし、以降オフラインでも表示できるようにする（cache-first / stale-while-revalidate） |
| FR-22 | 取得戦略: ページ（navigation）は network-first → キャッシュ → オフラインページの順。静的アセット（`js/` `css/` `img/` `build/` `cg/202610/`）は cache-first（背景で更新）。API POST（record-scan / check-prize / exchange-prize）は一切キャッシュしない |
| FR-23 | オフライン専用ページ `GET /stamp202610/offline`（外部アセット依存なし・インライン CSS/JS）を追加し、SW の最終フォールバックとして返す。収集済み種別数・未送信スキャン数を表示し、「AR を続ける（キャッシュ）」「再試行」「キャッシュ削除」を提供 |
| FR-24 | オフライン中のスキャン記録（`POST /stamp202610/record-scan`）は LocalStorage キュー `ar-scan-queue-202610-r2` に蓄積し、オンライン復帰（`online` イベント）時に自動で再送する（キュー上限 200 件、超過は古いものから破棄） |
| FR-25 | オフライン中は UI に状態表示（`#offline-banner`）を出し、オンライン復帰で自動で隠す |
| FR-26 | キャッシュ済み HTML の CSRF トークンが古くなった場合（セッション失効）に備え、`GET /stamp202610/csrf`（no-store）でトークンを再取得できること。オンライン復帰時およびキュー再送時に更新する |
| FR-27 | 景品交換（exchange-prize）はオンライン必須（オフラインでは行わない）。交換済み判定・閾値10種のサーバー強制は変更しない |
| FR-28 | 分離原則: キャッシュキーは `ar202610-*`、SW ファイル・ルートは 202610 専用。202609 等他キャンペーン・共有JS（`public/js/`）・`public/service-worker.js` は変更しない |
| FR-29 | 開発/デバッグ用に無効化導線を提供する: URL に `?sw=0` を付けると SW 登録をスキップ、オフラインページの「キャッシュ削除」で 202610 キャッシュを全消去できる |
