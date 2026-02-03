# 設計: WebVR中心暗転体験アプリ

## 作成日時
2026年2月3日

## 前提
`.claude_workflow/requirements_center_dark.md`を読み込み、要件を確認済み

## 既存実装の流用

### ベースとなる実装
- **参照元**: `/vr-tunnel`（視野狭窄アプリ）
- **流用方針**: ファイル構造とシェーダーの基本構造を流用し、ロジックを反転

### 主な変更点
| 項目 | 視野狭窄アプリ | 中心暗転アプリ |
|------|---------------|---------------|
| URL | `/vr-tunnel` | `/vr-center-dark` |
| エフェクト | 中心=透明、外側=黒 | 中心=黒、外側=透明 |
| コンポーネント名 | `tunnel-vision-overlay` | `center-dark-overlay` |
| JSファイル | `tunnel-vision.js` | `center-dark.js` |
| Bladeファイル | `vr-tunnel.blade.php` | `vr-center-dark.blade.php` |

## アーキテクチャ概要

### システム構成
```
┌─────────────────────────────────────────┐
│  Laravel Routing (/vr-center-dark)      │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  Blade View (vr-center-dark.blade.php)  │
│  ┌─────────────────────────────────┐   │
│  │  A-Frame Scene                   │   │
│  │  ├─ <a-sky> (360度画像)         │   │
│  │  ├─ <a-camera>                   │   │
│  │  └─ Center Dark Layer            │   │
│  │     (カスタムエンティティ)        │   │
│  └─────────────────────────────────┘   │
└─────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  カスタムコンポーネント                  │
│  - center-dark-overlay                  │
│    (中心暗転エフェクト制御)              │
└─────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  カスタムシェーダー（反転ロジック）      │
│  - Vertex Shader (位置計算)             │
│  - Fragment Shader (中心暗転描画)       │
└─────────────────────────────────────────┘
```

## シェーダー設計の核心：ロジック反転

### 視野狭窄アプリのロジック（参考）
```glsl
// 中心からの角度に基づいて透明度を計算
float alpha = smoothstep(innerRadius, outerRadius, normalizedDistance);
// 結果: 中心=0（透明）、外側=1（黒）
gl_FragColor = vec4(0.0, 0.0, 0.0, alpha * opacity);
```

### 中心暗転アプリの新ロジック（反転）
```glsl
// 中心からの角度に基づいて透明度を計算（逆転）
float alpha = smoothstep(innerRadius, outerRadius, normalizedDistance);
// alphaを反転: 中心=1（黒）、外側=0（透明）
alpha = 1.0 - alpha;
// 結果: 中心=黒、外側=透明
gl_FragColor = vec4(0.0, 0.0, 0.0, alpha * opacity);
```

### 重要な変更点
- **反転処理**: `alpha = 1.0 - alpha;` を追加するだけ
- **パラメータの意味が逆転**:
  - `innerRadius`: 完全に黒い中心領域
  - `outerRadius`: 完全に透明になる外側領域

## ファイル構成

### 新規作成ファイル
```
resources/views/vr-center-dark.blade.php    # メインビュー
public/js/vr-center-dark/
  └─ center-dark.js                         # カスタムコンポーネント
```

### 既存ファイルの変更
```
routes/web.php                              # ルート追加
```

## 実装詳細

### 1. Laravel ルーティング
```php
// routes/web.php に追加
Route::get('/vr-center-dark', function () {
    return view('vr-center-dark');
})->name('vr.center.dark');
```

### 2. Bladeビュー構造
```html
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VR 中心暗転体験</title>
    <script src="https://aframe.io/releases/1.4.0/aframe.min.js"></script>
    <script src="/js/vr-center-dark/center-dark.js"></script>
</head>
<body>
    <a-scene>
        <a-sky src="/cg/R0010034.JPG" rotation="0 -90 0"></a-sky>
        <a-entity id="camera-rig">
            <a-camera>
                <a-entity center-dark-overlay></a-entity>
            </a-camera>
        </a-entity>
    </a-scene>
</body>
</html>
```

### 3. カスタムコンポーネント設計

#### コンポーネント名: `center-dark-overlay`

**責務**:
- カメラに追従する球体メッシュの生成
- カスタムシェーダーの適用
- 時間経過による暗転範囲の動的変化
- VRモード開始時のタイマー開始
- 50秒ループでの連続再生

#### 時間制御フロー
```
1. ページロード
   ↓
2. VRモード開始を待機（enter-vrイベントリッスン）
   ↓
3. VRモード開始時にタイマースタート（startTime記録）
   ↓
4. tick()で毎フレーム経過時間を計算
   ↓
5. 経過時間を50秒で割った余りを取得（ループ処理）
   ↓
6. 現在のステージと次のステージを特定
   ↓
7. ステージ間で線形補間（lerp）
   ↓
8. シェーダーパラメータ（innerRadius, outerRadius）を更新
   ↓
9. 4に戻る（無限ループ）
```

#### パラメータ補間計算
```javascript
// 例: 15秒経過時（10～20秒の段階）
elapsedTime = 15
loopTime = 15 % 50 = 15

// 現在のステージを特定
currentStage = 1 (time: 10, innerRadius: 0.00)
nextStage = 2 (time: 20, innerRadius: 0.11)

// ステージ内の進行度を計算
progress = (15 - 10) / (20 - 10) = 0.5

// 線形補間
innerRadius = lerp(0.00, 0.11, 0.5) = 0.055
outerRadius = lerp(0.00, 0.15, 0.5) = 0.075
```

#### ステージ定義
```javascript
const stages = [
  { time: 0,  innerRadius: 0.00, outerRadius: 0.00 },  // 0～10秒: 暗転なし
  { time: 10, innerRadius: 0.00, outerRadius: 0.00 },  // ステージ境界
  { time: 20, innerRadius: 0.11, outerRadius: 0.15 },  // 10～20秒: 視野角20度
  { time: 30, innerRadius: 0.17, outerRadius: 0.23 },  // 20～30秒: 視野角30度
  { time: 40, innerRadius: 0.22, outerRadius: 0.30 },  // 30～40秒: 視野角40度
  { time: 50, innerRadius: 0.28, outerRadius: 0.36 }   // 40～50秒: 視野角50度
];
```

**プロパティ（動的変化）**:
```javascript
{
  // 時間経過で動的に変化するパラメータ
  // 各時間帯のパラメータ定義
  stages: [
    { time: 0,  innerRadius: 0.00, outerRadius: 0.00 },  // 暗転なし
    { time: 10, innerRadius: 0.00, outerRadius: 0.00 },  // 暗転なし
    { time: 20, innerRadius: 0.11, outerRadius: 0.15 },  // 視野角20度
    { time: 30, innerRadius: 0.17, outerRadius: 0.23 },  // 視野角30度
    { time: 40, innerRadius: 0.22, outerRadius: 0.30 },  // 視野角40度
    { time: 50, innerRadius: 0.28, outerRadius: 0.36 }   // 視野角50度
  ],
  loopDuration: 50,     // 50秒でループ
  sphereRadius: 0.4,    // 球体の半径（カメラに近い位置）
  opacity: 1.0,         // 黒い部分の不透明度（完全な黒）
  segments: 48          // 球体のセグメント数
}
```

**メソッド**:
- `init()`: 初期化、球体ジオメトリとシェーダー作成、VRイベントリスナー登録
- `tick(time, deltaTime)`: 毎フレーム呼ばれる更新処理、時間経過で暗転範囲を変化
- `onEnterVR()`: VRモード開始時にタイマースタート
- `updateParameters(elapsedTime)`: 経過時間から現在の暗転範囲を計算
- `lerp(a, b, t)`: 線形補間関数

### 4. シェーダー設計

#### Vertex Shader（頂点シェーダー）
```glsl
varying vec3 vPosition;

void main() {
    vPosition = position;
    gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
}
```
※視野狭窄アプリと同じ

#### Fragment Shader（フラグメントシェーダー）- 反転ロジック
```glsl
uniform float innerRadius;
uniform float outerRadius;
uniform float opacity;

varying vec3 vPosition;

void main() {
    // カメラからの方向ベクトル（正規化）
    vec3 direction = normalize(vPosition);
    
    // 前方向（Z軸負方向）との角度を計算
    float angle = acos(-direction.z);
    
    // 正規化された距離 (0.0 = 中心, 1.0 = 外側)
    float normalizedDistance = angle / 3.14159;
    
    // innerRadius～outerRadiusの範囲でスムーズに補間
    float alpha = smoothstep(innerRadius, outerRadius, normalizedDistance);
    
    // ★重要: alphaを反転（視野狭窄と逆）
    alpha = 1.0 - alpha;
    
    // 黒色で、alphaで透明度を制御
    // 中心（innerRadius以内）は不透明（alpha=1）→ 黒
    // 外側（outerRadius以上）は透明（alpha=0）→ 360度画像が見える
    gl_FragColor = vec4(0.0, 0.0, 0.0, alpha * opacity);
}
```

**計算ロジック**:
1. 各ピクセルの位置からカメラ方向を正規化
2. 前方向（視線方向）との角度を`acos`で計算
3. `smoothstep`関数で滑らかなグラデーション
4. **`1.0 - alpha`で反転** ← これが核心
5. 中心は黒、外側は透明

### 5. 描画順序とレンダリング設定

**重要なThree.js設定**:
```javascript
material.transparent = true;
material.side = THREE.BackSide;  // 球体の内側を描画
material.depthWrite = false;     // 深度バッファを書き込まない
material.depthTest = false;      // 深度テストをスキップ
```
※視野狭窄アプリと同じ

## パラメータ調整方針

### 視野角とパラメータの関係
- **innerRadius: 0.22** → 視野角約40度（完全に黒い中心）
- **outerRadius: 0.30** → 視野角約55度（完全に透明）
- **グラデーション幅**: 15度（40度～55度の範囲で滑らかに遷移）

### パラメータの微調整（必要に応じて）
```javascript
// より狭い暗転領域の場合
innerRadius: 0.10  // 視野角約20度
outerRadius: 0.15  // 視野角約30度

// より広い暗転領域の場合
innerRadius: 0.20  // 視野角約40度
outerRadius: 0.30  // 視野角約55度
```

## パフォーマンス最適化

### 視野狭窄アプリと同じ設定
- **セグメント数**: 48（滑らかさとパフォーマンスのバランス）
- **半径**: 0.4m（最適な視覚効果）
- **シェーダー**: 軽量な計算（反転処理は`1.0 - alpha`のみ追加）

### 追加の最適化
- 反転処理（`1.0 - alpha`）は非常に軽量で、パフォーマンスへの影響はほぼゼロ

## 問題点と対策

### 視野狭窄アプリと同じ対策
1. **球体の見切れや歪み** → 球体半径を0.4mに設定
2. **エッジのジャギー** → smoothstepで滑らかな遷移
3. **VRモードでの表示位置ずれ** → カメラの直接の子要素として配置
4. **パフォーマンス低下** → セグメント数48で最適化

### 中心暗転特有の考慮事項
- **暗転範囲が適切か**: 実装後に視覚的に確認し、必要に応じてパラメータ調整

## テスト計画

### デスクトップブラウザテスト
1. Chrome/Edgeで画面表示確認
2. マウスドラッグでの視点変更確認
3. 中心部が暗転、周辺部が明瞭に表示されることを確認

### VRデバイステスト
1. Pico4 Enterpriseでのアクセス
2. VRモード起動確認
3. 頭の動きに追従して暗転領域が移動することを確認
4. フレームレート計測（目標60fps以上）

## 実装優先順位

### Phase 1: 基本実装（MVP）
1. ルーティング追加
2. JSディレクトリ作成
3. Bladeビュー作成（視野狭窄アプリをコピー＆修正）
4. カスタムコンポーネント実装（視野狭窄アプリをコピー＆反転ロジック追加）
5. 動作確認

### Phase 2: 調整・最適化
1. パラメータの微調整
2. デスクトップブラウザテスト
3. VRデバイステスト

## 依存関係

### 外部ライブラリ
- **A-Frame**: CDN経由で読み込み（1.4.0）
- **Three.js**: A-Frameに含まれる

### 内部依存
- Laravel routing（既存）
- Blade templating（既存）
- public/cg/R0010034.JPG（既存）

## 実装の効率化

### コードの流用
1. **Bladeビュー**: `vr-tunnel.blade.php`をコピーしてタイトルとスクリプトパスを変更
2. **JSコンポーネント**: `tunnel-vision.js`をコピーして以下を変更:
   - コンポーネント名: `tunnel-vision-overlay` → `center-dark-overlay`
   - シェーダーに`alpha = 1.0 - alpha;`を追加
   - パラメータ調整（innerRadius, outerRadius）

### 変更箇所のまとめ
```
■ 必須変更
1. コンポーネント名の変更
2. Fragment Shaderに反転処理追加
3. パラメータ値の調整

■ その他の変更
- ファイル名、ディレクトリ名
- コメント内容
```

## 次のステップ
タスク化フェーズへの移行

---

**設計フェーズが完了しました。タスク化フェーズに進んでよろしいですか？**
