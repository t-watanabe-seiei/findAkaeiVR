# ARstampRally202603 にかかわるTodo
　・サンタクロース　→　キリン　（スタンプ帳が未対応）
　・キリン以外はシークレット
　・ヒントマップが修正できてない箇所あり
　・https://seiei.tech/dx-2025　へのリンク（活動を紹介）
　・

# ARstampRally202605 にかかわるTodo
　・model_01〜model_10 の GLBファイルを `public/cg/202605/Model_01.glb` 〜 `Model_10.glb` に配置すること
　・マーカーパターンファイルを `public/cg/202605/` に配置すること (pattern-maker00.patt, pattern-maker01.patt 〜 pattern-maker10.patt)
　・STAMPS オブジェクトのモデル名・アイコンを実際のキャラクター名に合わせて更新すること (`resources/views/ARstampRally202605/js-stamps.blade.php`)
　・景品交換閾値: 6 (PRIZE_EXCHANGE_THRESHOLD = 6)
　・ギャラリー(maker00): 捕獲済みモデルをY軸方向に並べて表示 (GALLERY_Y_SPACING = 0.6)
　・投げ方式: タップ即投げ（HUDスワイプなし）

---

### ARstampRally202605 - ギャラリーマーカーiPhone SEフリーズ修正 20260417

**maker00（ギャラリーマーカー）読み取り時にiPhone SEがフリーズする問題を修正:**

#### 原因
- `markerFound` 時に捕獲済み全モデル（最大10体）のGLBを同時ロードしていた
- `markerLost` のたびに全エンティティを `removeChild` で破棄 → 再検出時に再ロード（GC + GPU再アップロードで詰まる）
- `markerFound/Lost` の高速フリッカー（AR.jsの検出不安定）が上記を繰り返し発生させていた
- `AnimationMixer.update()` が呼ばれておらずアニメーションも正常再生されていなかった

#### 修正内容（3対策を統合）

1. **キャッシュ**: `markerLost` でエンティティを破棄せず `visible=false` のみ。再検出時は `visible=true` で即表示（再ロードなし）
2. **デバウンス 300ms**: `markerFound` 発火から300ms以内の再発火を無視。高速フリッカーによる繰り返し処理を防止
3. **逐次ロード 500ms間隔**: 未キャッシュモデルを同時生成せず1体ずつ500ms間隔でロード。`markerLost` 時にキュー中断

#### 追加修正
- `requestAnimationFrame` で全 `AnimationMixer` を毎フレーム `update()` するループを追加（アニメーション正常再生）

#### 変更ファイル
- `resources/views/ARstampRally202605/js-gallery.blade.php`: 全面書き換え（89行 → 138行）

#### 動作確認済み項目
- ✅ PHP構文エラーなし
- ✅ markerLost→markerFound高速切り替えで再ロードが走らない（キャッシュ）
- ✅ 初回のみ逐次ロード（500ms間隔）
- ✅ 既存の捕獲・投擲機能への影響なし

---

### ARstampRally202605 - 「捕まえました！」モーダル自動クローズ 20260417

**ボール命中後にモーダルが消えないことがある問題を修正:**

#### 修正内容
- `showCapturedMessage()` に1.5秒後の自動クローズタイマーを追加
- 連続ヒット時は前のタイマーをキャンセルして1.5秒リセット

#### 変更ファイル
- `resources/views/ARstampRally202605/js-stamps.blade.php`: `showCapturedMessage` 関数に `setTimeout` 追加

---

### ARstampRally202605 - 新規作成 (モジュール化リファクタリング)

**ARstampRally202603をベースに2026年5月イベント向け新版を作成:**

**新機能・変更点:**
- タップ投げ方式（HUDポケボール画像・スワイプ廃止）
- maker00をギャラリーマーカーとして使用（捕獲済みモデルをY軸方向に並べて表示）
- 景品交換閾値: 10→6
- Androidカメラズーム修正 CSS (`video { object-fit: contain !important; }`)
- 11ファイルのモジュール構成

**新規ファイル:**
- `resources/views/ARstampRally202605.blade.php`: エントリポイント
- `resources/views/ARstampRally202605/head.blade.php`: HEADタグ・CSS・グローバル変数
- `resources/views/ARstampRally202605/aframe-components.blade.php`: pokeball-throwable, hitbox, lazy-model, click-animation
- `resources/views/ARstampRally202605/scene.blade.php`: a-sceneとマーカー定義 (maker00+maker01-10)
- `resources/views/ARstampRally202605/ui.blade.php`: モーダル・ボタン等のHTML
- `resources/views/ARstampRally202605/js-stamps.blade.php`: STAMPS定義、LocalStorage管理
- `resources/views/ARstampRally202605/js-prize.blade.php`: IndexedDB/Cookie、景品交換API
- `resources/views/ARstampRally202605/js-throw.blade.php`: タップ投げ実装
- `resources/views/ARstampRally202605/js-gallery.blade.php`: maker00ギャラリー機能
- `resources/views/ARstampRally202605/js-camera.blade.php`: 写真・動画撮影
- `resources/views/ARstampRally202605/js-init.blade.php`: DOMContentLoaded初期化
- `app/Http/Controllers/AdminController.php`: `dashboard202605()`メソッド追加
- `resources/views/admin/dashboard202605.blade.php`: 管理ダッシュボード（2026年5月統計）

**追加ルート (`routes/web.php`):**
- `GET /stamp202605` → `ARstampRally202605` ビュー
- `GET /admin/dashboard202605` → `AdminController@dashboard202605`

# 変更点

### ARスタンプラリー202603 - dashboard202603のページネーションアイコンサイズ修正 20260219
**管理画面のページネーションアイコンサイズを統一:**

#### 修正内容
- 景品交換セクション（未使用・使用済み）のページネーションアイコンを18px × 18pxに修正
- `.pagination-wrapper svg`と`.pagination-wrapper nav svg`のCSSスタイルを追加
- すべてのページネーションアイコンのサイズを統一

#### 変更ファイル
- `resources/views/admin/dashboard202603.blade.php`: CSSスタイル追加（11行）

#### 技術的詳細
- `.pagination-wrapper svg`と`.pagination-wrapper nav svg`セレクターを追加
- `!important`でTailwind CSSクラス（w-5 h-5）を上書き
- `width`、`height`、`max-width`、`max-height`を18pxに設定
- 既存の`.pagination svg`スタイルとの一貫性を保つ

#### 動作確認済み項目
- ✅ 未使用景品交換のページネーションアイコンが18px × 18px
- ✅ 使用済み景品交換のページネーションアイコンが18px × 18px
- ✅ スキャン履歴のページネーションへの影響なし
- ✅ すべてのページネーションアイコンのサイズが統一
- ✅ レスポンシブデザインの維持
- ✅ 構文エラーなし

#### 設計ドキュメント
- 要件定義9: `.claude_workflow/requirements.md`
- 設計9: `.claude_workflow/design.md`
- タスク化9: `.claude_workflow/tasks.md`

---

### ARスタンプラリー202603 - dashboard202603に景品交換統計を追加 20260219
**admin/dashboard202603の管理画面に景品交換の統計情報と一覧を追加:**

#### 実装内容
1. **景品交換統計カードの追加**
   - 総景品交換数、使用済み数、未使用数を表示する3枚のカード
   - ヘッダー直後、動物別統計の前に配置
   - レスポンシブ対応（1200px以下で縦並び）

2. **未使用の景品交換セクション**
   - 景品コード検索機能（部分一致、大文字小文字区別なし）
   - 未使用景品交換一覧（20件/ページ、ページネーション）
   - 「使用済みにする」ボタン（AJAX処理）

3. **使用済み景品交換セクション**
   - 使用済み景品交換一覧（10件/ページ、ページネーション）
   - 使用日時の表示（緑色）
   - 景品コードのグレーアウト表示

4. **JavaScript機能**
   - redeemPrize(id)関数（AJAX POST通信）
   - 確認ダイアログ
   - CSRF token送信
   - 完了後のページリロード

#### 変更ファイル
- `app/Http/Controllers/AdminController.php`: dashboard202603()メソッド拡張（景品交換データ取得を追加）
- `resources/views/admin/dashboard202603.blade.php`: CSS、HTML、JavaScript追加

#### 技術的詳細
- データベースクエリ: 景品交換統計（2クエリ）、未使用景品交換（2クエリ）、使用済み景品交換（2クエリ）
- ページネーション: 異なるクエリパラメータ名で競合回避（exchanges_page, redeemed_page）
- 検索機能: GET param `q`で景品コード検索（部分一致、大文字小文字区別なし）
- セキュリティ: CSRF保護、認証、SQLインジェクション対策、XSS対策
- レスポンシブ: 1200px以下で1カラムレイアウトに変更

#### 動作確認済み項目
- ✅ 景品交換統計カードが表示される（3枚）
- ✅ 未使用の景品交換一覧が表示される（検索機能、ページネーション）
- ✅ 使用済み景品交換一覧が表示される（ページネーション）
- ✅ 「使用済みにする」ボタンが機能する（AJAX処理）
- ✅ ページネーションの独立性が保たれる
- ✅ 既存機能への影響なし
- ✅ 30秒ごとの自動更新が機能する
- ✅ PHP構文エラーなし

#### 設計ドキュメント
- 要件定義8: `.claude_workflow/requirements.md` (要件定義8セクション)
- 設計8: `.claude_workflow/design.md` (設計8セクション)
- タスク化8: `.claude_workflow/tasks.md` (タスク化8セクション)

---

### ARスタンプラリー202603 - dashboard202603のUI改善と日別個別ユーザー数統計追加 20260219
**admin/dashboard202603の管理画面を改善:**

#### 実装内容
1. **ページネーションUI修正**
   - SVGアイコンのサイズを18px × 18pxに制御
   - ボタンの視覚的なバランスを改善
   - 中央揃えとサイズ統一
   - !importantルールでLaravelデフォルトを上書き

2. **日別個別ユーザー数統計の追加**
   - 直近30日間の日別ユニークユーザー数をグラフ化
   - マーカー検出とボールヒット別に集計
   - Chart.jsで折れ線グラフとして表示
   - ホバー時にツールチップで詳細表示
   - データがない日は0として表示

#### 変更ファイル
- `app/Http/Controllers/AdminController.php`: dashboard202603()メソッド拡張（日別個別ユーザー数クエリ追加）
- `resources/views/admin/dashboard202603.blade.php`: CSS、HTML、JavaScript追加

#### 技術的詳細
- SQLクエリ: `COUNT(DISTINCT fingerprint)`で日別ユニークユーザー数を取得
- データ期間: 固定で直近30日間
- グラフタイプ: Chart.js Line Chart
- 色使い: マーカー検出（青: rgba(52, 152, 219, 1)）、ボールヒット（赤: rgba(231, 76, 60, 1)）
- 折れ線の透明度: 0.1（背景）
- ポイント半径: 4px（通常）、6px（ホバー）
- 曲線テンション: 0.3（なめらか）

#### 動作確認済み項目
- ✅ ページネーションのSVGアイコンが18px × 18pxで表示される
- ✅ 日別個別ユーザー数グラフが表示される
- ✅ グラフが2つのライン（マーカー検出/ボールヒット）で表示される
- ✅ ホバー時にツールチップで数値が表示される（「○○人」）
- ✅ データがない日は0として表示される
- ✅ 既存の統計表示に影響なし
- ✅ PHP構文エラーなし

#### 設計ドキュメント
- 要件定義7: `.claude_workflow/requirements.md` (要件定義7セクション)
- 設計7: `.claude_workflow/design.md` (設計7セクション)
- タスク化7: `.claude_workflow/tasks.md` (タスク化7セクション)

---

### ARスタンプラリー202603 - マーカー検出とボールヒットの統計分離 20260219
**ARマーカー検出とボールヒットを区別して統計を記録:**

#### 実装内容
1. **データベース拡張**
   - marker_scansテーブルにcapture_typeカラムを追加（'marker_scan' または 'ball_hit'）
   - 既存データは'ball_hit'として扱う
   - capture_typeにインデックスを追加（検索パフォーマンス向上）

2. **マーカー検出の記録（新機能）**
   - markerFoundイベント時に記録（未捕獲の動物のみ）
   - 同じ端末・同じマーカー・同じ日付の重複は記録しない
   - LocalStorageキャッシュで当日の重複を防止（`marker-scan-cache-202603-{markerId}-{date}`）
   - サーバー側でも日付ベースの重複チェック実装

3. **ボールヒットの記録（既存機能の拡張）**
   - collectStamp関数でrecordMarkerScan呼び出し時にcapture_type: 'ball_hit'を明示
   - 新規ゲット時のみ記録（既存ロジック維持）

4. **ダッシュボード拡張（admin/dashboard202603）**
   - 全20種類の動物の統計を表示（通常15種 + シークレット5種）
   - 各動物ごとにマーカー検出回数とボールヒット回数を表示
   - タイプ別の色分け表示（マーカー検出: 青、ボールヒット: 赤）
   - Chart.jsで積み上げ棒グラフを表示（日別統計、タイプ別）
   - ユニークユーザー数（fingerprint別）を表示
   - 最近のスキャン履歴にタイプ（マーカー検出/ボールヒット）を表示

#### 変更・追加ファイル
- `database/migrations/2026_02_19_144419_add_capture_type_to_marker_scans_table.php`: 新規マイグレーション
- `app/Models/MarkerScan.php`: fillable配列にcapture_type追加
- `app/Http/Controllers/MarkerScanController.php`: record()メソッド拡張
  - captureTypeパラメータを受け取る
  - marker_scanの場合、当日の重複チェック実装
  - タイプ別にscan_countを集計
- `resources/views/ARstampRally202603.blade.php`: 4箇所修正、1関数新規
  - recordMarkerDetection関数追加（2788行目付近）
  - recordMarkerScan関数にcaptureTypeパラメータ追加（2818行目）
  - markerFoundイベントリスナーにrecordMarkerDetection呼び出し追加（773行目付近）
  - collectStamp関数でrecordMarkerScan呼び出し時にcaptureType: 'ball_hit'を指定（143行目）
- `app/Http/Controllers/AdminController.php`: dashboard202603()メソッド拡張
  - 全20種類の動物の統計を取得
  - タイプ別（marker_scan, ball_hit）にカウント
  - 日別統計をタイプ別に取得
- `resources/views/admin/dashboard202603.blade.php`: 全面的に書き直し
  - 動物別統計テーブル追加（マーカー検出回数、ボールヒット回数、合計、ユニークユーザー数、最終スキャン）
  - 最近のスキャン履歴にタイプ表示追加
  - Chart.jsで積み上げ棒グラフ追加（日別統計、タイプ別）

#### 技術的詳細
- **マーカー検出記録フロー**:
  1. markerFoundイベント → recordMarkerDetection呼び出し
  2. LocalStorageで当日のキャッシュ確認 → あればスキップ
  3. recordMarkerScan(markerId, markerName, 'marker_scan')を呼び出し
  4. MarkerScanController::record()で日付ベースの重複チェック
  5. 重複がなければDBに記録、LocalStorageにキャッシュ

- **ボールヒット記録フロー**:
  1. collectStamp関数 → recordMarkerScan(stampId, name, 'ball_hit')呼び出し
  2. MarkerScanController::record()で記録（重複チェックなし）
  3. タイプ別にscan_countを集計

- **データベーススキーマ変更**:
  - capture_typeカラム: VARCHAR(20), default 'ball_hit'
  - インデックス: capture_type, (marker_id, capture_type), (fingerprint, marker_id, capture_type)

#### 動作確認項目
- ✅ マイグレーション実行成功（capture_typeカラム追加）
- ✅ PHP構文エラーなし（全ファイル）
- **テスト項目（実装後に確認が必要）**:
  - [ ] マーカー検出時にmarker_scansに記録される（capture_type: 'marker_scan'）
  - [ ] ボールヒット時にmarker_scansに記録される（capture_type: 'ball_hit'）
  - [ ] 同じ日に同じマーカーを再検出しても、カウントアップされない
  - [ ] 捕獲済みのマーカーは記録されない
  - [ ] ダッシュボードで各動物のマーカー検出回数とボールヒット回数が表示される
  - [ ] 積み上げ棒グラフがタイプ別に表示される
  - [ ] 既存機能（スタンプ収集、スタンプ帳表示）への影響なし

#### 設計ドキュメント
- 要件定義6: `.claude_workflow/requirements.md` (要件定義6セクション)
- 設計6: `.claude_workflow/design.md` (設計6セクション)
- タスク化6: `.claude_workflow/tasks.md` (タスク化6セクション)

---

### ARスタンプラリー202603 - スタンプ帳アイコン表示改善（追加修正） 20260219
**未収集動物のアイコン表示を統一:**

#### 実装内容
- 未収集の通常動物15種のアイコンを絵文字から足跡（🐾）に変更
- すべての未収集動物（パンダ、シークレット、通常動物）が足跡で統一表示される
- ユーザーが「まだ見つけていない動物」であることをより明確に認識できる

#### 変更ファイル
- `resources/views/ARstampRally202603.blade.php`: showStampBook関数（3390行目に1行追加）

#### 表示結果
**未収集時**:
- パンダ: 🐾（足跡） + 「パンダ」
- シークレット（パンダ以外）: 🐾（足跡） + 「シークレット」
- 通常動物15種: 🐾（足跡） + 「？？？」（**絵文字から足跡に変更**）

**収集済み時**:
- 全動物: スクリーンショットまたは絵文字 + 実際の名前（変更なし）

#### 動作確認済み項目
- ✅ 未収集動物のアイコンが🐾で統一表示される
- ✅ 収集済み動物の表示は変更なし
- ✅ CSS効果（grayscale、text-shadow）が正しく適用される
- ✅ 既存機能への影響なし

#### 設計ドキュメント
- 要件定義5: `.claude_workflow/requirements.md` (要件定義5セクション)
- 設計5: `.claude_workflow/design.md` (設計5セクション)
- タスク化5: `.claude_workflow/tasks.md` (タスク化5セクション)

---

### ARスタンプラリー202603 - スタンプ帳UI改善と管理画面統計追加 20260219
**ARstampRally202603.blade.phpのユーザー体験向上と管理者向け統計機能の追加:**

#### 実装内容
1. **スタンプ帳UI改善**
   - ヒントボタンを非表示化（1931行目をコメントアウト）
   - 未収集動物の名前表示ロジックを変更：
     - **パンダ**: 未収集でも「パンダ」と表示（シークレットだが名前を明示）
     - **シークレット（パンダ以外）**: 未収集時は「シークレット」と表示
     - **通常動物15種**: 未収集時は「？？？」と表示（興味喚起）
   - 収集済み動物は実際の名前が表示される（変更なし）

2. **管理画面統計ページ追加**
   - URL: `/admin/dashboard202603`
   - パンダマーカー専用の統計ダッシュボードを新規作成
   - 表示内容：
     - 総パンダスキャン数
     - ユニークユーザー数
     - 最近のパンダスキャン履歴（ページネーション付き）
     - 日別パンダスキャン数（テーブル + グラフ表示）
   - 既存ダッシュボードと統一感のあるデザイン
   - 認証必須（admin.authミドルウェア）
   - 双方向ナビゲーションリンク

3. **変更・追加ファイル**
   - `resources/views/ARstampRally202603.blade.php`: スタンプ帳UI改善（3箇所修正）
   - `app/Http/Controllers/AdminController.php`: dashboard202603()メソッド追加
   - `routes/web.php`: 新規ルート追加
   - `resources/views/admin/dashboard202603.blade.php`: 新規ビュー作成
   - `resources/views/admin/dashboard.blade.php`: ナビゲーションリンク追加

4. **技術的詳細**
   - MarkerScanモデルを使用してパンダマーカーの統計を取得
   - `where('marker_id', 'panda')` でフィルタリング
   - ページネーション対応（スキャン履歴30件/ページ、日別15件/ページ）
   - グラフはCSS barチャートで実装（軽量化）
   - 30秒ごとに自動更新

#### 動作確認済み項目
- ✅ ヒントボタンが非表示
- ✅ 未収集動物名の表示が要件通り（パンダ/シークレット/通常）
- ✅ 管理画面へのアクセスが認証で保護されている
- ✅ パンダマーカーの統計が正しく表示される
- ✅ ページネーションが機能する
- ✅ ナビゲーションが双方向で機能する
- ✅ 既存機能への影響なし

#### 設計ドキュメント
- 要件定義: `.claude_workflow/requirements.md` (要件定義4セクション)
- 設計: `.claude_workflow/design.md` (設計4セクション)
- タスク化: `.claude_workflow/tasks.md` (タスク化4セクション)

---

### ARスタンプラリー202603版 - LocalStorage分離実装 20260217
**ARstampRally.blade.phpをコピーして作成したARstampRally202603.blade.phpにおいて、LocalStorageキーの重複問題を解決:**

#### 実装内容
1. **問題の特定と解決**
   - `/stamp` と `/stamp202603` が同じLocalStorageキーを使用していたため、データが混在
   - 全てのストレージキーに `-202603` サフィックスを追加することで完全にデータを分離

2. **変更箇所（14箇所）**
   - **IndexedDB名**: `'ARStampRallyDB'` → `'ARStampRallyDB202603'`
   - **LocalStorageキー**: 
     - `'ar-user-id'` → `'ar-user-id-202603'`
     - `'ar-stamp-rally'` → `'ar-stamp-rally-202603'` (5箇所)
     - `'ar-captured-animals'` → `'ar-captured-animals-202603'` (4箇所)
     - `'ar-prize-exchanged'` → `'ar-prize-exchanged-202603'`
     - `'ar-prize-code'` → `'ar-prize-code-202603'`
   - **Cookie名**: `'ar_user_id'` → `'ar_user_id_202603'`

3. **技術的詳細**
   - ファイル: `resources/views/ARstampRally202603.blade.php` (6908行)
   - 変更対象: 変数定義3箇所 + localStorage文字列リテラル11箇所
   - 元ファイル (`ARstampRally.blade.php`) は一切変更なし
   - PHP構文エラーなし（`php -l` で検証済み）

4. **データ分離の効果**
   - `/stamp` と `/stamp202603` で完全に独立したスタンプラリー進行状況を保持
   - それぞれのページで捕獲した動物が他方に表示されない
   - 景品交換機能も独立して動作
   - ユーザーIDもページごとに別管理

#### 検証方法（Task 5）
1. ブラウザ開発者ツール（F12）→ Application タブ
2. LocalStorage、Cookie、IndexedDB を確認
3. `/stamp202603` で動物を捕獲 → `-202603` サフィックス付きキーが作成される
4. `/stamp` にアクセス → スタンプ帳が空（データ混在なし）

#### 設計ドキュメント
- 要件定義: `.claude_workflow/requirements.md` (ARスタンプラリー202603版セクション)
- 設計: `.claude_workflow/design.md` (ARスタンプラリー202603版セクション)
- タスク化: `.claude_workflow/tasks.md` (ARスタンプラリー202603版セクション)

---

### WebVR中心暗転体験アプリ - 動的版実装 20260203
**A-Frameを使用したVR中心暗転（逆トンネルビジョン）シミュレーション - 時間経過で変化:**

#### 実装内容
1. **360度パノラマVR環境**
   - `R0010034.JPG`を使用した没入型360度画像表示
   - Pico4 Enterprise対応のWebXRアプリケーション
   - アクセスURL: `/vr-center-dark`

2. **動的中心暗転エフェクト（時間変化）**
   - **VRモード開始時に自動的にタイマースタート**
   - **50秒で1ループ**、連続再生
   - **滑らかな線形補間**で段階間を遷移
   - **5つのステージ**で暗転範囲が変化:
     - 0～10秒: 中心暗転なし
     - 10～20秒: 視野角20度程度（小さい円）
     - 20～30秒: 視野角30度程度（中くらいの円）
     - 30～40秒: 視野角40度程度（やや大きい円）
     - 40～50秒: 視野角50度以上（大きい円）
   - リアルタイムで視線に追従するエフェクト
   - カスタムGLSLシェーダーによる高品質なグラデーション
   - **視野狭窄アプリの逆エフェクト**: シェーダーの`alpha`を反転（`1.0 - alpha`）するだけで実現

3. **技術実装**
   - **フレームワーク**: A-Frame 1.4.0（WebXR対応）
   - **カスタムコンポーネント**: `center-dark-overlay`
   - **シェーダー**: 
     - Vertex Shader: 頂点位置の計算（視野狭窄と同じ）
     - Fragment Shader: 視線中心からの角度に基づく透明度制御（**反転ロジック追加**）
   - **動的制御**:
     - `tick()`メソッド: 毎フレーム経過時間とステージを計算
     - `lerp()`関数: ステージ間の線形補間
     - VRモード検知: `enter-vr`イベントリスナー
     - 50秒ループ: モジュロ演算（`% 50`）
   - **最適化**: 
     - 球体セグメント数48（パフォーマンスと品質のバランス）
     - 球体半径0.4m（最適な視覚効果）
     - 深度テストオフ（常に最前面表示）

#### ファイル構成
- `routes/web.php`: `/vr-center-dark`ルート追加
- `resources/views/vr-center-dark.blade.php`: メインVRシーン
- `public/js/vr-center-dark/center-dark.js`: カスタムコンポーネント＋シェーダー（反転＋動的ロジック）
- `public/cg/R0010034.JPG`: 360度パノラマ画像（視野狭窄と共通）

#### パラメータ（動的変化）
```javascript
// ステージ定義
stages: [
  { time: 0,  innerRadius: 0.00, outerRadius: 0.00 },  // 暗転なし
  { time: 10, innerRadius: 0.00, outerRadius: 0.00 },  // ステージ境界
  { time: 20, innerRadius: 0.11, outerRadius: 0.15 },  // 視野角20度
  { time: 30, innerRadius: 0.17, outerRadius: 0.23 },  // 視野角30度
  { time: 40, innerRadius: 0.22, outerRadius: 0.30 },  // 視野角40度
  { time: 50, innerRadius: 0.28, outerRadius: 0.36 }   // 視野角50度
];
loopDuration: 50秒  // 50秒で最初に戻る
```

#### シェーダーの核心：反転ロジック + 動的更新
```glsl
// Fragment Shader（視野狭窄からの反転）
float alpha = smoothstep(innerRadius, outerRadius, normalizedDistance);
alpha = 1.0 - alpha;  // ← この1行で反転（中心=黒、外側=透明）
```

```javascript
// 動的パラメータ更新（tick()メソッド内）
const elapsedTime = (Date.now() - this.startTime) / 1000;
const loopTime = elapsedTime % 50;  // 50秒ループ
// ステージ間で線形補間
const innerRadius = this.lerp(currentStage.innerRadius, nextStage.innerRadius, stageProgress);
this.material.uniforms.innerRadius.value = innerRadius;
```

#### 使用方法
1. **デスクトップ**: `http://localhost:8000/vr-center-dark`にアクセス（VRモード前は暗転なし）
2. **VRデバイス**: 同URLにアクセスし、VRボタンでVRモード起動
3. **VRモード開始と同時にタイマースタート**、10秒後から暗転開始
4. Pico4 Enterpriseで頭を動かして時間変化する中心暗転体験
5. **50秒経過後、自動的にループして最初から再生**

#### 動作の詳細

**タイムライン（50秒周期）:**
```
0:00～0:10  │████████████████████│ 中心暗転なし（完全に明瞭）
0:10～0:20  │      ●●●●●●        │ 視野角20°の小さい円が暗転
0:20～0:30  │    ●●●●●●●●●●      │ 視野角30°の中くらいの円に拡大
0:30～0:40  │  ●●●●●●●●●●●●●●    │ 視野角40°のやや大きい円に拡大
0:40～0:50  │●●●●●●●●●●●●●●●●●●  │ 視野角50°の大きい円に拡大
0:50～      │████████████████████│ 最初に戻る（暗転なし）
```

**遷移の仕組み:**
- 各ステージ間は**線形補間（lerp）**で滑らかに遷移
- 例: 15秒時点（10～20秒の中間）では、視野角10°相当の暗転
- フレームレート: 60fps～90fps（デバイス依存）
- 補間計算: 毎フレーム実行（リアルタイム更新）

**VRモード検知:**
- A-Frameの`enter-vr`イベントを使用
- デスクトップの疑似VRモードでも動作
- タイマーはVRモード中のみカウント（exit-vrで一時停止しない仕様）

**技術的な特徴:**
1. **視線追従**: カメラの向きに関係なく、常に視線中心が暗転
2. **パフォーマンス**: シェーダーベースで軽量（GPUで処理）
3. **互換性**: WebXR対応ブラウザ全般で動作（Chrome, Firefox, Oculus Browser等）
4. **レスポンシブ**: 画面サイズや解像度に自動対応

#### トラブルシューティング

**Q: VRモードに入っても暗転が始まらない**
- A: 10秒待ってください。最初の10秒は暗転なしの期間です。

**Q: 暗転の円がカクカク動く**
- A: デバイスの性能不足の可能性。球体セグメント数を減らすと改善します：
  ```javascript
  // center-dark.js内で変更
  segments: { type: 'int', default: 32 }  // 48から32に減らす
  ```

**Q: ブラウザのコンソールにエラーが表示される**
- A: 以下を確認してください：
  - A-Frameのバージョン（1.4.0推奨）
  - Three.jsが正しく読み込まれているか
  - WebXR APIがサポートされているか（chrome://flags/で確認）

**Q: デスクトップで動作確認できない**
- A: VRボタンをクリックすると疑似VRモードになります。Escキーで解除。

**Q: タイマーをリセットしたい**
- A: VRモードを一旦終了（Escキー）し、再度VRボタンをクリック。

#### カスタマイズ方法

**ステージ時間を変更する:**
```javascript
// center-dark.js内のstages配列を編集
this.stages = [
  { time: 0,  innerRadius: 0.00, outerRadius: 0.00 },
  { time: 5,  innerRadius: 0.00, outerRadius: 0.00 },  // 5秒に短縮
  { time: 10, innerRadius: 0.11, outerRadius: 0.15 },  // 10秒に短縮
  // ...以降も調整
];

// tick()メソッド内のループ時間も変更
const loopTime = elapsedTime % 30;  // 50秒→30秒に短縮
```

**暗転の強さを変更する:**
```javascript
// schema内で不透明度を変更
opacity: { type: 'number', default: 0.8 }  // 1.0→0.8で薄く
```

**暗転範囲を変更する:**
```javascript
// stages配列のinnerRadius/outerRadiusを調整
{ time: 20, innerRadius: 0.15, outerRadius: 0.20 }  // より大きい円
```

#### 設計ドキュメント
- `.claude_workflow/requirements_center_dark.md`: 要件定義（動的版）
- `.claude_workflow/design_center_dark.md`: 設計書（動的版）
- `.claude_workflow/tasks_center_dark.md`: タスク一覧（動的版）

#### 既存アプリとの比較
| 項目 | 視野狭窄アプリ | 中心暗転アプリ（動的版） |
|------|---------------|---------------|
| URL | `/vr-tunnel` | `/vr-center-dark` |
| エフェクト | 中心=明瞭、周辺=暗転 | 中心=暗転、周辺=明瞭 |
| パラメータ | 固定値 | **時間経過で動的に変化（5ステージ、50秒ループ）** |
| タイマー | なし | **VRモード開始時に自動スタート** |
| 用途 | トンネルビジョン体験 | 時間変化する中心暗点体験 |
| 実装差異 | - | シェーダー反転＋tick()メソッド＋VRイベントリスナー |

---

### WebVR視野狭窄体験アプリ - 新規実装 20260203
**A-Frameを使用したVR視野狭窄（トンネルビジョン）シミュレーション:**

#### 実装内容
1. **360度パノラマVR環境**
   - `R0010034.JPG`を使用した没入型360度画像表示
   - Pico4 Enterprise対応のWebXRアプリケーション
   - アクセスURL: `/vr-tunnel`

2. **視野狭窄エフェクト（トンネルビジョン）**
   - 視線中心部（視野角約30度）のみ明瞭に表示
   - 周辺部は滑らかに暗転（視野角50度以降で完全な黒）
   - リアルタイムで視線に追従するエフェクト
   - カスタムGLSLシェーダーによる高品質なグラデーション

3. **技術実装**
   - **フレームワーク**: A-Frame 1.4.0（WebXR対応）
   - **カスタムコンポーネント**: `tunnel-vision-overlay`
   - **シェーダー**: 
     - Vertex Shader: 頂点位置の計算
     - Fragment Shader: 視線中心からの角度に基づく透明度制御
   - **最適化**: 
     - 球体セグメント数48（パフォーマンスと品質のバランス）
     - 球体半径0.4m（最適な視覚効果）
     - 深度テストオフ（常に最前面表示）

#### ファイル構成
- `routes/web.php`: `/vr-tunnel`ルート追加
- `resources/views/vr-tunnel.blade.php`: メインVRシーン
- `public/js/vr-tunnel/tunnel-vision.js`: カスタムコンポーネント＋シェーダー
- `public/cg/R0010034.JPG`: 360度パノラマ画像

#### パラメータ（固定値）
```javascript
innerRadius: 0.15    // 完全に透明な中心領域（視野角約30度）
outerRadius: 0.35    // 完全に黒くなる外側（視野角約50度）
sphereRadius: 0.4    // 球体の半径（メートル）
opacity: 0.95        // 暗転部の不透明度
segments: 48         // 球体セグメント数
```

#### 使用方法
1. **デスクトップ**: `http://localhost:8000/vr-tunnel`にアクセス、マウスドラッグで視点変更
2. **VRデバイス**: 同URLにアクセスし、VRボタンでVRモード起動
3. Pico4 Enterpriseで頭を動かして視野狭窄体験

#### 技術的な特徴
- **固定パラメータ**: 常に一定の視野狭窄（視野角30～50度）
- **軽量**: シェーダーのみで実装、tick()メソッド不要
- **シンプル**: イベントリスナーなし、初期化のみで動作
- **高品質**: smoothstep関数による滑らかなグラデーション

#### 中心暗転アプリとの違い
| 機能 | 視野狭窄 | 中心暗転（動的版） |
|------|----------|-------------------|
| エフェクト | 中心=明瞭、周辺=暗転 | 中心=暗転、周辺=明瞭 |
| 時間変化 | なし（固定） | あり（5ステージ、50秒ループ） |
| 実装複雑度 | シンプル | 中程度（タイマー＋補間） |
| 用途 | トンネルビジョン体験 | 中心暗点の段階的変化 |
| コード量 | 約160行 | 約200行 |

#### 設計ドキュメント
- `.claude_workflow/requirements.md`: 要件定義
- `.claude_workflow/design.md`: 設計書
- `.claude_workflow/tasks.md`: タスク一覧

---

### VRシューティングゲーム - バグ修正とUI改善 20260121
**shooting3Danimal.blade.php の修正履歴:**

#### 修正内容
1. **ゲームスタートボタン クリック不具合修正**
   - 問題: Level 1 / Level 2 ボタンがクリックできない
   - 原因: setupListeners が DOM 読み込み前に呼ばれていた
   - 解決: 複数のタイミング戦略と重複防止フラグを実装

2. **コンソールデバッグ有効化**
   - 問題: console.clear() が1秒ごとに実行され、デバッグ情報が消える
   - 解決: 開発者ツール検出コード（175-193行目）をコメントアウト

3. **JavaScript構文エラー修正**
   - 問題: 2115行目で構文エラー（if文の不適切な構造）
   - 解決: if (!window.gameStarted || !this.isMoving) のブロック構造を修正

4. **3Dモデル配置・サイズ調整**
   - 問題: モデルがカメラ位置に出現、サイズが25-26倍に拡大
   - 原因: approach-camera が動的カメラ位置を使用、fit-to-hitbox の計算ミス
   - 解決1: カメラ WASD 移動を無効化（3290行目）
   - 解決2: 全モデルを遠方位置 (0, 0, -50) に初期配置（3186-3265行目）
   - 解決3: fit-to-hitbox を固定スケール 0.5 に変更（300-330行目）
   - 解決4: スポーン距離を 10-12m → 4-5m に短縮（740-756行目）

5. **投げるボールの視認性改善**
   - 問題: ボールが小さすぎて見えない、暗い
   - 解決1: スケールを 0.1 → 0.5 に拡大（その後ユーザーが 0.2 に調整）
   - 問題2: ボールが白く見える（リンゴなのに赤色が失われる）
   - 原因: 発光（emissive）による色の上書き
   - 解決2: 発光を完全に削除し、元のモデル色を保持（1873-1886行目）
   
#### 技術的な変更詳細
- **Line 175-193**: Developer tools detection → コメントアウト
- **Line 300-330**: fit-to-hitbox component → 固定 scale: 0.5
- **Line 438-531**: start-menu setupListeners → タイミング改善
- **Line 740-756**: Movement patterns → 距離 4-5m に調整
- **Line 1869**: Ball scale → 0.5（ユーザーが後に 0.2 に変更）
- **Line 1873-1886**: Ball material → 発光なし、元の色のみ使用
- **Line 2100-2130**: approach-camera tick → 構文エラー修正、target position を原点に固定
- **Line 3186-3265**: modelGroup entities → position "0 0 -50", active: false
- **Line 3290**: Camera → wasd-controls disabled

---

### VRシューティングゲーム - 2種類のボール切り替え機能実装 20260120
**shooting3Danimal.blade.php の新機能:**

#### 実装内容
1. **2種類のポケボール対応**
   - `poke_ball_07apple.glb` (リンゴ型ポケボール)
   - `poke_ball_09cabbage.glb` (キャベツ型ポケボール)
   - トリガーを引いて投げるボールが2種類から選択可能

2. **VRコントローラーのグリップボタンでボール切り替え**
   - 左右どちらのコントローラーでもグリップボタンでボールタイプを切り替え可能
   - ゲーム中のみ有効（ゲーム開始前・終了後は無効）

3. **PC用キーボード操作の追加**
   - **Spaceキー**: ボールを投げる（トリガーボタン代替）
   - **Gキー**: ボールを切り替える（グリップボタン代替）
   - **マウスクリック**: ボールを投げる

4. **コントローラー先端にボールプレビュー表示**
   - 現在選択されているボールがVRコントローラーの先端に小さく表示
   - 回転アニメーション付き
   - ボール切り替え時に即座に更新

5. **UI改善**
   - スタートメニューにPC操作説明を追加（緑色テキスト）
   - ゲーム開始時にコンソールへPC操作ガイドを表示

#### 技術実装詳細
- **グローバル変数追加:**
  ```javascript
  window.ballTypes = ['cg/poke_ball_07apple.glb', 'cg/poke_ball_09cabbage.glb'];
  window.currentBallIndex = 0; // 現在選択中のボール
  ```

- **vr-controllerコンポーネント拡張:**
  - `onGripDown()`: グリップボタンイベントハンドラ
  - `createBallPreview()`: コントローラー先端にプレビュー作成
  - `updatePreview()`: ボール切り替え時にプレビュー更新

- **ball-shooter (shoot)コンポーネント修正:**
  - `onKeyDown()`にGキーの処理を追加
  - `shoot()`で`window.ballTypes[window.currentBallIndex]`を使用

#### ファイル構成
- 元ファイル: `shooting3Dcute.blade.php`をコピー
- 新ファイル: `shooting3Danimal.blade.php`
- 使用モデル: 同じ動物モデル（whiteTiger, pengin, namakemono等）
- 使用ボール: 新規に2種類のポケボール

### ダッシュボードについて　20251121
    /admin/login - ログインページ
    /admin/dashboard - ダッシュボード（認証必要）
    /admin/logout - ログアウト




## スタンプラリーアイデア
　・スタンプ帳にあらかじめ、シルエット的なものが表示され、スタンプを押すと、表示
　・スタンプ帳にシークレット
　・スタンプがたまると景品交換ボタン（ただし、端末ごとに1度しか交換できない仕様にする。DBで管理？？）
　・スタンプを押すボタン　➡　　GETするボタンに変更
　　　※乱数を使って、GET失敗するパターンも欲しい。
　　　　モンスターボールを投げる？？（当たり判定ボックス）


## terrer アイデア
　・BOSSの出現時に、ブラックアウト＆音楽変更＆ステージ変更（背景）
　・ボールに球数制限
　・武器変更
　・ミッションを課して、成功するとスコアアップ
　・体力ゲージ
　・武器を手に入れることができる味方（or 宝箱）を表示
　・BOSS撃退後は、トビラが表示され、OPEN　次のステージへ

## Cute アイデア
　・ボールではなく、Pikuminを投げる（Animetionついたまま）
　　ｙ座標が０のところで着地して、投げた方向に走っていく。当たり判定ぶつかるとAnime02に切替

## VR cute と vr Terrer を完全オフライン仕様に変更しようとしたが、どうしてもフォントが表示されない…ので巻き戻します　2025.11.18



## git コマンド
git log --oneline -10
git reset --hard ******
##### リモートリポジトリを強制的に特定のコミットに戻すには、force pushが必要です。
git push origin main --force



##AIモデル評価点ランキング（総合評価 vs. 実践評価）
## 管理ダッシュボード - CSVエクスポート機能 (追加)
管理ダッシュボードから以下のデータを CSV でダウンロード可能になりました（管理者ログイン後）:

- 未使用の景品交換 (recentExchanges)
- 使用済み景品交換 (redeemedPrizes)
- 全ての景品交換 (allExchanges)
- 最近のスキャン履歴 (recentScans)
- 日別スキャン数の集計 (dailyScans)
- マーカー別スキャン統計 (markerStats)

ダッシュボードの CSV エクスポート欄で、データセットを選択し、開始/終了日時を入力して「CSV をダウンロード」を押すと、フィルタ条件で絞った CSV がダウンロードされます。

注意: start/end の値は `datetime-local` 形式で入力してください（例: 2025-11-23T13:00）。未指定だと全期間のデータが出力されます。

順位	アクセス権限テストリスト (AIモデル)	評価点 (総合)	実践評価点	倍率	主な評価理由 (実践的)
1	12-GPT-5.1	5.0	5.0	x1	優れた網羅性と粒度。 否定優先、論理ロール制約、DL-08必須項目の保存検証が詳細かつ、実装フェーズに即した順序。
2	5-Raptor mini	5.0	4.9	x0	構造化と優先度付けが即戦力。 9カテゴリ分類、優先度（高/中）が明確。競合トランザクション、セキュリティ侵害ケースなど現実の課題を考慮。
3	10-GPT-5	4.8	5.0	x1	ユニットテストの粒度が最も TDD に適している。 複雑なロジック（否定優先、キャッシュ整合性）をデバッグしやすいよう細分化。
4	8-Claude Sonnet 4.5	4.9	4.8	x1	フェーズ構成とチェックリストが実装ガイドになる。Layer 1〜3の検証構造が明確で、DL-08の完全追跡要件への言及も詳細。
5	13-GPT-5.1-Codex	4.7	4.7	x1	IDと監査項目が堅牢。改ざん防止ハッシュのチェーン整合性など、高度なセキュリティ要件に具体的に踏み込む。
6	11-GPT-5-Codex	4.7	4.7	x1	三層認可の検証順序が論理的。「Layer 1/2 をバイパスしても RLS が拒否する」テストなど、最終ガード機能の検証が実践的。
7	7-Claude Sonnet 4	4.5	4.4	x1	堅牢な構造。Layer 1〜3の階層別テスト戦略と、DL-08の全項目追跡、NFR-01性能要件のチェックリストが詳細に整理。
8	6-Claude Haiku 4.5	4.5	4.4	x0.33	構造と期間推定が実践的。実装者が作業完了を判断する基準となる「実装指標」と、プロジェクト計画に役立つ期間推定を提供。
9	1-GPT-4.1	4.3	4.0	x0	網羅性は高いが粒度が粗い。TDDの「最小のテスト」としてはやや粒度が大きく、デバッグが難しくなる可能性がある。
10	4-Grok Code Fast 1	4.2	4.1	x0	Given-When-Then 形式の徹底。実装時にテストコードに変換しやすいが、ユニットレベルでの分割は上位モデルに劣る。
11	3-GPT-5 mini	4.0	3.9	x0	分類は良いが具体性が不足。DL-08の詳細監査項目など、設計書で強調されている複雑な要件への言及が少ない。
12	14-GPT-5.1-Codex-Mini	3.8	3.5	x0.33	要点に絞りすぎ。TDDに必要な網羅的なユニットテストの分解が不足しており、実装ドライバーとしては不十分。

---

### VRシューティングゲーム - Pico4パフォーマンス改善（traverse+dispose廃止） 20260319

**対象ファイル:** `shooting3Dterrer3.blade.php` / `shooting3DModel3.blade.php` / `shooting3Danimal3.blade.php`

Pico4 Enterprise（Snapdragon XR2）でVRブラウザがフリーズ・強制終了する問題を修正。
THREE.jsの不適切なリソース解放処理が原因。

#### 修正内容（3ファイル共通）

**Fix 1: aframe-physics-system 削除**
- `<script src="{{ asset('js/aframe-physics-system.min.js') }}"></script>` を削除
- `<a-scene>` から `physics="gravity: -9.8"` 属性を削除
- 物理演算エンジン（Cannon.js）を未使用なのにロードしていた

**Fix 2: restartGame のシーン全体dispose廃止**
- `model.object3D.traverse()` で geometry/material/texture を dispose するブロックを削除
- モデル削除は `removeChild` のみに変更
- シーン全体を traverse することでライト・スカイ・UIのリソースまで破壊していた

**Fix 3: anisotropy を 16 → 2 に変更**
- `enhance-materials` コンポーネント内の4箇所を変更
  - `node.material.map.anisotropy`
  - `node.material.metalnessMap.anisotropy`
  - `node.material.roughnessMap.anisotropy`
  - `node.material.normalMap.anisotropy`
- Snapdragon XR2 の最大異方性フィルタリング値は約4。16を指定するとGPUドライバーのオーバーヘッドが発生

**Fix 4: ヒット・削除時の traverse+dispose 廃止**
- `ball.object3D.traverse()` dispose ブロックを以下の全箇所から削除
  - ボールがモデルにヒットした後のコールバック（300ms遅延）
  - ボールが地面に落下・タイムアウトした際の削除処理
  - `showResult`（ゲーム終了時の残存ボール一括削除）
  - `restartGame`（リスタート時の残存ボール一括削除）
- A-Frameは GLBジオメトリ/マテリアルをキャッシュ・共有しているため、dispose すると次の描画でGPUストールが発生していた
- ボール削除は `removeChild` のみに統一

#### 変更ファイルと行数変化
- `shooting3Dterrer3.blade.php`: 参照元（先行修正済み）
- `shooting3DModel3.blade.php`: 3406行 → 3274行（132行削減）
- `shooting3Danimal3.blade.php`: 3730行 → 3640行（90行削減）

#### 技術的背景
- **ハードウェア**: Pico4 Enterprise / Qualcomm Snapdragon XR2 / 8GB LPDDR5 / 256GB UFS 3.1
- **フレームワーク**: A-Frame VR + THREE.js（Bladeテンプレート）
- THREE.jsはGLBをロードするとリソースをキャッシュ・共有する。そのリソースを `dispose()` で破棄すると、同じリソースを参照している別オブジェクトの描画時にGPUへの再アップロードが発生し、フリーズの原因となる
- renderer.renderLists.dispose() / renderer.info.reset() / THREE.Cache.clear() は残置（シーン全体のフレームバッファリセットは安全）
13	2-GPT-4o	2.5	2.0	x0	実践的な利用は困難。テスト内容が抽象的で、具体的な入力条件やDL-08の個別監査項目の検証がほとんど含まれていない。