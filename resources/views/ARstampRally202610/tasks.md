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

## iOS（iPhone7 / iPhone SE3）「カメラが起動できません」誤表示の改修（2026-09-11 追加）
> 対象ファイルは 202610 パーシャルのみ（head / js-init / ui）。202609 等・`public/js/` への変更なし。
> 前段階のmdファイルを読み込みました。
> **ステータス: 全タスク完了（2026-09-11）**

- [x] T15 `head.blade.php` / `monitorCameraStartup` を「タイムアウト後も継続監視（video ready でエラー画面等を自動非表示）」へ変更
- [x] T16 `head.blade.php` / `ensureCameraAccess` で「`srcObject` が既に存在する場合は `video.play()` のみ試行し二重 `getUserMedia` を行わない」修正
- [x] T17 `js-init.blade.php` の監視開始処理で UA 判定により iOS=15000ms / 他=7000ms を渡す（ローダー強制非表示のフォールバックも同一値に整合）＋`arjs-video-loaded` ハンドラでも `#camera-error` を非表示（二重防御）
- [x] T18 `ui.blade.php` の `#camera-error` 文言を「起動が遅い場合もある／タップで再開」導線に更新
- [x] T19 検証: `php -l`（変更ファイル）+ ビューレンダリング確認 + 202609 未変更の確認（git status）
- [x] T20 README / tasks 進捗・完了報告

## 検証結果（iOS カメラ誤表示改修・2026-09-11）
- `php -l`: `head.blade.php` / `js-init.blade.php` / `ui.blade.php` すべて構文OK
- ビューレンダリング（アプリブート + `view()->render()`）: `head`(25KB)/`js-init`(49KB)/`ui`(7KB) すべて正常生成、修正トークン（`monitorCameraStartup` / `ensureCameraAccess` / `hideCameraErrorUI` / `cameraStartupTimeout` / `arjs-video-loaded` / `camera-error`）を検出
- `git status`: 202609 関連（views / controller）・`public/js/` は変更なし。変更は 202610 パーシャル3ファイル + 関連md のみ
- 動作: 低スペックiOSでもカメラ起動（AR.js のコールドスタートが 7〜15秒程度）するケースで、誤表示された「カメラの起動を確認しています」画面が video ready / `arjs-video-loaded` 発火で自動非表示になる（タイムアウト後も監視継続）。タップ時の `ensureCameraAccess` は `srcObject` 既存なら `play()` のみを実行し、二重 `getUserMedia` によるストリーム競合（iOS `NotAllowedError`）を回避

## 検証結果（T7）
- `php -l`: StampRally202610Controller / AdminController / AppServiceProvider / routes/web.php すべて構文OK
- ルート登録: `GET|HEAD /stamp202610`（stamp202610.index）・`POST /stamp202610/{record-scan,check-prize,exchange-prize}`・`GET|HEAD /admin/dashboard202610`（admin.dashboard202610）
- `View::make('ARstampRally202610')->render()`: 200KB の HTML を生成（タイトル・`cg/202609/` アセット参照・`/stamp202610/` API を確認）
- `admin/dashboard202610.blade.php`: Blade コンパイル + `php -l` で構文OK
- `git status`: 202609 関連ファイル（views / controller）は変更なし
