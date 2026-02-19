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

---

# 設計: VRシューティングゲーム - ゲーム前の弾丸切り替え・発射機能

## 作成日時
2026年2月13日

## 前提
`.claude_workflow/requirements.md`の「VRシューティングゲーム - ゲーム前の弾丸切り替え・発射機能」セクションを読み込み済み

## コード構造の現状分析

### ファイル概要
- **ファイルパス**: `resources/views/shooting3Danimal.blade.php`
- **行数**: 3730行
- **構成**: Bladeテンプレート内にHTML、JavaScript、A-Frame コンポーネントが混在

### 主要コンポーネント

#### 1. グローバル変数（215-232行）
```javascript
window.gameStarted = false;      // ゲーム開始フラグ
window.gameEnded = false;        // ゲーム終了フラグ
window.ballTypes = [             // ボールのGLBモデルパス
    'cg/poke_ball_07apple.glb',
    'cg/poke_ball_09cabbage.glb'
];
window.currentBallIndex = 0;     // 現在選択されているボールのインデックス
```

#### 2. start-menuコンポーネント（440-1800行）
- **責務**: スタートメニューの表示・非表示、ゲーム開始処理
- **startGameメソッド（707行）**: 
  - `window.gameStarted = true`を設定（799行）
  - BGM再生、タイマー開始、モデルのスポーン
- **メニュー選択**: Level1 EasyとLevel2 Hardボタンの`click`イベント（512-532行）

#### 3. handle-shootコンポーネント（2150-2600行）
- **責務**: トリガーボタンでのボール発射処理
- **shootメソッド（2298行～）**:
  - **問題箇所（2299-2302行）**:
    ```javascript
    if (!window.gameStarted) {
        window.debugLog('Game not started, ignoring shoot');
        return;
    }
    ```
  - ゲーム前の発射を防止している
  - 発射後、ボールの物理演算と衝突判定を処理
- **onKeyDownメソッド（2232行～）**: 
  - スペースキーでトリガー代替
  - Gキーでボール切り替え（ゲーム中のみ: 2240-2260行）

#### 4. vr-controllerコンポーネント（3244-3337行）
- **責務**: VRコントローラーのボタン処理、ボールプレビュー表示
- **onButtonDownメソッド（3261-3277行）**:
  - **問題箇所（3266-3269行）**:
    ```javascript
    if (!window.gameStarted || window.gameEnded) {
        window.debugLog('Game not active, ignoring button');
        return;
    }
    ```
  - ゲーム前のボタン切り替えを防止している
  - A/B/グリップボタンでボール切り替え
  - プレビューモデルを更新
- **createBallPreviewメソッド（3280-3316行）**: コントローラー先端にプレビュー表示
- **updatePreviewメソッド（3318-3324行）**: プレビューモデルの切り替え

#### 5. hit-boxコンポーネント（2650-3000行）
- **責務**: ボールと動物（的）の衝突判定、スコア計算
- **ball-hitイベントリスナー（2667行～）**:
  - ボールマッチング判定（正しいボールか？）
  - **問題箇所（2718行）**:
    ```javascript
    window.totalScore += scoreChange;  // ゲーム前チェックなし
    ```
  - スコア加算処理にゲーム状態チェックがない
  - ヒット音再生、パーティクルエフェクト、スコア表示更新

### レイアウト（HTMLパート: 3340-3730行）
- スタートメニュー（3370-3480行）: Level1/Level2ボタン
- タイマーとスコア表示（3483-3510行）
- リザルト画面（3528-3630行）
- モデルエンティティ（3633-3715行）: 6体の動物モデル
- カメラとコントローラー（3368-3369行）

## 設計方針

### 基本原則
1. **最小限の変更**: CLAUDE.mdの指示に従い、既存コードの変更を最小限に抑える
2. **既存動作の維持**: ゲーム中の動作は一切変更しない
3. **条件分岐で制御**: 新機能はゲーム状態（`window.gameStarted`）で条件分岐

### 変更箇所の特定

#### 変更1: vr-controllerコンポーネント（3261-3277行）
**現在**:
```javascript
onButtonDown: function(e) {
    window.debugLog("A/B/Grip button pressed on", this.el.id);
    
    // ゲームが開始されていない場合は無視
    if (!window.gameStarted || window.gameEnded) {
        window.debugLog('Game not active, ignoring button');
        return;
    }
    
    // ボールタイプを切り替え（0 <-> 1）
    window.currentBallIndex = (window.currentBallIndex + 1) % window.ballTypes.length;
    const newBall = window.ballTypes[window.currentBallIndex];
    window.debugLog("Switched to ball:", newBall);
    
    // プレビューを更新
    this.updatePreview();
},
```

**変更後**:
```javascript
onButtonDown: function(e) {
    window.debugLog("A/B/Grip button pressed on", this.el.id);
    
    // 【変更】ゲーム前でもボール切り替えを許可
    // ゲーム終了後のみ無視
    if (window.gameEnded) {
        window.debugLog('Game ended, ignoring button');
        return;
    }
    
    // ボールタイプを切り替え（0 <-> 1）
    window.currentBallIndex = (window.currentBallIndex + 1) % window.ballTypes.length;
    const newBall = window.ballTypes[window.currentBallIndex];
    window.debugLog("Switched to ball:", newBall);
    
    // プレビューを更新
    this.updatePreview();
},
```

**変更理由**:
- `if (!window.gameStarted || window.gameEnded)`を`if (window.gameEnded)`に変更
- ゲーム前（`!window.gameStarted`）でも切り替えを許可
- ゲーム終了後は無視（リザルト画面表示中の誤操作防止）

#### 変更2: handle-shootコンポーネント（2298-2305行）
**現在**:
```javascript
shoot: function (event) {
    // イベントの伝播を完全に停止（メニューへの影響を防ぐ）
    if (event && event.stopPropagation) {
        event.stopPropagation();
    }
    if (event && event.preventDefault) {
        event.preventDefault();
    }
    
    // ゲームが開始されていない場合は撃てない
    if (!window.gameStarted) {
        window.debugLog('Game not started, ignoring shoot');
        return;
    }
    
    // ゲーム終了後は撃てない
    if (window.gameEnded) {
        window.debugLog('Game ended, ignoring shoot');
        return;
    }
    
    // ... 以下、ボール発射処理
}
```

**変更後**:
```javascript
shoot: function (event) {
    // イベントの伝播を完全に停止（メニューへの影響を防ぐ）
    if (event && event.stopPropagation) {
        event.stopPropagation();
    }
    if (event && event.preventDefault) {
        event.preventDefault();
    }
    
    // 【変更】ゲーム前でも発射を許可
    // ゲーム終了後のみ無視
    if (window.gameEnded) {
        window.debugLog('Game ended, ignoring shoot');
        return;
    }
    
    // ... 以下、ボール発射処理（変更なし）
}
```

**変更理由**:
- `if (!window.gameStarted)`の条件削除
- ゲーム前（`!window.gameStarted`）でも発射を許可
- ゲーム終了後は無視（既存の条件を維持）

#### 変更3: hit-boxコンポーネント（2667-2730行）
**現在**:
```javascript
this.el.addEventListener('ball-hit', (event) => {
    if(!hitFlag) {
        hitFlag = true;
        window.debugLog('Model hit!', modelEntity);
        
        // 捕獲した動物の数をインクリメント
        window.enemiesDefeated++;
        window.debugLog('Animals Captured:', window.enemiesDefeated);
        
        // ... 中略 ...
        
        // スコア計算（正しいボール: +10 + ボーナス, 間違ったボール: +3）
        const baseScore = isCorrectBall ? 10 : 3;
        const scoreChange = baseScore + comboBonus;
        window.totalScore += scoreChange;  // 【問題】ゲーム前でもスコア加算される
        
        // ... 以下、スコア表示の更新など
```

**変更後**:
```javascript
this.el.addEventListener('ball-hit', (event) => {
    if(!hitFlag) {
        hitFlag = true;
        window.debugLog('Model hit!', modelEntity);
        
        // 【追加】ゲーム中のみ、捕獲数をカウント
        if (window.gameStarted && !window.gameEnded) {
            window.enemiesDefeated++;
            window.debugLog('Animals Captured:', window.enemiesDefeated);
        }
        
        // ... 中略 ...
        
        // 【追加】ゲーム中のみ、スコア加算とコンボ管理
        let scoreChange = 0;
        if (window.gameStarted && !window.gameEnded) {
            const baseScore = isCorrectBall ? 10 : 3;
            scoreChange = baseScore + comboBonus;
            window.totalScore += scoreChange;
            
            // スコアが0未満にならないように制限
            if (window.totalScore < 0) window.totalScore = 0;
            
            // 最大コンボ数を更新
            if (window.comboCount > window.maxComboCount) {
                window.maxComboCount = window.comboCount;
                window.debugLog('New Max Combo:', window.maxComboCount);
            }
        }
        
        window.debugLog('Ball Match:', isCorrectBall ? `CORRECT (+${scoreChange})` : 'WRONG (+3)', 
                       '| Thrown:', thrownBallIndex, '| Required:', requiredBallIndex,
                       '| Combo:', window.comboCount, '| Total Score:', window.totalScore);
        
        // ... 以下、ヒット音再生とエフェクト（ゲーム前でも実行）
```

**変更理由**:
- スコア加算処理に`if (window.gameStarted && !window.gameEnded)`を追加
- ゲーム前のヒットではスコアを加算しない
- ヒット音とエフェクトは実行（練習時のフィードバック用）

#### 変更4: handle-shootコンポーネント - リアルタイムスコア表示更新（2757行付近）
**現在**:
```javascript
// リアルタイムスコア表示を更新
const currentScoreText = document.getElementById('currentScore');
if (currentScoreText) {
    currentScoreText.setAttribute('value', `SCORE: ${window.totalScore.toFixed(1)}`);
}
```

**変更後**:
```javascript
// 【追加】ゲーム中のみ、リアルタイムスコア表示を更新
if (window.gameStarted && !window.gameEnded) {
    const currentScoreText = document.getElementById('currentScore');
    if (currentScoreText) {
        currentScoreText.setAttribute('value', `SCORE: ${window.totalScore.toFixed(1)}`);
    }
}
```

**変更理由**:
- スコア表示がゲーム前に変化しないようにする
- ゲーム開始前は`SCORE: 0.0`のまま維持

## リスク分析と対策

### リスク1: メニュー選択との競合
**問題**: ゲーム前のトリガー発射が、メニュー選択と競合する可能性

**分析**:
- メニューボタンは`.clickable`クラスを持つ（3416, 3436行）
- handle-shootの`shoot`メソッドは`event.stopPropagation()`と`event.preventDefault()`を実行（2301-2305行）
- メニュークリック判定は`start-menu`コンポーネントの`handleClick`（621行～）で処理
- `handleClick`は`if (window.gameStarted && !window.gameEnded)`でゲーム中をブロック（626-629行）

**結論**: 
- イベント伝播の停止により、メニュー選択は影響を受けない
- メニュー表示中（`visible: true`）はメニューが優先される

**追加対策（不要）**:
- 既存の実装で十分に対策されている
- メニュー非表示後はraycasterから`.clickable`が除外される（769-776行）

### リスク2: 的（動物）の表示
**問題**: ゲーム前に的が表示されていない可能性

**分析**:
- モデルは初期状態で`visible="false"`（3633-3715行）
- ゲーム開始時に`startGame`メソッド内で`visible: true`に設定（874行）
- ゲーム前は的が非表示のため、ボールを当てることができない

**結論**:
- ゲーム前に的が表示されていないため、発射練習は「空撃ち」のみ
- 要件には「的への当たり判定は行わない」と明記されているため、問題なし

**追加対策（不要）**:
- 的を常時表示する必要はない（要件外）
- ゲーム前の発射は、トリガー操作の練習とボール切り替えの確認が目的

### リスク3: パフォーマンス
**問題**: ゲーム前のボール発射が多すぎると、メモリリークや描画負荷の問題

**分析**:
- ボール同時発射制限: 既存の`window.activeBalls.length >= 3`チェック（2312-2315行）
- ボール削除処理: 地面に落ちたら自動削除（2121-2175行）
- THREE.jsオブジェクトのメモリ解放: `dispose()`で適切に破棄（2130-2144, 2155-2168行）

**結論**:
- 既存の制限と削除処理により、パフォーマンス問題は発生しない
- ゲーム前でも同じ制限が適用される

**追加対策（不要）**:
- 既存の実装で十分

## テスト計画

### 単体テスト（手動）

#### テスト1: ゲーム前のボール切り替え
1. ページロード直後（メニュー表示中）
2. グリップ/A/Bボタンを押す
3. **期待結果**: 
   - プレビューモデルがリンゴ⇔キャベツに切り替わる
   - デバッグログに"Switched to ball: ..."が表示される

#### テスト2: ゲーム前のボール発射
1. ページロード直後（メニュー表示中）
2. トリガーを引く
3. **期待結果**:
   - ボールが発射される（物理演算で飛んでいく）
   - 3個まで同時発射可能
   - スコアは加算されない（`SCORE: 0.0`のまま）
   - デバッグログに"Shoot function called"が表示される

#### テスト3: メニュー選択の動作確認
1. ページロード直後（メニュー表示中）
2. ボール発射・切り替えを行う
3. Level1 Easyボタンをクリック
4. **期待結果**:
   - ゲームが正常に開始される
   - メニューが非表示になる
   - タイマーとスコア表示が表示される

#### テスト4: ゲーム中の動作確認
1. ゲーム開始後
2. ボール切り替え・発射を行う
3. **期待結果**:
   - ボール切り替えが機能する
   - ボール発射が機能する
   - 的に当たるとスコアが加算される
   - 従来通りの動作

#### テスト5: ゲーム終了後の動作確認
1. ゲーム終了（タイムアップ）
2. グリップ/A/Bボタンを押す
3. トリガーを引く
4. **期待結果**:
   - ボタンが無視される（リザルト画面表示中）
   - トリガーが無視される
   - デバッグログに"Game ended, ignoring..."が表示される

### 統合テスト

#### テスト6: VRデバイス（Pico4）での動作確認
1. Pico4でページにアクセス
2. ゲーム前にグリップボタンとトリガーをテスト
3. ゲームを開始し、プレイ
4. **期待結果**:
   - 全ての機能が正常に動作
   - フレームレートが安定（60fps以上）

#### テスト7: デスクトップブラウザでの動作確認
1. Chrome/Edgeでページにアクセス
2. ゲーム前にGキーとスペースキーをテスト
3. ゲームを開始し、プレイ
4. **期待結果**:
   - 全ての機能が正常に動作
   - メニュー選択がマウスでできる

## 実装優先順位

### Phase 1: 核心機能の実装
1. **変更1**: vr-controllerのonButtonDown修正（3266-3269行）
2. **変更2**: handle-shootのshoot修正（2299-2302行）
3. **変更3**: hit-boxのball-hitイベント修正（2670-2730行）
4. **変更4**: スコア表示更新の条件追加（2757行付近）

### Phase 2: テストと検証
1. 単体テスト実施（テスト1～5）
2. デバッグログの確認
3. 問題があれば修正

### Phase 3: 最終確認
1. 統合テスト実施（テスト6～7）
2. README.md更新（機能説明の追加）

## 予想される問題と解決策

### 問題1: ゲーム前にヒット音が鳴らない
**原因**: `ball-hit`イベントが発火しない（的が非表示のため）
**解決策**: 問題なし。ゲーム前は的が非表示なので当たらない（要件通り）

### 問題2: プレビュー表示が更新されない
**原因**: `updatePreview`メソッドの呼び出し漏れ
**解決策**: コード確認済み。`updatePreview()`は正しく呼び出される（3276行）

### 問題3: メニュー選択ができなくなる
**原因**: イベント伝播の問題
**解決策**: `event.stopPropagation()`と`event.preventDefault()`が既に実装済み（2301-2305行）

## 次のステップ
タスク化フェーズへの移行

---

**VRシューティングゲームの設計フェーズが完了しました。タスク化フェーズに進んでよろしいですか？**
---

# 設計: ARスタンプラリー202603版のlocalStorage分離

## 作成日時
2026年2月17日

## 前提
`.claude_workflow/requirements.md`の「ARスタンプラリー202603版のlocalStorage分離」を読み込み、要件を確認済み

## 問題分析

### 根本原因
ARstampRally202603.blade.phpがARstampRally.blade.phpからコピーされた際、以下のストレージキーが全く同じ値のまま使用されている：

**LocalStorageキー（5種類）:**
1. `'ar-stamp-rally'` - スタンプデータ（捕獲した動物の記録）
2. `'ar-captured-animals'` - 捕獲済みフラグ（3D表示制御用）
3. `'ar-prize-exchanged'` - 景品交換済みフラグ
4. `'ar-prize-code'` - 景品コード
5. `'ar-user-id'` - ユーザー識別子

**Cookie名（1種類）:**
6. `'ar_user_id'` - ユーザーID（Cookie版）

**IndexedDB名（1種類）:**
7. `'ARStampRallyDB'` - ユーザーIDの永続化用データベース

### 影響範囲
ARstampRally202603.blade.php（6908行）内の以下の箇所：
- localStorage操作: 15箇所
- Cookie操作: 2箇所（getUserId関数内）
- IndexedDB操作: 1箇所（データベース名定義）
- 関数定義: 6個（getCollectedStamps, saveCollectedStamps, getCapturedAnimals, saveCapturedAnimals, markAnimalCaptured, isAnimalCaptured）

## 解決アプローチ

### 基本方針
**全てのストレージキーに `-202603` サフィックスを追加**することで完全にデータを分離する。

### 変更対象の詳細

#### 1. LocalStorageキーの変更
| 変更前 | 変更後 | 使用箇所 |
|--------|--------|----------|
| `'ar-stamp-rally'` | `'ar-stamp-rally-202603'` | 行180, 2798, 2804, 2811, 6048 |
| `'ar-captured-animals'` | `'ar-captured-animals-202603'` | 行2816, 2822, 2828, 6049 |
| `'ar-prize-exchanged'` | `'ar-prize-exchanged-202603'` | 行5863 |
| `'ar-prize-code'` | `'ar-prize-code-202603'` | 行5864 |
| `'ar-user-id'` | `'ar-user-id-202603'` | 行2676 (storageKey変数) |

#### 2. Cookie名の変更
| 変更前 | 変更後 | 使用箇所 |
|--------|--------|----------|
| `'ar_user_id'` | `'ar_user_id_202603'` | 行2677 (cookieName変数) |

#### 3. IndexedDB名の変更
| 変更前 | 変更後 | 使用箇所 |
|--------|--------|----------|
| `'ARStampRallyDB'` | `'ARStampRallyDB202603'` | 行2587 (dbName変数) |

### 実装戦略

#### フェーズ1: 定数・変数定義の変更（優先度：高）
getUserId関数内の定数を変更：
- 行2676: `const storageKey = 'ar-user-id-202603';`
- 行2677: `const cookieName = 'ar_user_id_202603';`

UserIdDBオブジェクトの定義を変更：
- 行2587: `dbName: 'ARStampRallyDB202603',`

#### フェーズ2: LocalStorage操作の一括変更（優先度：中）
文字列リテラルを直接変更：
- `'ar-stamp-rally'` → `'ar-stamp-rally-202603'` (5箇所)
- `'ar-captured-animals'` → `'ar-captured-animals-202603'` (4箇所)
- `'ar-prize-exchanged'` → `'ar-prize-exchanged-202603'` (1箇所)
- `'ar-prize-code'` → `'ar-prize-code-202603'` (1箇所)

### リスク分析

#### リスク1: 見落としによる不完全な修正
**対策**: 
- grep検索で全てのlocalStorage/Cookie/IndexedDB操作を網羅的に確認
- 変更前後の行数をトラッキング
- テスト実施（手動確認）

#### リスク2: 既存データの喪失
**影響**: なし
**理由**: 新しいキーを使用するため、既存の /stamp のデータには一切影響しない

#### リスク3: 関数の依存関係
**対策**: 
- 関数定義（getCollectedStamps等）は変更不要
- 内部で使用するキー文字列のみを変更

## 技術的詳細

### 変更対象の関数
以下の関数は内部でlocalStorageキーを使用しているが、関数自体は変更不要：
1. `getUserId()` - storageKey変数を使用
2. `getCollectedStamps()` - 'ar-stamp-rally'を直接使用
3. `saveCollectedStamps()` - 'ar-stamp-rally'を直接使用
4. `getCapturedAnimals()` - 'ar-captured-animals'を直接使用
5. `saveCapturedAnimals()` - 'ar-captured-animals'を直接使用
6. `markAnimalCaptured()` - getCapturedAnimals/saveCapturedAnimalsを呼び出し
7. `isAnimalCaptured()` - getCapturedAnimalsを呼び出し

### 変更不要な領域
- APIエンドポイント（/api/marker-scans等）
- サーバー側のセッション管理
- データベーステーブル構造
- ルーティング設定（既に/stamp202603で設定済み）

## テスト計画

### 確認項目
1. ✅ /stamp202603 で新規に動物を捕獲
2. ✅ /stamp202603 のスタンプ帳に捕獲した動物が表示される
3. ✅ /stamp のスタンプ帳には /stamp202603 で捕獲した動物が表示されない
4. ✅ /stamp で以前捕獲した動物が /stamp202603 に表示されない
5. ✅ 景品交換が /stamp202603 で独立して機能する
6. ✅ IndexedDB、Cookie、LocalStorageすべてで独立したデータが保存される

### テスト手順
1. ブラウザの開発者ツールでApplicationタブを開く
2. LocalStorage、Cookie、IndexedDBを確認
3. /stamp202603 にアクセスして動物を捕獲
4. ストレージに `-202603` サフィックス付きのキーが作成されることを確認
5. /stamp にアクセスして、データが混在していないことを確認

## 実装の優先順位

### 高優先度（即座に実装）
1. getUserId関数内の定数変更（storageKey, cookieName）
2. UserIdDB.dbName の変更

### 中優先度（一括変更）
3. 'ar-stamp-rally' の全箇所変更
4. 'ar-captured-animals' の全箇所変更
5. 'ar-prize-exchanged' と 'ar-prize-code' の変更

## 次のステップ
タスク化フェーズへの移行（tasks.mdへの追記）

---

**ARスタンプラリー202603版のlocalStorage分離の設計フェーズが完了しました。タスク化フェーズに進んでよろしいですか？**

---

# 設計4: ARstampRally202603 スタンプ帳UI改善と管理画面統計追加

## 作成日時
2026年2月19日

## 前提
`.claude_workflow/requirements.md` の「要件定義4」を読み込み、要件を確認済み

## 現状分析

### ARstampRally202603.blade.phpの構造

**ファイル情報**:
- 総行数: 6951行
- 言語: HTML + JavaScript (Blade テンプレート)
- フレームワーク: A-Frame + AR.js

**重要な定義箇所**:

1. **STAMPSオブジェクト** (2560-2600行目):
```javascript
const STAMPS = {
    // 通常動物 (15種)
    'sheep': { name: 'ひつじ', icon: '🐑', model: '...' },
    'fox': { name: 'きつね', icon: '🦊', model: '...' },
    'pengin': { name: 'ペンギン', icon: '🐧', model: '...' },
    'tonakai': { name: 'トナカイ', icon: '🦌', model: '...' },
    'pig': { name: 'ぶた', icon: '🐷', model: '...' },
    'tora': { name: 'とら', icon: '🐯', model: '...' },
    'gollira': { name: 'ごりら', icon: '🦍', model: '...' },
    'whiteDuck': { name: '白アヒル', icon: '🦆', model: '...' },
    'araiguma': { name: 'あらいぐま', icon: '🦝', model: '...' },
    'wolf': { name: 'おおかみ', icon: '🐺', model: '...' },
    'duck': { name: 'あひる', icon: '🦆', model: '...' },
    'cat': { name: 'ねこ', icon: '🐱', model: '...' },
    'bear': { name: 'くま', icon: '🐻', model: '...' },
    'harinezumi': { name: 'はりねずみ', icon: '🦔', model: '...' },
    'hamstar': { name: 'ハムスター', icon: '🐹', model: '...' },
    
    // シークレット動物 (5種、全てsecret: trueフラグ付き)
    'burger': { name: 'バーガー', icon: '🍔', model: '...', secret: true },
    'kirin': { name: 'きりん', icon: '🦒', model: '...', secret: true },
    'namakemono': { name: 'なまけもの', icon: '🦥', model: '...', secret: true },
    't-rex': { name: 'ティラノサウルス', icon: '🦖', model: '...', secret: true },
    'panda': { name: 'パンダ', icon: '🐼', model: '...', secret: true }
};

const SECRET_STAMPS = ['burger', 'kirin', 'namakemono', 't-rex', 'panda'];
```

2. **showStampBook関数** (3333-3430行目):
   - スタンプ帳モーダルの表示ロジック
   - 各動物の表示状態を制御
   - 現状の処理フロー:
     ```javascript
     if (isCollected) {
         // 収集済み: 実際の名前と画像を表示
         nameText = stamp.name;
     } else if (isSecret) {
         // 未収集のシークレット: アイコン '🐾'、名前 'シークレット'
         iconContent = '🐾';
         nameText = 'シークレット';
     } else {
         // 未収集の通常動物: デフォルトのまま（実際の名前）
         nameText = stamp.name; // ← ここを変更する必要あり
     }
     ```

3. **hint-button** (HTML: 1931行目、イベントハンドラー: 5746-5754行目):
   - HTML:
   ```html
   <button id="hint-button" type="button">ヒントを見る</button>
   ```
   - JavaScript:
   ```javascript
   const hintButton = document.getElementById('hint-button');
   if (hintButton) {
       hintButton.addEventListener('click', function(e) {
           e.preventDefault();
           e.stopPropagation();
           window.open('{{ asset("/cg/stampRallyHints.pdf") }}', '_blank');
       });
   }
   ```

### 既存の管理画面構造

**AdminController.php** (298行):
- `dashboard()`: 既存のダッシュボード (42-159行目)
- MarkerScanモデルを使用した統計取得
- ページネーション対応
- Chart.js による可視化

**admin/dashboard.blade.php** (784行):
- 景品交換統計
- マーカー別スキャン統計
- 日別スキャン数
- ユニークユーザー数
- CSVエクスポート機能

**routes/web.php**:
- 管理画面ルートは `/admin` プレフィックスで統一
- `admin.auth` ミドルウェアによる認証保護

## 設計方針

### 1. スタンプ帳UI改善の設計

#### 1-1. ヒントボタンの非表示

**変更箇所**:
1. **HTML部分** (1931行目):
   ```html
   <!-- 変更前 -->
   <button id="hint-button" type="button">ヒントを見る</button>
   
   <!-- 変更後 -->
   <!-- <button id="hint-button" type="button">ヒントを見る</button> -->
   ```

2. **JavaScript部分** (5746-5754行目):
   ```javascript
   // 変更前: イベントハンドラーが存在
   const hintButton = document.getElementById('hint-button');
   if (hintButton) { ... }
   
   // 変更後: コメントアウトまたは削除
   /*
   const hintButton = document.getElementById('hint-button');
   if (hintButton) { ... }
   */
   ```

**理由**: HTMLをコメントアウトするだけで、JavaScriptは `if (hintButton)` で nullチェックしているため、エラーは発生しない。

#### 1-2. 未収集動物名の「？？？」表示

**変更箇所**: showStampBook関数 (3365-3386行目付近)

**現状のロジック**:
```javascript
if (isCollected) {
    // 収集済み: 実際の名前を表示
    nameText = stamp.name;
} else if (isSecret) {
    // シークレット: '🐾' と 'シークレット'
    iconContent = '🐾';
    nameText = 'シークレット';
}
// else: デフォルトのまま（実際の名前が表示される）
```

**新しいロジック**:
```javascript
if (isCollected) {
    // 収集済み: 実際の名前を表示
    nameText = stamp.name;
} else if (isSecret) {
    // シークレット動物の未収集時
    if (stampId === 'panda') {
        // パンダは特別扱い: 未収集でも「パンダ」と表示
        iconContent = '🐾';
        nameText = 'パンダ';
    } else {
        // パンダ以外のシークレット: 'シークレット'
        iconContent = '🐾';
        nameText = 'シークレット';
    }
} else {
    // 通常動物の未収集時: '？？？' を表示
    iconContent = stamp.icon; // アイコンはそのまま
    nameText = '？？？';
}
```

**対象動物の分類**:
- **パンダ (panda)**: `secret: true` だが未収集時も「パンダ」と表示
- **シークレット（パンダ以外）**: burger, kirin, namakemono, t-rex → 「シークレット」
- **通常動物（15種）**: 全て → 未収集時は「？？？」

### 2. 管理画面統計ページの設計

#### 2-1. システムアーキテクチャ

```
┌─────────────────────────────────────────┐
│  Route: /admin/dashboard202603          │
│  Name: admin.dashboard202603            │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  AdminController::dashboard202603()     │
│  - パンダマーカーの統計を取得            │
│  - MarkerScanモデルを使用                │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  View: admin/dashboard202603.blade.php  │
│  - 既存dashboardと同様のデザイン         │
│  - パンダ統計に特化                      │
└─────────────────────────────────────────┘
```

#### 2-2. データベース設計

**使用テーブル**: `marker_scans`

**カラム**:
- `marker_id`: マーカーの識別子 ('panda')
- `marker_name`: マーカーの表示名 ('パンダ')
- `fingerprint`: ユーザー識別子
- `scan_count`: スキャン回数
- `scanned_at`: スキャン日時
- `device_info`: デバイス情報 (JSON)

**クエリ例**:
```php
// パンダマーカーの総スキャン数
$totalPandaScans = MarkerScan::where('marker_id', 'panda')->count();

// パンダマーカーのユニークユーザー数
$uniquePandaUsers = MarkerScan::where('marker_id', 'panda')
    ->distinct('fingerprint')
    ->count();

// 最近のパンダスキャン履歴
$recentPandaScans = MarkerScan::where('marker_id', 'panda')
    ->orderBy('scanned_at', 'desc')
    ->paginate(30);
```

#### 2-3. コントローラーの実装設計

**ファイル**: `app/Http/Controllers/AdminController.php`

**新規メソッド**: `dashboard202603()`

**実装内容**:
```php
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

#### 2-4. ビューの実装設計

**ファイル**: `resources/views/admin/dashboard202603.blade.php`

**構造**:
1. **ヘッダー**: タイトル + ログアウトボタン + ナビゲーションリンク
2. **統計カード**:
   - 総パンダスキャン数
   - ユニークユーザー数
3. **最近のパンダスキャン履歴テーブル**:
   - 日時
   - フィンガープリント
   - スキャン回数
   - デバイス情報
4. **日別パンダスキャン数**:
   - テーブル表示
   - グラフ表示（Chart.js）

**デザインガイドライン**:
- 既存の `admin/dashboard.blade.php` のスタイルを踏襲
- 色合い、フォント、レイアウトを統一
- レスポンシブデザイン
- ページネーション対応

#### 2-5. ルーティング設計

**ファイル**: `routes/web.php`

**追加ルート**:
```php
// 認証が必要なルート（既存のミドルウェアグループ内に追加）
Route::middleware('admin.auth')->group(function () {
    // ... 既存のルート ...
    
    // ARstampRally202603用のダッシュボード
    Route::get('/dashboard202603', [AdminController::class, 'dashboard202603'])
        ->name('admin.dashboard202603');
});
```

#### 2-6. ナビゲーション設計

**既存ダッシュボードへのリンク追加**:
- `admin/dashboard.blade.php` のヘッダー部分にリンクを追加:
  ```html
  <div class="header">
      <h1>📊 ARスタンプラリー 管理ダッシュボード</h1>
      <div class="nav-links">
          <a href="{{ route('admin.dashboard202603') }}" class="nav-link">
              ARスタンプラリー202603
          </a>
          <a href="{{ route('admin.logout') }}" class="logout-btn">ログアウト</a>
      </div>
  </div>
  ```

**新ダッシュボードからのリンク**:
- `admin/dashboard202603.blade.php` のヘッダー部分:
  ```html
  <div class="header">
      <h1>📊 ARスタンプラリー202603 管理ダッシュボード</h1>
      <div class="nav-links">
          <a href="{{ route('admin.dashboard') }}" class="nav-link">
              通常ダッシュボード
          </a>
          <a href="{{ route('admin.logout') }}" class="logout-btn">ログアウト</a>
      </div>
  </div>
  ```

## 実装上の注意点

### 1. ARstampRally202603.blade.phpの変更

**注意事項**:
- 6951行の大規模ファイルのため、慎重に編集
- 既存の機能を損なわないように注意
- 変更箇所を最小限に抑える
- コメントアウトを活用（完全削除しない）

**テスト項目**:
- スタンプ帳の表示が正しいか
- 収集済み動物の名前が正しく表示されるか
- 未収集動物の名前が要件通り表示されるか（パンダ/シークレット/通常）
- ヒントボタンが非表示になっているか

### 2. 管理画面の実装

**注意事項**:
- 既存の `admin/dashboard.blade.php` のコードを参考にする
- スタイルは既存のものをコピー＆ペースト
- ページネーションのスタイルも同様に適用
- Chart.jsのCDNリンクを含める

**テスト項目**:
- 認証なしでアクセスできないか
- パンダマーカーの統計が正しく表示されるか
- ページネーションが機能するか
- ナビゲーションリンクが機能するか

### 3. パフォーマンス考慮

- `MarkerScan::where('marker_id', 'panda')` はインデックスが効いているか確認
- ページネーションで大量データの取得を避ける
- Chart.jsの描画が重くならないようにデータ量を制限

### 4. セキュリティ考慮

- `admin.auth` ミドルウェアで保護されているか確認
- CSRFトークンが適切に設定されているか
- SQLインジェクション対策（Eloquent使用で自動対策済み）

## 成功基準

### 1. スタンプ帳UI改善

✅ ヒントボタンが表示されない  
✅ パンダは未収集時も「パンダ」と表示される  
✅ シークレット（パンダ以外）は未収集時に「シークレット」と表示される  
✅ 通常動物15種は未収集時に「？？？」と表示される  
✅ 収集済み動物は実際の名前が表示される  
✅ 既存機能が損なわれていない  

### 2. 管理画面統計ページ

✅ `/admin/dashboard202603` でアクセス可能  
✅ 認証なしでアクセス不可  
✅ パンダマーカーの統計が正しく表示される  
✅ 既存ダッシュボードと統一感のあるデザイン  
✅ ナビゲーションが機能する  
✅ ページネーションが機能する  

## 次のステップ

1. タスク化フェーズ（tasks.mdへの追記）- 具体的な作業手順をリスト化
2. 実装フェーズ - コードの変更と追加

---

# 設計5: ARstampRally202603 - スタンプ帳アイコン表示改善 (追加修正)

## 作成日時
2026年2月19日

## 前提
`.claude_workflow/requirements.md`の要件定義5を読み込み、要件を確認済み

## 背景分析

### 現在の実装状態
前回の実装（設計4）で以下を変更：
- ヒントボタンを非表示化
- 未収集動物の**名前表示**を変更（パンダ→「パンダ」、シークレット→「シークレット」、通常動物→「？？？」）

しかし、**アイコン表示**は変更していなかった：
- パンダ: 🐾（足跡）✅ 変更済み
- シークレット: 🐾（足跡）✅ 変更済み
- 通常動物15種: 🐑🦊🐧など（絵文字そのまま）❌ **未変更**

### 問題点
未収集の通常動物が絵文字で表示されているため、ユーザーが「何の動物か」を予測できてしまう。名前は「？？？」なのに、アイコンで動物種がわかるという矛盾が生じている。

## コードベース調査結果

### 1. 関連箇所の全体マッピング

#### A. showStampBook関数（3333-3430行目）
スタンプ帳を表示する関数。未収集時・収集済み時のアイコン・名前表示ロジックが含まれる。

**変更対象箇所**（3388-3393行目）:
```javascript
} else {
    // 通常動物の未収集時: '？？？' を表示
    // iconContentはデフォルトのまま（stamp.icon）
    nameText = '？？？';
}
```

#### B. STAMPS定義（2562-2598行目）
20種の動物定義（通常15種＋シークレット5種）。各動物にicon（絵文字）が定義されている。

**通常動物15種**:
- sheep (🐑), fox (🦊), pengin (🐧), tonakai (🦌), pig (🐷)
- tora (🐯), gollira (🦍), whiteDuck (🦆), araiguma (🦝), wolf (🐺)
- duck (🦆), cat (🐱), bear (🐻), harinezumi (🦔), hamstar (🐹)

**シークレット5種**:
- burger (🍔), kirin (🦒), namakemono (🦥), t-rex (🦖), panda (🐼)

#### C. CSS定義（1559-1611行目）
スタンプアイコンのスタイル定義。

**重要なクラス**:
1. `.stamp-icon`: 基本スタイル（28px font-size、60px height、白背景）
2. `.secret .stamp-icon`: シークレット用（color: transparent + text-shadow）
3. `.not-collected .stamp-icon`: 未収集用（**grayscale(100%)**）

**影響確認**: 未収集の通常動物に足跡を表示しても、`.not-collected`クラスで自動的にgrayscale化される。

#### D. 収集済み時の処理（3367-3377行目）
```javascript
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
}
```

**影響確認**: 収集済み時は、screenshotがあればそれを表示、なければ`stamp.icon`（絵文字）を表示。今回の変更は未収集時のみなので影響なし。

#### E. シークレット動物の未収集時処理（3377-3388行目）
```javascript
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
}
```

**影響確認**: シークレット動物は既に足跡表示済み。今回の変更とは無関係。

#### F. showCapturedMessage関数（2966-2985行目）
動物捕獲時にメッセージを表示する関数。

```javascript
function showCapturedMessage(stampId) {
    const message = document.getElementById('captured-message');
    const animalName = document.getElementById('captured-animal-name');
    
    if (STAMPS[stampId]) {
        animalName.textContent = `${STAMPS[stampId].icon} ${STAMPS[stampId].name}`;
        currentCapturedAnimal = stampId;
        // ... (略)
    }
}
```

**影響確認**: 捕獲済みの動物のアイコン表示なので、今回の変更とは無関係。

#### G. collectStamp関数（124行目）
スタンプ収集時の処理。LocalStorageに保存。

**影響確認**: アイコン表示には関与しないため、今回の変更とは無関係。

### 2. 他の箇所への影響分析

| 箇所 | 影響の有無 | 理由 |
|------|-----------|------|
| STAMPS定義 | なし | アイコン定義は変更しない（収集済み時に使用） |
| CSS定義 | なし | 既存スタイルがそのまま適用される |
| 収集済み表示 | なし | screenshotまたはstamp.iconを表示（変更なし） |
| シークレット動物 | なし | 既に足跡表示済み |
| 捕獲メッセージ | なし | 収集済みの表示なので影響なし |
| collectStamp関数 | なし | 保存処理のみ、表示には関与しない |
| showStampBook以外 | なし | アイコン表示はshowStampBookのみ |

### 3. 変更の影響範囲
**変更箇所**: 1箇所のみ（約3390行目）  
**変更内容**: 1行追加（`iconContent = '🐾';`）  
**影響範囲**: 未収集の通常動物15種のアイコン表示のみ  

## 実装設計

### 1. 変更対象コード

**ファイル**: `resources/views/ARstampRally202603.blade.php`  
**行数**: 約3390行目  
**関数**: showStampBook()  

### 2. 変更内容

#### 変更前（現在のコード）
```javascript
} else {
    // 通常動物の未収集時: '？？？' を表示
    // iconContentはデフォルトのまま（stamp.icon）
    nameText = '？？？';
}
```

#### 変更後
```javascript
} else {
    // 通常動物の未収集時: '？？？' を表示、アイコンは足跡
    iconContent = '🐾'; // 足跡アイコンに変更
    nameText = '？？？';
}
```

### 3. 変更の詳細説明

**変更箇所**: 3390行目の次の行に追加  
**追加コード**: `iconContent = '🐾'; // 足跡アイコンに変更`  
**コメント修正**: 「iconContentはデフォルトのまま（stamp.icon）」→「通常動物の未収集時: '？？？' を表示、アイコンは足跡」  

### 4. CSS適用の確認

未収集の通常動物には以下のクラスが適用される：
- `stamp-item`: 基本スタイル
- `not-collected`: 未収集スタイル

`.not-collected .stamp-icon` に `filter: grayscale(100%);` が適用されるため、足跡アイコン（🐾）もグレースケール表示される。これは期待通りの動作。

### 5. 表示結果の確認

#### 未収集時の表示（変更後）

| 動物種 | アイコン | 名前 | CSS効果 |
|--------|---------|------|---------|
| **通常動物15種** | 🐾 | ？？？ | grayscale(100%) |
| **パンダ** | 🐾 | パンダ | grayscale(100%) + text-shadow |
| **シークレット（パンダ以外）** | 🐾 | シークレット | grayscale(100%) + text-shadow |

#### 収集済み時の表示（変更なし）

| 動物種 | アイコン | 名前 | CSS効果 |
|--------|---------|------|---------|
| **全動物** | スクリーンショット または 絵文字 | 実際の名前 | なし（colored） |

### 6. 一貫性の確認

変更後、未収集時のアイコン表示が完全に統一される：
- ✅ すべての未収集動物が足跡（🐾）で表示される
- ✅ 名前表示と整合性が取れる（動物種を隠す）
- ✅ ユーザー体験が向上（謎解き要素の強化）

## テスト計画

### 1. 動作確認項目

#### A. 未収集動物のアイコン表示
- [ ] 通常動物15種が未収集時に🐾で表示される
- [ ] パンダが未収集時に🐾で表示される（変更なし）
- [ ] シークレット（パンダ以外）が未収集時に🐾で表示される（変更なし）

#### B. 未収集動物の名前表示
- [ ] 通常動物15種が未収集時に「？？？」で表示される（変更なし）
- [ ] パンダが未収集時に「パンダ」で表示される（変更なし）
- [ ] シークレット（パンダ以外）が未収集時に「シークレット」で表示される（変更なし）

#### C. 収集済み動物の表示
- [ ] スクリーンショットがある場合は画像が表示される（変更なし）
- [ ] スクリーンショットがない場合は絵文字が表示される（変更なし）
- [ ] 収集済み動物の名前が正しく表示される（変更なし）

#### D. CSS効果
- [ ] 未収集動物のアイコンがgrayscaleで表示される
- [ ] シークレット動物のアイコンにtext-shadowが適用される

### 2. 回帰テスト

- [ ] スタンプ収集機能が正常に動作する
- [ ] スタンプ帳の表示が正常に動作する
- [ ] 捕獲メッセージが正常に表示される
- [ ] LocalStorageへの保存が正常に動作する
- [ ] ページリロード後もスタンプが保持される

### 3. ブラウザ互換性

- [ ] Chrome（デスクトップ・モバイル）
- [ ] Safari（iOS）
- [ ] Firefox
- [ ] Edge

## リスク評価

### リスク1: 絵文字表示の互換性
**リスク**: 一部のデバイスで🐾が正しく表示されない可能性  
**影響度**: 低（既にシークレット動物で使用済み、動作確認済み）  
**対策**: 既存のシークレット動物と同じ表現なので問題なし  

### リスク2: CSS適用の不具合
**リスク**: grayscaleが足跡に適用されない  
**影響度**: 極低（CSSは既存のまま、他の絵文字と同じ処理）  
**対策**: 変更不要  

### リスク3: パフォーマンス
**リスク**: アイコン変更による処理速度低下  
**影響度**: なし（文字列代入のみ、計算処理なし）  
**対策**: 不要  

## 成功基準

✅ 未収集の通常動物15種のアイコンが🐾（足跡）で表示される  
✅ 未収集のパンダとシークレット動物は引き続き🐾（足跡）で表示される  
✅ 収集済みの動物はスクリーンショットまたは絵文字が表示される（変更なし）  
✅ 動物名の表示は前回の実装のまま（パンダ/シークレット/？？？）  
✅ 既存機能が損なわれていない  
✅ CSSが正しく適用される  

## 次のステップ

1. タスク化フェーズ（tasks.mdへの追記）- 具体的な作業手順をリスト化
2. 実装フェーズ - コードの変更（1行の追加）
3. テストフェーズ - 動作確認と回帰テスト

---

# 設計6: ARstampRally202603 - マーカー検出とボールヒットの統計分離

## 作成日時
2026年2月19日

## 前提
`.claude_workflow/requirements.md`の要件定義6を読み込み、要件を確認済み

## アーキテクチャ概要

### 現在のデータフロー
```
┌─────────────────────────────────────────┐
│ markerFoundイベント（731行目）           │
│ - 未捕獲チェック                         │
│ - モデル表示                             │
│ - 【記録なし】← 問題点                   │
└─────────────────────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────┐
│ ボールヒット（handleHit、340行目）       │
│ - playHitAnimation                      │
│ - collectStamp（124行目）               │
│ - recordMarkerScan（143行目）           │
└─────────────────────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────┐
│ MarkerScanController::record            │
│ - marker_scansテーブルに保存             │
│ - capture_type なし ← 問題点             │
└─────────────────────────────────────────┘
```

### 新しいデータフロー
```
┌─────────────────────────────────────────┐
│ markerFoundイベント（731行目）           │
│ - 未捕獲チェック                         │
│ - モデル表示                             │
│ - 【新規】recordMarkerDetection呼び出し  │
│   - 当日の重複チェック（LocalStorage）   │
│   - recordMarkerScan呼び出し             │
│     (captureType: 'marker_scan')       │
└─────────────────────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────┐
│ ボールヒット（handleHit、340行目）       │
│ - playHitAnimation                      │
│ - collectStamp（124行目）               │
│ - recordMarkerScan（143行目）           │
│   【修正】(captureType: 'ball_hit')     │
└─────────────────────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────┐
│ MarkerScanController::record            │
│ - 【新規】captureType受け取り            │
│ - marker_scansテーブルに保存             │
│   (capture_type カラムに保存)           │
└─────────────────────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────┐
│ AdminController::dashboard202603        │
│ - 【新規】両タイプ別に集計               │
│   - marker_scan カウント                │
│   - ball_hit カウント                   │
└─────────────────────────────────────────┘
```

## コードベース詳細分析

### 1. markerFoundイベントリスナー（731-773行目）

**現在の実装**:
```javascript
marker.addEventListener('markerFound', () => {
    console.log('==================================================');
    console.log('✓ Marker found for:', stampId);
    console.log('  Checking capture state...');
    console.log('  modelCaptured flag:', modelCaptured);
    
    // ローカルフラグをチェック（ボールヒット直後）
    if (modelCaptured) {
        console.log('  → Model captured (local flag) - hiding model and showing message');
        el.setAttribute('visible', 'false');
        
        // 捕獲済みメッセージを表示
        if (typeof showCapturedMessage === 'function') {
            showCapturedMessage(stampId);
        }
        console.log('==================================================');
        return; // ここで処理終了
    }
    
    // 外部関数を使って捕獲済みかチェック（LocalStorageを確認）
    const isCaptured = typeof isAnimalCaptured === 'function' && isAnimalCaptured(stampId);
    console.log('  isAnimalCaptured(' + stampId + '):', isCaptured);
    
    if (isCaptured) {
        console.log('  → Already captured (LocalStorage) - showing message, hiding model');
        el.setAttribute('visible', 'false');
        modelCaptured = true; // ローカル状態も更新
        
        // 捕獲済みメッセージを表示
        if (typeof showCapturedMessage === 'function') {
            showCapturedMessage(stampId);
        }
        console.log('==================================================');
        return; // ここで処理終了
    }
    
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
    console.log('==================================================');
});
```

**変更箇所**: 760行目付近（「捕獲されていない場合のみ、モデルを表示」の直後）

**追加コード**:
```javascript
// 【新規】マーカー検出を記録（未捕獲の場合のみ、1日1回）
try {
    if (typeof recordMarkerDetection === 'function') {
        recordMarkerDetection(stampId, stamp.name);
    }
} catch (e) {
    console.warn('recordMarkerDetection failed', e);
}
```

### 2. recordMarkerScan関数（2789-2824行目）

**現在の実装**:
```javascript
async function recordMarkerScan(markerId, markerName) {
    const fingerprint = await generateFingerprint();
    const deviceInfo = collectDeviceInfo();
    
    // CSRFトークンを取得
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (!csrfToken) {
        console.error('CSRF token not found');
        return;
    }
    
    try {
        const response = await fetch('{{ url("/api/record-marker-scan") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken.content
            },
            body: JSON.stringify({
                markerId: markerId,
                markerName: markerName,
                fingerprint: fingerprint,
                deviceInfo: deviceInfo,
                scannedAt: new Date().toISOString()
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            console.log('✓ Marker scan recorded:', markerId, 'Total scans:', data.totalScans);
        }
    } catch (error) {
        console.error('Error recording marker scan:', error);
    }
}
```

**変更内容**: captureTypeパラメータを追加

**変更後**:
```javascript
async function recordMarkerScan(markerId, markerName, captureType = 'ball_hit') {
    const fingerprint = await generateFingerprint();
    const deviceInfo = collectDeviceInfo();
    
    // CSRFトークンを取得
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (!csrfToken) {
        console.error('CSRF token not found');
        return;
    }
    
    try {
        const response = await fetch('{{ url("/api/record-marker-scan") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken.content
            },
            body: JSON.stringify({
                markerId: markerId,
                markerName: markerName,
                fingerprint: fingerprint,
                deviceInfo: deviceInfo,
                captureType: captureType,  // 【新規】
                scannedAt: new Date().toISOString()
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            console.log('✓ Marker scan recorded:', markerId, 'Type:', captureType, 'Total scans:', data.totalScans);
        }
    } catch (error) {
        console.error('Error recording marker scan:', error);
    }
}
```

### 3. recordMarkerDetection関数（新規作成）

**配置場所**: recordMarkerScan関数の直前（約2788行目）

**実装**:
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

### 4. collectStamp関数の修正（143行目）

**現在の実装**:
```javascript
// 動物をゲットした時だけマーカースキャンを記録
try { recordMarkerScan(stampId, name); } catch (e) { console.warn('recordMarkerScan failed', e); }
```

**変更後**:
```javascript
// 動物をゲットした時だけマーカースキャンを記録（capture_type: 'ball_hit'）
try { recordMarkerScan(stampId, name, 'ball_hit'); } catch (e) { console.warn('recordMarkerScan failed', e); }
```

### 5. MarkerScanController::record メソッド

**現在の実装**:
```php
public function record(Request $request)
{
    $validated = $request->validate([
        'markerId' => 'required|string',
        'markerName' => 'required|string',
        'fingerprint' => 'required|string',
        'deviceInfo' => 'required|array',
        'scannedAt' => 'required|date'
    ]);
    
    $sessionId = session()->getId();
    $fingerprint = $validated['fingerprint'];
    $markerId = $validated['markerId'];
    
    // 同じセッション・フィンガープリント・マーカーの累積スキャン回数を取得
    $totalScans = MarkerScan::where(function($query) use ($sessionId, $fingerprint) {
            $query->where('session_id', $sessionId)
                  ->orWhere('fingerprint', $fingerprint);
        })
        ->where('marker_id', $markerId)
        ->count() + 1;
    
    // 記録を保存
    $scan = MarkerScan::create([
        'session_id' => $sessionId,
        'fingerprint' => $fingerprint,
        'marker_id' => $markerId,
        'marker_name' => $validated['markerName'],
        'scan_count' => $totalScans,
        'scanned_at' => now(), // 現在のJST時刻を使用
        'user_agent' => $request->userAgent(),
        'ip_address' => $request->ip(),
        'device_info' => $validated['deviceInfo']
    ]);
    
    return response()->json([
        'success' => true,
        'totalScans' => $totalScans,
        'scanId' => $scan->id
    ]);
}
```

**変更内容**: captureTypeを受け取り、保存

**変更後**:
```php
public function record(Request $request)
{
    $validated = $request->validate([
        'markerId' => 'required|string',
        'markerName' => 'required|string',
        'fingerprint' => 'required|string',
        'deviceInfo' => 'required|array',
        'captureType' => 'nullable|string|in:marker_scan,ball_hit',  // 【新規】
        'scannedAt' => 'required|date'
    ]);
    
    $sessionId = session()->getId();
    $fingerprint = $validated['fingerprint'];
    $markerId = $validated['markerId'];
    $captureType = $validated['captureType'] ?? 'ball_hit';  // 【新規】デフォルト値
    
    // marker_scan の場合、今日既に記録があるかチェック
    if ($captureType === 'marker_scan') {
        $today = now()->toDateString(); // YYYY-MM-DD
        $existingToday = MarkerScan::where('fingerprint', $fingerprint)
            ->where('marker_id', $markerId)
            ->where('capture_type', 'marker_scan')
            ->whereDate('scanned_at', $today)
            ->exists();
        
        if ($existingToday) {
            // 今日既に記録済み
            return response()->json([
                'success' => true,
                'message' => 'Already recorded today',
                'totalScans' => MarkerScan::where('fingerprint', $fingerprint)
                    ->where('marker_id', $markerId)
                    ->where('capture_type', 'marker_scan')
                    ->count()
            ]);
        }
    }
    
    // 同じセッション・フィンガープリント・マーカー・タイプの累積スキャン回数を取得
    $totalScans = MarkerScan::where(function($query) use ($sessionId, $fingerprint) {
            $query->where('session_id', $sessionId)
                  ->orWhere('fingerprint', $fingerprint);
        })
        ->where('marker_id', $markerId)
        ->where('capture_type', $captureType)  // 【新規】タイプ別にカウント
        ->count() + 1;
    
    // 記録を保存
    $scan = MarkerScan::create([
        'session_id' => $sessionId,
        'fingerprint' => $fingerprint,
        'marker_id' => $markerId,
        'marker_name' => $validated['markerName'],
        'scan_count' => $totalScans,
        'capture_type' => $captureType,  // 【新規】
        'scanned_at' => now(), // 現在のJST時刻を使用
        'user_agent' => $request->userAgent(),
        'ip_address' => $request->ip(),
        'device_info' => $validated['deviceInfo']
    ]);
    
    return response()->json([
        'success' => true,
        'totalScans' => $totalScans,
        'scanId' => $scan->id
    ]);
}
```

### 6. MarkerScanモデルの修正

**現在の実装**:
```php
protected $fillable = [
    'session_id',
    'fingerprint',
    'marker_id',
    'marker_name',
    'scan_count',
    'scanned_at',
    'user_agent',
    'ip_address',
    'device_info'
];
```

**変更後**:
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

### 7. データベースマイグレーション

**新規ファイル**: `database/migrations/2026_02_19_000000_add_capture_type_to_marker_scans_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marker_scans', function (Blueprint $table) {
            // capture_type カラムを追加
            $table->string('capture_type', 20)
                ->default('ball_hit')
                ->after('scan_count')
                ->comment('marker_scan: マーカー検出, ball_hit: ボールヒット');
            
            // インデックス追加
            $table->index('capture_type');
            $table->index(['marker_id', 'capture_type']);
            $table->index(['fingerprint', 'marker_id', 'capture_type']);
        });
        
        // 既存の全レコードのcapture_typeを'ball_hit'に設定
        DB::table('marker_scans')
            ->whereNull('capture_type')
            ->orWhere('capture_type', '')
            ->update(['capture_type' => 'ball_hit']);
    }

    public function down(): void
    {
        Schema::table('marker_scans', function (Blueprint $table) {
            $table->dropIndex(['marker_scans_capture_type_index']);
            $table->dropIndex(['marker_scans_marker_id_capture_type_index']);
            $table->dropIndex(['marker_scans_fingerprint_marker_id_capture_type_index']);
            $table->dropColumn('capture_type');
        });
    }
};
```

### 8. AdminController::dashboard202603 メソッドの拡張

**現在の実装**:
```php
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

**変更後**:
```php
public function dashboard202603(Request $request)
{
    // 全動物のリスト（STAMPS定義と同じ順序）
    $animals = [
        'sheep' => 'ひつじ',
        'fox' => 'きつね',
        'pengin' => 'ペンギン',
        'tonakai' => 'トナカイ',
        'pig' => 'ぶた',
        'tora' => 'とら',
        'gollira' => 'ごりら',
        'whiteDuck' => '白アヒル',
        'araiguma' => 'あらいぐま',
        'wolf' => 'おおかみ',
        'duck' => 'あひる',
        'cat' => 'ねこ',
        'bear' => 'くま',
        'harinezumi' => 'はりねずみ',
        'hamstar' => 'ハムスター',
        'burger' => 'バーガー',
        'kirin' => 'きりん',
        'namakemono' => 'なまけもの',
        't-rex' => 'ティラノサウルス',
        'panda' => 'パンダ'
    ];
    
    // 各動物の統計を取得
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
        
        // ユニークユーザー数（両タイプ合計）
        $uniqueUsers = MarkerScan::where('marker_id', $markerId)
            ->distinct('fingerprint')
            ->count();
        
        // 最終スキャン日時
        $lastScan = MarkerScan::where('marker_id', $markerId)
            ->orderBy('scanned_at', 'desc')
            ->value('scanned_at');
        
        $animalStats[] = [
            'marker_id' => $markerId,
            'marker_name' => $markerName,
            'marker_scan_count' => $markerScanCount,
            'ball_hit_count' => $ballHitCount,
            'total_count' => $markerScanCount + $ballHitCount,
            'unique_users' => $uniqueUsers,
            'last_scan' => $lastScan
        ];
    }
    
    // 最近のスキャン履歴（全動物、両タイプ）
    $recentScans = MarkerScan::orderBy('scanned_at', 'desc')
        ->paginate(30, ['*'], 'recent_scans_page');
    
    // 日別スキャン数（直近30日間、タイプ別）
    $dailyStats = MarkerScan::select(DB::raw('DATE(scanned_at) as date'))
        ->selectRaw('capture_type')
        ->selectRaw('COUNT(*) as count')
        ->where('scanned_at', '>=', now()->subDays(30))
        ->groupBy('date', 'capture_type')
        ->orderBy('date', 'desc')
        ->get()
        ->groupBy('date');
    
    return view('admin.dashboard202603', compact(
        'animalStats',
        'recentScans',
        'dailyStats'
    ));
}
```

### 9. dashboard202603.blade.php の修正

**追加セクション**: 動物別統計テーブル

```html
<!-- 動物別統計テーブル -->
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

<!-- 最近のスキャン履歴 -->
<div class="stats-section">
    <h2>最近のスキャン履歴</h2>
    <table class="scans-table">
        <thead>
            <tr>
                <th>日時</th>
                <th>動物名</th>
                <th>タイプ</th>
                <th>フィンガープリント</th>
                <th>デバイス</th>
            </tr>
        </thead>
        <tbody>
            @foreach($recentScans as $scan)
            <tr>
                <td>{{ $scan->scanned_at->format('Y-m-d H:i:s') }}</td>
                <td>{{ $scan->marker_name }}</td>
                <td>
                    <span class="badge {{ $scan->capture_type === 'marker_scan' ? 'marker' : 'ball' }}">
                        {{ $scan->capture_type === 'marker_scan' ? 'マーカー検出' : 'ボールヒット' }}
                    </span>
                </td>
                <td>{{ substr($scan->fingerprint, 0, 12) }}...</td>
                <td>{{ $scan->device_info['isIOS'] ?? false ? 'iOS' : ($scan->device_info['isAndroid'] ?? false ? 'Android' : 'Other') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {{ $recentScans->links() }}
</div>

<!-- 日別統計グラフ -->
<div class="stats-section">
    <h2>日別スキャン数（直近30日間）</h2>
    <div class="chart-container">
        <canvas id="dailyChart"></canvas>
    </div>
</div>

<script>
// Chart.jsで積み上げ棒グラフを表示
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
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            },
            {
                label: 'ボールヒット',
                data: ballHitData,
                backgroundColor: 'rgba(255, 99, 132, 0.6)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
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
</script>
```

## テスト計画

### 1. 単体テスト

#### A. recordMarkerDetection関数
- [ ] 未捕獲の動物でマーカー検出時、LocalStorageキャッシュが作成される
- [ ] 当日2回目の検出では記録されない（ローカルキャッシュで防止）
- [ ] 翌日の検出では記録される

#### B. recordMarkerScan関数
- [ ] captureType: 'marker_scan'で呼び出し時、正しくAPIに送信される
- [ ] captureType: 'ball_hit'で呼び出し時、正しくAPIに送信される
- [ ] パラメータ省略時、デフォルト'ball_hit'が使用される

#### C. MarkerScanController::record
- [ ] captureType: 'marker_scan'でリクエスト時、DBに正しく保存される
- [ ] captureType: 'ball_hit'でリクエスト時、DBに正しく保存される
- [ ] marker_scanタイプで同日の重複リクエストは記録されない

#### D. AdminController::dashboard202603
- [ ] 各動物のマーカー検出回数が正しく集計される
- [ ] 各動物のボールヒット回数が正しく集計される
- [ ] ユニークユーザー数が正しく集計される

### 2. 統合テスト

#### A. マーカー検出からDB保存まで
1. ARマーカーをカメラで検出
2. markerFoundイベント発火
3. recordMarkerDetection呼び出し
4. recordMarkerScan呼び出し（captureType: 'marker_scan'）
5. MarkerScanController::record実行
6. marker_scansテーブルに保存（capture_type: 'marker_scan'）

#### B. ボールヒットからDB保存まで
1. ボールを投げて動物にヒット
2. handleHit関数実行
3. collectStamp呼び出し
4. recordMarkerScan呼び出し（captureType: 'ball_hit'）
5. MarkerScanController::record実行
6. marker_scansテーブルに保存（capture_type: 'ball_hit'）

#### C. ダッシュボード表示
1. admin/dashboard202603にアクセス
2. 動物別統計テーブルが表示される
3. マーカー検出回数とボールヒット回数が別々に表示される
4. 最近のスキャン履歴にタイプが表示される
5. グラフが2つのタイプ別に表示される

### 3. 回帰テスト

- [ ] 既存のスタンプ収集機能が正常に動作する
- [ ] 既存のスタンプ帳表示が正常に動作する
- [ ] 既存の捕獲メッセージが正常に表示される
- [ ] 既存の管理画面（dashboard）が正常に動作する

## リスク管理と対策

### リスク1: markerFoundイベントの過剰発火
**問題**: マーカーが短時間に複数回検出される可能性  
**対策**: LocalStorageキャッシュで当日の重複を防止、API呼び出しも日付でチェック

### リスク2: 既存のrecordMarkerScan呼び出し箇所の影響
**問題**: 既存の呼び出し箇所（143行目）がcaptureTypeを指定していない  
**対策**: デフォルト値'ball_hit'を設定し、後方互換性を保つ

### リスク3: 大規模ファイル編集のミス
**問題**: ARstampRally202603.blade.phpは6964行の大規模ファイル  
**対策**: 変更箇所を限定（4箇所のみ）、段階的なテストを実施

### リスク4: 既存データとの互換性
**問題**: 既存のmarker_scansレコードがcapture_typeを持たない  
**対策**: マイグレーションで既存データのcapture_typeを'ball_hit'に設定

## 成功基準

### 必須条件
- [ ] マーカー検出時にmarker_scansに記録される（capture_type: 'marker_scan'）
- [ ] ボールヒット時にmarker_scansに記録される（capture_type: 'ball_hit'）
- [ ] 同じ日に同じマーカーを再検出しても、カウントアップされない
- [ ] 捕獲済みのマーカーは記録されない
- [ ] ダッシュボードで各動物のマーカー検出回数とボールヒット回数が表示される
- [ ] 既存のmarker_scansデータが正しく集計される
- [ ] 既存機能が損なわれない

## 次のステップ
1. タスク化フェーズ（tasks.mdへの追記）- 具体的な作業手順をリスト化
2. 実装フェーズ - コードの変更と追加
3. マイグレーション実行とテスト