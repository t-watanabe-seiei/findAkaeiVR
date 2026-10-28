# アセット切替ガイド（`cg/202610` → `cg/202611`）— ARstampRally202611

> 作成: 2026-10-29 / 対象要件: `requirements.md` FR-32〜FR-36・AC-2 / 設計: `design.md` §3.3・§8
> 目的: 3D モデルとマーカーを 202611 専用素材へ差し替える作業を、**設定 2 行 + SW 2 行**で完結させる。

## 1. 現状と切替の考え方

202611 は当面 `public/cg/202610/` の素材を参照して動作する（P-3）。
素材が揃い次第 `public/cg/202611/`（**同名ファイル**・Q-6）へ切り替える。
blade / Controller / 管理画面は `config('ar202611.asset_dir')` 経由でパスを組み立てるため、
**切替による再実装は不要**（FR-32）。Service Worker は `cg/` 配下全般に cache-first を適用するため、
**フェッチ戦略の変更も不要**（FR-33）。

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

- 動物名（`js-stamps.blade.php` の label、管理画面の動物リスト `model_01`〜`model_20`）は
  **ファイル名ではなくラベル**なので、素材差し替えだけでラベルは変わらない。動物名が変わる場合のみ該当箇所を編集する
- マーカー枚数を変える場合は `config/ar202611.php` の `marker_count`（または `.env` の `AR202611_MARKER_COUNT`）だけ変更する
- 202610 側（`public/cg/202610/`）は**削除しない**（既存キャンペーンが参照している）

## 3. 切替手順（チェックリスト・FR-36）

- [ ] 1. `public/cg/202611/` に 42 点を配置（§2 の命名規則。ファイル名が 1 文字でも違うと 404 になる）
- [ ] 2. `config/ar202611.php` の `asset_dir` を `'202611'` に変更
      （または `.env` に `AR202611_ASSET_DIR=202611`。本番は `php artisan config:cache` を再実行）
- [ ] 3. `public/sw-stamp202611.js` の **2 行**を変更
      ```js
      var VERSION   = 'v2';        // v1 → v2（activate で ar202611-*-v1 を破棄）
      var ASSET_DIR = '202611';    // cg/{ASSET_DIR} の単一点
      ```
- [ ] 4. 検証（§4）
- [ ] 5. 以降さらに素材を更新するときは `VERSION` を `v3` へ（**`ASSET_DIR` を戻しても VERSION は必ず上げる**）

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
2. `sw-stamp202611.js` の `ASSET_DIR` を `'202610'` に戻し、**`VERSION` は v2 のままにしない**（`v3` へ上げる）
3. §4 の検証を再実行

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
