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
| GET | `/admin/dashboard202610` | 管理ダッシュボード（期間 2026-09-17 〜 2026-10-31 JST） |

## 主なファイル
- `resources/views/ARstampRally202610.blade.php` — エントリ
- `resources/views/ARstampRally202610/` — パーシャル10個（head / ui / scene / aframe-components / js-stamps / js-prize / js-throw / js-gallery / js-camera / js-init）
- `app/Http/Controllers/StampRally202610Controller.php` — 景品交換API
- `app/Providers/AppServiceProvider.php` — レートリミッター（stamp202610_*）
- `app/Http/Controllers/AdminController.php` — dashboard202610()
- `resources/views/admin/dashboard202610.blade.php` — 管理画面

## 202610 固有の識別子（分離）
- LocalStorage: `ar-stamp-rally-202610` / `ar-captured-animals-202610` / `ar-gallery-selection-202610` / `ar-user-id-202610`
- IndexedDB: `ARStampRallyDB202610`、Cookie: `ar_user_id_202610`
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
