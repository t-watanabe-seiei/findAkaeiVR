# findHoufu タスクリスト

> 作成日: 2026-07-09  
> 最終更新: 2026-09-03  
> 前提: `.claude_workflow/findHoufu_design.md` を読み込み済み

| # | タスク | 対象ファイル | 優先 | 進捗 |
|---|--------|-------------|------|------|
| T1 | `index.blade.php` 作成 | `resources/views/findHoufu/index.blade.php` | P0 | ✅ |
| T2 | `_scene.blade.php` 作成 | `resources/views/findHoufu/_scene.blade.php` | P0 | ✅ |
| T3 | `_components.blade.php` 作成（ユーティリティ+グローバル） | `resources/views/findHoufu/_components.blade.php` | P0 | ✅ |
| T4 | `_components.blade.php` 作成（A-Frame コンポーネント） | `resources/views/findHoufu/_components.blade.php` | P0 | ✅ |
| T5 | 構文チェック（php -l） | 全 3 ファイル | P0 | ✅ |
| T6 | 動作確認 & 修正 | — | P1 | ⬜ |

---

## 修正タスク（2026-09-03 追加）

| # | タスク | 対象ファイル | 優先 | 進捗 |
|---|--------|-------------|------|------|
| T7 | `auto-enter-vr`：`loaded` イベント待ちに修正 | `_components.blade.php` | P0 | ✅ |
| T8 | `start-menu`：`clickBlocked` を `false` 初期化に修正 | `_components.blade.php` | P0 | ✅ |
| T9 | `shoot`：`triggerdown` リスナーをコントローラーに移動（PC は document mousedown 維持） | `_components.blade.php` | P0 | ✅ |
| T10 | `vr-controller` コンポーネント登録追加 | `_components.blade.php` | P0 | ✅ |
| T11 | 構文チェック（php -l） | `_components.blade.php` | P0 | ✅ |
| T12 | README.md に修正内容を追記 | `README.md` | P1 | ✅ |