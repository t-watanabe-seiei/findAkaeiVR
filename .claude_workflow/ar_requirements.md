# 要件定義 — ARstampRally202609 問題点修正

> 作成日: 2026-09-02
> 根拠: `resources/views/ARstampRally202609/analysis_qwen3.8.md`（検証レポート）、
> `resources/views/ARstampRally202606/analysis_qwen3.8.md`（主因A/B/C）、
> `resources/views/ARstampRally202606/analysis_claude_sonnet5.md`（全体分析）
> 対象: `resources/views/ARstampRally202609.blade.php` および `resources/views/ARstampRally202609/*.blade.php`（11ファイル）
> 制約（CLAUDE.md）: 日本語で説明・コード変更を最小に・カレントディレクトリ配下のみ・既存挙動の保存・実装前に php -l / テスト確認・指示にない機能は追加しない

---

## 1. 目的

ARstampRally202606 の分析で特定され、ARstampRally202609 でも**未改善のまま残っている**（検証レポート §0/§3/§4）問題点を修正し、
Android / iOS / PC の全端末で「カメラ映像の比率・マRecognizerとの3Dモデルの位置・スケール」が意図通り安定して動作するWebARアプリにする。

## 2. 現状把握（202609 の現状コード）

| # | 現状 | 所在（202609 の file:line） |
|---|------|------------------------------|
| 1 | Android 判定で `sourceWidth/Height` を 640×480（iOS=1280×720）にサーバーサイド固定。AR.js はこれを `getUserMedia` の `ideal` 制約兼射影パラメータに使用 → 実ストリーム・実画面比率と一致せず「カメラズーム・モデルズレ」 | `scene.blade.php` L1-7, L11 |
| 2 | `arjs-video-loaded` 時の arjs 再設定が**縦型ストリームのみ**（`videoWidth < videoHeight` 限定）。横長ストリーム/横長画面は 4:3 固定のまま。`resize`/`orientationchange` 追従もなし | `js-init.blade.php` L586-605 |
| 3 | Samsung 縦型のみ `480×640` に上書きする端末名ハードコードパッチ（場当たり対応） | `scene.blade.php` L73-92 |
| 4 | `applyCurrentScaleTo()` が「ルートスケール」「各メッシュスケール」の**両方**を同じ値 `v` に設定 → 世界スケールが `base²` 倍（maker00=0.36、maker01-20=1.21、PC wheel 2倍→4倍相当）。`markerFound` 毎に全端末で発火 | `js-init.blade.php` L31-56（呼出 L353, L378） |
| 5 | 「低解像度で再試行」で `sourceWidth:320; sourceHeight:240` に差し替えるが `displayWidth/Height` を更新しない → スケールがさらにずれる | `js-init.blade.php` L523-535 |
| 6 | `object-fit: cover !important` は見た目専用で射影パラメータには無効（コメント「Androidカメラズーム防止」は不正確） | `head.blade.php` L172-181 |
| 7 | 動画撮影: `MediaRecorder` 未実装環境（iOS 14.2 以前等）は alert しか出ず、ボタン自体は常時表示（機能低下） | `js-camera.blade.php` L93-100, `ui.blade.php` L16 |
| 8 | 景品交換 `exchangePrize()` に fetch 実行中の `disabled`/クールダウンガードなし（連打で二重POST） | `js-prize.blade.php` L178-214 |
| 9 | 低スペック判定が「Android 7以下」の UA 判定のみ（`deviceMemory`/`hardwareConcurrency` 未加味） | `head.blade.php` L22-35 |

## 3. 成功基準

1. **カメラ比率（主因A）**: Android（縦/横長画面）で、動画読み込み完了後、`a-scene` の `arjs` 属性の 4 値が「source=動画実寸（videoWidth/Height）、display=実画面（innerWidth/Height）」になっている。`markerFound` 時にカメラ映像の「ズーム込み」感が iOS と同程度になる。
2. **回転・リサイズ追従**: 画面を縦↔横に回転し、PC でウィンドウをリサイズしても、上記4値が実態に追従する（タイムアウト/デバウンス付き）。
3. **モデルスケール（主因B）**: マーカー認識時に、モデルの描画スケールが `dataset.baseScale`（maker00=0.6、maker01-20=1.1）× `currentScale`（1〜3）の**1回分**になる（`base²` でない）。PC の wheel を 2 倍にするとモデルも 2 倍（4倍でない）。
4. **低解像度経路（主因C）**: 「低解像度で再試行」後の再認識でも、モデルのスケール・位置が3.と同様に安定する。
5. **iOS 回帰**: iPhone（16:9ストリーム・縦型ストリーム両方）で上記1-3の挙動が劣化しない。
6. **動画撮影**: `MediaRecorder` 未実装環境では動画ボタンが非表示（クラッシュしない・誤タップしない）。対応環境では従来どおり撮影できる。
7. **景品交換**: 交換中の連打で `/api/exchange-prize` が二重に POST されない（UI のみで、サーバー挙動は変更しない）。
8. **既存機能の保存**: マーカー認識→3D表示→投擲→捕獲→スタンプ→景品交換→ギャラリー→写真撮影・カメラフォールバックの既存挙動がすべて維持される。
9. `php -l` が全変更 blade ファイルで警告・エラーなし。

## 4. スコープ

### In-Scope（今回の修正対象）
- `scene.blade.php`: UA 分岐の解消、`arjs` 初期値の整理、Samsung 上書きの処置
- `js-init.blade.php`: `applyCurrentScaleTo` 修正、`arjs-video-loaded` 全方向同期＋resize/orientation追従、低解像度リトライ時の display 更新
- `head.blade.php`: CSS コメントの修正（必要なら低スペック判定の精緻化）
- `js-camera.blade.php` / `ui.blade.php`: 動画ボタンの feature detection（必要なら）
- `js-prize.blade.php`: 交換ボタンの二重送信防止（必要なら）

### Out-of-Scope（`ARstampRally202609` 関連ファイルの範囲外・今回は実施しない）
- **P0 サーバー側**: `PrizeExchangeController` のスタンプ検証・`checkStatus()` バリデーション・API レート制限（`app/`, `routes/api.php`）
- **P1 インフラ系**: `ar-engine.min.js` / `ar-tracking.min.js` のバージョン管理・gzip/br 圧縮・`Cache-Control` 設定（`public/js`, web server 設定, `package.json`）
- 3Dモデル・マーカーアセット（`public/cg/202606/`）は流用したまま変更しない
- ARstampRally202606（旧キャンペーン）のファイルは変更しない

## 5. 前提・留意事項

- `ar-tracking.min.js` は `sourceWidth/Height` を `getUserMedia` の `ideal` 制約に、`displayWidth/Height` を射影計算に使用している（202606分析 §3/§4-1）。
- 4 値は動画読み込み完了後でないと確定しない → 起動直後の暫定値（`scene.blade.php`）は「読み込み後に即時上書きされる前提」で維持する。
- `frustumCulled = false`（AR でモデルが切り落とされないための正当な目的）は維持する。
- `object-fit: cover`（レターボックス防止）は見た目のため維持し、コメントのみ実態に修正する。
- `user-scalable=no` / ズーム抑止 / コンテキストメニューブロックはユーザー操作防御として維持する。
- Samsung 上書き（#3）を削除する場合、全方向同期（#2 の修正）が確実に発火することの順序依存に注意（`scene.blade.php` 内の script は A-Frame 初期化前に実行されるため、初期解像度が Samsung の実ストリームと大きく乖離すると認識不能になる懸念）。

## 6. 未確定事項（設計前に確認）

- ~~Q1: 修正スコープは In-Scope 全部（#1-#9）か、カメラ/スケール系（#1-#6）のみにするか~~
  → **決定（2026-09-02）: 全修正（#1-#9 すべて）**
- ~~Q2: `scene.blade.php` の Samsung 上書き（#3）は削除するか、暫定維持（実機確認後に削除）するか~~
  → **決定（2026-09-02）: 全方向同期（Fix-1）と同時に変更し、上書きブロックを削除**
- ~~Q3: 低スペック判定の精緻化（#9: `deviceMemory`/`hardwareConcurrency` 加味）は今回の対象にするか~~
  → **決定（2026-09-02）: 今回の対象とする**

※ 「待機時間の基準不一致（7s vs 10s）」はスコープ外として今回変更しない（指示にない機能追加を避けるため）。