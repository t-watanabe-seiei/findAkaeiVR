# ARstampRally202609 検証レポート — 202606分析で指摘された問題点の改善状況

> 検証対象: `resources/views/ARstampRally202609.blade.php` および `resources/views/ARstampRally202609/*.blade.php`（11ファイル）
> 根拠:
> - `ARstampRally202606/analysis_qwen3.8.md`（Androidカメラズーム / モデルスケール: 主因 A / B / C）
> - `ARstampRally202606/analysis_claude_sonnet5.md`（コード全体分析: P0 / P1 / P2 等）
> 検証方法: 202606 との全ファイル行末無視diff（`diff --strip-trailing-cr`）＋ `git show 8b1159a --stat` ＋ 各問題箇所の直接読み込み確認
> 検証日: 2026-09-02

---

## 0. 結論（TL;DR）

**❌ 202606 の両分析で指摘された問題点は、ARstampRally202609 で一切改善されていません。**

`ARstampRally202609` は `ARstampRally202606` のコピーに**バージョン文字列（`202606` → `202609`）の置換と改行コード変更（CRLF → LF）のみ**が施されたもので、実質的なコード修正は 0 件です。主因 A / B / C のいずれも、および P0 / P1 / P2 の項目も、すべて未実装のままです。

| 分類 | 指摘項目数 | 改善済み | 未改善 |
|------|-----------|---------|--------|
| analysis_qwen3.8.md（カメラズーム / モデルスケール） | 7 | **0** | 7 |
| analysis_claude_sonnet5.md（P0×2 / P1×3 / P2×3 / 他） | 15 | **0** | 15 |

**影響**: 202609 をそのまま公開すると、202606 と完全に同一の症状が再発します。

- Android で「カメラがズームに見える / マーカー・モデルがズレる」（主因 A）
- モデルが `base²` 倍で描画（maker00 ≒ 0.6²=0.36、maker01-20 ≒ 1.1²=1.21）（主因 B・全端末）
- 低解像度リトライ後にスケールがさらにずれる（主因 C）
- スタンプ0個で API を直接叩けば景品コードが発行される（P0・サーバー側）

唯一の実質的な変更は、localStorage / IndexedDB / Cookie のキーに `202609` 識別子が入り、202606 との**データ分離**が実現している点です（新規キャンペーン展開としては正しい対応ですが、分析で指摘された「問題点の修正」ではありません）。

---

## 1. 検証方法

1. **全ファイルdiff**: `ARstampRally202609/*.blade.php` 10ファイル＋メインビュー `ARstampRally202609.blade.php` を 202606 側と同ファイル名ファイルと `diff --strip-trailing-cr` で比較した（202606 が CRLF、202609 が LF であるため、行末を無視して比較しないと全ファイルが「変更あり」と誤表示される点に注意）。
2. **Git確認**: `git show 8b1159a --stat`（コミット「ARstamp202609 create01」）で、本コミットが触った全ファイルを確認した。
3. **直接読取**: 両分析が引用した各問題箇所（file:line）を 202609 のファイルで直接読み、現状コードを確認した。

---

## 2. 202609 と 202606 の実diff内容（全件）

| ファイル | diff 内容（実体） |
|----------|------------------|
| `ARstampRally202609.blade.php` | `@include('ARstampRally202606.*')` → `@include('ARstampRally202609.*')` のみ |
| `head.blade.php` | `<title>AR Stamp Rally 202609</title>`、`ar-camera-reload-202609`（計2箇所） |
| `scene.blade.php` | コンソールプレフィックス `[AR202609]`（1箇所） |
| `js-init.blade.php` | リセット用キー `ar-stamp-rally-202609` / `ar-captured-animals-202609`、コンソールプレフィックス（計3箇所） |
| `js-prize.blade.php` | `ARStampRallyDB202609` / `ar-user-id-202609` / `ar_user_id_202609` / `UserIdDB202609` / `CookieHelper202609` / `generateUUID202609` / `getUserId202609` / `marker-scan-cache-202609-` / `ar-prize-exchanged-202609` / `ar-prize-code-202609`（計17箇所） |
| `js-stamps.blade.php` | `ar-stamp-rally-202609` / `ar-captured-animals-202609` / `getCapturedAnimals202609` / `saveCapturedAnimals202609` / `ar-gallery-selection-202609`（計9箇所） |
| `aframe-components.blade.php` | **変更なし（完全一致）** |
| `js-camera.blade.php` | **変更なし（完全一致）** |
| `js-gallery.blade.php` | **変更なし（完全一致）** |
| `js-throw.blade.php` | **変更なし（完全一致）** |
| `ui.blade.php` | **変更なし（完全一致）** |

なお、コミット 8b1159a に**含まれていない**ファイル: `PrizeExchangeController.php`、`MarkerScanController.php`、`routes/api.php`、`public/js/ar-engine.min.js` / `ar-tracking.min.js`、`package.json`、`webpack.mix.cjs`（他には `AdminController.php` / `routes/web.php` / `admin/dashboard202609.blade.php` / `public/php_errors.log` の変更のみで、いずれも AR の問題点とは無関係）。

---

## 3. 問題点チェックリスト — analysis_qwen3.8.md（カメラズーム / モデルスケール）

| # | 指摘（202606分析） | 推奨修正 | 202609 状態 | 証拠（202609 の file:line） |
|---|--------------------|----------|-------------|------------------------------|
| A-1 | 【主因 A】Android の射影寸法が 4:3 (640×480) にハードコード | Fix-1（動画読み込み後に実寸で再設定） | ❌ 未改善 | `scene.blade.php` L1-7: `$_srcW = $_arjsIsAndroid ? 640 : 1280;` の UA 分岐がそのまま；L11 `arjs="sourceWidth: {{ $_srcW }}; ..."` |
| A-2 | `arjs-video-loaded` 補正が**縦型限定**で、横長ストリーム・横長画面は未修正；resize/orientation 追従なし | Fix-1（全方向 + リサイズ追従） | ❌ 未改善 | `js-init.blade.php` L594: `vid.videoWidth < vid.videoHeight` の縦型限定ガードがそのまま；全ファイルに `syncArjsToRealSize` / `resize` / `orientationchange` の同期ハンドラは存在しない（grep 0 件） |
| A-3 | Samsung 縦型上書きは場当たりパッチ（Fix-1 に取って代わる削除候補） | 削除 | ❌ 未改善 | `scene.blade.php` L78-91: `/samsung\|SM-[A-Z]/i` 判定＋`sourceWidth: 480; sourceHeight: 640;` がそのまま |
| B | 【主因 B】`applyCurrentScaleTo()` がルートと各メッシュの両方を `v` に設定 → 描画が `base²` 倍 | Fix-2（スケールはルートの1回だけ） | ❌ 未改善 | `js-init.blade.php` L31-56: L37 `setAttribute('scale')` ＋ L40 `object3D.scale.set(v,v,v)` ＋ L45 メッシュ `scale.set(v,v,v)` の3重設定がそのまま；呼出箇所 L353（markerFound）/ L378（PC wheel）も無変更 → maker00 はまだ 0.36、maker01-20 はまだ 1.21 |
| C | 【主因 C】低解像度リトライで `sourceWidth:320; sourceHeight:240` を差し替えるが `displayWidth/Height` を更新しない | Fix-3（`syncArjsToRealSize()` を呼ぶ） | ❌ 未改善 | `js-init.blade.php` L530: `sourceWidth: 320; sourceHeight: 240; …` のみで `displayWidth/displayHeight` 指定なし |
| D | `object-fit: cover` は見た目専用で射影のズレを解消しない | Fix-1 により代替 | ❌ 未改善 | `head.blade.php` L175 / L178: `object-fit: cover !important` がそのまま |
| E | Fix-4（任意）: `scene.blade.php` の初期 `display` 値を実画面に近づける | Fix-4 | ❌ 未改善 | `scene.blade.php` L5-6: `$_dispW/$_dispH` = Android で 640/480 のまま |

**判定: 7項目中 0 項目が改善（すべて未対応）。**

---

## 4. 問題点チェックリスト — analysis_claude_sonnet5.md

| 優先度 | 指摘（202606分析） | 202609 状態 | 証拠 |
|--------|--------------------|-------------|------|
| **P0** | Android の一律 640×480 を廃止し 1280×720 既定化＋実測値の縦横無視再設定（Samsung パッチ撤去） | ❌ 未改善 | `scene.blade.php` L1-7 / L78-91 が無変更（§3 A-1 / A-3 参照） |
| **P0** | 景品交換 API のサーバー側スタンプ件数・整合性検証 | ❌ 未改善 | `PrizeExchangeController.php` が 8b1159a に含まれず無変更；`js-prize.blade.php` L183-186 もクライアント側ガード（`count < 10` → alert+return）のみ |
| P1 | `MediaRecorder` 非対応環境での動画撮影ボタンの事前非表示（feature detection） | ❌ 未改善 | `js-camera.blade.php` が 202606 と完全一致；L93-100 は `isTypeSupported` 分岐＋try/catch 内 `new MediaRecorder(...)` のみでボタン非表示ロジックなし |
| P1 | AR.js / three.js バンドルのバージョン管理・圧縮配信・キャッシュ設定 | ❌ 未改善 | `ar-engine.min.js` / `ar-tracking.min.js` 無変更；`package.json` へも未取り込み |
| P1 | API エンドポイントへのレート制限（`throttle`） | ❌ 未改善 | `routes/api.php` 無変更 |
| P2 | UA 判定ロジックの一元化 | ❌ 未改善 | PHP側 UA 判定（`scene.blade.php` L1-7）＋ JS側 Samsung 判定（L81）＋ `detectOldAndroid`（`head.blade.php` L22-34）が依然分散 |
| P2 | 低スペック端末判定の精緻化（`deviceMemory` / `hardwareConcurrency` 加味） | ❌ 未改善 | `head.blade.php` L22-34 は「Android 7以下」判定のみ；`deviceMemory` / `hardwareConcurrency` は `js-prize.blade.php` L119-120 `generateFingerprint()`（サーバー送信用の指紋素材）でのみ使用 |
| P2 | スタンプ確定処理の共通化（handleHit / playHitAnimation / tryAutoGet の重複呼び出し） | ❌ 未改善 | `aframe-components.blade.php` が完全一致 |
| §3.1 | `hitbox` / `gallery-hitbox` の `tick()` が連投時に毎フレーム計算コスト積み上げ | ❌ 未改善 | `aframe-components.blade.php` 完全一致 |
| §3.1 | `maxDetectionRate: 30` が低性能 Android で発熱・フレーム落ちの懸念 | ❌ 未改善 | `scene.blade.php` L11 / L88 で `maxDetectionRate: 30` そのまま |
| §3.2 | フィンガープリント多重フォールバックでも複数交換防止は不完全 | ❌ 未改善（変更なし） | `js-prize.blade.php` のストレージ構造は無変更（バージョン文字列置換のみ） |
| §3.2 | `checkStatus()` の `fingerprint` バリデーション欠如 | ❌ 未改善 | `PrizeExchangeController.php` 無変更 |
| §3.4 | 待機時間の基準不一致（7秒 vs 10秒） | ❌ 未改善 | `js-init.blade.php` L613: `monitorCameraStartup(7000)` そのまま |
| §3.4 | 景品交換ボタンのクールダウン・二重送信防止（連打対策）不在 | ❌ 未改善 | `js-prize.blade.php` L178-214 `exchangePrize()`: fetch 実行中の `disabled` / クールダウンガードなし（L250-284 `updatePrizeButton()` は閾値・交換状態に基づく状態切替のみ） |
| §2.1 | `navigator.share` + `canShare({files})` が iOS バージョン依存（15+） | ❌ 未改善（変更なし） | `js-camera.blade.php` L219-222: `canShare` 分岐→ダウンロードフォールバックの構成のまま（クラッシュはしないが機能低下の可能性は継続） |

**判定: 15項目中 0 項目が改善（すべて未対応）。**

---

## 5. 正しく実施されていたこと（新キャンペーン準備として妥当な変更）

以下は問題点の修正ではありませんが、新規キャンペーン展開に必要な正しい対応として行われていました。

1. **データ分離**: 全ストレージキー（localStorage / IndexedDB / Cookie）が `202609` 識別子を持ち、202606 版とスタンプ・捕獲状態・ユーザーID・景品コードが互いに上書きし合わなくなった。✅
2. **メインビュー分離**: `ARstampRally202609.blade.php` が 202609 の partial のみを include するため、202606 と 202609 が併存可能。✅
3. **アセット流用**: 3Dモデル・マーカーは `public/cg/202606/` をそのまま流用（`scene.blade.php` L28 / L39 / L53 / L61）— 制作方針どおり。✅
4. （本検証の範囲外）管理ダッシュボード追加（`AdminController.php`、`admin/dashboard202609.blade.php`、`routes/web.php`）。

---

## 6. リスク / 影響

現状のままだと、202609 は 202606 で指摘された問題を**完全に再現**します。

| 症状 | 影響範囲 |
|------|----------|
| カメラ映像が「ズーム込み」に見える、マーカー・モデルの位置・サイズがズレる | Android 全機種（主因 A） |
| モデルが `base²` 倍で描画（maker00 ≒ 60% 縮小、maker01-20 ≒ 10% 拡大） | 全端末（主因 B） |
| PC の wheel ズームで 2 倍にすると 4 倍相当に見える | PC（主因 B） |
| 低解像度リトライ後にスケールがさらにずれる | 低解像度経路（主因 C） |
| スタンプ0個でも API を直接叩けば景品コードが発行される | 全ユーザー（P0・サーバー側） |
| 低速回線環境で初期表示が遅い（バンドル圧縮・キャッシュ未設定） | 全ユーザー（P1） |

---

## 7. 推奨事項（次アクション）

修正は**まだ1件も実施されていない**ため、以下の優先度順で実装することをお勧めします（根拠: `analysis_qwen3.8.md` §6/§10、`analysis_claude_sonnet5.md` §4）。

1. **Fix-2**: `applyCurrentScaleTo` の二重スケール解消（最小変更・全端末受益）→ `js-init.blade.php` L31-56
2. **Fix-1**: `syncArjsToRealSize()`（source=動画実寸、display=実画面、全方向対応）＋ `resize` / `orientationchange` 追従 → `js-init.blade.php` L585-605
3. **削除**: `scene.blade.php` L73-92 の Samsung 縦型上書き（Fix-1 の発火確認後に実施）
4. **Fix-3**: 低解像度リトライ時にも `display` を更新 → `js-init.blade.php` L523-535
5. **P0（サーバー側）**: `PrizeExchangeController::exchange()` へのスタンプ件数・整合性検証の追加
6. **P1**: `MediaRecorder` feature detection、バンドル圧縮/キャッシュ設定、API レート制限
7. 実装後は `analysis_qwen3.8.md` §7「検証手順」に従い Android 縦/横・iOS・PC で検証し、結果を本ドキュメントに追記すること