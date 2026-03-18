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
