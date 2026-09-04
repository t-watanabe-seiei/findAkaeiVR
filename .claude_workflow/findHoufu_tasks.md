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

---

## 追加タスク（2026-09-04 第2回）

前提: `.claude_workflow/findHoufu_design.md` §10 を読み込み済み

| # | タスク | 対象ファイル | 優先 | 進捗 |
|---|--------|-------------|------|------|
| T22 | `resetBallAppearance` 関数追加 | `_components.blade.php` | P0 | ⬜ |
| T23 | `acquireBall` リファクタ（アニメクリーンアップ+scale+emissive） | `_components.blade.php` | P0 | ⬜ |
| T24 | `releaseBall` リファクタ（アニメ削除+画外配置+scale 0.0001） | `_components.blade.php` | P0 | ⬜ |
| T25 | `createBallEntity` scale 0.15→0.1 | `_components.blade.php` | P0 | ⬜ |
| T26 | shoot: gravity 5.45→4.9, speed 15→20 | `_components.blade.php` | P0 | ⬜ |
| T27 | shoot tick: 回転 X軸 -1080°/sec に変更 | `_components.blade.php` | P0 | ⬜ |
| T28 | API相対パス化（baseUrl削除） | `_components.blade.php` | P0 | ⬜ |
| T29 | exitToStart: `sceneEl.exitVR()` パターンに変更 | `_components.blade.php` | P0 | ⬜ |
| T30 | 構文チェック（php -l） | `_components.blade.php` | P0 | ⬜ |
| T31 | README.md に更新内容を追記 | `README.md` | P1 | ⬜ |

---

## 進捗更新（2026-09-04 第2回 完了）

| # | タスク | 進捗 |
|---|--------|------|
| T22 | `resetBallAppearance` 関数追加 | ✅ |
| T23 | `acquireBall` リファクタ | ✅ |
| T24 | `releaseBall` リファクタ | ✅ |
| T25 | `createBallEntity` scale 0.15→0.1 | ✅ |
| T26 | gravity 5.45→4.9, speed 15→20 | ✅ |
| T27 | 回転 X軸 -1080°/sec | ✅ |
| T28 | API相対パス化 | ✅ |
| T29 | `exitToStart`: `sceneEl.exitVR()` | ✅ |
| T30 | 構文チェック | ✅ |
| T31 | README.md 追記 | ✅ |
