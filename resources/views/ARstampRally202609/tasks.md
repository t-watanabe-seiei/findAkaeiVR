# ARstampRally202609 作業タスク（iOS カメラ起動 表示遅延の改修）

> 作成日: 2026-09-23
> 進め方: 1タスクずつ実施し、各ステップで検証してから次へ進む。
> **ステータス: 全タスク完了（2026-09-23）**
> 範囲: `ARstampRally202609` パーシャル（head / ui）とドキュメントのみ（202610 / 他キャンペーン / `public/js/` / `public/cg/` 非変更・FR-5〜FR-7）。

---

# 追記: 2026-09-23 スタンプ動物名の更新（モデルデータ更新対応）

> 作成日: 2026-09-23
> 進め方: 1タスクずつ実施し、各ステップで検証してから次へ進む。
> 範囲: `ARstampRally202609/js-stamps.blade.php` の STAMPS 定義 + `app/Http/Controllers/AdminController.php::dashboard202609()` の `$animals` 配列 + 関連ドキュメント。

- [x] S1 ドキュメント作成（requirements / design / tasks への追記）
- [x] S2 `js-stamps.blade.php` の `STAMPS` 定義を新名20種に更新
- [x] S3 `AdminController.php::dashboard202609()` の `$animals` 配列を新名20種に更新
- [x] S4 検証: `php -l` ×2 + トークン検証（新名存在 / 旧名0件）+ `git status`（分離確認）
- [x] S5 `README.md` に追記
- [x] S6 `tasks.md` に進捗を最終更新

## 検証結果（2026-09-23・スタンプ名更新）
- `php -l`（2ファイル）: **No syntax errors detected**
- トークン検証:
  - 新名20種が `js-stamps.blade.php` に各1件 / `AdminController.php::dashboard202609()` 配列に各1件
  - 旧名16種（シマウマ / シカ / とら / とり / ビーバー / レッサーパンダ / きりん / リス / あらいぐま / チーター / きつね / カタツムリ1〜4 / イオちゃん / ぶっちー）が 202609 側2ファイルで **0件**
- 分離確認（`git status`）: 変更は `AdminController.php` / 202609 パーシャル1 / 関連md4 / `php_errors.log`（実行時ログ）のみ
  - 未変更: `ARstampRally202610/` / `ARstampRally202606/` / `ARstampRally202605/` / `ARstampRally202603/` / `public/cg/` / `public/js/` / `routes/` / `StampRally202609Controller.php`
- 影響ゼロ保証:
  - 収集判定は `STAMPS[sid].name` を参照しない（`captured[sid] === true` のキーのみ）
  - 景品判定は件数ベース（name 非依存）
  - ギャラリーは `stampId` 参照（name 非依存）
  - LocalStorage キー・API / DB スキーマ・アニメクリップ名・`cg/202609/` 内アセットは不変
  - 他キャンペーン（202610 / 202606 等）は独立した STAMPS / 配列 / アセットで影響なし

## 進捗ログ（2026-09-23・スタンプ名更新）
- 2026-09-23: S1 開始（requirements / design / tasks へ追記）
- 2026-09-23: S1 完了
- 2026-09-23: S2 完了（`js-stamps.blade.php` STAMPS 20行を新名に更新・`php -l` OK）
- 2026-09-23: S3 完了（`AdminController.php::dashboard202609()` `$animals` 20行を新名に更新・`php -l` OK）
- 2026-09-23: S4 完了（トークン検証 / 旧名0件 / `git status` で分離確認）
- 2026-09-23: S5 完了（`README.md` に §5〜§7 追記）
- 2026-09-23: S6 完了（本 tasks.md を最終更新・**全タスク完了**）

- [x] T1 ドキュメント作成（requirements.md / design.md / tasks.md）
- [x] T2 `head.blade.php` / `monitorCameraStartup` を 202610 同一ロジックに置換（`timedOut` フラグ / ready 時 `hideCameraErrorUI()` で `.arjs-loader` も非表示 / `_pendingCameraError` 復位）
- [x] T3 `head.blade.php` / `ensureCameraAccess` に FR-10 ブロック追加（`srcObject` 有なら `play()` のみ・2度目 `getUserMedia` 禁止）
- [x] T4 `ui.blade.php` の `#camera-error` 文言を 202610（FR-11）に準拠（h2 / p のみ・ボタン構造維持）
- [x] T5 `analysis20260911.md` に「2026-09-23 バックポート実施済み」を追記（§9）
- [x] T6 検証: `php -l`（head / ui）+ トークン検証 + ビューレンダリング + `git status`（202610・`public/` 未変更の確認）
- [x] T7 `README.md` 作成・更新内容の追記、tasks.md 進捗更新

## 検証結果（2026-09-23・T6）
- `php -l`: `head.blade.php` / `ui.blade.php` ともに **No syntax errors detected**
- ビューレンダリング（Laravel ブート + `view()->render()`）: `head` = 25979 bytes / `ui` = 7462 bytes、正常生成
- トークン検証（レンダリング後 HTML）:
  - `timedOut` = 3 / `hideCameraErrorUI` = 2（定義+呼出）/ `querySelector('video')` = 3
  - FR-10 ブロック `if (v && v.srcObject) {` = 1 / `[AR202609]` = 2 / `ar-camera-reload-202609` = 1（202609 固有識別子維持）
  - `#ar-scene video` リテラル = 0 / ui: 新文言 = 1 / `retry-camera-lowres` 維持 = 1 / 旧文言 = 0
- 分離確認（`git status`）: 変更は `ARstampRally202609` パーシャル2（head / ui）+ 関連md5（requirements / design / tasks / analysis20260911 / README）のみ。202610・`public/js/`・`public/cg/`・他キャンペーンは未変更
- 影響ゼロ: スタンプ集計 / 景品交換 / ギャラリー / ボール投擲 / マーカー認識は変更コードに依存しないため不変

## 進捗ログ
- 2026-09-23: T1 開始（requirements / design / tasks 作成）
- 2026-09-23: T1 完了
- 2026-09-23: T2 完了（`monitorCameraStartup` 置換・`php -l` 構文OK）
- 2026-09-23: T3 完了（FR-10 ブロック追加・`php -l` 構文OK）
- 2026-09-23: T4 完了（`#camera-error` 文言置換・`php -l` 構文OK）
- 2026-09-23: T5 完了（`analysis20260911.md` §9 追記）
- 2026-09-23: T6 完了（上記検証結果すべて合格・一時検証スクリプトは削除済み）
- 2026-09-23: T7 完了（README.md 作成・本 tasks.md 進捗更新・**全タスク完了**）
