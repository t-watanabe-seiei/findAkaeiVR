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

---

# 要件定義 — ARstampRally202609 景品交換APIのサーバー側強化（202609専用エンドポイント）

> 作成日: 2026-09-08
> 根拠: `resources/views/ARstampRally202609/analysis_qwen3.8_20260908.md` の P0-1 / P0-2 / P0-3
> 制約（CLAUDE.md）: 日本語で説明・変更を最小に・既存挙動の保存・実装前に `php -l` 確認・**202605 / 202606（旧キャンペーン）への影響を完全に排除**
> 重要: `routes/api.php` の `/api/record-marker-scan`・`/api/check-prize-exchange`・`/api/exchange-prize` は **202605 / 202606 が継続利用するため変更しない**

## 7. 目的

202609 の景品交換まわりの3つの P0 脆弱性を、**202609専用のサーバー側エンドポイント**として新設し、他キャンペーン（202605=閾値5 / 202606=閾値10）の閾値差を壊さず修正する。

## 8. 現状（202609 の脆弱性）

| # | 問題 | 箇所 |
|---|------|------|
| P0-1 | 景品交換API（`/api/exchange-prize`）がスタンプ件数・整合性を検証しない。クライアントの「10個以上」ガードは JS 内のみで、APIを直接叩けばスタンプ0個でもコード発行できる | `PrizeExchangeController::exchange()`・`routes/api.php:28` |
| P0-2 | 3端点が `routes/api.php`（CSRF 非適用グループ）にあり、`head.blade.php` の CSRF トークンは検証されない。クロスサイトフォームPOSTで景品コード発行可能 | `routes/api.php:26-28` |
| P0-3 | 3 API にレート制限（throttle）が無く、連打・自動化で DB 汚染・コード発行量暴走 | `routes/api.php:26-28` |

## 9. 成功基準

1. **P0-1**: `/stamp202609/exchange-prize` が `stamps` 件数10未満のとき **422** を返し、景品コードを発行しない（サーバー側強制）。
2. **P0-2**: 3端点すべてが `web` ミドルウェアグループ（CSRF + Session）を強制しセッションに紐づく。別サイトからのフォームPOSTは CSRF で拒否。
3. **P0-3**: スキャン記録・照会・交換それぞれに `throttle` を適用し、1分間 / 1時間 単位で上限を設ける。
4. **他キャンペーン影響なし**: 202605（閾値5）・202606（閾値10）は従来どおり `/api/...` を使用し、本変更の影響を受けない。
5. **既存機能保存**: マーカー認識→捕獲→スタンプ→交換→コード表示の既存UI挙動は維持（応答形状も同一）。
6. `php -l` が変更した PHP ファイルで警告・エラーなし。`php artisan route:list` で3新ルートが `StampRally202609Controller` に解決される。

## 10. スコープ

### In-Scope（今回実施）
- 新規 `app/Http/Controllers/StampRally202609Controller.php`（`recordScan` / `checkStatus` / `exchange`、閾値10をサーバー側で強制）
- `routes/web.php`: 202609 専用の3ルート（`web`グループ + `throttle`）
- `app/Providers/AppServiceProvider.php`: 202609 専用の命名レートリミッター3種
- `resources/views/ARstampRally202609/js-prize.blade.php`: 3つの fetch を新エンドポイントへ差し替え

### Out-of-Scope（変更しない）
- `routes/api.php` の `/api/record-marker-scan`・`/api/check-prize-exchange`・`/api/exchange-prize`（202605 / 202606 が継続利用）
- `app/Http/Controllers/MarkerScanController.php`・`PrizeExchangeController.php`（他キャンペーン向け）
- 202605 / 202606 の `js-prize.blade.php`（各閾値は従来どおり JS 内ガードのまま）

## 11. 前提・留意事項

- `SESSION_DRIVER=database`（`env` 確認済み）→ `web` グループでセッション/CSRF が機能する前提。
- 閾値10は 202609 の `js-prize.blade.php` の `PRIZE_EXCHANGE_THRESHOLD = 10` と一致させる。
- フィンガープリントは `recordScan` 時にセッションへ保持し、`checkStatus` / `exchange` でセッション優先・リクエスト値フォールバックに読み替える（既存の `orWhere` 挙動を維持しつつセッションと紐付け）。
- コード生成ロジック（ランダム5桁＋衝突チェック）は既存 `PrizeExchangeController::exchange()` と同一。
---

# 要件定義 — ARstampRally202609 最優先バグ修正（T-01 / T-02 / T-03）

> 作成日: 2026-09-09
> 根拠: `resources/views/ARstampRally202609/analysis_qwen3.8_20260908.md` §10.1（T-01/T-02/T-03）、§9.4（N1/N2）
> 制約（CLAUDE.md）: 変更を最小に・既存挙動保存・`php -l` 必須・他キャンペーン影響排除

## 12. 目的

P0 対応後に判明した**最優先3項目**（データ損失・大規模データ送信・機能欠損）を修正する。

| # | 問題 | 現状 | 修正目標 |
|---|------|------|----------|
| **T-01** | `collectStamp()` の catch が `localStorage.removeItem()` で**全スタンプ消去** | base64スクショ蓄積→クォータ超過時、景品交換直前で全データ消失 | catch 内で `screenshot: null` 化して**再保存**（スタンプ個数・名前は保持） |
| **T-02** | `exchangePrize()` が base64 スクショを含む `stamps` をそのまま POST | `stamps_data` 列に数MB JSON が蓄積（20体×1MB超） | 送付前に `screenshot` を除外し `{ stampId, collectedAt, name }` 配列のみ送付 |
| **T-03** | `checkStatus()` のレスポンスに `exchangedAt` 欠落 | 交換済みモーダルで交換日時が `undefined`（非表示） | `'exchangedAt' => $exchange->exchanged_at->toIso8601String()` を追加 |

## 13. 成功基準

1. **T-01**: `localStorage.setItem` が `QuotaExceededError` を投げても、`getCollectedStamps()` から `screenshot` を除外したデータが再保存され、スタンプ個数・名前が保持される。
2. **T-02**: `exchangePrize()` の POST body に `screenshot` フィールドが存在しない（DevTools Network で確認）。サーバー側 `stamps_data` 列に `screenshot` 文字列が含まれない。
3. **T-03**: `checkStatus()` のレスポンス JSON に `exchangedAt` フィールドが含まれる（ISO 8601 文字列 or `null`）。
4. **既存機能保存**: スタンプ捕獲・交換・コード表示・交換済み表示の既存UI挙動が維持される。
5. **他キャンペーン影響なし**: 202605 / 202606 のファイルに一切変更がない。
6. `php -l` が変更した全 PHP ファイルで警告・エラーなし。

## 14. スコープ

### In-Scope（今回変更）
- `resources/views/ARstampRally202609/js-stamps.blade.php` — `collectStamp()` の catch ブロック（L164-167）
- `resources/views/ARstampRally202609/js-prize.blade.php` — `exchangePrize()` の fetch body 整形
- `app/Http/Controllers/StampRally202609Controller.php` — `checkStatus()` のレスポンス

### Out-of-Scope
- `js-stamps.blade.php` の他関数（`collectAndMarkWithRetry` 等）
- `js-prize.blade.php` の他関数（`recordMarkerScan` / `checkPrizeExchangeStatus`）
- `PrizeExchangeController.php`（他キャンペーン共用、変更しない）
- DB スキーマ変更（`stamps_data` 列は `text`/`json` のまま）
- IndexedDB 移行・スクリーンショット保存の完全廃止（将来の選択肢）

## 15. 前提・留意事項

- T-01 の catch 内再保存もクォータ超過する可能性（極端な場合）は残る → その場合は従来の `removeItem` を最終フォールバックとして残す。
- T-02 で送付する `stamps` の形状がオブジェクト（`{ stampId: {...} }`）→ 配列（`[{ stampId, collectedAt, name }]`）に変わるが、サーバー側 `count($validated['stamps'])` は両方で動作する（PHP の `count` は配列・オブジェクト両対応）。
- T-03 の `exchanged_at` は `PrizeExchange` テーブルの `timestamp` 列（`exchanged_at`）で、`$casts` で `datetime` にキャスト済み（or `created_at` と同じ形式）。`toIso8601String()` で安全に文字列化できる。
