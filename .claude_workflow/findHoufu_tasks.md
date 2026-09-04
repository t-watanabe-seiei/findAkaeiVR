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
---

## 追加タスク（2026-09-04）

前提: `.claude_workflow/findHoufu_design.md` §9 を読み込み済み

| # | タスク | 対象ファイル | 優先 | 進捗 |
|---|--------|-------------|------|------|
| T13 | STAGE_CONFIG: 全ステージ `timeLimit: 16` + Stage1〜6 `bgmId: 'bgm_s1'` に変更 | `_components.blade.php` | P0 | ⬜ |
| T14 | LOCATIONS: 全モデルサイズ50%に变更 | `_components.blade.php` | P0 | ⬜ |
| T15 | グローバル `window._cachedHitEmissive = new THREE.Color(0xFFFFFF)` 追加 | `_components.blade.php` | P0 | ⬜ |
| T16 | shoot: `this.speed = 30` → `this.speed = 15` | `_components.blade.php` | P0 | ⬜ |
| T17 | shoot tick: ボールヒット時 emissiveフラッシュ + scaleフェードアウト + 300ms後リリース | `_components.blade.php` | P0 | ⬜ |
| T18 | advanceStage: BGMロジックを「同一BGMループ」方式に変更（isResult時はスキップ） | `_components.blade.php` | P0 | ⬜ |
| T19 | showResult: `resultTimeLeft = 12` → `16` | `_components.blade.php` | P0 | ⬜ |
| T20 | 構文チェック（php -l） | `_components.blade.php` | P0 | ⬜ |
| T21 | README.md に更新内容を追記 | `README.md` | P1 | ⬜ |

---

## 進捗更新（2026-09-04 完了）

| # | タスク | 進捗 |
|---|--------|------|
| T13 | STAGE_CONFIG: timeLimit 16 + bgm_s1 統一 | ✅ |
| T14 | LOCATIONS: モデルサイズ50% | ✅ |
| T15 | `_cachedHitEmissive` グローバル追加 | ✅ |
| T16 | ボール速度 30→15 | ✅ |
| T17 | ボールヒット時 emissive+scaleフェード | ✅ |
| T18 | BGM同一ループ方式 | ✅ |
| T19 | showResult: 12→16秒 | ✅ |
| T20 | 構文チェック（php -l） | ✅ |
| T21 | README.md 追記 | ✅ |
