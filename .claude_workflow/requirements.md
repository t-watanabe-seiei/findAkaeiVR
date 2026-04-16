# 要件定義: ARstampRally202605 新規作成

## 作成日時
2026年4月15日

## 参照
`.claude_workflow/complete.md` を参照済み

## プロジェクト概要
`ARstampRally202603.blade.php`（7291行）を参考に、ARスタンプラリー2026年5月版を新規作成する。  
ファイルが1000行を超えるため、`resources/views/ARstampRally202605/` サブディレクトリにモジュール化して作成する。

## 現状把握
- 202603版はマーカー数20個、7291行の単一ファイル
- AR.js + A-Frame を用いたWebARアプリ
- 既存APIエンドポイント（`/api/record-marker-scan`, `/api/exchange-prize`, `/api/check-prize-exchange`）を流用
- 既存ダッシュボード（`admin/dashboard202603`）を参考に202605版を追加

## 要件詳細

### 1. マーカー・モデル構成
- **マーカー数**: 11個（maker00 ～ maker10）
- **捕獲対象モデル**: 10個（Model_01.glb ～ Model_10.glb）
  - スタンプID: `model_01` ～ `model_10`（汎用ID、後で名称変更可）
  - 各モデルに `anime01`, `anime02`, `anime03` アニメーションを持つ
- **maker00**: ギャラリー専用マーカー（捕獲対象なし）

### 2. アニメーション仕様
| 状態 | アニメーション |
|------|--------------|
| ボールが当たる前（マーカー検出時） | `anime01` をループ再生 |
| ボールヒット時 | `anime02` を1回のみ再生 |
| ギャラリー表示（maker00） | `anime03` をループ再生 |

### 3. maker00 ギャラリー機能
- marker00を読み込んだとき、これまで捕まえたモデルをすべてARとして表示
- **位置**: X・Z は同じ（例: `0 * 0`）、Y だけ 1.0 ずつずらして縦に並べる
- **アニメーション**: `anime03` をループ再生
- ギャラリーモデルは捕獲対象外（ヒットボックスなし）

### 4. ボール投擲仕様（変更点）
- **方式**: 画面のどこをタップしても即座にボールを投げる
- HUDボール（スワイプ方式）は廃止
- UIボタン（スタンプ帳・写真等）タップ時は除外
- 実装：`touchstart`→`touchend` の座標差ベクトルで方向を決め `throwPokeballToCenter()` 呼び出し

### 5. Androidカメラズーム問題の修正
- **症状**: 一部Androidでカメラ映像が拡大（ズームイン）されて見える
- **原因**: AR.jsがデフォルトで`object-fit: cover`相当の挙動をするため、カメラの縦横比とディスプレイの縦横比のミスマッチが生じる
- **解決策**: 
  - Android検出時に `sourceWidth: 640; sourceHeight: 480` を保持（現状維持）
  - 追加で `video` 要素に `object-fit: contain` をCSS適用
  - AR.jsの `displayWidth` / `displayHeight` オプションを明示的に指定
  - `cameraParametersUrl` を明示してカメラ歪み補正を活用

### 6. 景品交換
- **必要スタンプ数**: **6個以上**（202603の10個から変更）
- その他の景品交換ロジックは202603と同じ

### 7. 管理画面
- パス: `/admin/dashboard202605`
- 同じ認証ミドルウェア（`admin.auth`）
- レイアウト: dashboard202603と同一
- 集計対象: 2026年5月（JST）のデータ
- 対象マーカーID: `model_01` ～ `model_10`

### 8. API・ストレージキー
- 既存API（`/api/record-marker-scan`, `/api/exchange-prize`等）を流用
- LocalStorageキー: `ar-stamp-rally-202605`（202603と分離）
- `ar-captured-animals-202605`
- `ar-user-id-202605`
- IndexedDB名: `ARStampRallyDB202605`

## 成功基準
- [ ] 10個のマーカーでARモデルが表示・捕獲できる
- [ ] maker00でギャラリー表示が動作する
- [ ] 画面タップでボールが投げられる
- [ ] Android端末でカメラのズーム問題が改善される
- [ ] 6個以上のスタンプで景品交換できる
- [ ] `/admin/dashboard202605` が202603と同様に表示される

---

# 要件定義: shooting3Dterrer3 VRゴーグル処理落ち修正

## 作成日時
2026年3月18日

## 参照
`.claude_workflow/complete.md` を参照済み

## プロジェクト概要
`shooting3Dterrer3.blade.php` をPico4 Enterprise（Snapdragon XR2, 8GB RAM）のVRゴーグルブラウザで動作させた際に発生する、画面カクつき・フリーズ・ブラウザ強制終了を修正する。

## 修正対象（前回調査で判明した4項目）

### 問題1（最優先）: aframe-physics-system を無駄にロード
- `<script src="{{ asset('js/aframe-physics-system.min.js') }}"></script>` を読み込んでいる
- しかし `dynamic-body` / `static-body` コンポーネントを使っているエンティティは1つもない
- `<a-scene physics="gravity: -9.8">` の属性だけが残っている
- 物理エンジンが毎フレーム全エンティティをスキャンし続け、常時CPU/GPU負荷になっている

### 問題2（最優先）: restartGame でシーン全体の geometry/material を dispose
- `restartGame()` 内の「THREE.jsの完全なGPUリソース解放」ブロックで `sceneEl.object3D.traverse()` を実行
- スカイボックス・ライト・UIパネル・固定モデルなど**シーン全体**を破棄している
- A-Frameがそれらを引き続き使おうとしてWebGLエラー → ブラウザクラッシュの原因

### 問題3（優先度高）: anisotropy: 16 をモバイルGPUで使用
- `enhance-materials` コンポーネント内で `anisotropy = 16` を4箇所に設定
- モバイルGPU（Snapdragon XR2）では非常に高コストな設定
- PC向けGPUと比べてanisotropyが遅く、過負荷の原因

### 問題4（優先度高）: ヒット毎・リスポーン毎に traverse+dispose を実行
- `despawnAndRespawn()` 内でモデルDOMから削除する前に traverse+dispose を実行
- `restartGame()` の `allModels.forEach` 内でも各モデルを traverse+dispose
- ボール除去時（ヒット時・タイムアウト時）にも traverse+dispose
- GLBモデルは `<a-assets>` でキャッシュされた共有リソース。dispose すると他インスタンスも壊れる
- 毎ヒット毎にGPUリソースを破棄→フリーズの原因

## 成功基準
1. physics-system を削除後も物理演算不使用のゲームロジックが正常動作すること
2. リスタート後にシーンが正常に表示され、2回目以降もクラッシュしないこと
3. anisotropy を適正値（2）に変更後も視覚品質が許容範囲であること
4. ヒット・リスポーン・リスタート時のフリーズが解消されること

## 変更対象ファイル
- `resources/views/shooting3Dterrer3.blade.php`

---

# 要件定義: ARstampRally202603 Android (moto g64y) バグ修正

## 作成日時
2026年3月18日

## 参照
`.claude_workflow/complete.md` を参照済み

## プロジェクト概要
AR スタンプラリーアプリ (`ARstampRally202603.blade.php`) において
Android 端末 (moto g64y, Android 14) で報告された3つのバグを修正する。

## 報告された不具合

### 不具合1: HUD ボールが表示されない
- 手元のポケボール (`#holding-pokeball`) が Android で見えない
- iPhoneでは正常に表示される

### 不具合2: ボールが投げられない
- 画面下部をスワイプしてもボールが飛ばない
- タッチ/スワイプ操作を受け付けていない可能性がある

### 不具合3: カメラが若干ズームしている
- AR カメラ映像が少し拡大されて表示される
- moto g64y のポートレート画面で発生

## 現状コードの分析（ファイル全読完了）

対象ファイル: `resources/views/ARstampRally202603.blade.php` (約7350行)

### 既に実装済みの対策（前回セッション）
- ファイル末尾 (行 6984〜7232) に「新しいポケボール操作ロジック」IIFE が存在
- `applyHoldingBallFix()` — HUD ボールへの `frustumCulled=false` を多重タイマーで適用
- `touchstart/touchend` + `throwBall()` — 旧 throwButton に代わる新タッチシステム
- 旧 `#throw-button` は `display: none !important` で非表示化済み

### 残存する問題の根本原因

#### 不具合1・2 共通原因：`loaded` イベント未発火
`throwBall()` 内のボール生成コード（行 7186付近）:
```js
newBall.addEventListener('loaded', () => {
    // frustumCulled=false 適用 + throw() 呼び出し
});
// フォールバックは restoreBall() のみ（HUD ボールを戻すだけ）
```

A-Frame では `loaded` = エンティティ初期化完了、`model-loaded` = GLB 完全読み込み完了。
Android ではキャッシュ済みモデルの場合、`loaded` が投球リスナー登録前に同期的に発火するか、
あるいは `loaded` 発火時に `getObject3D('mesh')` がまだ null の場合がある。
→ `frustumCulled=false` が適用されない → ボールがカリングで消える（不具合1）
→ `throw()` が呼ばれないと同時に `loaded` のタイミングで mesh が null でも throw 自体は実行されているが
  `model-loaded` ではなく `loaded` を使っているため mesh 操作が失敗している
→ **修正：`model-loaded` イベントを使用、かつ 500ms フォールバックタイマーを追加**

#### 不具合3 原因：AR.js sourceWidth とポートレート画面の不一致
`<a-scene arjs="sourceWidth: 1280; sourceHeight: 720; ...">`
moto g64y (Android 14) のポートレート画面 (例: 412×915px) に対して
16:9 横向きソース要求 → AR.js が映像を引き延ばし/ズームして表示
→ **修正：Android 検出時に portrait 向きの解像度 (640×480) を JS で動的設定**

## 制約条件

### 絶対条件
- iPhone など現在正常動作している端末を壊さない
- Android 固有の修正は必ず UA 判定でガード

### 変更範囲
- `ARstampRally202603.blade.php` のみ
- 新規ファイル作成不要

## 成功基準

| 項目 | 基準 |
|------|------|
| HUD ボール表示 | moto g64y で手元のボールが見える |
| 投球動作 | スワイプでボールが飛ぶ |
| カメラズーム | カメラ映像が自然なサイズで表示される |
| iPhone 動作継続 | 既存の iPhone 動作に影響なし |

## 次ステップ
設計フェーズへ進む

---

# 要件定義: ARstampRally202603 HUDポケボール DOM オーバーレイ化（パターン2）

## 作成日時
2026年4月6日

## 参照
`.claude_workflow/complete.md` を参照済み

## プロジェクト概要
AR スタンプラリーアプリ (`ARstampRally202603.blade.php`) において、HUD ポケボール（手元に持つボール表示）が一部端末で表示されない問題を根本解決する。  
A-Frame 3D エンティティとして camera の子要素に配置している `#holding-pokeball` を廃止し、HTML `<img>` DOM オーバーレイに置き換える（パターン2実装）。

## 問題の根本原因
- **原因A（フラスタムカリング誤検出）**: AR.js がカメラのワールドマトリクスを直接書き換えるため、Three.js がバウンディング球を誤算 → `frustumCulled=true` でHUDが消える
- **原因B（アスペクト比/FOV不一致）**: `sourceWidth/sourceHeight` が端末画面比率と異なると、A-Frame カメラのプロジェクション行列と画面サイズがずれる → 固定位置 `0 -0.24 -0.5` が画面外になる

## 解決策（パターン2）
`#holding-pokeball`（A-Frame 3D エンティティ）を `visible="false"` で残しつつ、  
`<div id="hud-pokeball"><img ...></div>` という純粋な DOM 要素に置き換えてポケボール画像を表示する。

### 採用理由
- DOM `<img>` は A-Frame・AR.js・Three.js の影響を一切受けない → どの端末でも確実に表示される
- フラスタムカリングもアスペクト比問題も原理的に発生しない
- CSS `position: fixed; bottom: 12%; left: 50%` で任意の画面サイズに対応

## 制約条件
- `#holding-pokeball`（A-Frame エンティティ）は `visible="false"` で DOM に残す（後方互換のため）
- `applyHoldingBallFix` の3Dエンティティ向けコードは削除してよい（不要になるため）
- iPhone の既存動作（動物アニメーション・ボール投げ）を壊さない
- 変更は `ARstampRally202603.blade.php` 1ファイルのみ
- 用意するアセット: `public/cg/pokeball_image.png`（ユーザー用意済み）

## 変更箇所の概要

| # | 種類 | 場所 |
|---|------|------|
| 1 | CSS追加 | `</style>` 直前（約2003行目） |
| 2 | HTML追加 | `<button id="throw-button">` の閉じタグ直後（約2172行目） |
| 3 | HTML変更 | `#holding-pokeball` の `visible="true"` → `visible="false"` |
| 4a | JS変更（hide） | `captureModelScreenshot()` 内 holding 非表示コード（3371行目） |
| 4b | JS変更（restore）| 同関数内 restore コード × 3か所（3400, 3415, 3421行目） |
| 5 | JS変更 | IIFE 先頭: `hudEl` 変数追加、`applyHoldingBallFix` 全削除（7028〜7074行目） |
| 6a | JS変更 | touchstart 持ち上げ演出（7096行目） |
| 6b | JS変更 | touchend ボール隠し（7131〜7133行目） |
| 6c | JS変更 | touchcancel 復元（7139〜7141行目） |
| 6d | JS変更 | mousedown 持ち上げ演出（7155行目） |
| 6e | JS変更 | mouseup ボール隠し（7169〜7170行目） |
| 7 | JS変更 | `throwBall()` 内 初期位置計算（7183〜7185行目） |
| 8 | JS変更 | `restoreBall()` 内 ボール復元（7255〜7259行目） |

## 成功基準
- iOS・Android 両端末でポケボール画像が画面下中央に常時表示される
- スワイプでボールが正常に投げられる
- 既存の iPhone 動作に影響なし

## 次ステップ
設計フェーズへ進む

---

# 要件定義10: ギャラリーマーカー（maker00）iPhone SEフリーズ修正

## 作成日時
2026年4月17日

## 問題
- `/stamp202605` で6匹捕獲後、`pattern-maker00.patt`（ギャラリーマーカー）を読むとiPhone SEがフリーズする
- 原因: 6つのGLBモデルを同時にロード＋markerFound/markerLostフリッカーで繰り返し破棄・再生成

## 選択された解決策
- **A: キャッシュ＋デバウンス** — markerLostでモデルを破棄せずvisible=falseにし、markerFoundで再表示。デバウンス300msでフリッカー防止
- **B: 逐次ロード** — 6モデルを同時ロードせず、1つずつ500ms間隔で順次ロード。初回のみ

## 変更対象
- `resources/views/ARstampRally202605/js-gallery.blade.php` のみ

## 成功基準
- iPhone SEでギャラリーマーカーを読んでもフリーズしない
- 捕獲済みモデルがギャラリーに表示される
- markerLost→markerFoundの高速切り替えで再ロードが走らない
- 既存の捕獲・投擲機能に影響なし
