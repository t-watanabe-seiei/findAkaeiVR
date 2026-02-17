# タスク化: WebVR視野狭窄体験アプリ

## 作成日時
2026年2月3日

## 前提
`.claude_workflow/design.md`を読み込み、設計内容を確認済み

## タスク一覧

### Phase 1: 基本実装（MVP）

#### ✅ Task 1: Laravelルーティングの追加
**目的**: `/vr-tunnel` エンドポイントの作成
**ファイル**: `routes/web.php`
**作業内容**:
- `/vr-tunnel` ルートを追加
- `vr-tunnel` Bladeビューを返す
**依存関係**: なし
**所要時間**: 5分
**完了条件**: ルートにアクセスできる（ビューは後で作成）
**ステータス**: ⬜ 未着手

---

#### ✅ Task 2: JSディレクトリ構造の作成
**目的**: カスタムJSファイル用のディレクトリ作成
**ディレクトリ**:
- `public/js/vr-tunnel/`
**作業内容**:
- ディレクトリを作成
**依存関係**: なし
**所要時間**: 2分
**完了条件**: ディレクトリが存在する
**ステータス**: ⬜ 未着手

---

#### ✅ Task 3: Bladeビューの作成（基本構造）
**目的**: A-Frameの基本シーンを含むHTMLページ作成
**ファイル**: `resources/views/vr-tunnel.blade.php`
**作業内容**:
- HTML基本構造
- A-Frame CDN読み込み（1.4.0）
- `<a-scene vr-mode-ui="enabled: true" auto-enter-vr>`タグ（vr-mode-ui設定が重要）
- オーバーレイUI（`#vr-start-overlay`）の追加
- オーバーレイ用CSS（hidden状態の定義含む）
- メタタグ（viewport等）
**依存関係**: Task 1（ルーティング）
**所要時間**: 15分
**完了条件**: ページが表示され、A-Frameが読み込まれ、オーバーレイが表示される
**ステータス**: ⬜ 未着手

---

#### ✅ Task 3-2: 自動VRモード切り替えコンポーネントの実装
**目的**: `auto-enter-vr` コンポーネントの実装
**ファイル**: `public/js/vr-tunnel/tunnel-vision.js`
**作業内容**:
- `AFRAME.registerComponent('auto-enter-vr')`を実装
- WebXR API（`navigator.xr.isSessionSupported('immersive-vr')`）でVRデバイス検出
- VRデバイス検出時: オーバーレイを非表示にして、1秒後に`sceneEl.enterVR()`を呼び出し
- デスクトップ環境: オーバーレイクリックで`requestFullscreen()`
- コンソールログで動作状態を出力
**依存関係**: Task 2（ディレクトリ作成）、Task 3（Bladeビュー）
**所要時間**: 20分
**完了条件**: 
- Pico4 Enterpriseで自動的にVRモードに切り替わる
- デスクトップでオーバーレイクリックでフルスクリーン表示される
**ステータス**: ⬜ 未着手
**重要**: vr-mode-ui="enabled: true"設定がないと、Picoブラウザで自動VRモードが起動しない

---

#### ✅ Task 4: 360度画像の表示
**目的**: `<a-sky>`で360度画像を表示
**ファイル**: `resources/views/vr-tunnel.blade.php`
**作業内容**:
- `<a-sky src="/cg/R0010034.JPG" rotation="0 -90 0"></a-sky>`を追加
- カメラリグ（`<a-entity id="camera-rig">`）を追加
- `<a-camera>`を追加
**依存関係**: Task 3（Bladeビュー基本構造）
**所要時間**: 5分
**完了条件**: ブラウザで360度画像が表示され、マウスドラッグで視点変更可能
**ステータス**: ⬜ 未着手

---

#### ✅ Task 5: カスタムコンポーネントの実装（基本構造）
**目的**: `tunnel-vision-overlay` コンポーネントの骨格作成
**ファイル**: `public/js/vr-tunnel/tunnel-vision.js`
**作業内容**:
- A-Frameコンポーネント登録（`AFRAME.registerComponent`）
- スキーマ定義（innerRadius, outerRadius等）
- `init()`メソッド（空でOK）
- Bladeビューからの読み込み
**依存関係**: Task 2（ディレクトリ作成）、Task 3（Bladeビュー）
**所要時間**: 15分
**完了条件**: コンポーネントが登録され、エラーなく読み込まれる
**ステータス**: ⬜ 未着手

---

#### ✅ Task 6: 球体ジオメトリの生成
**目的**: カメラに追従する球体メッシュの作成
**ファイル**: `public/js/vr-tunnel/tunnel-vision.js`
**作業内容**:
- `init()`メソッド内で`THREE.SphereGeometry`を生成
- パラメータ: radius=0.5, segments=64
- `THREE.MeshBasicMaterial`で仮マテリアル（黒、半透明）
- `THREE.Mesh`を生成してシーンに追加
- エンティティの`object3D`に追加
**依存関係**: Task 5（コンポーネント基本構造）
**所要時間**: 20分
**完了条件**: カメラ周りに黒い半透明の球体が表示される
**ステータス**: ⬜ 未着手

---

#### ✅ Task 7: カスタムシェーダーの実装（Vertex Shader）
**目的**: 頂点シェーダーの定義
**ファイル**: `public/js/vr-tunnel/tunnel-vision.js`
**作業内容**:
- Vertex Shaderコードを文字列で定義
- `varying vec3 vPosition;` を宣言
- `vPosition = position;` で頂点位置を渡す
**依存関係**: Task 6（球体ジオメトリ）
**所要時間**: 10分
**完了条件**: シェーダーコードが定義される（まだ適用しない）
**ステータス**: ⬜ 未着手

---

#### ✅ Task 8: カスタムシェーダーの実装（Fragment Shader）
**目的**: フラグメントシェーダーの定義
**ファイル**: `public/js/vr-tunnel/tunnel-vision.js`
**作業内容**:
- Fragment Shaderコードを文字列で定義
- `uniform float innerRadius, outerRadius, opacity;` を宣言
- `varying vec3 vPosition;` を受け取る
- 視線中心からの角度計算
- `smoothstep`でグラデーション
- 黒色（0,0,0）でalphaを制御
**依存関係**: Task 7（Vertex Shader）
**所要時間**: 20分
**完了条件**: シェーダーコードが定義される（まだ適用しない）
**ステータス**: ⬜ 未着手

---

#### ✅ Task 9: ShaderMaterialの作成と適用
**目的**: カスタムシェーダーを球体に適用
**ファイル**: `public/js/vr-tunnel/tunnel-vision.js`
**作業内容**:
- `THREE.ShaderMaterial`を作成
- Vertex/Fragment Shaderを設定
- Uniformsを定義（innerRadius, outerRadius, opacity）
- マテリアル設定:
  - `transparent: true`
  - `side: THREE.BackSide`
  - `depthWrite: false`
  - `depthTest: false`
- 球体メッシュにマテリアルを適用
**依存関係**: Task 6, 7, 8
**所要時間**: 25分
**完了条件**: 視野狭窄エフェクトが視覚的に確認できる
**ステータス**: ⬜ 未着手

---

#### ✅ Task 10: エンティティへのコンポーネント適用
**目的**: Bladeビューで`tunnel-vision-overlay`を使用
**ファイル**: `resources/views/vr-tunnel.blade.php`
**作業内容**:
- `<a-camera>`の子要素に`<a-entity tunnel-vision-overlay></a-entity>`を追加
**依存関係**: Task 4（カメラ追加）、Task 9（シェーダー適用）
**所要時間**: 5分
**完了条件**: カメラに視野狭窄エフェクトが追従する
**ステータス**: ⬜ 未着手

---

#### ✅ Task 10-2: タイマー機能の実装（VRイベントリスナー）
**目的**: VRモード開始/終了時のイベント処理
**ファイル**: `public/js/vr-tunnel/tunnel-vision.js`
**作業内容**:
- `init()`メソッドに`enter-vr`イベントリスナーを追加
- `enter-vr`時に`startTime`を記録（`Date.now()`）
- `exit-vr`イベントリスナーを追加（タイマー停止）
- `isVRMode`フラグを管理
**依存関係**: Task 9（ShaderMaterial作成）
**所要時間**: 15分
**完了条件**: VRモード開始時にタイマーが開始される
**ステータス**: ⬜ 未着手

---

#### ✅ Task 10-3: tick()メソッドの実装
**目的**: 毎フレーム時間を計算してinnerRadiusを更新
**ファイル**: `public/js/vr-tunnel/tunnel-vision.js`
**作業内容**:
- `tick()`メソッドを実装
- VRモード中のみ動作するようチェック
- 経過時間（`Date.now() - startTime`）を計算
- 50秒でループ（`elapsedMs % 50000`）
- `getInnerRadiusForTime()`を呼び出して値を取得
- シェーダーのuniformsを更新（`innerRadius`, `outerRadius`）
**依存関係**: Task 10-2（イベントリスナー）
**所要時間**: 20分
**完了条件**: 時間経過でinnerRadiusが更新される
**ステータス**: ⬜ 未着手

---

#### ✅ Task 10-4: innerRadius計算関数の実装
**目的**: 経過時間からinnerRadiusを計算
**ファイル**: `public/js/vr-tunnel/tunnel-vision.js`
**作業内容**:
- `getInnerRadiusForTime(elapsedMs)`関数を実装
- 5つの時間帯（0-10s, 10-20s, 20-30s, 30-40s, 40-50s）で分岐
- 線形補間（lerp）で値を計算
- lerp関数を実装（`lerp(a, b, t) = a + (b - a) * t`）
- 目標値: 1.0 → 0.175 → 0.125 → 0.075 → 0.025
**依存関係**: Task 10-3（tick実装）
**所要時間**: 15分
**完了条件**: 正しいinnerRadius値が返される
**ステータス**: ⬜ 未着手

---

#### ✅ Task 10-5: outerRadius自動計算の実装
**目的**: innerRadiusに連動してouterRadiusを更新
**ファイル**: `public/js/vr-tunnel/tunnel-vision.js`
**作業内容**:
- `tick()`内でouterRadiusを計算（`innerRadius + 0.20`）
- シェーダーuniformsのouterRadiusを更新
**依存関係**: Task 10-4（innerRadius計算）
**所要時間**: 5分
**完了条件**: outerRadiusがinnerRadiusに追従する
**ステータス**: ⬜ 未着手

---

### Phase 2: 調整・最適化

#### ✅ Task 11: パラメータの微調整
**目的**: 視野狭窄の見た目を最適化
**ファイル**: `public/js/vr-tunnel/tunnel-vision.js`
**作業内容**:
- `innerRadius`, `outerRadius`の値を調整
- 球体の`radius`を調整（0.3～0.8で実験）
- セグメント数の調整（パフォーマンスとのバランス）
**依存関係**: Task 10（全基本実装完了）
**所要時間**: 15分
**完了条件**: 自然な視野狭窄効果が得られる
**ステータス**: ⬜ 未着手

---

#### ✅ Task 12: デスクトップブラウザでの動作確認
**目的**: 基本動作のテスト
**テスト内容**:
- Chrome/Edgeでページアクセス
- 360度画像の表示確認
- マウスドラッグでの視点変更
- 視野狭窄エフェクトの視覚確認
- コンソールエラーの確認
**依存関係**: Task 11（パラメータ調整）
**所要時間**: 10分
**完了条件**: すべてのテスト項目でOK
**ステータス**: ⬜ 未着手

---

#### ✅ Task 13: VRモードの動作確認
**目的**: VRデバイスでの動作テスト
**テスト内容**:
- VRボタンの表示確認
- VRモード起動
- 頭の動きに追従するか確認
- エフェクトが正しく表示されるか確認
**依存関係**: Task 12（デスクトップ確認）
**所要時間**: 20分（デバイス接続含む）
**完了条件**: VRモードで正常動作
**ステータス**: ⬜ 未着手

---

### Phase 3: 改善（オプション）

#### ✅ Task 14: エラーハンドリングの追加（オプション）
**目的**: 画像読み込み失敗時の対処
**ファイル**: `resources/views/vr-tunnel.blade.php`
**作業内容**:
- `<a-sky>`にイベントリスナー追加（loaded/error）
- エラー時にコンソール出力またはフォールバック画像
**依存関係**: Task 13
**所要時間**: 10分
**完了条件**: 画像エラー時に適切に処理される
**ステータス**: ⬜ 未着手

---

#### ✅ Task 15: ローディング画面の追加（オプション）
**目的**: 画像読み込み中の表示
**ファイル**: `resources/views/vr-tunnel.blade.php`
**作業内容**:
- A-Frameの`loading-screen`コンポーネント活用
- またはカスタムローディング表示
**依存関係**: Task 13
**所要時間**: 15分
**完了条件**: 読み込み中に適切な表示がされる
**ステータス**: ⬜ 未着手

---

## タスク実行順序

```
Task 1 (ルーティング)
  ↓
Task 2 (ディレクトリ) + Task 3 (Blade基本)
  ↓
Task 4 (360度画像) + Task 5 (コンポーネント骨格)
  ↓
Task 6 (球体ジオメトリ)
  ↓
Task 7 (Vertex Shader) → Task 8 (Fragment Shader)
  ↓
Task 9 (ShaderMaterial適用)
  ↓
Task 10 (エンティティ適用)
  ↓
Task 11 (パラメータ調整)
  ↓
Task 12 (デスクトップ確認)
  ↓
Task 13 (VR確認)
  ↓
Task 14, 15 (オプション改善)
```

## 進捗管理

### 現在の状況
- **完了**: 0/15 タスク
- **進行中**: 0タスク
- **未着手**: 15タスク

### 重要なマイルストーン
1. **MVP完了**: Task 10まで完了（基本機能動作）
2. **調整完了**: Task 13まで完了（VR動作確認）
3. **最終完成**: Task 15まで完了（オプション含む）

## リスクとブロッカー

### 潜在的な問題
1. **シェーダーのバグ**: Task 8-9で計算ロジックエラーの可能性
   - 対策: 段階的にテスト、コンソールで確認
2. **パフォーマンス問題**: Task 11-13でフレームレート低下の可能性
   - 対策: セグメント数削減、シェーダー最適化
3. **VRデバイス互換性**: Task 13でPico4特有の問題の可能性
   - 対策: A-Frameの最新版使用、フォールバック実装

### 前提条件の確認
- [x] `public/cg/R0010034.JPG` が存在する
- [ ] A-Frame CDN（1.4.0）がアクセス可能
- [ ] Three.jsの基本知識（A-Frame内で使用）

## 次のステップ
実行フェーズへの移行

---

**タスク化フェーズが完了しました。実行フェーズに進んでよろしいですか？**

---

# タスク化: VRシューティングゲーム - ゲーム前の弾丸切り替え・発射機能

## 作成日時
2026年2月13日

## 前提
`.claude_workflow/design.md`の「VRシューティングゲーム - ゲーム前の弾丸切り替え・発射機能」セクションを読み込み済み

## タスク一覧

### Phase 1: 核心機能の実装

#### Task 1: vr-controllerコンポーネントの条件変更
**目的**: ゲーム前でもA/B/グリップボタンでボール切り替えを可能にする
**ファイル**: `resources/views/shooting3Danimal.blade.php`
**対象行**: 3266-3269行（onButtonDownメソッド内）
**作業内容**:
```javascript
// 【変更前】
if (!window.gameStarted || window.gameEnded) {
    window.debugLog('Game not active, ignoring button');
    return;
}

// 【変更後】
if (window.gameEnded) {
    window.debugLog('Game ended, ignoring button');
    return;
}
```
- `if (!window.gameStarted || window.gameEnded)`を`if (window.gameEnded)`に変更
- ゲーム前（`!window.gameStarted`）でも切り替えを許可
- ゲーム終了後は無視（リザルト画面表示中の誤操作防止）
**依存関係**: なし
**所要時間**: 3分
**完了条件**: 
- コードが変更される
- デバッグログが適切に出力される
**ステータス**: ⬜ 未着手

---

#### Task 2: handle-shootコンポーネントの条件変更
**目的**: ゲーム前でもトリガーでボール発射を可能にする
**ファイル**: `resources/views/shooting3Danimal.blade.php`
**対象行**: 2299-2307行（shootメソッド内）
**作業内容**:
```javascript
// 【変更前】
if (!window.gameStarted) {
    window.debugLog('Game not started, ignoring shoot');
    return;
}

if (window.gameEnded) {
    window.debugLog('Game ended, ignoring shoot');
    return;
}

// 【変更後】
if (window.gameEnded) {
    window.debugLog('Game ended, ignoring shoot');
    return;
}
```
- `if (!window.gameStarted)`のブロックを削除
- ゲーム前（`!window.gameStarted`）でも発射を許可
- ゲーム終了後は無視（既存の条件を維持）
**依存関係**: なし
**所要時間**: 3分
**完了条件**:
- コードが変更される
- ゲーム前にトリガーを引くとボールが発射される
**ステータス**: ⬜ 未着手

---

#### Task 3: hit-boxコンポーネントのスコア加算条件追加
**目的**: スコア加算とenemies defeatedカウントをゲーム中のみに制限
**ファイル**: `resources/views/shooting3Danimal.blade.php`
**対象行**: 2667-2730行（ball-hitイベントリスナー内）
**作業内容**:
**変更箇所1: enemies defeatedカウント（2671-2673行）**:
```javascript
// 【変更前】
window.enemiesDefeated++;
window.debugLog('Animals Captured:', window.enemiesDefeated);

// 【変更後】
if (window.gameStarted && !window.gameEnded) {
    window.enemiesDefeated++;
    window.debugLog('Animals Captured:', window.enemiesDefeated);
}
```

**変更箇所2: スコア加算処理（2693-2725行）**:
```javascript
// 【変更前（一部抜粋）】
const baseScore = isCorrectBall ? 10 : 3;
const scoreChange = baseScore + comboBonus;
window.totalScore += scoreChange;

if (window.totalScore < 0) window.totalScore = 0;

if (window.comboCount > window.maxComboCount) {
    window.maxComboCount = window.comboCount;
    window.debugLog('New Max Combo:', window.maxComboCount);
}

// 【変更後】
let scoreChange = 0;
if (window.gameStarted && !window.gameEnded) {
    const baseScore = isCorrectBall ? 10 : 3;
    scoreChange = baseScore + comboBonus;
    window.totalScore += scoreChange;
    
    if (window.totalScore < 0) window.totalScore = 0;
    
    if (window.comboCount > window.maxComboCount) {
        window.maxComboCount = window.comboCount;
        window.debugLog('New Max Combo:', window.maxComboCount);
    }
}
```
- enemies defeatedのインクリメントを条件分岐内に移動
- スコア加算処理全体を`if (window.gameStarted && !window.gameEnded)`で囲む
- ゲーム前のヒットではスコアを加算しない
- ヒット音とエフェクトは実行（練習時のフィードバック用）
**依存関係**: Task 2（発射可能にする必要がある）
**所要時間**: 10分
**完了条件**:
- コードが正しく変更される
- ゲーム前のヒットではスコアが加算されない
- ゲーム中のヒットではスコアが加算される
**ステータス**: ⬜ 未着手

---

#### Task 4: スコア表示更新の条件追加
**目的**: リアルタイムスコア表示の更新をゲーム中のみに制限
**ファイル**: `resources/views/shooting3Danimal.blade.php`
**対象行**: 2757行付近（hit-box内のリアルタイムスコア表示更新）
**作業内容**:
```javascript
// 【変更前】
const currentScoreText = document.getElementById('currentScore');
if (currentScoreText) {
    currentScoreText.setAttribute('value', `SCORE: ${window.totalScore.toFixed(1)}`);
}

// 【変更後】
if (window.gameStarted && !window.gameEnded) {
    const currentScoreText = document.getElementById('currentScore');
    if (currentScoreText) {
        currentScoreText.setAttribute('value', `SCORE: ${window.totalScore.toFixed(1)}`);
    }
}
```
- スコア表示更新処理を`if (window.gameStarted && !window.gameEnded)`で囲む
- ゲーム開始前は`SCORE: 0.0`のまま維持
**依存関係**: Task 3（スコア加算条件追加）
**所要時間**: 3分
**完了条件**:
- コードが変更される
- ゲーム前のスコア表示が変わらない
- ゲーム中のスコア表示が正しく更新される
**ステータス**: ⬜ 未着手

---

### Phase 2: テストと検証

#### Task 5: 単体テスト実施
**目的**: 実装した機能が正しく動作することを確認
**テスト項目**:

**テスト5-1: ゲーム前のボール切り替え**
- アクセス方法: `/shooting3Danimal`にアクセス
- 操作: メニュー表示中にグリップ/A/Bボタンを押す（または、Gキーを押す）
- 期待結果:
  - ✅ プレビューモデルがリンゴ⇔キャベツに切り替わる
  - ✅ デバッグログに"Switched to ball: ..."が表示される
  - ✅ メニュー選択が影響を受けない
- ステータス: ⬜ 未実施

**テスト5-2: ゲーム前のボール発射**
- アクセス方法: `/shooting3Danimal`にアクセス
- 操作: メニュー表示中にトリガーを引く（または、スペースキーを押す）
- 期待結果:
  - ✅ ボールが発射される（物理演算で飛んでいく）
  - ✅ 3個まで同時発射可能（4個目は無視される）
  - ✅ スコアは加算されない（`SCORE: 0.0`のまま）
  - ✅ デバッグログに"Shoot function called"が表示される
  - ✅ メニュー選択が影響を受けない
- ステータス: ⬜ 未実施

**テスト5-3: メニュー選択の動作確認**
- アクセス方法: `/shooting3Danimal`にアクセス
- 操作: ボール発射・切り替えを行った後、Level1 Easyボタンをクリック
- 期待結果:
  - ✅ ゲームが正常に開始される
  - ✅ メニューが非表示になる
  - ✅ タイマーとスコア表示が表示される
  - ✅ 的（動物）が出現する
- ステータス: ⬜ 未実施

**テスト5-4: ゲーム中の動作確認**
- アクセス方法: `/shooting3Danimal`にアクセスしてゲーム開始
- 操作: ゲーム中にボール切り替え・発射を行う
- 期待結果:
  - ✅ ボール切り替えが機能する
  - ✅ ボール発射が機能する
  - ✅ 的に当たるとスコアが加算される
  - ✅ 正しいボールで当てると+10pt、間違ったボールで+3pt
  - ✅ コンボ機能が動作する
  - ✅ 従来通りの動作
- ステータス: ⬜ 未実施

**テスト5-5: ゲーム終了後の動作確認**
- アクセス方法: `/shooting3Danimal`にアクセスしてゲーム終了まで待つ
- 操作: ゲーム終了（タイムアップ）後、グリップ/A/Bボタンを押し、トリガーを引く
- 期待結果:
  - ✅ ボタンが無視される（リザルト画面表示中）
  - ✅ トリガーが無視される
  - ✅ デバッグログに"Game ended, ignoring ..."が表示される
  - ✅ リザルト画面が正常に表示される
- ステータス: ⬜ 未実施

**依存関係**: Task 1-4（全ての実装完了）
**所要時間**: 20分
**完了条件**: すべてのテスト項目が✅になる
**ステータス**: ⬜ 未着手

---

#### Task 6: デバッグモードでの動作確認
**目的**: DEBUG_MODE=trueでログ出力を確認
**ファイル**: `resources/views/shooting3Danimal.blade.php`
**対象行**: 206行（`window.DEBUG_MODE = false;`）
**作業内容**:
- 一時的に`window.DEBUG_MODE = true;`に変更
- ブラウザのコンソールでログを確認
- 各操作時のログが正しく出力されるか確認
- テスト後、`window.DEBUG_MODE = false;`に戻す
**依存関係**: Task 5（単体テスト）
**所要時間**: 10分
**完了条件**:
- ログが適切に出力される
- デバッグモードをfalseに戻す
**ステータス**: ⬜ 未着手

---

### Phase 3: 最終確認とドキュメント更新

#### Task 7: VRデバイス（Pico4）での動作確認
**目的**: 実機での動作テスト
**テスト内容**:
- Pico4でページにアクセス（`/shooting3Danimal`）
- ゲーム前にグリップボタンでボール切り替え
- ゲーム前にトリガーでボール発射
- ゲームを開始し、プレイ
- 期待結果:
  - ✅ 全ての機能が正常に動作
  - ✅ フレームレートが安定（60fps以上）
  - ✅ コントローラーのボタンが正しく反応
  - ✅ プレビュー表示が正しく切り替わる
**依存関係**: Task 5（単体テスト完了）
**所要時間**: 15分（デバイス接続とセットアップ含む）
**完了条件**: Pico4で正常動作
**ステータス**: ⬜ 未着手

---

#### Task 8: デスクトップブラウザでの動作確認
**目的**: PC環境での動作テスト
**テスト内容**:
- Chrome/Edgeでページにアクセス（`/shooting3Danimal`）
- ゲーム前にGキーでボール切り替え
- ゲーム前にスペースキーでボール発射
- ゲームを開始し、プレイ
- 期待結果:
  - ✅ 全ての機能が正常に動作
  - ✅ キーボード操作が正しく反応
  - ✅ マウスでメニュー選択ができる
**依存関係**: Task 5（単体テスト完了）
**所要時間**: 10分
**完了条件**: Chrome/Edgeで正常動作
**ステータス**: ⬜ 未着手

---

#### Task 9: README.md更新
**目的**: 新機能の説明を追加
**ファイル**: `README.md`
**作業内容**:
- VRシューティングゲームのセクションに新機能を追記
- **ゲーム前の操作**セクションを追加:
  - ボール切り替え（グリップ/A/B/Gキー）
  - ボール発射（トリガー/スペースキー）
  - 練習可能であることを明記
- **注意事項**:
  - ゲーム前のヒットではスコアが加算されない
  - 的（動物）はゲーム開始後に表示される
- コードブロックやスクリーンショットを追加（オプション）
**依存関係**: Task 7, 8（動作確認完了）
**所要時間**: 10分
**完了条件**: 
- README.mdに新機能の説明が追加される
- 説明が明確で分かりやすい
**ステータス**: ⬜ 未着手

---

## タスク実行順序

```
Task 1 (vr-controller修正) ──┐
                              ├→ Task 5 (単体テスト) → Task 6 (デバッグ確認)
Task 2 (handle-shoot修正) ────┤                              ↓
                              │                        Task 7 (Pico4確認)
Task 3 (hit-box修正) ─────────┤                              ↓
                              │                        Task 8 (デスクトップ確認)
Task 4 (スコア表示修正) ──────┘                              ↓
                                                        Task 9 (README更新)
```

## 進捗管理

### 現在の状況
- **完了**: 0/9 タスク
- **進行中**: 0タスク
- **未着手**: 9タスク

### 重要なマイルストーン
1. **実装完了**: Task 1-4まで完了（コード変更完了）
2. **テスト完了**: Task 5-6まで完了（動作検証完了）
3. **最終完成**: Task 9まで完了（ドキュメント更新完了）

## リスクとブロッカー

### 潜在的な問題
1. **メニュー選択との競合**: Task 2実装時
   - 対策: 既存のイベント伝播停止処理により対策済み（設計で確認済み）
2. **スコア加算の漏れ**: Task 3実装時
   - 対策: 全てのスコア加算箇所を確認（2718行付近、2757行付近）
3. **デバッグログの見落とし**: Task 6実施時
   - 対策: DEBUG_MODE=trueで全ログを確認

### 前提条件の確認
- [x] `resources/views/shooting3Danimal.blade.php`が存在する
- [x] ボール切り替え機能が既に存在する（ゲーム中のみ）
- [x] トリガー発射機能が既に存在する（ゲーム中のみ）
- [x] スコア加算処理が既に存在する

## 次のステップ
実行フェーズへの移行

---

**VRシューティングゲームのタスク化フェーズが完了しました。実行フェーズに進んでよろしいですか？**
---

# タスク化: ARスタンプラリー202603版のlocalStorage分離

## 作成日時
2026年2月17日

## 前提
`.claude_workflow/design.md`の「ARスタンプラリー202603版のlocalStorage分離」セクションを読み込み、設計内容を確認済み

## 変更箇所の完全リスト

### ファイル: resources/views/ARstampRally202603.blade.php (6908行)

#### 変更対象（合計14箇所）

**変数定義（3箇所）:**
1. 行2587: `dbName: 'ARStampRallyDB'` → `dbName: 'ARStampRallyDB202603'`
2. 行2676: `const storageKey = 'ar-user-id'` → `const storageKey = 'ar-user-id-202603'`
3. 行2677: `const cookieName = 'ar_user_id'` → `const cookieName = 'ar_user_id_202603'`

**'ar-stamp-rally' キー（5箇所）:**
4. 行180: `localStorage.removeItem('ar-stamp-rally')` → `localStorage.removeItem('ar-stamp-rally-202603')`
5. 行2798: `localStorage.getItem('ar-stamp-rally')` → `localStorage.getItem('ar-stamp-rally-202603')`
6. 行2804: `localStorage.removeItem('ar-stamp-rally')` → `localStorage.removeItem('ar-stamp-rally-202603')`
7. 行2811: `localStorage.setItem('ar-stamp-rally', ...)` → `localStorage.setItem('ar-stamp-rally-202603', ...)`
8. 行6048: `localStorage.removeItem('ar-stamp-rally')` → `localStorage.removeItem('ar-stamp-rally-202603')`

**'ar-captured-animals' キー（4箇所）:**
9. 行2816: `localStorage.getItem('ar-captured-animals')` → `localStorage.getItem('ar-captured-animals-202603')`
10. 行2822: `localStorage.removeItem('ar-captured-animals')` → `localStorage.removeItem('ar-captured-animals-202603')`
11. 行2828: `localStorage.setItem('ar-captured-animals', ...)` → `localStorage.setItem('ar-captured-animals-202603', ...)`
12. 行6049: `localStorage.removeItem('ar-captured-animals')` → `localStorage.removeItem('ar-captured-animals-202603')`

**'ar-prize-*' キー（2箇所）:**
13. 行5863: `localStorage.setItem('ar-prize-exchanged', 'true')` → `localStorage.setItem('ar-prize-exchanged-202603', 'true')`
14. 行5864: `localStorage.setItem('ar-prize-code', data.prizeCode)` → `localStorage.setItem('ar-prize-code-202603', data.prizeCode)`

**自動的に対応される箇所（storageKey変数を使用）:**
- 行2680: `localStorage.getItem(storageKey)` ← storageKey変数が変われば自動対応
- 行2687: `localStorage.setItem(storageKey, userId)` ← 同上
- 行2696: `localStorage.setItem(storageKey, userId)` ← 同上
- 行2708: `localStorage.setItem(storageKey, userId)` ← 同上

## タスク一覧

### ⬜ Task 1: 変数定義の変更（最優先）
**目的**: IndexedDB名、LocalStorageキー名、Cookie名の変数定義を変更
**ファイル**: `resources/views/ARstampRally202603.blade.php`
**作業内容**:
1. 行2587: `dbName: 'ARStampRallyDB'` → `dbName: 'ARStampRallyDB202603'`
2. 行2676: `const storageKey = 'ar-user-id'` → `const storageKey = 'ar-user-id-202603'`
3. 行2677: `const cookieName = 'ar_user_id'` → `const cookieName = 'ar_user_id_202603'`
**依存関係**: なし
**所要時間**: 5分
**完了条件**: 3箇所の変数定義が正しく変更されている
**ステータス**: ⬜ 未着手

---

### ⬜ Task 2: 'ar-stamp-rally' キーの一括変更
**目的**: スタンプデータ用LocalStorageキーをすべて変更
**ファイル**: `resources/views/ARstampRally202603.blade.php`
**作業内容**:
1. 行180: `'ar-stamp-rally'` → `'ar-stamp-rally-202603'`
2. 行2798: `'ar-stamp-rally'` → `'ar-stamp-rally-202603'`
3. 行2804: `'ar-stamp-rally'` → `'ar-stamp-rally-202603'`
4. 行2811: `'ar-stamp-rally'` → `'ar-stamp-rally-202603'`
5. 行6048: `'ar-stamp-rally'` → `'ar-stamp-rally-202603'`
**依存関係**: なし（Task 1と並行可能）
**所要時間**: 5分
**完了条件**: 5箇所すべてで `'ar-stamp-rally-202603'` が使用されている
**ステータス**: ⬜ 未着手

---

### ⬜ Task 3: 'ar-captured-animals' キーの一括変更
**目的**: 捕獲済み動物データ用LocalStorageキーをすべて変更
**ファイル**: `resources/views/ARstampRally202603.blade.php`
**作業内容**:
1. 行2816: `'ar-captured-animals'` → `'ar-captured-animals-202603'`
2. 行2822: `'ar-captured-animals'` → `'ar-captured-animals-202603'`
3. 行2828: `'ar-captured-animals'` → `'ar-captured-animals-202603'`
4. 行6049: `'ar-captured-animals'` → `'ar-captured-animals-202603'`
**依存関係**: なし（Task 1, 2と並行可能）
**所要時間**: 5分
**完了条件**: 4箇所すべてで `'ar-captured-animals-202603'` が使用されている
**ステータス**: ⬜ 未着手

---

### ⬜ Task 4: 景品関連キーの変更
**目的**: 景品交換用LocalStorageキーを変更
**ファイル**: `resources/views/ARstampRally202603.blade.php`
**作業内容**:
1. 行5863: `'ar-prize-exchanged'` → `'ar-prize-exchanged-202603'`
2. 行5864: `'ar-prize-code'` → `'ar-prize-code-202603'`
**依存関係**: なし（Task 1-3と並行可能）
**所要時間**: 3分
**完了条件**: 2箇所すべてで `-202603` サフィックスが追加されている
**ステータス**: ⬜ 未着手

---

### ⬜ Task 5: 動作確認テスト（必須）
**目的**: データ分離が正しく機能することを確認
**作業内容**:
1. ブラウザの開発者ツールを開く（F12）
2. Application タブを選択
3. LocalStorage、Cookie、IndexedDBをクリア
4. /stamp202603 にアクセス
5. ARマーカーをスキャンして動物を捕獲
6. LocalStorageに `ar-stamp-rally-202603` キーが作成されることを確認
7. LocalStorageに `ar-captured-animals-202603` キーが作成されることを確認
8. IndexedDBに `ARStampRallyDB202603` が作成されることを確認
9. Cookieに `ar_user_id_202603` が作成されることを確認
10. /stamp にアクセス
11. スタンプ帳が空であることを確認（/stamp202603のデータが表示されない）
12. 逆に /stamp で動物を捕獲
13. /stamp202603 のスタンプ帳に反映されないことを確認
**依存関係**: Task 1-4 すべて完了後
**所要時間**: 15分
**完了条件**: 
- ✅ /stamp と /stamp202603 のデータが完全に分離されている
- ✅ それぞれのページで独立したストレージキーが使用されている
- ✅ 既存の /stamp の動作に影響がない
**ステータス**: ⬜ 未着手

---

## 実装の注意事項

### コード変更時のチェックリスト
- [ ] 変更前に該当行の周辺コードを確認（3-5行前後）
- [ ] 文字列リテラルの完全一致を確認（スペース、引用符含む）
- [ ] 変更後にPHPの構文エラーがないか確認（php -l コマンド）
- [ ] 変更箇所の行番号と内容を記録

### 実装順序
1. **Task 1（変数定義）を最初に実施** - 最も影響が大きい
2. **Task 2-4を一度に実施** - multi_replace_string_in_fileで効率化
3. **Task 5（テスト）で検証** - 問題があれば即座に修正

### リスク管理
- **バックアップ**: Git commitまたはファイルコピーを事前に実施（任意）
- ** rollback**: 問題があれば元のARstampRally.blade.phpから再コピー可能
- **影響範囲**: ARstampRally202603.blade.phpのみ（元のファイルは変更しない）

## 成功基準（再確認）

### 必須条件
- [x] ARstampRally.blade.php（元ファイル）は一切変更されていない
- [ ] ARstampRally202603.blade.phpの全14箇所が正しく変更されている
- [ ] /stamp202603 で動物を捕獲したデータが /stamp に表示されない
- [ ] /stamp で捕獲したデータが /stamp202603 に表示されない
- [ ] 各ページで独立したストレージが使用されている

### 検証方法
1. ブラウザの開発者ツールでストレージを目視確認
2. 両ページを交互にアクセスしてデータの混在がないことを確認
3. 景品交換機能が/stamp202603で独立して動作することを確認

## 次のステップ
実行フェーズへの移行

---

**ARスタンプラリー202603版のlocalStorage分離のタスク化フェーズが完了しました。実行フェーズに進んでよろしいですか？**