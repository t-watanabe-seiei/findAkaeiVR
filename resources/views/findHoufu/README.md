# findHoufu（seieiVR FIND BUCCHI）

A-Frame(WebXR/VR) による「モデルを探して撃つ」シューティングゲーム。

## 概要
- スタートメニューから開始し、**各ステージでモデル(bucchi)が出現** するので、トリガー／クリックで撃ち落とす。
- **10段階**のステージを制覇すると結果（スコア）画面へ遷移し、スコア・最大コンボ・ヒット数・ランキングを表示する。
- 一定時間経過後、自動でスタートメニューへ戻る。

## 構成ファイル
| ファイル | 役割 |
|----------|------|
| `index.blade.php` | HTML / A-Frame スクリプト読み込み / ビュー include |
| `_scene.blade.php` | a-scene・アセット(sky・BGM・SE・モデル)・UI(start/result/HUD) 定義 |
| `_components.blade.php` | ゲームロジック（ステージ進行・モデル配置・発射・当たり判定・スコア・ランキング等） |
| `requirements.md` | 要件定義 |
| `design.md` | 設計 |
| `tasks.md` | タスクと進捗 |

## ルート
- `GET /findHoufu` … ゲーム表示（`routes/web.php`）
- `POST /api/findhoufu-scores` … スコア保存（`routes/api.php`）
- `GET /api/findhoufu-scores/top5` … ランキング取得（`routes/api.php`）

## sky（背景）
- **sky01〜sky10**：`public/cg/202609/` 内の jpg（`IMG_20260405_507.jpg` を除いた10枚）。
  - sky01 `IMG_20260204_153.jpg`
  - sky02 `IMG_20260204_154.jpg`
  - sky03 `IMG_20260305_425.jpg`
  - sky04 `IMG_20260331_473.jpg`
  - sky05 `IMG_20260405_497.jpg`
  - sky06 `IMG_20260405_502.jpg`
  - sky07 `IMG_20260405_506.jpg`
  - sky08 `IMG_20260405_508.jpg`
  - sky09 `IMG_20260405_526.jpg`
  - sky10 `IMG_20260405_537.jpg`
- **skyResult（結果画面用）**：`IMG_20260405_507.jpg`

## モデル表示
- 表示位置は以下9点からランダム選択：
  `(-5,0,-5) (0,0,-7) (5,0,-5) (7,0,0) (5,0,5) (-7,0,0) (0,0,7) (-5,0,5) (-2,-1,1)`
- 向きは**カメラの方を向く**（`computeYawFacingCamera` による yaw 計算）。
- スケールは全位置共通 **0.5**（`window.MODEL_SCALE`）。

## 更新履歴
### 2026-09-14
- sky01〜sky06 を **sky01〜sky10 に拡張**（画像追加）。
- **結果画面時の sky を `IMG_20260405_507.jpg`（`skyResult`）に変更**。
- ステージを **10段階 ＋ 結果(11)** に変更（プリロード判定も `stageNum < 11` に更新）。
- モデル表示位置を **指定9点のランダム** に変更し、**向きをカメラ方面** に変更。スケールは全位置で **0.5** に統一。
- 上記以外の既存機能（スコア／コンボ／BGM／SE／ランキング／発射・当たり判定／フェード／VR 入退場）は変更なし。
