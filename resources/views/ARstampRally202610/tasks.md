# ARstampRally202610 作業タスク

> 進め方: 1タスクずつ実施し、各ステップで検証してから次へ進む。
> **ステータス: 全タスク完了（2026-09-10）**

- [x] T1 ドキュメント作成（requirements / design / tasks / README）
- [x] T2 ビュー作成: 202609 のエントリ+パーシャル10個を 202610 に複製し、識別子を 202610 化（アセットパス `cg/202609/` ・ `202609/Model_*` は維持）
- [x] T3 `app/Http/Controllers/StampRally202610Controller.php` を作成（202609 版の複製）
- [x] T4 `routes/web.php` に `/stamp202610`（表示）と API 3本を追加
- [x] T5 `AppServiceProvider.php` にレートリミッター3種（stamp202610_*）を追加
- [x] T6 管理ダッシュボード: `AdminController::dashboard202610` + `admin/dashboard202610.blade.php` + admin ルート（期間 2026-09-17 〜 2026-10-31 JST）
- [x] T7 検証: `php -l`（全PHPファイル）/ ルート登録確認 / ビューレンダリング確認 / 202609 が未変更であることを確認
- [x] T8 完了報告（README 最終確認）

## 検証結果（T7）
- `php -l`: StampRally202610Controller / AdminController / AppServiceProvider / routes/web.php すべて構文OK
- ルート登録: `GET|HEAD /stamp202610`（stamp202610.index）・`POST /stamp202610/{record-scan,check-prize,exchange-prize}`・`GET|HEAD /admin/dashboard202610`（admin.dashboard202610）
- `View::make('ARstampRally202610')->render()`: 200KB の HTML を生成（タイトル・`cg/202609/` アセット参照・`/stamp202610/` API を確認）
- `admin/dashboard202610.blade.php`: Blade コンパイル + `php -l` で構文OK
- `git status`: 202609 関連ファイル（views / controller）は変更なし
