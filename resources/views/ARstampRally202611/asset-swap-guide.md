# アセット切替ガイド（`cg/202610` → `cg/202611`）— ARstampRally202611

> 作成: 2026-10-29 / 更新: 2026-11-04（`cg/202611` への切替を実施・tasks.md T60）/ 対象要件: `requirements.md` FR-32〜FR-36・AC-2 / 設計: `design.md` §3.3・§8
> 目的: 3D モデルとマーカーを 202611 専用素材へ差し替える作業を、**設定 2 行 + SW 2 行**で完結させる。

## 1. 現状と切替の考え方

**2026-11-04 に `cg/202611` への切替を完了した**（`config/ar202611.php` の `asset_dir` = `'202611'`、
`public/sw-stamp202611.js` の `ASSET_DIR` = `'202611'` / `VERSION` = `'v2'`、
`public/cg/202611/` に 42 点配置）。202611 が参照する pattern / glb はすべて 202611 専用素材で、
`cg/202610/` への参照はレンダリング出力上 **0 件**（`cg/poke_ball_seinei2.glb` 等の共有アセットは除く）。

素材をさらに差し替える場合も、blade / Controller / 管理画面は `config('ar202611.asset_dir')` 経由で
パスを組み立てるため **切替による再実装は不要**（FR-32）。Service Worker は `cg/` 配下全般に
cache-first を適用するため、**フェッチ戦略の変更も不要**（FR-33）。

参照箇所（変更してはいけない箇所は無い。すべて下記の 2 ファイルから導出される）:

| 参照 | ファイル | 経路 |
|---|---|---|
| マーカー（AR.js pattern） | `resources/views/ARstampRally202611/scene.blade.php` | `config('ar202611.asset_dir')` + `marker_count` |
| 遅延ロードモデル（lazy-model） | 同上 | 同上 |
| スタンプ定義 `STAMPS[].model` | `resources/views/ARstampRally202611/js-stamps.blade.php` | 同上 |
| SW の precache（`CORE_URLS`） | `public/sw-stamp202611.js` | 先頭の `ASSET_DIR` 定数 |

## 2. 配置するファイル（Q-6 の命名規則）

`public/cg/202611/` に **202610 と同一的文件名**で 42 点:

```
public/cg/202611/
├── Model_00.glb … Model_20.glb            （21 点・Model_00 はロード中モデル）
└── pattern-maker00.patt … pattern-maker20.patt （21 点）
```

- **配置済み（2026-11-04）**: `public/cg/202611/` に 42 点（`Model_00.glb`〜`Model_20.glb` /
  `pattern-maker00.patt`〜`pattern-maker20.patt`）。`pattern-maker*.patt` は 202610 のものと
  **内容が異なる**（md5 不一致）ため、会場印刷物は 202611 版へ差し替えが必要（202610 の旧マーカーは認識されない）
- 動物名（`js-stamps.blade.php` の `STAMPS[].name` と管理画面の `$animals`）は 202611 専用素材の
  ラベルへ更新済み（下表・**202610 とは別物**）。キー `model_01`〜`model_20`（= `marker_id`）は変更していない
- マーカー枚数を変える場合は `config/ar202611.php` の `marker_count`（または `.env` の `AR202611_MARKER_COUNT`）だけ変更する
- 202610 側（`public/cg/202610/`）は**削除しない**（既存キャンペーンが参照している）

### 2.1 202611 の動物名（2026-11-04 確定）

`model_NN` は `pattern-makerNN.patt` ↔ `Model_NN.glb` の対応（`scene.blade.php` の `@for` ループと
`STAMPS` のキーで決まる）。**印刷物とモデルの対応はファイル番号で決まる**ため、業者素材の番号と
動物名がずれていないかは現物で確認する。

| `marker_id` | 動物名 | `marker_id` | 動物名 |
|---|---|---|---|
| `model_01` | ぶた① | `model_11` | クマ |
| `model_02` | カモ | `model_12` | ぶた③ |
| `model_03` | ペンギン① | `model_13` | ネコ |
| `model_04` | パンダ | `model_14` | クォッカ |
| `model_05` | 白くま | `model_15` | キリン |
| `model_06` | トナカイ | `model_16` | かわうそ |
| `model_07` | ゾウ | `model_17` | カピバラ |
| `model_08` | ペンギン② | `model_18` | きつね |
| `model_09` | ハシビロコウ | `model_19` | なかけもの |
| `model_10` | ぶた② | `model_20` | バーガー |

動物名を変更するときは **2 箇所を同時に**書き換える（名前だけの変更に `asset_dir` は無関係）:

1. `resources/views/ARstampRally202611/js-stamps.blade.php` の `STAMPS['model_NN'].name`
   （画面表示と `record-scan` の `markerName` 送信元）
2. `app/Http/Controllers/AdminController.php` の `dashboard202611()` 内 `$animals`
   （管理画面の動物別統計のラベル）

`marker_scans` には送信時点の `marker_name` が文字列で保存されるため、**既存行は自動では更新されない**
（集計は `marker_id` ベースなので種数・倍率・応募には影響しない。過去行のラベルを揃えるか否かは運用判断）。

## 3. 切替手順（チェックリスト・FR-36）

- [x] 1. `public/cg/202611/` に 42 点を配置（§2 の命名規則。ファイル名が 1 文字でも違うと 404 になる）→ **2026-11-04 完了**
- [x] 2. `config/ar202611.php` の `asset_dir` を `'202611'` に変更 → **完了**
      （`.env` の `AR202611_ASSET_DIR` で上書き可能。本番は `php artisan config:cache` を再実行）
- [x] 3. `public/sw-stamp202611.js` の **2 行**を変更 → **完了**
      ```js
      var VERSION   = 'v2';        // v1 → v2（activate で ar202611-*-v1 を破棄）
      var ASSET_DIR = '202611';    // cg/{ASSET_DIR} の単一点
      ```
- [x] 4. 動物名 20 種を §2.1 の表へ更新（`js-stamps.blade.php` と `AdminController::dashboard202611()`）→ **完了**
- [x] 5. 検証（§4）→ **完了**（tasks.md T60: `php -l` 3 件 OK / `StampRally202611Test` 28 tests・228 assertions PASS /
      `view('ARstampRally202611')->render()` = 235,283 字・`cg/202611/` 43 箇所・`cg/202610/` 0 箇所）
- [ ] 6. 以降さらに素材を更新するときは `VERSION` を `v3` へ（**`ASSET_DIR` を戻しても VERSION は必ず上げる**）

## 4. 切替後の検証

```
# PHP 構文（AC-1）
php -l config/ar202611.php

# blade が cg/202611 を指しているか（0 件であれば未切替）
grep -c "cg/202611" resources/views/ARstampRally202611/scene.blade.php   # config 経由のため 0 が正常
grep -n "asset_dir" resources/views/ARstampRally202611/scene.blade.php resources/views/ARstampRally202611/js-stamps.blade.php

# 実際の HTML 出力が cg/202611 になっているか（render して確認）
php artisan tinker
>>> view('ARstampRally202611')->render();   # 出力に cg/202611/Model_01.glb と pattern-maker01.patt が並ぶか

# SW の precache が 21 枚分生成されているか
grep -n "ASSET_DIR" public/sw-stamp202611.js

# ルートに変化がないか（AC-2）
php artisan route:list --path=stamp202611
```

ブラウザ側:

1. `/stamp202611` を開き、DevTools → Network で `cg/202611/…` の 200 を確認（`cg/202610/…` が出ていないか）
2. Application → Service Workers で `sw-stamp202611.js` が **v2** のキャッシュ
   （`ar202611-html-v2` / `ar202611-core-v2` / `ar202611-assets-v2`）を作り、`-v1` が消えていることを確認
3. 実機（Android Chrome / iOS Safari）でマーカー認識 → モデル表示。旧マーカー（202610 の印刷物）は認識されないはず

## 5. キャッシュの扱い（切替が反映されないときの対処）

- SW の静的アセット判定は `cg/` 配下全般が **cache-first**（`ignoreSearch: true`）。
  同一 URL の旧コピーがキャッシュに残ると、クエリを付けても更新されない
- `VERSION` を上げると `activate` で `ar202611-*-v1` 以前を削除し、新しい precache 名で再取得する（他キャンペーンのキャッシュには触れない）
- 手動で確実に消す: `/stamp202611/offline` の「キャッシュ削除」ボタン（SW へ `{type:'CLEAR_CACHES'}` → `ar202611-*` のみ消去・登録解除してリロード）
- デバッグ: `/stamp202611?sw=0` で SW 登録をスキップして素の取得状態を確認できる（FR-28）

## 6. ロールバック（202610 素材へ戻す）

1. `config/ar202611.php` の `asset_dir` を `'202610'` に戻す（または `.env`）→ `php artisan config:cache`
2. `sw-stamp202611.js` の `ASSET_DIR` を `'202610'` に戻し、**`VERSION` を `v2` のままにしない**（`v3` へ上げる）
3. 動物名も 202610 の一覧へ戻す（§2.1 の 2 箇所。`js-stamps.blade.php` と `AdminController::dashboard202611()`）
4. §4 の検証を再実行

DB の `marker_scans` は `marker_id` / `marker_name` で記録しているため、素材差し替えによって
既存の収集記録・抽選応募の種数判定は影響を受けない（fingerprint / email ベースの集計も同じ）。

## 7. つまずきやすい点

| 症状 | 原因 | 対処 |
|---|---|---|
| モデルもマーカーも 404 | `cg/202611` のファイル名が規則と違う（`model_01.glb` 小文字・`Model_1.glb` など） | §2 の命名に統一（大文字 `Model_NN` / `pattern-makerNN`、2 桁） |
| 画面は 202611 なのに旧モデルが出る | SW の cache-first で旧コピーが命中 | §5（`VERSION` 更新 / 「キャッシュ削除」/ `?sw=0`） |
| blade を編集したのに反映されない | 設定キャッシュ | `php artisan config:cache`（開発時は `config:clear`） |
| マーカーが 21 枚より少ない | `marker_count` と実ファイル数が不一致 | `marker_count` を実数に合わせる |
| 202610 の画面まで素材が変わった | `public/cg/202610/` を上書きした | 202610 側は変更しない（P-2）。`cg/202611/` にコピーする |
