# 設計: WebVR視野狭窄体験アプリ

## 作成日時
2026年2月3日

## 前提
`.claude_workflow/requirements.md`を読み込み、要件を確認済み

## アーキテクチャ概要

### システム構成
```
┌─────────────────────────────────────────┐
│  Laravel Routing (/vr-tunnel)           │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  Blade View (vr-tunnel.blade.php)       │
│  ┌─────────────────────────────────┐   │
│  │  A-Frame Scene                   │   │
│  │  ├─ <a-sky> (360度画像)         │   │
│  │  ├─ <a-camera>                   │   │
│  │  └─ Tunnel Vision Layer          │   │
│  │     (カスタムエンティティ)        │   │
│  └─────────────────────────────────┘   │
└─────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  カスタムコンポーネント                  │
│  - tunnel-vision-overlay               │
│    (視野狭窄エフェクト制御)              │
└─────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  カスタムシェーダー                      │
│  - Vertex Shader (位置計算)             │
│  - Fragment Shader (視野狭窄描画)       │
└─────────────────────────────────────────┘
```

## 技術選択と理由

### 1. A-Frame Version
**選択**: A-Frame 1.4.0以降
**理由**:
- 安定版で広くサポートされている
- WebXR対応が完全に統合済み
- Three.js r152以降を使用（パフォーマンス最適化）

### 2. 視野狭窄実装方法
**選択**: カメラに追従する半透明球体 + カスタムシェーダー
**理由**:
- ポストプロセッシングよりも軽量
- カメラに直接親子関係で配置可能
- シェーダーで中心からの距離に基づいて透明度を制御

**代替案と却下理由**:
- ❌ ポストプロセッシング: A-Frameでは複雑、パフォーマンス負荷が高い
- ❌ HTML/CSS オーバーレイ: VRモードで正しく表示されない
- ✅ カメラ子要素の球体メッシュ: シンプルで効果的、パフォーマンス良好

### 3. シェーダー方式
**方式**: カスタムシェーダーマテリアル（THREE.ShaderMaterial）
**計算ロジック**:
1. 頂点シェーダーで各ピクセルの位置を計算
2. フラグメントシェーダーで視線中心（カメラ向き）からの角度を計算
3. 角度に基づいて透明度を計算（近い=透明、遠い=黒）

## ファイル構成

### 新規作成ファイル
```
resources/views/vr-tunnel.blade.php      # メインビュー
public/js/vr-tunnel/
  ├─ tunnel-vision.js                    # カスタムコンポーネント
  └─ shaders/
      └─ tunnel-vision-shader.js         # シェーダー定義
```

### 既存ファイルの変更
```
routes/web.php                           # ルート追加
```

## 実装詳細

### 1. Laravel ルーティング
```php
// routes/web.php に追加
Route::get('/vr-tunnel', function () {
    return view('vr-tunnel');
})->name('vr.tunnel');
```

### 2. Bladeビュー構造
```html
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VR 視野狭窄体験</title>
    <!-- A-Frame CDN -->
    <script src="https://aframe.io/releases/1.4.0/aframe.min.js"></script>
    <!-- カスタムコンポーネント -->
    <script src="/js/vr-tunnel/tunnel-vision.js"></script>
    <style>
        body { margin: 0; overflow: hidden; }
        #vr-start-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            cursor: pointer;
        }
        #vr-start-overlay.hidden {
            display: none;
        }
        #vr-start-overlay p {
            color: white;
            font-size: 24px;
            font-family: sans-serif;
        }
    </style>
</head>
<body>
    <div id="vr-start-overlay">
        <p>クリックしてVR体験を開始</p>
    </div>
    
    <!-- 重要: vr-mode-ui設定がPicoブラウザでの自動VRモード起動に不可欠 -->
    <a-scene vr-mode-ui="enabled: true" auto-enter-vr>
        <!-- 360度画像 -->
        <a-sky src="/cg/R0010034.JPG" rotation="0 -90 0"></a-sky>
        
        <!-- カメラ（VRカメラ） -->
        <a-entity id="camera-rig">
            <a-camera>
                <!-- 視野狭窄オーバーレイ -->
                <a-entity tunnel-vision-overlay></a-entity>
            </a-camera>
        </a-entity>
    </a-scene>
</body>
</html>
```

**vr-mode-ui設定の重要性**:
- `vr-mode-ui="enabled: true"`がないと、Picoブラウザで自動VRモードが起動しない
- この設定により、WebXR APIが正しく初期化される
- shooting3Danimal.blade.phpの実装から発見された重要な設定

### 3. カスタムコンポーネント設計

#### コンポーネント1: `auto-enter-vr`

**責務**:
- ページロード時にVRデバイスを検出
- VRデバイスの場合、自動的にVRモードに切り替え
- デスクトップの場合、オーバーレイクリックでフルスクリーン表示

**実装ロジック**:
```javascript
AFRAME.registerComponent('auto-enter-vr', {
  init: function () {
    const sceneEl = this.el;
    const overlay = document.getElementById('vr-start-overlay');
    
    // WebXR APIでVRデバイスをチェック
    if (navigator.xr && navigator.xr.isSessionSupported) {
      navigator.xr.isSessionSupported('immersive-vr').then((supported) => {
        if (supported) {
          console.log('[tunnel-vision] VRデバイス検出 - 自動VRモード起動');
          overlay.classList.add('hidden');
          setTimeout(() => {
            sceneEl.enterVR();
          }, 1000);
        } else {
          console.log('[tunnel-vision] デスクトップ環境 - オーバーレイ表示');
          setupDesktopMode();
        }
      });
    } else {
      console.log('[tunnel-vision] WebXR非対応 - オーバーレイ表示');
      setupDesktopMode();
    }
    
    function setupDesktopMode() {
      overlay.addEventListener('click', () => {
        overlay.classList.add('hidden');
        if (document.documentElement.requestFullscreen) {
          document.documentElement.requestFullscreen();
        }
      });
    }
  }
});
```

#### コンポーネント2: `tunnel-vision-overlay`

**責務**:
- カメラに追従する球体メッシュの生成
- カスタムシェーダーの適用
- 視野狭窄パラメータの管理
- **時間ベースの動的パラメータ変化**

**プロパティ（初期値）**:
```javascript
{
  innerRadius: 1.0,     // 初期状態は透明（視野狭窄なし）
  outerRadius: 1.2,     // innerRadius + 0.20（固定オフセット）
  sphereRadius: 0.4,    // 球体の半径（カメラに近い位置）
  opacity: 1.0          // 黒い部分の不透明度
}
```

**タイマー関連プロパティ**:
```javascript
{
  startTime: null,      // VRモード開始時刻（ミリ秒）
  cycleDuration: 50000, // 1サイクルの長さ（50秒 = 50000ms）
  isVRMode: false       // VRモード状態フラグ
}
```

**メソッド**:
- `init()`: 初期化、球体ジオメトリとシェーダー作成、VRイベントリスナー登録
- `update()`: パラメータ更新時の再描画
- `tick()`: 毎フレーム実行、経過時間に応じてinnerRadiusとouterRadiusを更新
- `getInnerRadiusForTime(elapsedMs)`: 経過時間からinnerRadiusを計算
- `getOuterRadiusOffset(elapsedMs)`: 経過時間からouterRadiusオフセットを計算
  - 0-20秒: 0.20を返す
  - 20-40秒: 0.20から0.05へ線形補間（lerp）
- `onEnterVR()`: VRモード開始時にタイマースタート
- `onExitVR()`: VRモード終了時にタイマー停止

**innerRadius計算ロジック**:
```javascript
// 時間帯ごとの目標値
// 0-5s: 1.0, 5-10s: 1.0→0.175, 10-15s: 0.175→0.125, 
// 15-20s: 0.125→0.075, 20-25s: 0.075→0.025, 25-35s: 0.025→0.002, 35-40s: 0.002→0.0005
function getInnerRadiusForTime(elapsedMs) {
  const elapsed = elapsedMs % 40000; // 40秒でループ
  const sec = elapsed / 1000;
  
  if (sec < 5) {
    // 0-5秒: 1.0を維持（視野狭窄なし）
    return 1.0;
  } else if (sec < 10) {
    // 5-10秒: 1.0から0.175へ線形補間
    const t = (sec - 5) / 5;
    return lerp(1.0, 0.175, t);
  } else if (sec < 15) {
    // 10-15秒: 0.175から0.125へ線形補間
    const t = (sec - 10) / 5;
    return lerp(0.175, 0.125, t);
  } else if (sec < 20) {
    // 15-20秒: 0.125から0.075へ線形補間
    const t = (sec - 15) / 5;
    return lerp(0.125, 0.075, t);
  } else if (sec < 25) {
    // 20-25秒: 0.075から0.025へ線形補間
    const t = (sec - 20) / 5;
    return lerp(0.075, 0.025, t);
  } else if (sec < 35) {
    // 25-35秒: 0.025から0.002へ線形補間（視野角0.2度程度）
    const t = (sec - 25) / 10;
    return lerp(0.025, 0.002, t);
  } else {
    // 35-40秒: 0.002から0.0005へ線形補間（視野角0.05度程度）
    const t = (sec - 35) / 5;
    return lerp(0.002, 0.0005, t);
  }
}

function lerp(a, b, t) {
  return a + (b - a) * t;
}
```

### 4. シェーダー設計

#### Vertex Shader（頂点シェーダー）
```glsl
varying vec3 vPosition;

void main() {
    vPosition = position;
    gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
}
```

#### Fragment Shader（フラグメントシェーダー）
```glsl
uniform float innerRadius;
uniform float outerRadius;
uniform float opacity;

varying vec3 vPosition;

void main() {
    // カメラからの方向ベクトル（正規化）
    vec3 direction = normalize(vPosition);
    
    // 前方向（Z軸負方向）との角度を計算
    // direction.z = cos(angle)
    float angle = acos(-direction.z);
    
    // 正規化された距離 (0.0 = 中心, 1.0 = 外側)
    float normalizedDistance = angle / 3.14159;
    
    // innerRadius～outerRadiusの範囲でスムーズに補間
    float alpha = smoothstep(innerRadius, outerRadius, normalizedDistance);
    
    // 黒色で、alphaで透明度を制御
    gl_FragColor = vec4(0.0, 0.0, 0.0, alpha * opacity);
}
```

**計算ロジック**:
1. 各ピクセルの位置からカメラ方向を正規化
2. 前方向（視線方向）との角度を`acos`で計算
3. `smoothstep`関数で滑らかなグラデーション
4. 中心は透明、外側は黒

### 5. 描画順序とレンダリング設定

**重要なThree.js設定**:
```javascript
material.transparent = true;
material.side = THREE.BackSide;  // 球体の内側を描画
material.depthWrite = false;     // 深度バッファを書き込まない
material.depthTest = false;      // 深度テストをスキップ
```

**理由**:
- `BackSide`: カメラが球体の中にあるため、内側を描画
- `depthWrite: false`: 他のオブジェクトの描画を邪魔しない
- `depthTest: false`: 常に最前面に描画

## パフォーマンス最適化

### 1. ジオメトリの最適化
- **セグメント数**: 64x64程度（滑らかさとパフォーマンスのバランス）
- **半径**: 0.5（カメラに近く、小さい球体）

### 2. シェーダーの最適化
- **計算削減**: 複雑な計算を最小限に
- **precision**: `mediump`で十分（`highp`不要）

### 3. 更新頻度
- **静的オブジェクト**: 一度生成したら再生成しない
- **カメラ追従**: 親子関係で自動追従（毎フレーム更新不要）

## 問題点と対策

### 問題点1: 球体の見切れや歪み
**原因**: カメラのnearプレーンやFOVの影響
**対策**:
- 球体の半径を適切に調整（0.3～0.8の範囲）
- カメラのnearプレーンを確認（デフォルト0.5）

### 問題点2: エッジのジャギー（ギザギザ）
**原因**: アンチエイリアシング不足
**対策**:
- `smoothstep`関数で滑らかな遷移
- WebGLアンチエイリアシング有効化（A-Frameデフォルトで有効）

### 問題点3: VRモードでの表示位置ずれ
**原因**: カメラの親子関係や座標系の問題
**対策**:
- `<a-camera>`の直接の子要素として配置
- ローカル座標系で位置を(0,0,0)に設定

### 問題点4: パフォーマンス低下
**原因**: シェーダー計算負荷またはジオメトリの複雑さ
**対策**:
- セグメント数を削減（32x32まで下げる）
- シェーダーの計算を簡略化
- フレームレートモニタリング

## テスト計画

### デスクトップブラウザテスト
1. Chrome/Edge/Firefoxで画面表示確認
2. マウスドラッグでの視点変更確認
3. エフェクトの視覚的確認

### VRデバイステスト
1. Pico4 Enterpriseでのアクセス
2. VRモード起動確認
3. 頭の動きに追従するか確認
4. フレームレート計測（目標60fps以上）

### エッジケーステスト
- 360度画像の読み込み失敗時の挙動
- VR非対応ブラウザでのフォールバック

## 実装優先順位

### Phase 1: 基本実装（MVP）
1. ルーティング追加
2. Bladeビュー作成（A-Frame基本構造）
3. 360度画像表示
4. カスタムコンポーネント実装
5. シェーダー実装

### Phase 2: 調整・最適化
1. エフェクトパラメータの微調整
2. パフォーマンステスト
3. Pico4での動作確認

### Phase 3: 改善（オプション）
1. ローディング画面追加
2. エラーハンドリング
3. 操作説明の追加

## 依存関係

### 外部ライブラリ
- **A-Frame**: CDN経由で読み込み（1.4.0）
- **Three.js**: A-Frameに含まれる（明示的な追加不要）

### 内部依存
- Laravel routing（既存）
- Blade templating（既存）
- public/cg/R0010034.JPG（既存）

## セキュリティ考慮事項

### CORS対策
- 同一オリジンなので問題なし
- 外部CDN（A-Frame）はCORS対応済み

### CSP（Content Security Policy）
- A-FrameのCDNを許可リストに追加（必要に応じて）

## 次のステップ
タスク化フェーズへの移行

---

**設計フェーズが完了しました。タスク化フェーズに進んでよろしいですか？**
