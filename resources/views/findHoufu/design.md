# 設計（findHoufu）

## 更新履歴
| 日付 | 内容 |
|------|------|
| 2026-09-14 | sky01〜sky10 / 結果 sky / モデル位置・向き の設計を追加（第2フェーズ） |

## 1. 対象ファイル
- `resources/views/findHoufu/_scene.blade.php` … `<a-assets>` 内の sky 画像定義、`<a-sky>` 初期値
- `resources/views/findHoufu/_components.blade.php` … `STAGE_CONFIG` / `LOCATIONS` / `MODEL_SCALE` / `preloadNextStage` / `computeYawFacingCamera` / `placeModelAt`

## 2. sky 定義（_scene.blade.php）
- `<a-assets>` 内に `sky01`〜`sky10` の `<a-asset-item>`/`<img>` を、`requirements.md` の対応表に従い `cg/202609/*.jpg` を指定して定義する。
- 結果画面用として `skyResult`（`IMG_20260405_507.jpg`）を追加する。
- `<a-sky id="aSky" src="#sky01">` の初期値は `#sky01` のまま維持する。

## 3. ステージ設定（_components.blade.php / STAGE_CONFIG）
- キー `1`〜`10` は `skyId: sky01`〜`sky10`、`bgmId: bgm_s1`、`isResult: false`、`timeLimit: 16`。
- キー `11` は `skyId: skyResult`、`bgmId: bgm_s4`、`isResult: true`。
- プリロード判定 `if (stageNum < 7)` → **`if (stageNum < 11)`** に変更し、全10段階で次段 sky を読み込む。

## 4. モデル配置（_components.blade.php）
- `window.LOCATIONS` を「座標 `{ x, y, z }` のみ」を持つ9要素の配列に差し替える。
- 全位置共通のスケールを `window.MODEL_SCALE = '0.5 0.5 0.5'` として定義する。
- `computeYawFacingCamera(mx, mz)` を新設：
  - カメラ座標（`#my_camera` の世界座標、取得できない場合は原点）とモデル座標のxz差分から、
    A-Frame の正面（+Z 軸）がカメラを向く yaw（度）を `Math.atan2(dx, dz) * 180 / Math.PI` で算出する。
  - 距離が 0 に近い場合は `0` を返す。
- `placeModelAt(locIdx)`：
  - 位置を `cfg.x/cfg.y/cfg.z` から文字列化して設定する。
  - 回転は `computeYawFacingCamera(cfg.x, cfg.z)` で算出した yaw を `0 {yaw} 0` として設定する。
  - スケールは `window.MODEL_SCALE` を設定する。
  - 以降の gltf-model / 出現アニメ / 当たり判定(hit-box) / 出現音 の処理は従来通り維持する。

## 5. 非破壊設計
- スコア計算・コンボ・BGM 切替・効果音・ランキング送信/取得・ボール発射・当たり判定・フェード遷移・VR 入退場 のロジックは **変更しない**。
- `exitToStart()` の sky リセット（`#sky01`）は維持する。
- `showResult()` の `placeModelAt(0)` は新形式の `LOCATIONS[0]` で動作する（カメラ方面を向く）。

## 6. 検証
- `php artisan view:cache` で全 Blade をコンパイルし、findHoufu 系ビューがエラーなく生成することを確認。
- `_components.blade.php` の JS を抽出して `node --check` で構文検証済み。
- `public/cg/202609/` に参照する11枚（sky01〜sky10 + 結果用）の jpg が実在することを確認。
