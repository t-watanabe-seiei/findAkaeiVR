# ARstampRally202610

2026年10月（2026-09-17 開始）開催の AR スタンプラリー。ARstampRally202609 のクローンとして作成し、
3Dモデル・マーカーアセットは `public/cg/202609/` をそのまま参照しています。

## 概要
- AR（マーカー走査 / ボール投擲）で隠れたキャラクター（20種 + Model_00）を捕まえるスタンプラリー
- 10種以上を捕まえると景品コードと交換可能（サーバー側で閾値を強制）
- 集計・交換実績は管理ダッシュボードで確認可能

## ルート
| メソッド | URI | 説明 |
|---|---|---|
| GET | `/stamp202610` | 参加ページ |
| POST | `/stamp202610/record-scan` | スキャン記録（分120回制限） |
| POST | `/stamp202610/check-prize` | 交換状態確認（分120回制限） |
| POST | `/stamp202610/exchange-prize` | 景品コード発行（時10回制限・閾値10種） |
| GET | `/admin/dashboard202610` | 管理ダッシュボード（期間 2026-10-01 〜 2026-10-24 JST） |

## 主なファイル
- `resources/views/ARstampRally202610.blade.php` — エントリ
- `resources/views/ARstampRally202610/` — パーシャル10個（head / ui / scene / aframe-components / js-stamps / js-prize / js-throw / js-gallery / js-camera / js-init）
- `app/Http/Controllers/StampRally202610Controller.php` — 景品交換API
- `app/Providers/AppServiceProvider.php` — レートリミッター（stamp202610_*）
- `app/Http/Controllers/AdminController.php` — dashboard202610()
- `resources/views/admin/dashboard202610.blade.php` — 管理画面

## 202610 固有の識別子（分離）
- LocalStorage: `ar-stamp-rally-202610-r2` / `ar-captured-animals-202610-r2` / `ar-gallery-selection-202610-r2` / `ar-user-id-202610-r2`
- IndexedDB: `ARStampRallyDB202610r2`、Cookie: `ar_user_id_202610_r2`
- スキャン日次キャッシュ: `marker-scan-cache-202610-*`
- レートリミッター: `stamp202610_scan` / `stamp202610_check` / `stamp202610_redeem`

## アセット（共用）
- モデル: `public/cg/202609/Model_00.glb` 〜 `Model_20.glb`
- マーカー: `public/cg/202609/pattern-maker00.patt` 〜 `pattern-maker20.patt`

## iOS 対応（カメラ起動の誤表示対策・2026-09-11 追加）
- iPhone7 / iPhone SE3 などの低スペック機では、AR.js のカメラコールドスタートに 7〜15 秒程度かかる場合があります。
  その場合でも実際にはカメラが起動しており、従来は「カメラが起動できません」画面が**そのまま残る**不具合がありました。
- 対応内容（202610 パーシャルのみ・`public/js/` 共通ライブラリは未変更）:
  - `monitorCameraStartup` はタイムアウト後も**監視を継続**し、video が後から ready になった時点でエラー画面等を自動非表示にします。
  - タップ時の `ensureCameraAccess` は、`video.srcObject` が既に存在する場合は**二重 `getUserMedia` を行わず `video.play()` のみ**を実行（iOS のストリーム競合 / `NotAllowedError` を回避）。
  - UA 判定で iOS なら監視タイムアウトを **15000ms**（他は 7000ms）に延長し、ローダー強制非表示のフォールバックとも同一値に整合。
  - `#camera-error` の文言を「起動に時間がかかる場合がある／タップで自動再開されることがある」導線に更新。
- 詳細な仕様は `requirements.md`（FR-8〜FR-12）/ `design.md` / `tasks.md`（T15〜T20）を参照してください。

## 注意
- スキャン・交換データは `marker_scans` / `prize_exchanges` テーブルを既存キャンペーンと共用している（202609 と同一仕様）。
- `cg/202609/` アセットを削除すると 202610 にも影響します。

## 根本原因の特定と最終修正（video セレクタ誤り・2026-09-11 追記）
- 上記「iOS 対応（FR-8〜FR-12）」の安全策（監視継続 / 二重 `getUserMedia` 防止 / タイムアウト拡張）を
  適用しても旧 iPhone で症状が解消しなかった**真の根本原因**を特定した。
  - **原因**: AR.js（`public/js/ar-tracking.min.js`）はカメラ `<video>` を `<a-scene>` 配下ではなく
    `document.body` 直下に `appendChild` するため、`document.querySelector('#ar-scene video')` が
    **常に `null`** になり、カメラが実動作していても監視の ready 判定に到達しなかった。
  - **修正**: 202610 の `head.blade.php` 3箇所（`monitorCameraStartup` / `ensureCameraAccess` /
    `getUserMedia` 成功ハンドラ）のセレクタを `document.querySelector('video')` に復旧（正常系
    202605 / 202609@e0c369c と同一）。
- **影響範囲**: 202610 の `head.blade.php` のみ（`public/js/` 共有ライブラリ・他キャンペーンは未変更）。
- **検証**: `php -l` 通過 / レンダリング正常（`querySelector('video')` 3件、`#ar-scene video` はコメント2行のみ）/
  `git status` で 202609 未変更を確認。詳細は `requirements.md`（§6・FR-13〜FR-14）/
  `design.md`（§8）/ `tasks.md`（T21〜T24）/ `analysis20260911.md` を参照。
- **既知の未対応（別キャンペーン）**: 現行 `ARstampRally202609`（HEAD）にも同様の `#ar-scene video`
  セレクタ（3箇所）が残存し、旧 iPhone で同症状が出る可能性。分離原則により本キャンペーンでは
  未修正（要対応: `resources/views/ARstampRally202609/analysis20260911.md` 参照）。

## 再交換リセットと期間変更（方式②・2026-09-30 追記）
- **目的**: 過去に景品交換済みのユーザーが **再交換できるようにする**（DB の交換履歴は保持）。
- **手段（方式②・保存キー名変更）**: 202610 関連のクライアント保存キーにサフィックス `-r2` を付与。
  - userId 系キー（LocalStorage `ar-user-id-202610-r2` / Cookie `ar_user_id_202610_r2` / IndexedDB `ARStampRallyDB202610r2`）が変わることで
    **新 userId → 新 fingerprint** が生成され、`prize_exchanges` の交換済み判定（`session_id` / `fingerprint`）と切り離される。
  - 同時にスタンプ収集状態（`ar-stamp-rally-*` / `ar-captured-animals-*` / `ar-gallery-selection-*` / `ar-prize-exchanged-*` / `ar-prize-code-*`）も新キー化し、
    **全面リセット**で 10/24 に改めて 10 種を集めてから交換させる。
- **変更ファイル**: `js-prize.blade.php` / `js-stamps.blade.php` / `js-init.blade.php`（キー文字列）＋ `AdminController::dashboard202610()`（集計期間）。
- **非変更**: 202609 等他キャンペーン、API（`StampRally202610Controller`）、DB スキーマ、`public/js/`。旧キーの既存データは残存（履歴保持）。
- **管理ダッシュボード期間**: `admin/dashboard202610` は **2026-10-01 00:00:00 〜 2026-10-24 23:59:59（JST）** に変更。
- 詳細な仕様・影響範囲は `requirements.md`（§7 / FR-15〜FR-18）・`design.md`（§9）・`tasks.md`（T25〜T29）を参照してください。
