# 要件定義: admin/dashboard202606 景品交換セクションを最上部へ移動（2026-05-31）

## 作成日時
2026-05-31

## 対象ファイル
- `resources/views/admin/dashboard202606.blade.php`

## 背景
- 現在、「🎁 未使用の景品交換」「✅ 使用済み景品交換」の2セクション（prizes-grid）はページ最下部にある
- 管理者がページを開いた際に景品交換状況を最優先で確認したいため、最上部への移動が必要

## 現在の表示順序
1. ヘッダー
2. 景品交換統計カード（総数/使用済み/未使用の数字）
3. 🐾 動物別統計
4. 📝 最近のスキャン履歴
5. 📅 日別スキャン統計
6. 👥 日別個別ユーザー数
7. 🎁 未使用の景品交換 ＋ ✅ 使用済み景品交換

## 変更後の表示順序
1. ヘッダー
2. 🎁 未使用の景品交換 ＋ ✅ 使用済み景品交換  ← ここへ移動
3. 景品交換統計カード（総数/使用済み/未使用の数字）
4. 🐾 動物別統計
5. 📝 最近のスキャン履歴
6. 📅 日別スキャン統計
7. 👥 日別個別ユーザー数

## 要件
- `prizes-grid` div ブロック全体をヘッダー直後に移動する
- HTML・CSS・JavaScript・コントローラーの機能変更は不要

## 成功基準
- ページを開いたとき、ヘッダーの直後に 🎁/✅ の2セクションが表示される
- 他のセクションの表示内容・機能は変化なし
- `php -l` で構文エラーなし（Bladeファイルのみ変更のためphp -l不要だが、構文崩れがないこと）

---

# 要件定義: admin/dashboard202606 動物別統計の動物名表示修正（2026-05-31）

## 作成日時
2026-05-31

## 対象ファイル
- `app/Http/Controllers/AdminController.php`（`dashboard202606` メソッド内 `$animals` 配列）

## 背景
- `admin/dashboard202606` の「動物別統計」セクションで、動物名が `キャラクター01`〜`キャラクター20` と表示されている
- 実際の動物名は `resources/views/ARstampRally202606/js-stamps.blade.php` の `STAMPS` 定数に定義されている
- 「最近のスキャン履歴」では DB の `marker_name` カラムから実名が表示されているが、「動物別統計」ではコントローラー側の固定マップを使用しているため不一致

## 要件
- `AdminController::dashboard202606()` 内の `$animals` 配列を、実際の動物名に更新する
- STAMPS の定義と完全に一致させる（以下の対応表）:
  - model_01: シマウマ
  - model_02: シカ
  - model_03: とら
  - model_04: とり
  - model_05: ぶた
  - model_06: ビーバー
  - model_07: レッサーパンダ
  - model_08: きりん
  - model_09: いぬ
  - model_10: リス
  - model_11: あらいぐま
  - model_12: チーター
  - model_13: きつね
  - model_14: パンダ
  - model_15: ぞう
  - model_16: カタツムリ1
  - model_17: カタツムリ2
  - model_18: カタツムリ3
  - model_19: カタツムリ4
  - model_20: ぶっちー

## 成功基準
- 「動物別統計」テーブルの `marker_name` 列にシマウマ、シカ、とら… と表示される
- `php -l` で構文エラーなし
- 変更は `$animals` 配列の値のみ（最小変更）

---

# 要件定義: ARstampRally202606 オフライン GLB 表示対応（2026-05-30）

## 作成日時
2026-05-30

## 対象ファイル
- `resources/views/ARstampRally202606/aframe-components.blade.php`

## 背景
- ARstampRally202605 はオフライン（通信なし）でもマーカースキャン時にモデルが表示される
- ARstampRally202606 は通信環境がないとモデルが表示されない
- 原因: 202606 は新しく GLB ファイルがブラウザ HTTP キャッシュに存在しないため、
  `lazy-model` が `setAttribute('gltf-model', url)` を呼んだとき GLTFLoader の fetch が失敗する
- 202605 は過去の訪問で GLB がキャッシュ済みなのでオフラインでも表示できている

## 要件
- ページをオンラインで一度開いた後、オフラインでもマーカースキャン時にモデルが表示されること
- `lazy-model` の `init()` 内で `fetch(url, { cache: 'default' })` をバックグラウンド実行し、
  GLB ファイルをブラウザ HTTP キャッシュに事前登録する
- fetch はレスポンスボディを使わない（キャッシュ登録が目的）
- fetch 失敗（オフライン起動など）は無視する（エラー表示不要）
- 21 ファイル（Model_00〜Model_20）が並列でダウンロードされることを許容する

## 成功基準
- オンラインでページを開いた後、通信を切断してマーカーをスキャンするとモデルが表示される
- `php -l` で構文エラーなし
- 既存の lazy-model の動作（markerFound でロード、markerLost でアンロード）に変化なし

---

# 要件定義: ARstampRally202606 ギャラリー選択モデルスケール縮小（2026-05-21）

## 作成日時
2026-05-21

## 対象ファイル
- `resources/views/ARstampRally202606/js-gallery.blade.php`

## 変更概要
ギャラリー（maker00）に表示される捕獲済み選択モデル4体のサイズを20%小さくする。
Model_00.glb（中央固定モデル）はサイズ変更なし。

## 現状
- `js-gallery.blade.php` の `loadNextModel()` 内で選択4体のスケールを `0.6 0.6 0.6` に設定
- `scene.blade.php` の `model-00` エンティティのスケールは `0.6 0.6 0.6`

## 要件
- 捕獲済み選択モデル4体: スケール `0.6 0.6 0.6` → `0.48 0.48 0.48`（現在値の80%）
- Model_00.glb（`#model-00`）: スケール変更なし（`0.6 0.6 0.6` を維持）

## 成功基準
- ギャラリーで4体のモデルが0.48スケールで表示される
- Model_00.glb は0.6スケールのまま
- 既存のアニメーション・hitbox・配置位置に変更なし

---

# 要件定義: ARstampRally202606 新規作成（2026-05-20）

## 作成日時
2026-05-20

---

## ARstampRally202606 要件

### 1. 概要
ARstampRally202605を参考に、ARstampRally202606を新規作成する。
1ファイル1,000行超を避けるためサブディレクトリ方式でモジュール化する。

### 2. ファイル構成
```
resources/views/ARstampRally202606.blade.php
resources/views/ARstampRally202606/
    head.blade.php, ui.blade.php, scene.blade.php,
    aframe-components.blade.php, js-stamps.blade.php,
    js-prize.blade.php, js-throw.blade.php,
    js-gallery.blade.php, js-camera.blade.php, js-init.blade.php
resources/views/admin/dashboard202606.blade.php
```

### 3. マーカー・モデル
- マーカー: 21個 (maker00〜maker20)、maker00はギャラリー専用
- 捕獲対象: maker01〜maker20（20種）
- CGパス: `public/cg/202606/`
- モデル: Model_00.glb〜Model_20.glb（各anime01/02/03付き）

### 4. アニメーション仕様
- ヒット前: anime01ループ
- ヒット時: anime02一度再生 → モデル非表示（捕獲完了）
- ギャラリーヒット時: anime02一度再生 → anime01ループに戻る（スタンプ取得なし）

### 5. 投擲: 画面タップ（HUDスワイプなし）202605と同じ

### 6. スタンプ帳
- 対象: model_01〜model_20（20種）
- 名前: キャラクター01〜キャラクター20（仮）
- TOTAL_STAMP_SLOTS: 20
- LocalStorageキー: `ar-stamp-rally-202606`, `ar-captured-animals-202606`
- リセットボタン: 「キャラクターを逃がす」

### 7. 景品交換
- 閾値: 10匹以上（202605の5匹から変更）

### 8. ギャラリー（maker00）
- **Model_00.glb**: 常に (0,0,0) に固定表示（選択対象外）
- **捕獲済み選択モデル最大4体**: スタンプ帳で選択した4体を (0,0,1),(0,0,-1),(1,0,0),(-1,0,0) に表示
- 合計最大5体（Model_00固定 + 選択4体）、すべてY=0
- anime01ループ、ヒット時anime02→anime01（スタンプ取得なし）
- hitboxコンポーネント付き（Model_00・選択4体ともに）
- スタンプ帳の選択上限: 4体（GALLERY_MAX_DISPLAY = 4）

### 9. 管理ダッシュボード（/admin/dashboard202606）
- 期間: 2026-05-20 00:00:00 〜 2026-06-10 23:59:59 (JST)
- 集計: model_01〜model_20の20種、202605ダッシュボードと同形式

### 10. 差分まとめ（202605との比較）
| 項目 | 202605 | 202606 |
|------|--------|--------|
| 捕獲対象数 | 10種 | 20種 |
| 景品交換閾値 | 5匹 | 10匹 |
| ギャラリー配置 | Y軸方向等間隔 | 固定XZ（Y=0） |
| ギャラリーアニメ | anime03 | anime01（ヒット時anime02→anime01） |
| ギャラリーhitbox | なし | あり |
| リセットボタン | コイを逃がす | キャラクターを逃がす |
| CGパス | cg/202605/ | cg/202606/ |

---

# 旧要件定義: /stamp202605 Galaxy S20+ 縦長カメラ・マーカー非認識バグ修正（2026-04-28）

## 作成日時
2026-04-28

## 前段階ファイル読込
既存 `requirements.md`, `design.md`, `tasks.md`, `complete.md` を読み込みました。

## 背景
- `/stamp202605` を Galaxy S20+（縦向き使用）で開くと、カメラ映像が縦長表示になる。
- マーカーが認識されず、3D モデルが表示されない。
- 以前に `arjs-video-loaded` イベント内で portrait 検出→ `setAttribute('arjs', ...)` という修正を行ったが、効果が出ていない可能性がある。

## コード分析による根本原因

### 判明した動作フロー（Samsung Galaxy S20+ 縦向き）
1. `ar-tracking.min.js` が `displayWidth`/`displayHeight` をもとに video 要素の inline style に `width` / `height` を px 指定する
2. `ar-engine.min.js` は `window.screen.width/height × devicePixelRatio` を使って表示サイズを計算し、縦向き時はビデオを縦型サイズ（例: 412×915 CSS px）に設定する
3. 一方 `scene.blade.php` の `sourceWidth: 640, sourceHeight: 480` はカメラを横型で要求する
4. Samsung は `{ width: { ideal: 640 }, height: { ideal: 480 } }` を無視し、縦型のストリーム（480×640）を返す場合がある
5. 結果: 横型を想定した tracking canvas と、縦型の video 表示の間でアスペクト比不一致が発生し、マーカーを認識できない
6. 既存の `arjs-video-loaded` 内での `setAttribute` による修正は A-Frame 初期化後の変更であり、AR.js のトラッキングコンテキストが再初期化されない可能性がある

### 現在の CSS の問題
- `video { object-fit: cover !important; }` は content の fitting のみ制御し、**要素自体のサイズは変更しない**
- AR.js が設定した `style.width/height` が残るため、video が画面を覆わない場合がある

## 目的
- Galaxy S20+（縦向き）でカメラ映像が正常に全画面表示され、マーカーが認識できるようにする
- 既存動作端末（iPhone/非Samsung Android/PC）への影響をゼロにする

## 確認済みユーザー回答
- 使用向き: **縦向き（ポートレート）のみ**
- 現在正常動作中: **iPhone(iOS)・非Samsung Android・PC**

## 変更対象ファイル候補
- `resources/views/ARstampRally202605/head.blade.php`（CSS / `getUserMedia` override）
- `resources/views/ARstampRally202605/scene.blade.php`（arjs 属性値・同期スクリプト追加）
- `resources/views/ARstampRally202605/js-init.blade.php`（既存 portrait 検出コードの改良）

## 成功基準
1. Galaxy S20+（縦向き）でカメラ映像が全画面に正しく表示される
2. Galaxy S20+（縦向き）でマーカーを検出し、3D モデルが表示される
3. iPhone・非Samsung Android・PC での既存動作が維持される
4. 変更ファイルに `php -l` エラーがない

## 制約
- 最小限の変更
- `ar-engine.min.js` / `ar-tracking.min.js` は変更しない（minified ファイル）
- Samsung 端末専用の分岐を入れる場合は UA 文字列を使用（`samsung` / `SM-[A-Z]`）

---

# 要件定義: /stamp202605 投擲時自動GET化（ズーム継続時の運用回避）

---

# 要件定義: /stamp202603 UI改修・自動GET実装（2026-04-22）

## 作成日時
2026-04-22

## 前段階ファイル読込
前段階のmdファイルを読み込みました（既存 `requirements.md`, `design.md`, `tasks.md`）。

## 背景
- `/stamp202603` は `/stamp202605` に先行するバージョン。
- `/stamp202605` で導入した以下の改善を `/stamp202603` にも反映したい。

## 目的・仕様（ユーザー確定済み）

### 1. ガイド初期非表示
- `window.guideModalOpen = true` → `false` に変更し、起動時にガイドモーダルを自動表示しない。
- ガイドボタンは残し、手動で開けるようにする。

### 2. Android Zoom修正
- 案B採用: 202605 の CSS・イベント一式をポート。
  - `video { object-fit: contain !important; }`
  - `a-scene canvas { object-fit: contain !important; width: 100% !important; height: 100% !important; }`
  - `gesturestart/change/end` preventDefault イベント追加
  - `touchmove` 2本指防止、`touchend` ダブルタップ防止イベント追加
  - `<a-entity camera look-controls="enabled: false">` 追加

### 3. UI左上集約
- 案A採用: `#top-left-buttons` コンテナを追加し、以下4ボタンを横並びに集約。
  - `#stamp-book-button`, `#guide-button`, `#camera-button`, `#video-button`
- 各ボタンの個別 `position: fixed` CSS は削除し、コンテナ内のスタイルに統一。
- `#throw-button` は現状位置のまま維持。

### 4. カメラ切替ボタン削除
- HTML から `<button id="switch-camera-button">` を削除。
- CSS から `#switch-camera-button` スタイルを削除。
- JS から `switchCameraButton.addEventListener(...)` イベントハンドラを削除。
- `isUIButton()` 内の `switch-camera-button` 参照を削除。

### 5. 投擲演出維持（案C: 両方残す）
- `#throw-button` → `throwPokeballToCenter(speed)` は従来どおり維持。
- 新規: `document.addEventListener('touchstart/touchend')` でどこでもタップで方向投げを追加。
- 新規: `document.addEventListener('mousedown/mouseup')` で PC マウスでも投げられるように追加。

### 6. マーカー検出中の自動GET（投擲1秒後）
- `pokeball-throwable` コンポーネントに `schema: { autoGetStampId, autoGetDelayMs }` 追加。
- `throw()` 実行時に `autoGetStampId` が設定されていれば、`autoGetDelayMs`（1000ms）後に `tryAutoGet()` を呼ぶ。
- `tryAutoGet()` は `collectAndMarkWithRetry(stampId, ...)` を呼ぶ。
- 実ヒットが先に発生した場合（`handleHit()` 内）は `autoGetTimer` をキャンセルして二重処理を防止。
- `getActiveVisibleStampId()` 関数を追加し、可視ヒットボックスから stampId を取得。
- 投擲時（ボタン・タップどちらも）に `getActiveVisibleStampId()` を取得して pokeball に渡す。

## 現状把握
- 対象ファイル: `resources/views/ARstampRally202603.blade.php`（7291行の単一ファイル）
- 参考ファイル: `resources/views/ARstampRally202605/` 配下の各サブファイル
- `pokeball-throwable` は line 349 付近。schema なし。
- `throwPokeballToCenter()` は line 4659 付近。
- `#switch-camera-button` CSS は line 1165 付近、イベントハンドラは line 6515 付近。
- ガイド初期フラグは line 14。
- UI ボタン HTML は line 2166 付近。

## 成功基準
1. ガイドモーダルが起動時に自動表示されない。ガイドボタンで手動表示は可能。
2. Android でのカメラ映像ズームが抑制される（`object-fit: contain !important` + look-controls 無効）。
3. UIボタン4個が左上にまとまって表示される。カメラ切替ボタンが消える。
4. 画面どこでもタップでも、下部ボタンでも投げられる。演出（ボール飛行）が維持される。
5. マーカー検出中に投げたとき、1秒後に自動GETが発動する。
6. 実ヒットが先に発生した場合、自動GETはキャンセルされる。
7. `php -l` で構文エラーがない。

## 制約
- 変更は `ARstampRally202603.blade.php` のみ。
- 既存の命中判定（tick/handleHit）ロジックを壊さない。
- iPhone・PC などの既存正常端末の動作を維持する。

## 作成日時
2026-04-21

## 前段階ファイル読込
前段階のmdファイルを読み込みました（`.claude_workflow/complete.md` / 既存 `requirements.md`）。

## 背景
- Android（moto g64y 5G）でズーム問題が未解消。
- ボールが飛ぶこと自体は見えるが、ズームにより着弾位置が把握しづらく捕獲しにくい。
- 既存端末を壊さないため、最小限変更で回避策を入れたい。

## 目的
- 画面タップでボールを投げた際、**マーカー検出中の対象を自動GET** できるようにする。
- 既存の投擲演出（ボールが飛ぶ表示）は維持する。

## ユーザー確認（2026-04-21）
- 適用範囲: **全端末（Android / iPhone / PC）**
- 自動GET条件: **マーカー検出中のみ（activeModel がある時だけ）**
- 演出: **ボールは従来どおり飛ばしつつ、同時に自動GET**

## 現状実装の確認結果
- 投擲入力は `js-throw.blade.php` の `touchend` / `mouseup` で処理。
- 実際の当たり判定・GETは `aframe-components.blade.php` の `pokeball-throwable` 内 `tick()` と `handleHit()` で処理。
- GET確定処理は `collectAndMarkWithRetry(stampId, ...)`（`js-stamps.blade.php`）で一元化済み。

## 成功基準
1. タップ投擲時、`activeModel` が存在する場合は当たり判定なしで該当 `stampId` をGETできる。
2. `activeModel` がない時は従来どおり（何もGETしない）。
3. 既存の命中時処理（パーティクル、通知、スタンプ帳反映）との整合性が崩れない。
4. `php -l` で変更ファイルの構文エラーがない。

## 制約
- 最小変更を最優先（既存ロジックの大規模改修は行わない）。
- 既存端末で動作中の機能を壊さない。
- 仕様追加は `/stamp202605` のみ。

## 調査対象（全読了）
- `routes/web.php`
- `resources/views/ARstampRally202605.blade.php`
- `resources/views/ARstampRally202605/head.blade.php`
- `resources/views/ARstampRally202605/scene.blade.php`
- `resources/views/ARstampRally202605/ui.blade.php`
- `resources/views/ARstampRally202605/aframe-components.blade.php`
- `resources/views/ARstampRally202605/js-init.blade.php`
- `resources/views/ARstampRally202605/js-stamps.blade.php`
- `resources/views/ARstampRally202605/js-prize.blade.php`
- `resources/views/ARstampRally202605/js-throw.blade.php`
- `resources/views/ARstampRally202605/js-gallery.blade.php`
- `resources/views/ARstampRally202605/js-camera.blade.php`

## 現状把握（原因候補）

### 原因候補1: AR.js設定の二重定義
- `scene.blade.php` でサーバーサイドに `arjs` を定義している。
- `js-init.blade.php` で Android 時に再度 `setAttribute('arjs', ...)` している。
- 結果として、初期化順序とAR.js内部状態によって挙動が不安定化する可能性がある。

### 原因候補2: ズーム対策CSSの適用対象が不十分
- `head.blade.php` では `video { object-fit: contain !important; }` のみ適用。
- AR.js/A-Frame の最終描画は `canvas` であるため、`video` のみ対象では期待どおりに効かない端末がある。

### 原因候補3: Android解像度が固定（640x480）
- `scene.blade.php` と `js-init.blade.php` の双方で Android を 4:3 固定にしている。
- moto g64y の縦長画面（ポートレート）との比率差により、見かけ上「拡大される」ように見える可能性がある。

### 原因候補4: ユーザー操作としてのピンチとブラウザズーム防止の競合
- `js-init.blade.php` ではシーン内ピンチでモデル拡大縮小を許可している。
- `head.blade.php` ではブラウザズーム防止イベントを登録している。
- 端末実装依存でイベント競合し、意図しない表示スケーリングになる可能性がある。

## 変更方針の制約
- 既存の正常端末（iPhone含む）の動作を壊さない。
- 既存機能（マーカー検出、投擲、ギャラリー、撮影、景品交換）を維持する。
- 変更は最小限かつ段階的に行う。
- 原因切り分け可能な順序で適用する。

## 成功基準
- moto g64y 5G で `/stamp202605` 表示時に、操作に支障のあるズーム状態が解消される。
- モデル表示・マーカー追従・投擲・ギャラリーが継続動作する。
- iPhone など既存正常端末の挙動を維持する。

## 設計前確認の回答（ユーザー確定）
1. ズーム種別: 「ARカメラ映像だけ拡大される」
2. ピンチ操作: 「無効化する」
3. 端末特化対応: 「できれば避ける（汎用対応優先）」
4. 修正範囲: 「ARstampRally202605 配下のみ」


---

# 要件定義: shooting3Dterrer4 VRシューティングゲーム 2ステージ構成（2026-06-09）

## 作成日時
2026-06-09

## 背景
- shooting3Dterrer3.blade.php（3508行）を参考にした新規VRシューティングゲーム
- 2ステージ制・武器選択・コントローラーモデル切り替えを追加
- 1ファイル1000行超になるためモジュール化（3ファイル構成）

## 対象ファイル（新規作成）
- `resources/views/shooting3Dterrer4/index.blade.php`（メインエントリー、~50行）
- `resources/views/shooting3Dterrer4/_components.blade.php`（A-Frameコンポーネント定義JS、~1800行）
- `resources/views/shooting3Dterrer4/_scene.blade.php`（a-scene内HTMLエンティティ、~700行）
- `routes/web.php`（ルート追加）

## 既存参照ファイル
- `resources/views/shooting3Dterrer3.blade.php`（3508行）

---

## ゲーム仕様

### 全体構成
- ステージ制：ステージ1 → ステージ2 の順に進む（Level選択なし）
- スタートメニューから直接ゲーム開始（START ボタン1つ）
- 武器選択はゲーム開始前にグリップ/A/Bボタンで切り替え

### 武器選択
| 武器 | コントローラーモデル | 発射弾 |
|------|------|------|
| Gun 1（デフォルト） | cg/gun_01.glb | cg/poke_ball_05.glb |
| Gun 2 | cg/gun_02.glb | cg/poke_ball_seieiv.glb |

- VRモード: 左右コントローラーのグリップ/A/Bボタンで切り替え
- 非VRモード: スタートメニューの武器表示をクリックで切り替え
- スタートメニューに「Grip/A/B: Switch Weapon」アナウンスを表示
- スタートメニューに現在の選択武器を表示（Gun 1 / Gun 2）
- ゲーム開始後は武器ロック（変更不可）
- 右コントローラーのモデルが選択武器に合わせて切り替わる

---

### ステージ1

| 項目 | 値 |
|------|------|
| 制限時間 | 90秒 |
| 背景 | cg/R0010143a.JPG |
| BGM | cg/sound_bgm08.mp3 |
| ゾンビモデル（通常） | cg/20260613/01.glb 〜 05.glb（5体同時出現） |
| ボスモデル | cg/zombie_morishige4.glb |
| 必要ヒット数（通常） | 1回 |
| 必要ヒット数（ボス） | 15回 |
| ボス出現タイミング | 残り15秒 |
| スコアAPI game_mode | 'terrer4_s1' |

#### ステージ1終了後
- スコア表示（terrer3と同様）
- ランキング表示（terrer4_s1 ランキング）
- 最下部に「Next Stage」ボタン（緑色）
- Restartボタンなし（Next Stageのみ）

---

### ステージ2

| 項目 | 値 |
|------|------|
| 制限時間 | 90秒 |
| 背景 | cg/R0010131a.JPG |
| BGM | cg/sound_bgm06.mp3 |
| ゾンビモデル（通常） | cg/20260613/06.glb 〜 10.glb（5体同時出現） |
| ボスモデル | cg/zombie_fujii.glb |
| 必要ヒット数（通常） | 2回 |
| 必要ヒット数（ボス） | 15回 |
| ボス出現タイミング | 残り15秒 |
| スコアAPI game_mode | 'terrer4_s2' |

#### ステージ2終了後
- スコア表示（terrer3と同様）
- ランキング表示（terrer4_s2 ランキング）
- 最下部に「Game Over」ボタン（赤色）
- Game Overボタン押下 → 画面が次第に暗くなる → 5秒後 window.close()
- window.close()失敗時: 「このタブを閉じてください」メッセージ表示

---

### ステージ遷移（1→2）
1. 「Next Stage」ボタンをクリック/トリガー
2. 画面が次第に暗くなる（黒フェードアウト、1秒）
3. ステージ2の初期化処理（背景・BGM・モデル切り替え）
4. 画面が明るくなる（黒フェードイン、1秒）
5. ステージ2スタートメニューは表示しない（直接ゲーム開始）

#### 事前ロード（プリロード）
- ステージ1プレイ中（ゲーム開始直後）にステージ2アセットをバックグラウンドでロード
- a-assetsにステージ1・ステージ2の全モデル・サウンドを宣言（preload属性管理）
- ステージ2の a-asset-item は src を空にして開始時に動的設定 → または全アセットを初期ロード

---

### UI要素
#### スタートメニュー（ステージ1開始時のみ表示）
- タイトル: "seieiVR SHOOTING GAME Stage 1"
- 現在の武器表示: "WEAPON: Gun 1 [Switch: Grip/A/B]"
- STARTボタン（水色）

#### タイマー・スコア表示（ゲーム中）
- terrer3と同様（TIME: / SCORE:）

#### リザルト画面（ステージ1）
- STAGE CLEAR
- スコア・最大コンボ・コメント
- ランキング（terrer4_s1）
- Next Stage ボタン（緑）

#### リザルト画面（ステージ2）
- GAME OVER
- スコア・最大コンボ・コメント
- ランキング（terrer4_s2）
- Game Over ボタン（赤）

---

## モジュール構成

### index.blade.php (~50行)
```
<!DOCTYPE html>
<html><head>
  <meta charset="UTF-8">
  <meta name="csrf-token">
  <title>seieiVR Terrer4</title>
  <script> // ライブラリ読み込み </script>
  @include('shooting3Dterrer4._components')
</head>
<body>
  @include('shooting3Dterrer4._scene')
</body>
</html>
```

### _components.blade.php (~1800行)
- window.DEBUG_MODE 等グローバル変数定義
- ステージ管理変数（window.currentStage = 1）
- AFRAME.registerComponent: enhance-materials
- AFRAME.registerComponent: face-camera
- AFRAME.registerComponent: start-menu
- AFRAME.registerComponent: result-menu
- AFRAME.registerComponent: shoot
- AFRAME.registerComponent: approach-camera
- AFRAME.registerComponent: hit-box
- AFRAME.registerComponent: auto-enter-vr
- AFRAME.registerComponent: vr-controller

### _scene.blade.php (~700行)
- a-assets（全ステージのモデル・サウンド・画像）
- ライト設定
- カーソル・コントローラー
- スタートメニューHTML
- タイマー・スコア表示
- リザルト画面（ステージ1: STAGE CLEAR + Next Stage）
- リザルト画面（ステージ2: GAME OVER + Game Over button）
- モデルグループ×5
- 背景（a-sky）
- パーティクルエフェクト
- カメラ

---

## ルーティング
```php
Route::match(['get', 'head'], '/terrer4', function () {
    return view('shooting3Dterrer4.index');
})->name('terrer4.index');
```

---

## API
既存の `/api/shooting-scores` エンドポイントをそのまま使用。
- ステージ1スコア保存: `game_mode: 'terrer4_s1'`
- ステージ2スコア保存: `game_mode: 'terrer4_s2'`
- ランキング取得: `?level=1&game_mode=terrer4_s1` / `?level=1&game_mode=terrer4_s2`

---

## 差異（terrer3との比較）
| 項目 | terrer3 | terrer4 |
|------|---------|---------|
| ステージ | 単一 | 2ステージ制 |
| Level選択 | Level 1/2 | なし（ステージが難易度差を担う） |
| 武器 | gun_01固定 | gun_01 / gun_02 選択可 |
| 弾 | poke_ball_seiei.glb | gun1: poke_ball_05 / gun2: poke_ball_seieiv |
| ゾンビモデル | zombie_* 6種 | 20260613/01-10 + boss各1体 |
| BGM | bgm10 | ステージ1: bgm08 / ステージ2: bgm06 |
| 背景 | R0010131a | ステージ1: R0010143a / ステージ2: R0010131a |
| 終了 | Restart / ページリロード | Next Stage → Game Over → ブラウザ閉じる |
| game_mode | terrer | terrer4_s1 / terrer4_s2 |

---

## 成功基準
- PHP構文エラーなし（php -l で確認）
- ステージ1 → ステージ2 がスムーズに遷移する
- 武器切り替えが正常に動作する
- スコアがステージ別に保存・表示される
- VRゴーグルで正常に動作する
- 各ファイルが1000行以内

