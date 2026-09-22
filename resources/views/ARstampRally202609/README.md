# ARstampRally202609（iOS カメラ起動 表示遅延の改修・完了報告）

> 作成日: 2026-09-23
> 本 README は 202609 側の「iPhone SE 3rd（iOS 17.2）で `ARstampRally202610` より起動表示が遅い」問題の
> 202610 側ロジック（FR-9 / FR-10 / FR-11 同等）バックポート改修の完了報告である。

## 1. 内容
202609 と 202610 の `head.blade.php` を diff 比較し、202610 にのみ存在する**カメラ監視・再試行ロジック差分**
（以下 C1〜C4）を 202609 へ移植した。2026-09-11 に適用済みの video セレクタ修正（`analysis20260911.md` §6）は
維持・変更なし。

| # | 移植項目（202610 由来） | 対象 |
|---|---|---|
| C1 | video ready 時 `hideCameraErrorUI()` で `#camera-error` / `#camera-help-modal` / **`.arjs-loader`** を自動非表示（**表示遅延の本命修正**） | `head.blade.php` / `monitorCameraStartup` |
| C2 | `timedOut` フラグ（タイムアウト分岐の重複抑制）/ ready 時 `window._pendingCameraError=false` 復位 | 同上 |
| C3 | FR-10: `video.srcObject` 有なら 2度目 `getUserMedia` を行わず `play()` のみ（iOS ストリーム競合 → `location.reload()` ループの解消） | `head.blade.php` / `ensureCameraAccess` |
| C4 | FR-11: `#camera-error` 文言の導線更新（「カメラの起動を確認しています」/ タップで再開される場合がある） | `ui.blade.php` |

- 202609 固有識別子（`ar-camera-reload-202609` / `[AR202609]` ログ / アセットパス `cg/202609/`）・
  ボタン構造（`再試行` / `低解像度で再試行`）・`js-init` の iOS=15000ms / 他=7000ms タイムアウトは**維持**。
- 変更ファイル: `head.blade.php` / `ui.blade.php` の2パーシャルのみ。
  202610・他キャンペーン・`public/js/`・`public/cg/` は**未変更**（`git status` 確認済み）。

## 2. 変更ファイル
| ファイル | 変更 |
|---|---|
| `resources/views/ARstampRally202609/head.blade.php` | `monitorCameraStartup` 置換（C1/C2）/ `ensureCameraAccess` に FR-10 ブロック追加（C3） |
| `resources/views/ARstampRally202609/ui.blade.php` | `#camera-error` の h2 / p 文言置換（C4・構造不変） |
| `resources/views/ARstampRally202609/requirements.md` | 新規作成（FR-1〜FR-7） |
| `resources/views/ARstampRally202609/design.md` | 新規作成（差分設計・影響範囲・検証計画） |
| `resources/views/ARstampRally202609/tasks.md` | 新規作成（T1〜T7 + 進捗ログ） |
| `resources/views/ARstampRally202609/analysis20260911.md` | §9「2026-09-23 バックポート実施済み」追記 |

## 3. 検証結果（2026-09-23）
- `php -l`: `head.blade.php` / `ui.blade.php` ともに **No syntax errors detected**
- ビューレンダリング（Laravel ブート + `view()->render()`）: `head` = 25979 bytes / `ui` = 7462 bytes、正常生成
- トークン検証（レンダリング後 HTML）:
  - `timedOut` = 3件 / `hideCameraErrorUI` = 2件（定義+呼出）/ `querySelector('video')` = 3件
  - FR-10 ブロック（`if (v && v.srcObject) {`）= 1件 / `[AR202609]` = 2件 / `ar-camera-reload-202609` = 1件（維持）
  - `#ar-scene video` リテラル = **0件**
  - ui: 新文言 = 1件 / `retry-camera-lowres` ボタン維持 = 1件 / 旧文言（「カメラを起動できません」）= **0件**
- 分離確認（`git status`）: 変更は `ARstampRally202609` パーシャル2 + 関連md5 のみ（202610 / `public/js/` / `public/cg/` / 他キャンペーン未変更）
- 影響ゼロ保証: スタンプ集計 / 景品交換 / ギャラリー / ボール投擲 / マーカー認識は対象コード（ローダー / `#camera-error` / video 監視）に依存しないため不変

## 4. 参照
- 要件: `requirements.md`（FR-1〜FR-7）
- 設計: `design.md`（§3 改修設計 / §4 影響範囲）
- タスク・進捗: `tasks.md`（T1〜T7）
- 過去の経緯: `analysis20260911.md`（§6: 2026-09-11 セレクタ修正 / §9: 本バックポート）
