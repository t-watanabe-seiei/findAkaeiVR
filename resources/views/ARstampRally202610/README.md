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

## 注意
- スキャン・交換データは `marker_scans` / `prize_exchanges` テーブルを既存キャンペーンと共用している（202609 と同一仕様）。
- `cg/202609/` アセットを削除すると 202610 にも影響します。
