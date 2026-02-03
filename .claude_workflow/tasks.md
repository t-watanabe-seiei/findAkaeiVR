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
- 空の`<a-scene>`タグ
- メタタグ（viewport等）
**依存関係**: Task 1（ルーティング）
**所要時間**: 10分
**完了条件**: ページが表示され、A-Frameが読み込まれる
**ステータス**: ⬜ 未着手

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
