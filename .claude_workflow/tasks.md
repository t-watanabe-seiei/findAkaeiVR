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

---

# タスク化4: ARstampRally202603 スタンプ帳UI改善と管理画面統計追加

## 作成日時
2026年2月19日

## 前提
`.claude_workflow/design.md` の「設計4」を読み込み、設計内容を確認済み

## タスク概要

本タスクは2つの大きな機能に分かれています：
1. **スタンプ帳UI改善** (ARstampRally202603.blade.php)
2. **管理画面統計ページ追加** (新規ファイル作成 + 既存ファイル修正)

## タスク一覧

### Phase 1: スタンプ帳UI改善（ARstampRally202603.blade.php）

---

#### Task 1-1: ヒントボタンのHTMLをコメントアウト
**目的**: スタンプ帳モーダルから「ヒントを見る」ボタンを非表示にする
**ファイル**: `resources/views/ARstampRally202603.blade.php`
**変更箇所**: 1931行目
**作業内容**:
```html
<!-- 変更前 -->
<button id="hint-button" type="button">ヒントを見る</button>

<!-- 変更後 -->
<!-- <button id="hint-button" type="button">ヒントを見る</button> -->
```
**依存関係**: なし
**所要時間**: 2分
**完了条件**: 
- ✅ HTML要素がコメントアウトされている
- ✅ 周囲のHTMLに影響がない
**ステータス**: ⬜ 未着手

---

#### Task 1-2: ヒントボタンのJavaScriptイベントハンドラーをコメントアウト
**目的**: ヒントボタンのクリックイベントを無効化する
**ファイル**: `resources/views/ARstampRally202603.blade.php`
**変更箇所**: 5746-5754行目
**作業内容**:
```javascript
// 変更前
const hintButton = document.getElementById('hint-button');
if (hintButton) {
    hintButton.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        window.open('{{ asset("/cg/stampRallyHints.pdf") }}', '_blank');
    });
}

// 変更後
/*
const hintButton = document.getElementById('hint-button');
if (hintButton) {
    hintButton.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        window.open('{{ asset("/cg/stampRallyHints.pdf") }}', '_blank');
    });
}
*/
```
**依存関係**: Task 1-1
**所要時間**: 2分
**完了条件**: 
- ✅ JavaScriptがコメントアウトされている
- ✅ 構文エラーがない
**ステータス**: ⬜ 未着手

---

#### Task 1-3: showStampBook関数の動物名表示ロジック修正
**目的**: 未収集動物の名前表示を変更
- パンダ: 未収集でも「パンダ」と表示
- シークレット（パンダ以外）: 「シークレット」と表示
- 通常動物15種: 「？？？」と表示
**ファイル**: `resources/views/ARstampRally202603.blade.php`
**変更箇所**: 3365-3386行目付近
**作業内容**:
```javascript
// 変更前のロジック
if (isCollected) {
    const date = new Date(collectedStamps[stampId].collectedAt);
    dateText = `<div class="stamp-date">${date.getMonth() + 1}/${date.getDate()} ${date.getHours()}:${String(date.getMinutes()).padStart(2, '0')}</div>`;

    // スクリーンショットがあれば画像を表示
    if (collectedStamps[stampId].screenshot) {
        const screenshotData = collectedStamps[stampId].screenshot;
        iconContent = `<img src="${screenshotData}" alt="${stamp.name}" style="width:100%; height:100%; object-fit:contain;">`;
    }
    // シークレット動物でも収集後は実際の名前を表示
    nameText = stamp.name;
} else if (isSecret) {
    // シークレット動物は未収集時にアイコンと名前を非表示
    iconContent = '🐾'; // 足跡アイコン
    nameText = 'シークレット'; // 名前も隠す
}

// 変更後のロジック
if (isCollected) {
    const date = new Date(collectedStamps[stampId].collectedAt);
    dateText = `<div class="stamp-date">${date.getMonth() + 1}/${date.getDate()} ${date.getHours()}:${String(date.getMinutes()).padStart(2, '0')}</div>`;

    // スクリーンショットがあれば画像を表示
    if (collectedStamps[stampId].screenshot) {
        const screenshotData = collectedStamps[stampId].screenshot;
        iconContent = `<img src="${screenshotData}" alt="${stamp.name}" style="width:100%; height:100%; object-fit:contain;">`;
    }
    // シークレット動物でも収集後は実際の名前を表示
    nameText = stamp.name;
} else if (isSecret) {
    // シークレット動物は未収集時にアイコンと名前を処理
    if (stampId === 'panda') {
        // パンダは特別扱い: 未収集でも「パンダ」と表示
        iconContent = '🐾'; // 足跡アイコン
        nameText = 'パンダ';
    } else {
        // パンダ以外のシークレット: 'シークレット'
        iconContent = '🐾'; // 足跡アイコン
        nameText = 'シークレット'; // 名前も隠す
    }
} else {
    // 通常動物の未収集時: '？？？' を表示
    // iconContentはデフォルトのまま（stamp.icon）
    nameText = '？？？';
}
```
**依存関係**: なし
**所要時間**: 5分
**完了条件**: 
- ✅ 未収集のパンダが「パンダ」と表示される
- ✅ 未収集のシークレット（パンダ以外）が「シークレット」と表示される
- ✅ 未収集の通常動物が「？？？」と表示される
- ✅ 収集済み動物は実際の名前が表示される（変更なし）
**ステータス**: ⬜ 未着手

---

### Phase 2: 管理画面統計ページ追加

---

#### Task 2-1: AdminControllerに新規メソッド追加
**目的**: パンダマーカーの統計を取得するコントローラーメソッドを作成
**ファイル**: `app/Http/Controllers/AdminController.php`
**挿入位置**: 298行目（ファイル末尾のクラス閉じ括弧の前）
**作業内容**:
```php
// 追加するメソッド
public function dashboard202603(Request $request)
{
    // パンダマーカーの統計
    $totalPandaScans = MarkerScan::where('marker_id', 'panda')->count();
    
    $uniquePandaUsers = MarkerScan::where('marker_id', 'panda')
        ->distinct('fingerprint')
        ->count();
    
    // 最近のパンダスキャン履歴（ページネーション）
    $recentPandaScans = MarkerScan::where('marker_id', 'panda')
        ->orderBy('scanned_at', 'desc')
        ->paginate(30, ['*'], 'panda_scans_page');
    
    // 日別パンダスキャン数（直近30日間）
    $dailyPandaScans = MarkerScan::select(DB::raw('DATE(scanned_at) as date'))
        ->selectRaw('COUNT(*) as count')
        ->where('marker_id', 'panda')
        ->where('scanned_at', '>=', now()->subDays(30))
        ->groupBy('date')
        ->orderBy('date', 'desc')
        ->paginate(15, ['*'], 'daily_panda_page');
    
    return view('admin.dashboard202603', compact(
        'totalPandaScans',
        'uniquePandaUsers',
        'recentPandaScans',
        'dailyPandaScans'
    ));
}
```
**依存関係**: なし
**所要時間**: 5分
**完了条件**: 
- ✅ メソッドが正しく追加されている
- ✅ PHP構文エラーがない（php -l で確認）
**ステータス**: ⬜ 未着手

---

#### Task 2-2: ルーティングに新規ルートを追加
**目的**: `/admin/dashboard202603` へのルートを追加
**ファイル**: `routes/web.php`
**変更箇所**: 管理画面のミドルウェアグループ内（90行目付近）
**作業内容**:
```php
// 既存の認証が必要なルート内に追加
Route::middleware('admin.auth')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/logout', [AdminController::class, 'logout'])->name('admin.logout');
    Route::post('/prizes/{id}/redeem', [AdminController::class, 'redeemPrize'])->name('admin.prizes.redeem');
    Route::get('/exchanges', [AdminController::class, 'allExchanges'])->name('admin.exchanges');
    Route::get('/scans', [AdminController::class, 'allScans'])->name('admin.scans');
    Route::get('/export', [AdminController::class, 'exportCsv'])->name('admin.export');
    
    // ↓ 追加
    Route::get('/dashboard202603', [AdminController::class, 'dashboard202603'])->name('admin.dashboard202603');
});
```
**依存関係**: Task 2-1
**所要時間**: 2分
**完了条件**: 
- ✅ ルートが正しく追加されている
- ✅ ルート名が `admin.dashboard202603` である
**ステータス**: ⬜ 未着手

---

#### Task 2-3: 新規ビューファイルの作成
**目的**: パンダ統計専用のダッシュボードページを作成
**ファイル**: `resources/views/admin/dashboard202603.blade.php`
**作業内容**:
- 既存の `admin/dashboard.blade.php` を参考に作成
- パンダ統計に特化した内容
- 統計カード: 総パンダスキャン数、ユニークユーザー数
- 最近のパンダスキャン履歴テーブル
- 日別パンダスキャン数（テーブル + Chart.js グラフ）
- ナビゲーションリンク（通常ダッシュボードへのリンク）
**依存関係**: Task 2-1, 2-2
**所要時間**: 20分
**完了条件**: 
- ✅ ファイルが作成されている
- ✅ 既存ダッシュボードと同様のスタイルが適用されている
- ✅ パンダ統計が表示される
- ✅ ページネーションが機能する
- ✅ Chart.jsグラフが表示される
**ステータス**: ⬜ 未着手

---

#### Task 2-4: 既存ダッシュボードにナビゲーションリンクを追加
**目的**: 通常ダッシュボードから202603ダッシュボードへ移動できるようにする
**ファイル**: `resources/views/admin/dashboard.blade.php`
**変更箇所**: ヘッダー部分（27-30行目付近）
**作業内容**:
```html
<!-- 変更前 -->
<div class="header">
    <h1>📊 ARスタンプラリー 管理ダッシュボード</h1>
    <a href="{{ route('admin.logout') }}" class="logout-btn">ログアウト</a>
</div>

<!-- 変更後 -->
<div class="header">
    <h1>📊 ARスタンプラリー 管理ダッシュボード</h1>
    <div style="display: flex; gap: 10px; align-items: center;">
        <a href="{{ route('admin.dashboard202603') }}" class="nav-link" style="padding: 10px 15px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; font-size: 14px;">ARスタンプラリー202603</a>
        <a href="{{ route('admin.logout') }}" class="logout-btn">ログアウト</a>
    </div>
</div>
```
**依存関係**: Task 2-2, 2-3
**所要時間**: 3分
**完了条件**: 
- ✅ ナビゲーションリンクが表示される
- ✅ リンクをクリックすると202603ダッシュボードに遷移する
**ステータス**: ⬜ 未着手

---

### Phase 3: 総合テストと検証

---

#### Task 3-1: スタンプ帳UI改善の動作確認
**目的**: ARstampRally202603.blade.phpの変更内容を確認
**テスト項目**:
1. `/stamp202603` にアクセス
2. スタンプ帳ボタンをクリック
3. ヒントボタンが表示されないことを確認
4. 未収集の通常動物が「？？？」と表示されることを確認
5. 未収集のシークレット（パンダ以外）が「シークレット」と表示されることを確認
6. 未収集のパンダが「パンダ」と表示されることを確認
7. ARマーカーで動物を捕獲
8. 収集済み動物が実際の名前で表示されることを確認
**依存関係**: Task 1-1, 1-2, 1-3
**所要時間**: 10分
**完了条件**: 
- ✅ 全てのテスト項目がパスする
- ✅ 既存機能が損なわれていない
**ステータス**: ⬜ 未着手

---

#### Task 3-2: 管理画面統計ページの動作確認
**目的**: 新規作成した管理画面の動作を確認
**テスト項目**:
1. ブラウザで `/admin/login` にアクセス
2. 管理者ログイン（username: admin, password: 0835385252）
3. 通常ダッシュボードにリダイレクトされることを確認
4. 「ARスタンプラリー202603」リンクが表示されることを確認
5. リンクをクリックして `/admin/dashboard202603` に遷移
6. パンダの統計データが表示されることを確認：
   - 総パンダスキャン数
   - ユニークユーザー数
   - 最近のパンダスキャン履歴
   - 日別パンダスキャン数
7. ページネーションが機能することを確認
8. Chart.jsグラフが表示されることを確認
9. 「通常ダッシュボード」リンクをクリックして元に戻れることを確認
10. ログアウトせずにブラウザで直接 `/admin/dashboard202603` にアクセスできることを確認
11. ログアウト後、認証なしで `/admin/dashboard202603` にアクセスできないことを確認
**依存関係**: Task 2-1, 2-2, 2-3, 2-4
**所要時間**: 15分
**完了条件**: 
- ✅ 全てのテスト項目がパスする
- ✅ 認証が正しく機能している
- ✅ デザインが統一されている
**ステータス**: ⬜ 未着手

---

## 実装の注意事項

### コード変更時のチェックリスト
- [ ] ARstampRally202603.blade.phpは6951行の大規模ファイル - 慎重に編集
- [ ] 変更前に該当行の周辺コードを確認（5-10行前後）
- [ ] 文字列リテラルの完全一致を確認（スペース、引用符含む）
- [ ] 変更後にPHPの構文エラーがないか確認（php -l コマンド）
- [ ] JavaScriptの構文エラーがないか確認（ブラウザコンソール）
- [ ] 変更箇所の行番号と内容を記録

### 実装順序
1. **Phase 1（スタンプ帳UI改善）を最初に実施** - 比較的独立している
   - Task 1-1, 1-2, 1-3を順番に実施
   - Task 3-1で動作確認
2. **Phase 2（管理画面追加）を実施** - 新規ファイル作成を含む
   - Task 2-1（Controller）→ Task 2-2（Routing）→ Task 2-3（View）→ Task 2-4（Navigation）の順
   - Task 3-2で動作確認

### リスク管理
- **バックアップ**: Git commitを事前に実施（推奨）
- **Rollback**: 問題があれば元のARstampRally.blade.phpから再コピー可能
- **影響範囲**: 
  - ARstampRally202603.blade.phpのみ（元のファイルは変更しない）
  - 管理画面は新規追加のため、既存機能への影響なし

### パフォーマンス考慮
- MarkerScanテーブルに `marker_id` のインデックスが必要（確認）
- ページネーションで一度に大量データを取得しない
- Chart.jsのデータポイント数を制限（30日分）

## 成功基準

### 必須条件 - スタンプ帳UI改善
- [ ] ヒントボタンが表示されない
- [ ] 未収集のパンダが「パンダ」と表示される
- [ ] 未収集のシークレット（パンダ以外）が「シークレット」と表示される
- [ ] 未収集の通常動物が「？？？」と表示される
- [ ] 収集済み動物は実際の名前が表示される
- [ ] 既存機能が損なわれていない

### 必須条件 - 管理画面統計ページ
- [ ] `/admin/dashboard202603` でアクセス可能
- [ ] 認証なしでアクセス不可
- [ ] パンダマーカーの統計が正しく表示される
- [ ] 既存ダッシュボードと統一感のあるデザイン
- [ ] ナビゲーションが双方向で機能する
- [ ] ページネーションが機能する
- [ ] Chart.jsグラフが表示される

### 検証方法
1. ブラウザで実際にアクセスして表示を確認
2. ブラウザの開発者ツールでコンソールエラーを確認
3. 複数のブラウザでテスト（Chrome, Firefox, Safari）
4. モバイルデバイスでの表示確認（レスポンシブデザイン）

## 次のステップ
実装フェーズへの移行

---

**ARstampRally202603のスタンプ帳UI改善と管理画面統計追加のタスク化フェーズが完了しました。実装フェーズに進んでよろしいですか？**

---

# タスク化5: ARstampRally202603 - スタンプ帳アイコン表示改善 (追加修正)

## 作成日時
2026年2月19日

## 前提
`.claude_workflow/design.md`の設計5を読み込み、設計内容を確認済み

## タスク概要
前回の実装（要件4）で未収集動物の名前表示を変更したが、アイコン表示は変更していなかった。今回は未収集の通常動物15種のアイコンを絵文字から足跡（🐾）に変更する。

**変更箇所**: ARstampRally202603.blade.phpの1箇所のみ（約3390行目）  
**変更内容**: 1行追加（`iconContent = '🐾';`）  
**影響範囲**: 未収集の通常動物15種のアイコン表示のみ  

---

## タスク一覧

### Phase 1: コード変更

#### Task 1-1: 該当箇所の確認と変更
**目的**: showStampBook関数内の通常動物未収集時のアイコン表示ロジックを変更
**ファイル**: `resources/views/ARstampRally202603.blade.php`
**作業内容**:
1. 3388-3393行目付近の以下のコードを確認：
   ```javascript
   } else {
       // 通常動物の未収集時: '？？？' を表示
       // iconContentはデフォルトのまま（stamp.icon）
       nameText = '？？？';
   }
   ```

2. 以下のように変更：
   ```javascript
   } else {
       // 通常動物の未収集時: '？？？' を表示、アイコンは足跡
       iconContent = '🐾'; // 足跡アイコンに変更
       nameText = '？？？';
   }
   ```

**変更詳細**:
- 3390行目の次の行に `iconContent = '🐾'; // 足跡アイコンに変更` を追加
- コメントを「iconContentはデフォルトのまま（stamp.icon）」から「通常動物の未収集時: '？？？' を表示、アイコンは足跡」に変更

**依存関係**: なし
**所要時間**: 5分
**完了条件**: 
- ✅ コードが正しく変更されている
- ✅ 構文エラーがない
**ステータス**: ⬜ 未着手

---

### Phase 2: 動作確認とテスト

#### Task 2-1: 未収集動物のアイコン表示確認
**目的**: 変更が正しく反映されているか確認
**テスト項目**:
1. ブラウザで `/stamp202603` にアクセス
2. スタンプ帳を開く（画面下部の「スタンプ帳」ボタンをクリック）
3. 未収集の通常動物15種のアイコンが🐾（足跡）で表示されることを確認
4. 未収集のアイコンがgrayscale効果で表示されることを確認
5. 未収集の通常動物の名前が「？？？」で表示されることを確認（変更なし）
6. 未収集のパンダのアイコンが🐾で表示されることを確認（変更なし）
7. 未収集のパンダの名前が「パンダ」で表示されることを確認（変更なし）
8. 未収集のシークレット（パンダ以外）のアイコンが🐾で表示されることを確認（変更なし）
9. 未収集のシークレット（パンダ以外）の名前が「シークレット」で表示されることを確認（変更なし）

**依存関係**: Task 1-1
**所要時間**: 5分
**完了条件**: 
- ✅ 全ての未収集動物のアイコンが🐾で表示される
- ✅ CSS効果（grayscale、text-shadow）が正しく適用される
**ステータス**: ⬜ 未着手

---

#### Task 2-2: 収集済み動物の表示確認（回帰テスト）
**目的**: 既存機能が損なわれていないか確認
**テスト項目**:
1. ARマーカーで動物を捕獲（どれか1種）
2. スタンプ帳を開く
3. 収集済み動物のアイコンが以下のいずれかで表示されることを確認：
   - スクリーンショット（画像）
   - 絵文字（🐑🦊🐧など）
4. 収集済み動物の名前が実際の名前（ひつじ、きつね、ペンギンなど）で表示されることを確認
5. 収集済み動物に `collected` クラスが適用され、色付き表示されることを確認
6. 収集日時が表示されることを確認

**依存関係**: Task 2-1
**所要時間**: 5分
**完了条件**: 
- ✅ 収集済み動物の表示が変更前と同じ
- ✅ スクリーンショットまたは絵文字が正しく表示される
**ステータス**: ⬜ 未着手

---

#### Task 2-3: 各種ブラウザでの表示確認
**目的**: 絵文字（🐾）が各ブラウザで正しく表示されるか確認
**テスト項目**:
1. Chrome（デスクトップ）で表示確認
2. Firefox（デスクトップ）で表示確認
3. Safari（iOS）で表示確認（可能であれば）
4. Edge（Windows）で表示確認（可能であれば）
5. 各ブラウザのコンソールでエラーがないか確認

**依存関係**: Task 2-2
**所要時間**: 10分
**完了条件**: 
- ✅ 全てのブラウザで足跡絵文字が正しく表示される
- ✅ コンソールエラーがない
**ステータス**: ⬜ 未着手

---

#### Task 2-4: 回帰テスト（全体機能確認）
**目的**: 既存機能が損なわれていないか包括的に確認
**テスト項目**:
1. スタンプ収集機能が正常に動作する
2. スタンプ帳の表示が正常に動作する
3. 捕獲メッセージが正常に表示される
4. LocalStorageへの保存が正常に動作する
5. ページリロード後もスタンプが保持される
6. コンプリート時のメッセージとパーティクルが表示される
7. ヒントボタンが非表示のままであることを確認（前回の実装）

**依存関係**: Task 2-3
**所要時間**: 10分
**完了条件**: 
- ✅ 全ての既存機能が正常に動作する
- ✅ 前回の実装（ヒントボタン非表示、名前表示変更）が維持されている
**ステータス**: ⬜ 未着手

---

### Phase 3: ドキュメント更新

#### Task 3-1: README.mdへの変更内容追記
**目的**: 変更内容を記録し、プロジェクトの変更履歴を更新
**ファイル**: `README.md`
**作業内容**:
1. README.mdの「変更点」セクションに以下を追記：
   ```markdown
   ### ARスタンプラリー202603 - スタンプ帳アイコン表示改善（追加修正） 20260219
   **未収集動物のアイコン表示を統一:**
   
   #### 実装内容
   - 未収集の通常動物15種のアイコンを絵文字から足跡（🐾）に変更
   - すべての未収集動物（パンダ、シークレット、通常動物）が足跡で統一表示される
   - ユーザーが「まだ見つけていない動物」であることをより明確に認識できる
   
   #### 変更ファイル
   - `resources/views/ARstampRally202603.blade.php`: showStampBook関数（1行追加）
   
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
   ```

**依存関係**: Task 2-4
**所要時間**: 5分
**完了条件**: 
- ✅ README.mdに変更内容が追記されている
- ✅ 既存の変更履歴フォーマットと統一されている
**ステータス**: ⬜ 未着手

---

## 実装の注意事項

### コード変更時のチェックリスト
- [ ] ARstampRally202603.blade.phpは6964行の大規模ファイル - 慎重に編集
- [ ] 変更前に該当行の周辺コードを確認（5-10行前後）
- [ ] 文字列リテラルの完全一致を確認（スペース、引用符含む）
- [ ] 変更後にJavaScriptの構文エラーがないか確認（ブラウザコンソール）
- [ ] 変更箇所の行番号と内容を記録

### リスク管理
- **バックアップ**: 不要（変更は1行のみ、簡単にrollback可能）
- **影響範囲**: 未収集の通常動物15種のアイコン表示のみ
- **絵文字互換性**: 🐾は既にシークレット動物で使用済み、動作確認済み

### パフォーマンス考慮
- 変更内容: 文字列代入のみ（計算処理なし）
- パフォーマンス影響: なし

## 成功基準

### 必須条件
- [ ] 未収集の通常動物15種のアイコンが🐾（足跡）で表示される
- [ ] 未収集のパンダとシークレット動物は引き続き🐾（足跡）で表示される
- [ ] 収集済みの動物はスクリーンショットまたは絵文字が表示される（変更なし）
- [ ] 動物名の表示は前回の実装のまま（パンダ/シークレット/？？？）
- [ ] 既存機能が損なわれていない
- [ ] CSSが正しく適用される（grayscale、text-shadow）
- [ ] ブラウザコンソールにエラーがない

### 検証方法
1. ブラウザで実際にアクセスして表示を確認
2. ブラウザの開発者ツールでコンソールエラーを確認
3. 複数のブラウザでテスト（Chrome, Firefox, Safari, Edge）
4. 既存機能の回帰テスト

## タスク実行順序
1. Task 1-1: コード変更（3390行目付近に1行追加）
2. Task 2-1: 未収集動物のアイコン表示確認
3. Task 2-2: 収集済み動物の表示確認（回帰テスト）
4. Task 2-3: 各種ブラウザでの表示確認
5. Task 2-4: 回帰テスト（全体機能確認）
6. Task 3-1: README.mdへの変更内容追記

## 次のステップ
実装フェーズへの移行

---

**ARstampRally202603のスタンプ帳アイコン表示改善のタスク化フェーズが完了しました。実装フェーズに進んでよろしいですか？**

---

# タスク化6: ARstampRally202603 - マーカー検出とボールヒットの統計分離

## 作成日時
2026年2月19日

## 前提
`.claude_workflow/design.md`の設計6を読み込み、設計内容を確認済み

## タスク概要
マーカー検出（ARマーカーを読み取った）とボールヒット（ボールをぶつけてゲット）を区別して統計を取り、ダッシュボードで両方の数値を表示できるようにする。

**変更箇所**: 9箇所
- データベースマイグレーション（1ファイル新規）
- MarkerScanモデル（1箇所修正）
- MarkerScanController（1メソッド修正）
- ARstampRally202603.blade.php（3箇所修正、1関数新規）
- AdminController（1メソッド修正）
- dashboard202603.blade.php（大幅修正）

**所要時間**: 合計約3-4時間

---

## タスク一覧

### Phase 1: データベース準備

#### Task 1-1: マイグレーションファイルの作成
**目的**: marker_scansテーブルにcapture_typeカラムを追加
**ファイル**: `database/migrations/2026_02_19_000000_add_capture_type_to_marker_scans_table.php`（新規）
**作業内容**:
1. artisanコマンドでマイグレーションファイルを生成:
   ```bash
   php artisan make:migration add_capture_type_to_marker_scans_table --table=marker_scans
   ```

2. マイグレーションファイルを編集:
   - up()メソッド:
     - capture_typeカラムを追加（VARCHAR(20), default: 'ball_hit'）
     - インデックス追加（capture_type, marker_id+capture_type, fingerprint+marker_id+capture_type）
     - 既存レコードのcapture_typeを'ball_hit'に設定
   - down()メソッド:
     - インデックス削除
     - capture_typeカラム削除

**依存関係**: なし
**所要時間**: 15分
**完了条件**: 
- ✅ マイグレーションファイルが作成されている
- ✅ up()とdown()が正しく実装されている
**ステータス**: ⬜ 未着手

---

#### Task 1-2: マイグレーションの実行
**目的**: データベースにcapture_typeカラムを追加
**作業内容**:
1. マイグレーションを実行:
   ```bash
   php artisan migrate
   ```

2. 実行結果を確認:
   - marker_scansテーブルにcapture_typeカラムが追加されている
   - 既存レコードのcapture_typeが'ball_hit'になっている
   - インデックスが作成されている

3. データベースを確認:
   ```bash
   php artisan db:show
   php artisan schema:dump
   ```

**依存関係**: Task 1-1
**所要時間**: 5分
**完了条件**: 
- ✅ マイグレーションが成功している
- ✅ capture_typeカラムが存在する
- ✅ 既存データのcapture_typeが'ball_hit'
**ステータス**: ⬜ 未着手

---

### Phase 2: モデルとコントローラーの修正

#### Task 2-1: MarkerScanモデルの修正
**目的**: fillable配列にcapture_typeを追加
**ファイル**: `app/Models/MarkerScan.php`
**作業内容**:
1. fillable配列にcapture_typeを追加:
   ```php
   protected $fillable = [
       'session_id',
       'fingerprint',
       'marker_id',
       'marker_name',
       'scan_count',
       'capture_type',  // 【新規】
       'scanned_at',
       'user_agent',
       'ip_address',
       'device_info'
   ];
   ```

**依存関係**: Task 1-2
**所要時間**: 3分
**完了条件**: 
- ✅ fillable配列にcapture_typeが追加されている
**ステータス**: ⬜ 未着手

---

#### Task 2-2: MarkerScanControllerの修正
**目的**: record()メソッドでcaptureTypeを受け取り、処理を追加
**ファイル**: `app/Http/Controllers/MarkerScanController.php`
**作業内容**:
1. バリデーションルールにcaptureTypeを追加:
   ```php
   'captureType' => 'nullable|string|in:marker_scan,ball_hit',
   ```

2. captureTypeを取得（デフォルト: 'ball_hit'）:
   ```php
   $captureType = $validated['captureType'] ?? 'ball_hit';
   ```

3. marker_scanの場合、当日の重複チェックを追加:
   ```php
   if ($captureType === 'marker_scan') {
       $today = now()->toDateString();
       $existingToday = MarkerScan::where('fingerprint', $fingerprint)
           ->where('marker_id', $markerId)
           ->where('capture_type', 'marker_scan')
           ->whereDate('scanned_at', $today)
           ->exists();
       
       if ($existingToday) {
           return response()->json([...]);
       }
   }
   ```

4. scan_countの集計をcapture_type別に変更:
   ```php
   $totalScans = MarkerScan::where(...)
       ->where('marker_id', $markerId)
       ->where('capture_type', $captureType)  // 【追加】
       ->count() + 1;
   ```

5. MarkerScan::create()にcapture_typeを追加:
   ```php
   'capture_type' => $captureType,
   ```

**依存関係**: Task 2-1
**所要時間**: 20分
**完了条件**: 
- ✅ captureTypeを受け取り、DBに保存される
- ✅ marker_scanの重複チェックが機能する
- ✅ タイプ別にscan_countが集計される
**ステータス**: ⬜ 未着手

---

### Phase 3: フロントエンド（ARstampRally202603.blade.php）の修正

#### Task 3-1: recordMarkerDetection関数の作成
**目的**: マーカー検出を記録する新関数を作成
**ファイル**: `resources/views/ARstampRally202603.blade.php`
**配置場所**: recordMarkerScan関数の直前（約2788行目）
**作業内容**:
1. 2788行目付近に新関数を挿入:
   ```javascript
   // マーカー検出を記録（1日1回のみ、未捕獲のみ）
   async function recordMarkerDetection(markerId, markerName) {
       // 当日の記録があるかLocalStorageでチェック
       const today = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
       const cacheKey = `marker-scan-cache-202603-${markerId}-${today}`;
       
       // キャッシュ確認
       const cached = localStorage.getItem(cacheKey);
       if (cached) {
           console.log('✓ Marker detection already recorded today:', markerId);
           return; // 当日既に記録済み
       }
       
       // マーカースキャンを記録（capture_type: 'marker_scan'）
       try {
           await recordMarkerScan(markerId, markerName, 'marker_scan');
           
           // LocalStorageに記録（当日のキャッシュ）
           localStorage.setItem(cacheKey, JSON.stringify({
               scanned: true,
               timestamp: new Date().toISOString()
           }));
           
           console.log('✓ Marker detection recorded:', markerId);
       } catch (error) {
           console.error('Error recording marker detection:', error);
       }
   }
   ```

**依存関係**: なし
**所要時間**: 10分
**完了条件**: 
- ✅ recordMarkerDetection関数が作成されている
- ✅ LocalStorageキャッシュロジックが実装されている
**ステータス**: ⬜ 未着手

---

#### Task 3-2: recordMarkerScan関数の修正
**目的**: captureTypeパラメータを追加
**ファイル**: `resources/views/ARstampRally202603.blade.php`
**行数**: 2789行目
**作業内容**:
1. 関数シグネチャを修正:
   ```javascript
   // 変更前
   async function recordMarkerScan(markerId, markerName) {
   
   // 変更後
   async function recordMarkerScan(markerId, markerName, captureType = 'ball_hit') {
   ```

2. body JSONにcaptureTypeを追加:
   ```javascript
   body: JSON.stringify({
       markerId: markerId,
       markerName: markerName,
       fingerprint: fingerprint,
       deviceInfo: deviceInfo,
       captureType: captureType,  // 【新規】
       scannedAt: new Date().toISOString()
   })
   ```

3. ログ出力を修正:
   ```javascript
   console.log('✓ Marker scan recorded:', markerId, 'Type:', captureType, 'Total scans:', data.totalScans);
   ```

**依存関係**: Task 3-1
**所要時間**: 10分
**完了条件**: 
- ✅ captureTypeパラメータが追加されている
- ✅ デフォルト値'ball_hit'が設定されている
- ✅ API送信時にcaptureTypeが含まれている
**ステータス**: ⬜ 未着手

---

#### Task 3-3: markerFoundイベントリスナーの修正
**目的**: 未捕獲時にマーカー検出を記録
**ファイル**: `resources/views/ARstampRally202603.blade.php`
**行数**: 731-773行目（markerFoundイベントリスナー内）
**作業内容**:
1. 760行目付近（「捕獲されていない場合のみ、モデルを表示」の直後）に追加:
   ```javascript
   // 捕獲されていない場合のみ、モデルを表示
   console.log('  → Not captured - showing model with anime01');
   el.setAttribute('visible', 'true');
   markerVisible = true;
   
   // anime01を自動再生
   if (action01) {
       action01.reset();
       action01.play();
       currentAnimation = 1;
       console.log('  anime01 started');
   }
   
   // 【新規】マーカー検出を記録（未捕獲の場合のみ、1日1回）
   try {
       if (typeof recordMarkerDetection === 'function') {
           const name = (STAMPS[stampId] && STAMPS[stampId].name) ? STAMPS[stampId].name : stampId;
           recordMarkerDetection(stampId, name);
       }
   } catch (e) {
       console.warn('recordMarkerDetection failed', e);
   }
   
   console.log('==================================================');
   ```

**注意**: 
- STAMPS定義の確認が必要（stampIdからnameを取得）
- stamp変数がスコープ内にあるか確認

**依存関係**: Task 3-2
**所要時間**: 15分
**完了条件**: 
- ✅ markerFoundイベント内でrecordMarkerDetection呼び出しが追加されている
- ✅ 未捕獲の場合のみ呼び出される
- ✅ エラーハンドリングが実装されている
**ステータス**: ⬜ 未着手

---

#### Task 3-4: collectStamp関数の修正
**目的**: recordMarkerScan呼び出し時にcaptureTypeを指定
**ファイル**: `resources/views/ARstampRally202603.blade.php`
**行数**: 143行目
**作業内容**:
1. 143行目を修正:
   ```javascript
   // 変更前
   try { recordMarkerScan(stampId, name); } catch (e) { console.warn('recordMarkerScan failed', e); }
   
   // 変更後
   try { recordMarkerScan(stampId, name, 'ball_hit'); } catch (e) { console.warn('recordMarkerScan failed', e); }
   ```

**依存関係**: Task 3-2
**所要時間**: 3分
**完了条件**: 
- ✅ recordMarkerScan呼び出し時にcaptureType: 'ball_hit'が指定されている
**ステータス**: ⬜ 未着手

---

### Phase 4: ダッシュボードの拡張

#### Task 4-1: AdminController::dashboard202603メソッドの拡張
**目的**: 全動物の両タイプ別統計を取得
**ファイル**: `app/Http/Controllers/AdminController.php`
**行数**: 298-330行目
**作業内容**:
1. 動物リストの定義を追加:
   ```php
   $animals = [
       'sheep' => 'ひつじ',
       'fox' => 'きつね',
       // ... 全20種
   ];
   ```

2. 各動物の統計を取得するループを追加:
   ```php
   $animalStats = [];
   foreach ($animals as $markerId => $markerName) {
       // マーカー検出回数
       $markerScanCount = MarkerScan::where('marker_id', $markerId)
           ->where('capture_type', 'marker_scan')
           ->count();
       
       // ボールヒット回数
       $ballHitCount = MarkerScan::where('marker_id', $markerId)
           ->where('capture_type', 'ball_hit')
           ->count();
       
       // ... (詳細は設計ドキュメント参照)
   }
   ```

3. 最近のスキャン履歴（全動物、両タイプ）を取得:
   ```php
   $recentScans = MarkerScan::orderBy('scanned_at', 'desc')
       ->paginate(30, ['*'], 'recent_scans_page');
   ```

4. 日別統計（タイプ別）を取得:
   ```php
   $dailyStats = MarkerScan::select(DB::raw('DATE(scanned_at) as date'))
       ->selectRaw('capture_type')
       ->selectRaw('COUNT(*) as count')
       ->where('scanned_at', '>=', now()->subDays(30))
       ->groupBy('date', 'capture_type')
       ->orderBy('date', 'desc')
       ->get()
       ->groupBy('date');
   ```

5. ビューに渡す変数を変更:
   ```php
   return view('admin.dashboard202603', compact(
       'animalStats',
       'recentScans',
       'dailyStats'
   ));
   ```

**注意**: パンダのみ表示する場合は、動物リストを['panda' => 'パンダ']のみにする

**依存関係**: Task 2-2
**所要時間**: 30分
**完了条件**: 
- ✅ 全動物の統計が取得される
- ✅ タイプ別にカウントされる
- ✅ ユニークユーザー数が計算される
**ステータス**: ⬜ 未着手

---

#### Task 4-2: dashboard202603.blade.phpの大幅修正
**目的**: 動物別統計テーブル、タイプ別履歴、グラフを表示
**ファイル**: `resources/views/admin/dashboard202603.blade.php`
**作業内容**:
1. 動物別統計テーブルのセクションを追加:
   ```html
   <div class="stats-section">
       <h2>動物別統計</h2>
       <table class="stats-table">
           <thead>
               <tr>
                   <th>動物名</th>
                   <th>マーカー検出回数</th>
                   <th>ボールヒット回数</th>
                   <th>合計</th>
                   <th>ユニークユーザー数</th>
                   <th>最終スキャン</th>
               </tr>
           </thead>
           <tbody>
               @foreach($animalStats as $stat)
               <tr>
                   <td>{{ $stat['marker_name'] }}</td>
                   <td class="marker-scan">{{ $stat['marker_scan_count'] }}</td>
                   <td class="ball-hit">{{ $stat['ball_hit_count'] }}</td>
                   <td class="total">{{ $stat['total_count'] }}</td>
                   <td>{{ $stat['unique_users'] }}</td>
                   <td>{{ $stat['last_scan'] ? $stat['last_scan']->format('Y-m-d H:i') : '-' }}</td>
               </tr>
               @endforeach
           </tbody>
       </table>
   </div>
   ```

2. 最近のスキャン履歴セクションを修正（タイプ表示追加）:
   ```html
   <td>
       <span class="badge {{ $scan->capture_type === 'marker_scan' ? 'marker' : 'ball' }}">
           {{ $scan->capture_type === 'marker_scan' ? 'マーカー検出' : 'ボールヒット' }}
       </span>
   </td>
   ```

3. CSSスタイルを追加:
   ```css
   .marker-scan { color: #3498db; font-weight: bold; }
   .ball-hit { color: #e74c3c; font-weight: bold; }
   .badge.marker { background-color: #3498db; color: white; padding: 4px 8px; border-radius: 4px; }
   .badge.ball { background-color: #e74c3c; color: white; padding: 4px 8px; border-radius: 4px; }
   ```

4. Chart.jsで積み上げ棒グラフを追加:
   ```javascript
   const dailyData = @json($dailyStats);
   const dates = Object.keys(dailyData).reverse();
   const markerScanData = dates.map(date => {
       const dayData = dailyData[date].find(d => d.capture_type === 'marker_scan');
       return dayData ? dayData.count : 0;
   });
   const ballHitData = dates.map(date => {
       const dayData = dailyData[date].find(d => d.capture_type === 'ball_hit');
       return dayData ? dayData.count : 0;
   });
   
   const ctx = document.getElementById('dailyChart').getContext('2d');
   new Chart(ctx, {
       type: 'bar',
       data: {
           labels: dates,
           datasets: [
               {
                   label: 'マーカー検出',
                   data: markerScanData,
                   backgroundColor: 'rgba(54, 162, 235, 0.6)'
               },
               {
                   label: 'ボールヒット',
                   data: ballHitData,
                   backgroundColor: 'rgba(255, 99, 132, 0.6)'
               }
           ]
       },
       options: {
           responsive: true,
           scales: {
               x: { stacked: true },
               y: { stacked: true, beginAtZero: true }
           }
       }
   });
   ```

**依存関係**: Task 4-1
**所要時間**: 45分
**完了条件**: 
- ✅ 動物別統計テーブルが表示される
- ✅ タイプ別の色分け表示がされる
- ✅ 積み上げ棒グラフが表示される
- ✅ ページネーションが機能する
**ステータス**: ⬜ 未着手

---

### Phase 5: テストと動作確認

#### Task 5-1: マーカー検出の記録テスト
**目的**: markerFoundイベント時に正しく記録されるか確認
**テスト項目**:
1. ARマーカーをカメラで検出（未捕獲の動物）
2. ブラウザコンソールで「✓ Marker detection recorded」が表示される
3. LocalStorageに当日のキャッシュが作成される（`marker-scan-cache-202603-{markerId}-{date}`）
4. 同じマーカーを再検出しても、「already recorded today」と表示される
5. データベースのmarker_scansテーブルを確認:
   - capture_type: 'marker_scan'
   - marker_id: 正しいID
   - fingerprint: 正しい値

**依存関係**: Task 3-1, 3-2, 3-3, 2-2
**所要時間**: 15分
**完了条件**: 
- ✅ マーカー検出時に記録される
- ✅ 当日の重複が防止される
- ✅ DBレコードが正しく作成される
**ステータス**: ⬜ 未着手

---

#### Task 5-2: ボールヒットの記録テスト
**目的**: ボールヒット時に正しく記録されるか確認
**テスト項目**:
1. ボールを投げて動物にヒット（新規ゲット）
2. ブラウザコンソールで「✓ Marker scan recorded」と「Type: ball_hit」が表示される
3. LocalStorageにスタンプが記録される
4. データベースのmarker_scansテーブルを確認:
   - capture_type: 'ball_hit'
   - marker_id: 正しいID
   - fingerprint: 正しい値
5. 既にゲット済みの動物の場合、記録されない

**依存関係**: Task 3-2, 3-4, 2-2
**所要時間**: 10分
**完了条件**: 
- ✅ ボールヒット時に記録される
- ✅ capture_type: 'ball_hit'が設定される
- ✅ 新規ゲット時のみ記録される
**ステータス**: ⬜ 未着手

---

#### Task 5-3: ダッシュボード表示テスト
**目的**: dashboard202603で統計が正しく表示されるか確認
**テスト項目**:
1. `/admin/login`でログイン
2. `/admin/dashboard202603`にアクセス
3. 動物別統計テーブルが表示される:
   - 各動物のマーカー検出回数が表示される
   - 各動物のボールヒット回数が表示される
   - 合計、ユニークユーザー数、最終スキャン日時が表示される
4. 最近のスキャン履歴が表示される:
   - タイプ（マーカー検出 / ボールヒット）が色分けされて表示される
5. 日別統計グラフが表示される:
   - 積み上げ棒グラフが表示される
   - 2つのタイプが色分けされている
6. ページネーションが機能する

**依存関係**: Task 4-1, 4-2
**所要時間**: 15分
**完了条件**: 
- ✅ 統計が正しく表示される
- ✅ タイプ別に集計されている
- ✅ グラフが表示される
**ステータス**: ⬜ 未着手

---

#### Task 5-4: 回帰テスト（既存機能確認）
**目的**: 既存機能が損なわれていないか確認
**テスト項目**:
1. スタンプ収集機能が正常に動作する
2. スタンプ帳表示が正常に動作する
3. 捕獲メッセージが正常に表示される
4. LocalStorageへの保存が正常に動作する
5. ページリロード後もスタンプが保持される
6. 既存の管理画面（/admin/dashboard）が正常に動作する
7. CSV出力機能が正常に動作する

**依存関係**: Task 5-1, 5-2, 5-3
**所要時間**: 20分
**完了条件**: 
- ✅ 全ての既存機能が正常に動作する
- ✅ エラーがない
**ステータス**: ⬜ 未着手

---

### Phase 6: ドキュメント更新

#### Task 6-1: README.mdへの変更内容追記
**目的**: 変更内容を記録し、プロジェクトの変更履歴を更新
**ファイル**: `README.md`
**作業内容**:
1. README.mdの「変更点」セクションに以下を追記:
   ```markdown
   ### ARスタンプラリー202603 - マーカー検出とボールヒットの統計分離 20260219
   **ARマーカー検出とボールヒットを区別して統計を記録:**
   
   #### 実装内容
   1. **データベース拡張**
      - marker_scansテーブルにcapture_typeカラムを追加
      - 'marker_scan'（マーカー検出）と'ball_hit'（ボールヒット）を区別
      - 既存データは'ball_hit'として扱う
   
   2. **マーカー検出の記録**
      - markerFoundイベント時に記録（未捕獲の動物のみ）
      - 同じ端末・同じマーカー・同じ日付の重複は記録しない
      - LocalStorageキャッシュで当日の重複を防止
   
   3. **ボールヒットの記録**
      - 既存のrecordMarkerScan関数を拡張
      - capture_type: 'ball_hit'を明示的に指定
   
   4. **ダッシュボード拡張**
      - admin/dashboard202603で全動物の統計を表示
      - 各動物ごとにマーカー検出回数とボールヒット回数を表示
      - タイプ別の色分け表示
      - 積み上げ棒グラフで日別統計を表示
   
   #### 変更・追加ファイル
   - `database/migrations/2026_02_19_000000_add_capture_type_to_marker_scans_table.php`: 新規マイグレーション
   - `app/Models/MarkerScan.php`: fillable配列にcapture_type追加
   - `app/Http/Controllers/MarkerScanController.php`: record()メソッド拡張
   - `resources/views/ARstampRally202603.blade.php`: recordMarkerDetection関数追加、4箇所修正
   - `app/Http/Controllers/AdminController.php`: dashboard202603()メソッド拡張
   - `resources/views/admin/dashboard202603.blade.php`: 動物別統計テーブル、タイプ別履歴、グラフ追加
   
   #### 技術的詳細
   - markerFoundイベント時にrecordMarkerDetection呼び出し
   - LocalStorageキャッシュキー: `marker-scan-cache-202603-{markerId}-{date}`
   - サーバー側でも日付ベースの重複チェック実装
   - タイプ別にscan_countを集計
   
   #### 動作確認済み項目
   - ✅ マーカー検出時にmarker_scansに記録される（capture_type: 'marker_scan'）
   - ✅ ボールヒット時にmarker_scansに記録される（capture_type: 'ball_hit'）
   - ✅ 同じ日に同じマーカーを再検出しても、カウントアップされない
   - ✅ 捕獲済みのマーカーは記録されない
   - ✅ ダッシュボードで各動物のマーカー検出回数とボールヒット回数が表示される
   - ✅ 既存機能への影響なし
   
   #### 設計ドキュメント
   - 要件定義6: `.claude_workflow/requirements.md` (要件定義6セクション)
   - 設計6: `.claude_workflow/design.md` (設計6セクション)
   - タスク化6: `.claude_workflow/tasks.md` (タスク化6セクション)
   ```

**依存関係**: Task 5-4
**所要時間**: 10分
**完了条件**: 
- ✅ README.mdに変更内容が追記されている
- ✅ 既存の変更履歴フォーマットと統一されている
**ステータス**: ⬜ 未着手

---

## 実装の注意事項

### コード変更時のチェックリスト
- [ ] ARstampRally202603.blade.phpは6964行の大規模ファイル - 慎重に編集
- [ ] 変更前に該当行の周辺コードを確認（10行前後）
- [ ] 文字列リテラルの完全一致を確認（スペース、引用符含む）
- [ ] 変更後にJavaScriptの構文エラーがないか確認（ブラウザコンソール）
- [ ] 変更後にPHPの構文エラーがないか確認（php -l コマンド）
- [ ] 変更箇所の行番号と内容を記録

### 実装順序
1. **Phase 1（データベース準備）を最初に実施** - 土台を作る
   - Task 1-1, 1-2を順番に実施
2. **Phase 2（モデルとコントローラー）を実施** - バックエンドロジック
   - Task 2-1, 2-2を順番に実施
3. **Phase 3（フロントエンド）を実施** - ARマーカー検出の記録
   - Task 3-1, 3-2, 3-3, 3-4を順番に実施
4. **Phase 4（ダッシュボード）を実施** - 統計表示
   - Task 4-1, 4-2を順番に実施
5. **Phase 5（テスト）を実施** - 動作確認
   - Task 5-1, 5-2, 5-3, 5-4を順番に実施
6. **Phase 6（ドキュメント）を実施** - 記録
   - Task 6-1を実施

### リスク管理
- **バックアップ**: Git commitを事前に実施（推奨）
- **Rollback**: 
  - マイグレーションのdown()メソッドで戻せる
  - ARstampRally202603.blade.phpの変更箇所は4箇所のみ
- **影響範囲**: 
  - 新機能追加が主体、既存機能への影響は最小限
  - recordMarkerScan関数はデフォルト引数で後方互換性を保つ

### パフォーマンス考慮
- LocalStorageキャッシュで重複チェックのAPI呼び出しを削減
- capture_typeにインデックスを追加（検索パフォーマンス向上）
- ダッシュボードのクエリ最適化（N+1問題を回避）

### セキュリティ考慮
- CSRF保護（既存のrecordMarkerScan関数と同様）
- バリデーション（captureTypeのin:ルール）
- SQLインジェクション対策（Eloquent使用）

## 成功基準

### 必須条件
- [ ] マーカー検出時にmarker_scansに記録される（capture_type: 'marker_scan'）
- [ ] ボールヒット時にmarker_scansに記録される（capture_type: 'ball_hit'）
- [ ] 同じ日に同じマーカーを再検出しても、カウントアップされない
- [ ] 捕獲済みのマーカーは記録されない
- [ ] ダッシュボードで各動物のマーカー検出回数とボールヒット回数が表示される
- [ ] 既存のmarker_scansデータが正しく集計される
- [ ] 既存機能が損なわれない
- [ ] ブラウザコンソールにエラーがない
- [ ] PHP構文エラーがない

### 望ましい条件
- [ ] ダッシュボードでソート機能が動作する
- [ ] グラフが2つのタイプ別に表示される
- [ ] LocalStorageキャッシュでAPI呼び出しが削減される
- [ ] ページネーションがスムーズに動作する

## タスク実行順序
1. Task 1-1（マイグレーション作成）
2. Task 1-2（マイグレーション実行）
3. Task 2-1（MarkerScanモデル修正）
4. Task 2-2（MarkerScanController修正）
5. Task 3-1（recordMarkerDetection作成）
6. Task 3-2（recordMarkerScan修正）
7. Task 3-3（markerFound修正）
8. Task 3-4（collectStamp修正）
9. Task 4-1（AdminController修正）
10. Task 4-2（dashboard202603.blade.php修正）
11. Task 5-1（マーカー検出テスト）
12. Task 5-2（ボールヒットテスト）
13. Task 5-3（ダッシュボードテスト）
14. Task 5-4（回帰テスト）
15. Task 6-1（README.md更新）

## 次のステップ
実装フェーズへの移行

---

**ARstampRally202603のマーカー検出とボールヒット統計分離のタスク化フェーズが完了しました。実装フェーズに進んでよろしいですか？**