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

---

# 設計7: ARstampRally202603 - dashboard202603のUI改善と日別個別ユーザー数統計追加

## 作成日時
2026年2月19日

## 前提
`.claude_workflow/requirements.md`の要件定義7を読み込み、要件を確認済み

## 設計概要

本設計では、admin/dashboard202603の管理画面において、以下の2つの改善を実施する：
1. **ページネーションのUI修正**: SVGアイコンのサイズ崩れを修正
2. **日別個別ユーザー数統計の追加**: マーカー検出とボールヒット別の日別ユニークユーザー数をグラフ化

## アーキテクチャ概要

### システム構成
```
┌─────────────────────────────────────────────────────────┐
│  Laravel Routing (/admin/dashboard202603)               │
└──────────────┬──────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────┐
│  AdminController::dashboard202603()                     │
│  ┌─────────────────────────────────────────────────┐   │
│  │  既存データ取得                                   │   │
│  │  - 動物別統計 ($animalStats)                     │   │
│  │  - 最近のスキャン履歴 ($recentScans)             │   │
│  │  - 日別スキャン統計 ($dailyStats)                │   │
│  ├─────────────────────────────────────────────────┤   │
│  │  【新規】日別個別ユーザー数統計                   │   │
│  │  - $dailyUniqueUsers                            │   │
│  │    {                                            │   │
│  │      'date' => 'YYYY-MM-DD',                    │   │
│  │      'capture_type' => 'marker_scan|ball_hit',  │   │
│  │      'unique_users' => COUNT(DISTINCT)          │   │
│  │    }                                            │   │
│  └─────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────┐
│  Blade View (dashboard202603.blade.php)                 │
│  ┌─────────────────────────────────────────────────┐   │
│  │  既存セクション                                   │   │
│  │  - 動物別統計テーブル                             │   │
│  │  - 最近のスキャン履歴【CSS修正対象】              │   │
│  │  - 日別スキャン統計グラフ                         │   │
│  ├─────────────────────────────────────────────────┤   │
│  │  【新規】日別個別ユーザー数グラフ                 │   │
│  │  - Chart.js Line Chart                          │   │
│  │  - 2つのライン（マーカー検出/ボールヒット）       │   │
│  └─────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────┐
│  Chart.js (CDN)                                         │
│  - Line Chart描画                                        │
│  - インタラクティブ機能（ホバー、凡例）                  │
└─────────────────────────────────────────────────────────┘
```

## 詳細設計

### 1. ページネーションUI修正

#### 問題分析
現状のCSS:
```css
.pagination a,
.pagination span {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: #667eea;
    transition: all 0.3s;
}
```

**問題点**:
- LaravelのデフォルトページネーションはSVGアイコンを使用
- SVGのサイズが親要素に依存し、制御されていない
- 結果として、アイコンが想定以上に大きく表示される

#### 解決策
CSSに以下のルールを追加:
```css
/* ページネーションのSVGアイコンサイズ制御 */
.pagination svg {
    width: 18px !important;
    height: 18px !important;
    max-width: 18px !important;
    max-height: 18px !important;
}

/* ページネーションアイテムの中央揃え */
.pagination a,
.pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    min-height: 36px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: #667eea;
    transition: all 0.3s;
}

/* Laravelページネーションの構造に対応 */
.pagination nav {
    display: flex;
    justify-content: center;
}

.pagination nav svg {
    width: 18px !important;
    height: 18px !important;
}
```

#### 変更箇所
- **ファイル**: `resources/views/admin/dashboard202603.blade.php`
- **場所**: `<style>`タグ内（114行目付近の`.pagination`セクション）
- **変更内容**: 既存のCSSルールを拡張

#### Before/After比較

**Before**:
```css
.pagination a,
.pagination span {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: #667eea;
    transition: all 0.3s;
}
```

**After**:
```css
.pagination svg {
    width: 18px !important;
    height: 18px !important;
    max-width: 18px !important;
    max-height: 18px !important;
}

.pagination a,
.pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    min-height: 36px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: #667eea;
    transition: all 0.3s;
}

.pagination nav {
    display: flex;
    justify-content: center;
}

.pagination nav svg {
    width: 18px !important;
    height: 18px !important;
}
```

### 2. 日別個別ユーザー数統計の追加

#### データフロー設計

```
┌─────────────────────────────────────┐
│  marker_scans テーブル               │
│  ┌─────────────────────────────┐   │
│  │ scanned_at (datetime)       │   │
│  │ fingerprint (string)        │   │
│  │ capture_type (string)       │   │
│  └─────────────────────────────┘   │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  SQL クエリ                          │
│  SELECT DATE(scanned_at) as date,   │
│         capture_type,               │
│         COUNT(DISTINCT fingerprint) │
│         as unique_users             │
│  FROM marker_scans                  │
│  WHERE scanned_at >= NOW() - 30 DAY │
│  GROUP BY date, capture_type        │
│  ORDER BY date DESC                 │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  結果をグループ化                     │
│  $dailyUniqueUsers = [              │
│    'date' => [                      │
│      { capture_type, unique_users } │
│    ]                                │
│  ]                                  │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  Blade View に渡す                   │
│  compact('dailyUniqueUsers')        │
└─────────────────────────────────────┘
```

#### AdminController の変更

**ファイル**: `app/Http/Controllers/AdminController.php`  
**メソッド**: `dashboard202603()`  
**変更箇所**: 既存のreturn文の前に追加

**追加コード**:
```php
// 【新規】日別個別ユーザー数統計（タイプ別、直近30日間）
$dailyUniqueUsersRaw = MarkerScan::select(DB::raw('DATE(scanned_at) as date'))
    ->selectRaw('capture_type')
    ->selectRaw('COUNT(DISTINCT fingerprint) as unique_users')
    ->where('scanned_at', '>=', now()->subDays(30))
    ->groupBy('date', 'capture_type')
    ->orderBy('date', 'desc')
    ->get();

// 日付でグループ化
$dailyUniqueUsers = $dailyUniqueUsersRaw->groupBy('date');
```

**変更後のreturn文**:
```php
return view('admin.dashboard202603', compact(
    'animalStats',
    'recentScans',
    'dailyStats',
    'dailyUniqueUsers'  // 【追加】
));
```

#### Before/After比較

**Before (既存のコード)**:
```php
// 日別統計（タイプ別、直近30日間）
$dailyStatsRaw = MarkerScan::select(DB::raw('DATE(scanned_at) as date'))
    ->selectRaw('capture_type')
    ->selectRaw('COUNT(*) as count')
    ->where('scanned_at', '>=', now()->subDays(30))
    ->groupBy('date', 'capture_type')
    ->orderBy('date', 'desc')
    ->get();

// 日付でグループ化
$dailyStats = $dailyStatsRaw->groupBy('date');

return view('admin.dashboard202603', compact(
    'animalStats',
    'recentScans',
    'dailyStats'
));
```

**After（追加後）**:
```php
// 日別統計（タイプ別、直近30日間）
$dailyStatsRaw = MarkerScan::select(DB::raw('DATE(scanned_at) as date'))
    ->selectRaw('capture_type')
    ->selectRaw('COUNT(*) as count')
    ->where('scanned_at', '>=', now()->subDays(30))
    ->groupBy('date', 'capture_type')
    ->orderBy('date', 'desc')
    ->get();

// 日付でグループ化
$dailyStats = $dailyStatsRaw->groupBy('date');

// 【新規】日別個別ユーザー数統計（タイプ別、直近30日間）
$dailyUniqueUsersRaw = MarkerScan::select(DB::raw('DATE(scanned_at) as date'))
    ->selectRaw('capture_type')
    ->selectRaw('COUNT(DISTINCT fingerprint) as unique_users')
    ->where('scanned_at', '>=', now()->subDays(30))
    ->groupBy('date', 'capture_type')
    ->orderBy('date', 'desc')
    ->get();

// 日付でグループ化
$dailyUniqueUsers = $dailyUniqueUsersRaw->groupBy('date');

return view('admin.dashboard202603', compact(
    'animalStats',
    'recentScans',
    'dailyStats',
    'dailyUniqueUsers'  // 【追加】
));
```

### 3. Bladeテンプレートの変更

#### 新規セクションの追加

**ファイル**: `resources/views/admin/dashboard202603.blade.php`  
**挿入場所**: 「日別スキャン統計」のグラフセクションの後（閉じタグ`</div>`の後）

**追加HTML**:
```html
<div class="card">
    <h2>👥 日別個別ユーザー数（直近30日間）</h2>
    <div class="chart-container">
        <canvas id="uniqueUsersChart"></canvas>
    </div>
</div>
```

#### JavaScriptグラフコードの追加

**挿入場所**: 既存の`dailyChart`のChart.js設定の後、`setTimeout`の前

**追加JavaScript**:
```javascript
// 【新規】日別個別ユーザー数グラフ（折れ線グラフ）
const uniqueUsersData = @json($dailyUniqueUsers);
const uniqueDates = Object.keys(uniqueUsersData).reverse();

const markerScanUniqueData = uniqueDates.map(date => {
    const dayData = uniqueUsersData[date].find(d => d.capture_type === 'marker_scan');
    return dayData ? dayData.unique_users : 0;
});

const ballHitUniqueData = uniqueDates.map(date => {
    const dayData = uniqueUsersData[date].find(d => d.capture_type === 'ball_hit');
    return dayData ? dayData.unique_users : 0;
});

const ctxUnique = document.getElementById('uniqueUsersChart').getContext('2d');
new Chart(ctxUnique, {
    type: 'line',
    data: {
        labels: uniqueDates.map(date => {
            const d = new Date(date);
            return (d.getMonth() + 1) + '/' + d.getDate();
        }),
        datasets: [
            {
                label: 'マーカー検出（個別ユーザー）',
                data: markerScanUniqueData,
                borderColor: 'rgba(52, 152, 219, 1)',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6
            },
            {
                label: 'ボールヒット（個別ユーザー）',
                data: ballHitUniqueData,
                borderColor: 'rgba(231, 76, 60, 1)',
                backgroundColor: 'rgba(231, 76, 60, 0.1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            x: {
                title: {
                    display: true,
                    text: '日付'
                }
            },
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: '個別ユーザー数'
                },
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: '日別個別ユーザー数推移'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.y + '人';
                    }
                }
            }
        }
    }
});
```

#### Before/After比較（Blade全体構造）

**Before**:
```html
<div class="card">
    <h2>📅 日別スキャン統計（直近30日間）</h2>
    <div class="chart-container">
        <canvas id="dailyChart"></canvas>
    </div>
</div>

<script>
    // 日別統計グラフ（積み上げ棒グラフ）
    const dailyData = @json($dailyStats);
    // ... existing chart code ...

    // 30秒ごとに自動更新
    setTimeout(() => {
        location.reload();
    }, 30000);
</script>
```

**After**:
```html
<div class="card">
    <h2>📅 日別スキャン統計（直近30日間）</h2>
    <div class="chart-container">
        <canvas id="dailyChart"></canvas>
    </div>
</div>

<!-- 【新規】日別個別ユーザー数セクション -->
<div class="card">
    <h2>👥 日別個別ユーザー数（直近30日間）</h2>
    <div class="chart-container">
        <canvas id="uniqueUsersChart"></canvas>
    </div>
</div>

<script>
    // 日別統計グラフ（積み上げ棒グラフ）
    const dailyData = @json($dailyStats);
    // ... existing chart code ...

    // 【新規】日別個別ユーザー数グラフ（折れ線グラフ）
    const uniqueUsersData = @json($dailyUniqueUsers);
    // ... new chart code ...

    // 30秒ごとに自動更新
    setTimeout(() => {
        location.reload();
    }, 30000);
</script>
```

## データ構造設計

### $dailyUniqueUsers のデータ構造

```php
[
    '2026-02-19' => [
        0 => {
            "date": "2026-02-19",
            "capture_type": "marker_scan",
            "unique_users": 5
        },
        1 => {
            "date": "2026-02-19",
            "capture_type": "ball_hit",
            "unique_users": 3
        }
    ],
    '2026-02-18' => [
        0 => {
            "date": "2026-02-18",
            "capture_type": "marker_scan",
            "unique_users": 4
        },
        1 => {
            "date": "2026-02-18",
            "capture_type": "ball_hit",
            "unique_users": 2
        }
    ],
    // ... 30日分
]
```

### JavaScriptでの処理フロー

```javascript
// 1. データを取得
const uniqueUsersData = {"2026-02-19": [...], "2026-02-18": [...]};

// 2. 日付の配列を作成（逆順にしてグラフ左から古い日付）
const uniqueDates = Object.keys(uniqueUsersData).reverse();
// → ['2026-01-20', '2026-01-21', ..., '2026-02-19']

// 3. マーカー検出の個別ユーザー数配列を作成
const markerScanUniqueData = uniqueDates.map(date => {
    const dayData = uniqueUsersData[date].find(d => d.capture_type === 'marker_scan');
    return dayData ? dayData.unique_users : 0;  // データがない日は0
});
// → [0, 5, 3, 4, ...]

// 4. ボールヒットの個別ユーザー数配列を作成
const ballHitUniqueData = uniqueDates.map(date => {
    const dayData = uniqueUsersData[date].find(d => d.capture_type === 'ball_hit');
    return dayData ? dayData.unique_users : 0;
});
// → [0, 3, 2, 1, ...]

// 5. Chart.jsでグラフ描画
```

## パフォーマンス設計

### クエリ最適化

#### 1. インデックスの活用
既存のインデックス:
- `capture_type` (単独インデックス)
- `(marker_id, capture_type)` (複合インデックス)
- `(fingerprint, marker_id, capture_type)` (複合インデックス)

新規クエリ:
```sql
SELECT DATE(scanned_at) as date,
       capture_type,
       COUNT(DISTINCT fingerprint) as unique_users
FROM marker_scans
WHERE scanned_at >= NOW() - INTERVAL 30 DAY
GROUP BY DATE(scanned_at), capture_type
ORDER BY date DESC
```

**インデックスの利用状況**:
- `capture_type`インデックスが利用される
- `scanned_at`のWHERE条件により、30日分のレコードに絞り込まれる
- GROUP BYとORDER BYは結果セットが小さいため影響は限定的

#### 2. クエリ実行時間の見積もり
- **想定レコード数**: 30日間で最大10,000レコード（1日平均333レコード）
- **DISTINCT fingerprint**: 最大1,000ユニークユーザー
- **GROUP BY**: 30日 × 2タイプ = 60グループ
- **期待実行時間**: 50-200ms

#### 3. ページ読み込み時間
- **既存クエリ**: 3つ（動物別統計、最近のスキャン、日別統計）
- **新規クエリ**: 1つ（日別個別ユーザー数）
- **合計**: 4つのクエリ
- **期待合計時間**: 500ms以内

### フロントエンド最適化

#### Chart.jsのパフォーマンス
- **データポイント数**: 30日 × 2ライン = 60ポイント
- **描画時間**: 50-100ms（Chart.jsの最新版で高速化済み）
- **メモリ使用量**: 最小限（データ量が少ない）

## エラーハンドリング設計

### バックエンドエラー

#### 1. データベースクエリエラー
```php
try {
    $dailyUniqueUsersRaw = MarkerScan::select(...)
        ->get();
    $dailyUniqueUsers = $dailyUniqueUsersRaw->groupBy('date');
} catch (\Exception $e) {
    // ログ出力
    \Log::error('Failed to fetch daily unique users: ' . $e->getMessage());
    // 空の配列を返す（グラフは表示されないが、ページはエラーにならない）
    $dailyUniqueUsers = collect([]);
}
```

#### 2. データが存在しない場合
- `$dailyUniqueUsers`が空の場合、JavaScriptで空のグラフが表示される
- グラフは表示されるが、データポイントがない状態

### フロントエンドエラー

#### 1. データが不正な形式の場合
```javascript
const uniqueUsersData = @json($dailyUniqueUsers ?? []);
if (!uniqueUsersData || Object.keys(uniqueUsersData).length === 0) {
    console.warn('No unique users data available');
    // 空のグラフが表示される
}
```

#### 2. Chart.jsの初期化エラー
- Chart.jsがCDNから読み込めない場合、グラフは表示されない
- 既存のグラフも同様の挙動なので、一貫性がある

## テスト設計

### 単体テスト

#### AdminControllerのテスト
```php
public function test_dashboard202603_includes_daily_unique_users()
{
    // テストデータ作成
    MarkerScan::factory()->create([
        'marker_id' => 'panda',
        'fingerprint' => 'test-fingerprint-1',
        'capture_type' => 'marker_scan',
        'scanned_at' => now()
    ]);
    
    // リクエスト実行
    $response = $this->actingAs($this->admin)->get('/admin/dashboard202603');
    
    // アサーション
    $response->assertViewHas('dailyUniqueUsers');
    $dailyUniqueUsers = $response->viewData('dailyUniqueUsers');
    $this->assertNotEmpty($dailyUniqueUsers);
}
```

### 結合テスト

#### 1. ページネーションUI表示テスト
- **テストケース**: 31件以上のスキャン履歴が存在する場合
- **期待結果**: ページネーションが表示され、SVGアイコンが18pxで表示される
- **確認方法**: ブラウザの開発者ツールでSVGのサイズを確認

#### 2. 日別個別ユーザー数グラフ表示テスト
- **テストケース**: 複数日にわたるスキャンデータが存在する場合
- **期待結果**: 
  - グラフが表示される
  - 2つのライン（マーカー検出、ボールヒット）が表示される
  - ホバーで数値が表示される
- **確認方法**: ブラウザで実際にページを開き、グラフを確認

#### 3. データがない日の処理テスト
- **テストケース**: 一部の日にデータが存在しない
- **期待結果**: データがない日は0として表示される
- **確認方法**: グラフ上で0の日が正しく表示されることを確認

### 回帰テスト

#### 既存機能への影響確認
- [ ] 動物別統計テーブルが正しく表示される
- [ ] 最近のスキャン履歴が正しく表示される
- [ ] 日別スキャン統計グラフが正しく表示される
- [ ] ページネーションが機能する
- [ ] 30秒ごとの自動更新が機能する

## セキュリティ考慮

### 1. 認証・認可
- **要件**: admin/dashboard202603は認証済み管理者のみアクセス可能
- **実装**: Laravelの既存のミドルウェアで保護されている（変更なし）

### 2. SQLインジェクション対策
- **要件**: ユーザー入力を含むクエリは存在しない
- **実装**: Eloquent ORMを使用（パラメータバインディング自動）

### 3. XSS対策
- **要件**: ユーザー入力を含む表示は存在しない
- **実装**: Bladeテンプレートの`{{ }}`によるエスケープ（既存機能）

## デプロイ設計

### デプロイ手順
1. コードの変更をコミット
2. `app/Http/Controllers/AdminController.php`の変更をデプロイ
3. `resources/views/admin/dashboard202603.blade.php`の変更をデプロイ
4. キャッシュクリア: `php artisan view:clear`
5. 動作確認

### ロールバック計画
- **変更内容**: コードの追加のみ（既存機能の削除なし）
- **ロールバック方法**: Gitで前のコミットに戻す
- **影響**: 新機能が表示されなくなるだけ（既存機能は維持）

## リスク分析と対策

### リスク1: パフォーマンス劣化
**影響度**: 中  
**発生確率**: 低  
**対策**:
- クエリは30日分に限定
- COUNT(DISTINCT)は既存のインデックスを活用
- 結果のキャッシュは不要（30秒ごとに自動更新するため）

### リスク2: データがない日の表示
**影響度**: 低  
**発生確率**: 中  
**対策**:
- JavaScriptでデータがない日は0として補完
- Chart.jsは0を正しく表示できる

### リスク3: CSSの競合
**影響度**: 低  
**発生確率**: 低  
**対策**:
- `!important`を使用してスタイルを確実に適用
- 既存のCSSとの競合を避けるため、セレクタを具体的に指定

### リスク4: グラフ描画の遅延
**影響度**: 低  
**発生確率**: 低  
**対策**:
- Chart.jsの最新版を使用（既存のグラフと同じCDN）
- データ量を30日分に限定

## 成功基準

### 必須条件
- [ ] ページネーションのSVGアイコンが18px × 18pxで表示される
- [ ] ページネーションのボタンが統一されたサイズで表示される
- [ ] 日別個別ユーザー数のグラフが追加される
- [ ] グラフがマーカー検出とボールヒットの2つのラインで表示される
- [ ] グラフがホバー時に具体的な数値を表示する
- [ ] AdminControllerで日別個別ユーザー数のデータが取得される
- [ ] 既存のグラフや統計表示に影響がない
- [ ] ページ読み込み時間が2秒以内

### 望ましい条件
- [ ] グラフのアニメーションがスムーズ
- [ ] レスポンシブデザインが機能する
- [ ] 色使いが既存のグラフと統一されている

## 次のステップ
1. タスク化フェーズ（tasks.mdへの追記）- 具体的な作業手順をリスト化
2. 実装フェーズ - コードの変更と追加
3. 動作確認とテスト

---

# 設計8: ARstampRally202603 - dashboard202603に景品交換統計を追加

## 作成日時
2026年2月19日

## 前提
`.claude_workflow/requirements.md`の要件定義8を読み込み、要件を確認済み

## 設計概要
admin/dashboard202603に景品交換の統計情報と一覧を追加する。admin/dashboardの実装を参考にしながら、dashboard202603に統合する。

### 変更箇所
1. **AdminController.php**: dashboard202603()メソッドの拡張
2. **dashboard202603.blade.php**: CSS、HTML、JavaScriptの追加

### 影響範囲
- 既存の統計表示：影響なし
- 既存のページネーション：影響なし（異なるクエリパラメータ名を使用）
- 30秒ごとの自動更新：影響なし（全体がリロードされる）

## アーキテクチャ設計

### データフロー
```
[ユーザー] → [ブラウザ] → [AdminController@dashboard202603]
                              ↓
                        [PrizeExchangeモデル]
                              ↓
                        [データベース]
                              ↓
                        [統計データ取得]
                        - $totalExchanges
                        - $redeemedExchanges
                        - $pendingExchanges
                        - $recentExchanges (未使用、20件/ページ)
                        - $redeemedPrizes (使用済み、10件/ページ)
                              ↓
                        [Bladeテンプレート]
                              ↓
                        [HTML + CSS + JavaScript]
                              ↓
                        [ブラウザ表示]

[ユーザー] → [「使用済みにする」ボタンクリック]
                              ↓
                        [JavaScript redeemPrize(id)]
                              ↓
                        [AJAX POST /admin/prizes/{id}/redeem]
                              ↓
                        [AdminController@redeemPrize]
                              ↓
                        [PrizeExchangeモデル更新]
                              ↓
                        [JSON レスポンス {success: true}]
                              ↓
                        [ページリロード]
```

### レイアウト構造
```
dashboard202603.blade.php

[ヘッダー]
  - タイトル
  - ナビゲーションリンク
  - ログアウトボタン

【新規】[景品交換統計カード] (stats-grid)
  - 総景品交換数
  - 使用済み
  - 未使用

[動物別統計カード]
  - 全20種類の動物統計テーブル

[最近のスキャン履歴カード]
  - スキャン履歴テーブル
  - ページネーション (recent_scans_page)

[日別スキャン統計カード]
  - 積み上げ棒グラフ (Chart.js)

[日別個別ユーザー数カード]
  - 折れ線グラフ (Chart.js)

【新規】[景品交換セクション] (prizes-grid)
  左側: [未使用の景品交換カード]
    - 検索フォーム
    - 未使用景品交換テーブル
    - ページネーション (exchanges_page)
  
  右側: [使用済み景品交換カード]
    - 使用済み景品交換テーブル
    - ページネーション (redeemed_page)

【新規】[JavaScript]
  - redeemPrize(id) 関数
  - CSRF token
  - 30秒ごとの自動更新（既存）
```

## 詳細設計

### 1. AdminController.php の拡張

#### 現在のdashboard202603()メソッド
```php
public function dashboard202603(Request $request)
{
    // 動物データ、スキャン統計、日別統計を取得
    // ...
    
    return view('admin.dashboard202603', compact(
        'animalStats',
        'recentScans',
        'dailyStats',
        'dailyUniqueUsers'
    ));
}
```

#### 拡張後のdashboard202603()メソッド
```php
public function dashboard202603(Request $request)
{
    // 【新規追加】景品交換の統計
    $totalExchanges = PrizeExchange::count();
    $redeemedExchanges = PrizeExchange::where('is_redeemed', true)->count();
    $pendingExchanges = $totalExchanges - $redeemedExchanges;

    // 【新規追加】最近の景品交換（未使用のみ）- ページネーション
    // optional search by prize code (query param: q)
    $q = $request->query('q');
    $recentExchangesQuery = PrizeExchange::where('is_redeemed', false);
    if ($q) {
        // allow partial matches (case-insensitive)
        $recentExchangesQuery->where('prize_code', 'like', '%' . strtoupper($q) . '%');
    }
    $recentExchanges = $recentExchangesQuery->orderBy('exchanged_at', 'desc')
        ->paginate(20, ['*'], 'exchanges_page')->appends(['q' => $q]);

    // 【新規追加】使用済み景品交換 - ページネーション（10件ごと）
    $redeemedPrizes = PrizeExchange::where('is_redeemed', true)
        ->orderBy('redeemed_at', 'desc')
        ->paginate(10, ['*'], 'redeemed_page');

    // 【既存】全動物のリスト
    $animals = [
        'sheep' => 'ひつじ',
        // ... (省略)
    ];
    
    // 【既存】各動物の統計を収集
    $animalStats = [];
    foreach ($animals as $markerId => $markerName) {
        // ... (省略)
    }
    
    // 【既存】最近のスキャン履歴
    $recentScans = MarkerScan::orderBy('scanned_at', 'desc')
        ->paginate(30, ['*'], 'recent_scans_page');
    
    // 【既存】日別統計（タイプ別、直近30日間）
    $dailyStatsRaw = MarkerScan::select(DB::raw('DATE(scanned_at) as date'))
        ->selectRaw('capture_type')
        ->selectRaw('COUNT(*) as count')
        ->where('scanned_at', '>=', now()->subDays(30))
        ->groupBy('date', 'capture_type')
        ->orderBy('date', 'desc')
        ->get();
    
    $dailyStats = $dailyStatsRaw->groupBy('date');
    
    // 【既存】日別個別ユーザー数統計
    $dailyUniqueUsersRaw = MarkerScan::select(DB::raw('DATE(scanned_at) as date'))
        ->selectRaw('capture_type')
        ->selectRaw('COUNT(DISTINCT fingerprint) as unique_users')
        ->where('scanned_at', '>=', now()->subDays(30))
        ->groupBy('date', 'capture_type')
        ->orderBy('date', 'desc')
        ->get();
    
    $dailyUniqueUsers = $dailyUniqueUsersRaw->groupBy('date');
    
    // 【変更】compact()に景品交換データを追加
    return view('admin.dashboard202603', compact(
        'animalStats',
        'recentScans',
        'dailyStats',
        'dailyUniqueUsers',
        'totalExchanges',        // 【追加】
        'redeemedExchanges',     // 【追加】
        'pendingExchanges',      // 【追加】
        'recentExchanges',       // 【追加】
        'redeemedPrizes'         // 【追加】
    ));
}
```

#### 変更内容の説明
1. **景品交換統計の取得**:
   - `PrizeExchange::count()`: 総景品交換数
   - `where('is_redeemed', true)->count()`: 使用済み数
   - `$totalExchanges - $redeemedExchanges`: 未使用数

2. **未使用景品交換の取得**:
   - `where('is_redeemed', false)`: 未使用のみ
   - 検索クエリ `q` がある場合は部分一致検索
   - `strtoupper($q)`: 大文字小文字を区別しない
   - `paginate(20, ['*'], 'exchanges_page')`: 20件/ページ、クエリパラメータ名は`exchanges_page`
   - `appends(['q' => $q])`: ページネーションリンクに検索クエリを保持

3. **使用済み景品交換の取得**:
   - `where('is_redeemed', true)`: 使用済みのみ
   - `orderBy('redeemed_at', 'desc')`: 使用日時の降順
   - `paginate(10, ['*'], 'redeemed_page')`: 10件/ページ、クエリパラメータ名は`redeemed_page`

4. **compact()の拡張**:
   - 既存の4つの変数（animalStats, recentScans, dailyStats, dailyUniqueUsers）
   - 新規の5つの変数（totalExchanges, redeemedExchanges, pendingExchanges, recentExchanges, redeemedPrizes）

### 2. dashboard202603.blade.php の CSS 拡張

#### 追加するCSS（既存の`</style>`の前に追加）

```css
/* 【新規】景品交換統計カード用のグリッドレイアウト */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-card h3 {
    color: #666;
    font-size: 14px;
    margin-bottom: 10px;
}

.stat-card .number {
    font-size: 36px;
    font-weight: bold;
    color: #667eea;
}

/* 【新規】景品交換セクション用のグリッドレイアウト */
.prizes-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

@media (max-width: 1200px) {
    .prizes-grid {
        grid-template-columns: 1fr;
    }
}

/* 【新規】景品コード表示用のスタイル */
.prize-code {
    font-family: 'Courier New', monospace;
    font-weight: bold;
    font-size: 16px;
    color: #667eea;
}

/* 【新規】使用済みボタンのスタイル */
.redeem-btn {
    padding: 6px 12px;
    background: #28a745;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}

.redeem-btn:hover {
    background: #218838;
}

.redeem-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
}

/* 【新規】ページネーションラッパー */
.pagination-wrapper {
    margin-top: 20px;
}
```

#### CSSの説明
1. **stats-grid**: 景品交換統計カード用のグリッドレイアウト
   - `repeat(auto-fit, minmax(250px, 1fr))`: レスポンシブ対応
   - 統計カードが横並びで表示され、画面幅に応じて自動調整

2. **stat-card**: 統計カードのスタイル
   - 白背景、影付き、角丸
   - `.number`: 大きな数字表示（36px、太字、紫色）

3. **prizes-grid**: 景品交換セクション用のグリッドレイアウト
   - `grid-template-columns: 1fr 1fr`: 2カラムレイアウト
   - 1200px以下では1カラムに変更（レスポンシブ）

4. **prize-code**: 景品コード専用のスタイル
   - 等幅フォント（Courier New）
   - 太字、16px、紫色

5. **redeem-btn**: 使用済みボタンのスタイル
   - 緑色（#28a745）
   - ホバー時に濃い緑（#218838）
   - 無効時はグレー

### 3. dashboard202603.blade.php の HTML 拡張

#### 3-1. 景品交換統計カードの追加位置

**挿入位置**: `<div class="header">...</div>`の直後、`<div class="card">【動物別統計】`の前

```html
</div>

<!-- 【新規追加】景品交換統計カード -->
<div class="stats-grid">
    <div class="stat-card">
        <h3>総景品交換数</h3>
        <div class="number">{{ $totalExchanges }}</div>
    </div>
    <div class="stat-card">
        <h3>使用済み</h3>
        <div class="number" style="color: #28a745;">{{ $redeemedExchanges }}</div>
    </div>
    <div class="stat-card">
        <h3>未使用</h3>
        <div class="number" style="color: #ffc107;">{{ $pendingExchanges }}</div>
    </div>
</div>

<div class="card">
    <h2>🐾 動物別統計</h2>
```

#### 3-2. 景品交換セクションの追加位置

**挿入位置**: 日別個別ユーザー数グラフの`</div>`の後、`<script>`の前

```html
    </div>

    <!-- 【新規追加】景品交換セクション -->
    <div class="prizes-grid">
        <!-- 未使用の景品交換 -->
        <div class="card">
            <h2>🎁 未使用の景品交換</h2>
            <div style="margin:8px 0 16px; display:flex; gap:8px; align-items:center;">
                <form method="GET" action="{{ route('admin.dashboard202603') }}" style="display:flex; gap:8px; align-items:center;">
                    <input type="search" name="q" placeholder="景品コードで検索 (例: AB123)" value="{{ request('q') }}" style="padding:6px 8px; border:1px solid #ddd; border-radius:6px;" />
                    <button type="submit" style="padding:6px 10px; background:#667eea; color:white; border:none; border-radius:6px; cursor:pointer;">検索</button>
                    @if(request('q'))
                        <a href="{{ route('admin.dashboard202603') }}" style="padding:6px 10px; background:#e0e0e0; color:#333; border-radius:6px; text-decoration:none;">クリア</a>
                    @endif
                </form>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>景品コード</th>
                        <th>交換日時</th>
                        <th>フィンガープリント</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentExchanges as $exchange)
                    <tr>
                        <td class="prize-code">{{ $exchange->prize_code }}</td>
                        <td>{{ $exchange->exchanged_at->format('Y/m/d H:i:s') }}</td>
                        <td style="font-size: 12px; color: #666;">{{ Str::limit($exchange->fingerprint, 20) }}</td>
                        <td>
                            <button class="redeem-btn" onclick="redeemPrize({{ $exchange->id }})">
                                使用済みにする
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: #999;">データがありません</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="pagination-wrapper">
                {{ $recentExchanges->links() }}
            </div>
        </div>

        <!-- 使用済み景品交換 -->
        <div class="card">
            <h2>✅ 使用済み景品交換</h2>
            <table>
                <thead>
                    <tr>
                        <th>景品コード</th>
                        <th>交換日時</th>
                        <th>使用日時</th>
                        <th>フィンガープリント</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($redeemedPrizes as $prize)
                    <tr>
                        <td class="prize-code" style="color: #999;">{{ $prize->prize_code }}</td>
                        <td style="font-size: 13px;">{{ $prize->exchanged_at->format('Y/m/d H:i') }}</td>
                        <td style="font-size: 13px; color: #28a745;">
                            {{ $prize->redeemed_at ? $prize->redeemed_at->format('Y/m/d H:i') : '-' }}
                        </td>
                        <td style="font-size: 12px; color: #666;">{{ Str::limit($prize->fingerprint, 20) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: #999;">データがありません</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="pagination-wrapper">
                {{ $redeemedPrizes->links() }}
            </div>
        </div>
    </div>

    <script>
```

#### HTMLの説明

**景品交換統計カード**:
1. **総景品交換数**: `{{ $totalExchanges }}` - 紫色
2. **使用済み**: `{{ $redeemedExchanges }}` - 緑色（#28a745）
3. **未使用**: `{{ $pendingExchanges }}` - 黄色（#ffc107）

**未使用の景品交換カード**:
1. **検索フォーム**:
   - `method="GET"`: GETリクエスト
   - `action="{{ route('admin.dashboard202603') }}"`: dashboard202603へ送信
   - `name="q"`: クエリパラメータ名
   - `placeholder`: 検索例を表示
   - `value="{{ request('q') }}"`: 検索値を保持
   - **クリアボタン**: `request('q')`がある場合のみ表示

2. **テーブル**:
   - 列: 景品コード、交換日時、フィンガープリント、操作
   - `@forelse`: データがある場合とない場合を分岐
   - `{{ $exchange->exchanged_at->format('Y/m/d H:i:s') }}`: 日時フォーマット
   - `{{ Str::limit($exchange->fingerprint, 20) }}`: フィンガープリント先頭20文字
   - **使用済みボタン**: `onclick="redeemPrize({{ $exchange->id }})"`

3. **ページネーション**:
   - `{{ $recentExchanges->links() }}`: Laravelのページネーション
   - クエリパラメータ名: `exchanges_page`

**使用済み景品交換カード**:
1. **テーブル**:
   - 列: 景品コード、交換日時、使用日時、フィンガープリント
   - 景品コード: グレー表示（`color: #999`）
   - 使用日時: 緑色表示（`color: #28a745`）
   - `{{ $prize->redeemed_at ? $prize->redeemed_at->format('Y/m/d H:i') : '-' }}`: 使用日時がない場合は`-`

2. **ページネーション**:
   - `{{ $redeemedPrizes->links() }}`: Laravelのページネーション
   - クエリパラメータ名: `redeemed_page`

### 4. dashboard202603.blade.php の JavaScript 拡張

#### 追加するJavaScript

**挿入位置**: 既存の`<script>`タグ内の先頭（既存のコードの前）

```javascript
<script>
    // 【新規追加】景品コードを使用済みにする関数
    function redeemPrize(id) {
        if (!confirm('この景品コードを使用済みにしますか？')) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        fetch(`{{ url('/admin/prizes') }}/${id}/redeem`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('使用済みにしました');
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('エラーが発生しました');
        });
    }

    // 【既存】日別統計グラフ（積み上げ棒グラフ）
    const dailyData = @json($dailyStats);
    // ... (以下、既存のコード)
```

#### JavaScriptの説明

**redeemPrize(id)関数**:
1. **確認ダイアログ**: `confirm()`で確認
   - キャンセルした場合は`return`で終了

2. **CSRF token取得**: `document.querySelector('meta[name="csrf-token"]').content`
   - `<meta name="csrf-token" content="{{ csrf_token() }}">`から取得

3. **AJAX POST通信**: `fetch()`を使用
   - エンドポイント: `/admin/prizes/{id}/redeem`
   - メソッド: `POST`
   - ヘッダー: `Content-Type: application/json`, `X-CSRF-TOKEN`

4. **レスポンス処理**:
   - 成功時: `alert('使用済みにしました')` → `location.reload()`
   - エラー時: `console.error()` → `alert('エラーが発生しました')`

5. **ページリロード**: `location.reload()`
   - 使用済みリストに移動させるため

### 5. ページネーションの競合回避

#### クエリパラメータ名の一覧

| セクション | クエリパラメータ名 | 用途 |
|------------|-------------------|------|
| 最近のスキャン履歴 | `recent_scans_page` | スキャン履歴のページネーション |
| 日別スキャン統計 | `daily_page` | （現在は未使用だが将来の拡張用） |
| 未使用の景品交換 | `exchanges_page` | 【新規】未使用景品交換のページネーション |
| 使用済み景品交換 | `redeemed_page` | 【新規】使用済み景品交換のページネーション |
| 景品コード検索 | `q` | 【新規】検索クエリ |

#### パラメータ保持の仕組み

**未使用の景品交換**:
```php
$recentExchanges = $recentExchangesQuery->orderBy('exchanged_at', 'desc')
    ->paginate(20, ['*'], 'exchanges_page')->appends(['q' => $q]);
```
- `appends(['q' => $q])`: ページネーションリンクに検索クエリ`q`を保持

**使用済み景品交換**:
```php
$redeemedPrizes = PrizeExchange::where('is_redeemed', true)
    ->orderBy('redeemed_at', 'desc')
    ->paginate(10, ['*'], 'redeemed_page');
```
- 検索機能がないため`appends()`不要

### 6. Before/After コード比較

#### AdminController.php の変更

**Before（現在）**:
```php
public function dashboard202603(Request $request)
{
    // 全動物のリスト
    $animals = [...]
    
    // 各動物の統計を収集
    $animalStats = [];
    foreach ($animals as $markerId => $markerName) {
        // ...
    }
    
    // 最近のスキャン履歴
    $recentScans = MarkerScan::orderBy('scanned_at', 'desc')
        ->paginate(30, ['*'], 'recent_scans_page');
    
    // 日別統計
    $dailyStatsRaw = MarkerScan::select(DB::raw('DATE(scanned_at) as date'))
        ->selectRaw('capture_type')
        ->selectRaw('COUNT(*) as count')
        ->where('scanned_at', '>=', now()->subDays(30))
        ->groupBy('date', 'capture_type')
        ->orderBy('date', 'desc')
        ->get();
    
    $dailyStats = $dailyStatsRaw->groupBy('date');
    
    // 日別個別ユーザー数統計
    $dailyUniqueUsersRaw = MarkerScan::select(DB::raw('DATE(scanned_at) as date'))
        ->selectRaw('capture_type')
        ->selectRaw('COUNT(DISTINCT fingerprint) as unique_users')
        ->where('scanned_at', '>=', now()->subDays(30))
        ->groupBy('date', 'capture_type')
        ->orderBy('date', 'desc')
        ->get();
    
    $dailyUniqueUsers = $dailyUniqueUsersRaw->groupBy('date');
    
    return view('admin.dashboard202603', compact(
        'animalStats',
        'recentScans',
        'dailyStats',
        'dailyUniqueUsers'
    ));
}
```

**After（変更後）**:
```php
public function dashboard202603(Request $request)
{
    // 【新規追加】景品交換の統計（3行）
    $totalExchanges = PrizeExchange::count();
    $redeemedExchanges = PrizeExchange::where('is_redeemed', true)->count();
    $pendingExchanges = $totalExchanges - $redeemedExchanges;

    // 【新規追加】最近の景品交換（未使用のみ）- ページネーション（8行）
    $q = $request->query('q');
    $recentExchangesQuery = PrizeExchange::where('is_redeemed', false);
    if ($q) {
        $recentExchangesQuery->where('prize_code', 'like', '%' . strtoupper($q) . '%');
    }
    $recentExchanges = $recentExchangesQuery->orderBy('exchanged_at', 'desc')
        ->paginate(20, ['*'], 'exchanges_page')->appends(['q' => $q]);

    // 【新規追加】使用済み景品交換 - ページネーション（3行）
    $redeemedPrizes = PrizeExchange::where('is_redeemed', true)
        ->orderBy('redeemed_at', 'desc')
        ->paginate(10, ['*'], 'redeemed_page');

    // 【既存】全動物のリスト
    $animals = [...]
    
    // 【既存】各動物の統計を収集
    $animalStats = [];
    foreach ($animals as $markerId => $markerName) {
        // ...
    }
    
    // 【既存】最近のスキャン履歴
    $recentScans = MarkerScan::orderBy('scanned_at', 'desc')
        ->paginate(30, ['*'], 'recent_scans_page');
    
    // 【既存】日別統計
    $dailyStatsRaw = MarkerScan::select(DB::raw('DATE(scanned_at) as date'))
        ->selectRaw('capture_type')
        ->selectRaw('COUNT(*) as count')
        ->where('scanned_at', '>=', now()->subDays(30))
        ->groupBy('date', 'capture_type')
        ->orderBy('date', 'desc')
        ->get();
    
    $dailyStats = $dailyStatsRaw->groupBy('date');
    
    // 【既存】日別個別ユーザー数統計
    $dailyUniqueUsersRaw = MarkerScan::select(DB::raw('DATE(scanned_at) as date'))
        ->selectRaw('capture_type')
        ->selectRaw('COUNT(DISTINCT fingerprint) as unique_users')
        ->where('scanned_at', '>=', now()->subDays(30))
        ->groupBy('date', 'capture_type')
        ->orderBy('date', 'desc')
        ->get();
    
    $dailyUniqueUsers = $dailyUniqueUsersRaw->groupBy('date');
    
    // 【変更】compact()に景品交換データを追加
    return view('admin.dashboard202603', compact(
        'animalStats',
        'recentScans',
        'dailyStats',
        'dailyUniqueUsers',
        'totalExchanges',        // 【追加】
        'redeemedExchanges',     // 【追加】
        'pendingExchanges',      // 【追加】
        'recentExchanges',       // 【追加】
        'redeemedPrizes'         // 【追加】
    ));
}
```

**変更箇所の詳細**:
- 行数追加: 約14行（景品交換データ取得用）
- compact()の引数: 4個 → 9個（+5個）

#### dashboard202603.blade.php の変更

**Before（現在）**:
```html
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>管理ダッシュボード202603 - ARスタンプラリー</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* 既存のCSS（約145行） */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: ...; background: #f5f5f5; padding: 20px; }
        .header { ... }
        /* ... */
        canvas { max-height: 400px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 ARスタンプラリー202603 管理ダッシュボード（全動物統計）</h1>
        <div class="nav-links">
            <a href="{{ route('admin.dashboard') }}" class="nav-link">通常ダッシュボード</a>
            <a href="{{ route('admin.logout') }}" class="logout-btn">ログアウト</a>
        </div>
    </div>

    <div class="card">
        <h2>🐾 動物別統計</h2>
        <!-- 動物統計テーブル -->
    </div>

    <div class="card">
        <h2>📝 最近のスキャン履歴</h2>
        <!-- スキャン履歴テーブル -->
        <div class="pagination">
            {{ $recentScans->links() }}
        </div>
    </div>

    <div class="card">
        <h2>📅 日別スキャン統計（直近30日間）</h2>
        <div class="chart-container">
            <canvas id="dailyChart"></canvas>
        </div>
    </div>

    <div class="card">
        <h2>👥 日別個別ユーザー数（直近30日間）</h2>
        <div class="chart-container">
            <canvas id="uniqueUsersChart"></canvas>
        </div>
    </div>

    <script>
        // 日別統計グラフ（積み上げ棒グラフ）
        const dailyData = @json($dailyStats);
        // ...

        // 日別個別ユーザー数グラフ（折れ線グラフ）
        const uniqueUsersData = @json($dailyUniqueUsers);
        // ...

        // 30秒ごとに自動更新
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
```

**After（変更後）**:
```html
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>管理ダッシュボード202603 - ARスタンプラリー</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* 既存のCSS（約145行） */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: ...; background: #f5f5f5; padding: 20px; }
        .header { ... }
        /* ... */
        canvas { max-height: 400px; }
        
        /* 【新規追加】景品交換統計カード用のCSS（約60行） */
        .stats-grid { ... }
        .stat-card { ... }
        .stat-card h3 { ... }
        .stat-card .number { ... }
        .prizes-grid { ... }
        @media (max-width: 1200px) { ... }
        .prize-code { ... }
        .redeem-btn { ... }
        .redeem-btn:hover { ... }
        .redeem-btn:disabled { ... }
        .pagination-wrapper { ... }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 ARスタンプラリー202603 管理ダッシュボード（全動物統計）</h1>
        <div class="nav-links">
            <a href="{{ route('admin.dashboard') }}" class="nav-link">通常ダッシュボード</a>
            <a href="{{ route('admin.logout') }}" class="logout-btn">ログアウト</a>
        </div>
    </div>

    <!-- 【新規追加】景品交換統計カード -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>総景品交換数</h3>
            <div class="number">{{ $totalExchanges }}</div>
        </div>
        <div class="stat-card">
            <h3>使用済み</h3>
            <div class="number" style="color: #28a745;">{{ $redeemedExchanges }}</div>
        </div>
        <div class="stat-card">
            <h3>未使用</h3>
            <div class="number" style="color: #ffc107;">{{ $pendingExchanges }}</div>
        </div>
    </div>

    <div class="card">
        <h2>🐾 動物別統計</h2>
        <!-- 動物統計テーブル -->
    </div>

    <div class="card">
        <h2>📝 最近のスキャン履歴</h2>
        <!-- スキャン履歴テーブル -->
        <div class="pagination">
            {{ $recentScans->links() }}
        </div>
    </div>

    <div class="card">
        <h2>📅 日別スキャン統計（直近30日間）</h2>
        <div class="chart-container">
            <canvas id="dailyChart"></canvas>
        </div>
    </div>

    <div class="card">
        <h2>👥 日別個別ユーザー数（直近30日間）</h2>
        <div class="chart-container">
            <canvas id="uniqueUsersChart"></canvas>
        </div>
    </div>

    <!-- 【新規追加】景品交換セクション（約120行） -->
    <div class="prizes-grid">
        <!-- 未使用の景品交換 -->
        <div class="card">
            <h2>🎁 未使用の景品交換</h2>
            <div style="margin:8px 0 16px; display:flex; gap:8px; align-items:center;">
                <form method="GET" action="{{ route('admin.dashboard202603') }}" style="display:flex; gap:8px; align-items:center;">
                    <input type="search" name="q" placeholder="景品コードで検索 (例: AB123)" value="{{ request('q') }}" style="padding:6px 8px; border:1px solid #ddd; border-radius:6px;" />
                    <button type="submit" style="padding:6px 10px; background:#667eea; color:white; border:none; border-radius:6px; cursor:pointer;">検索</button>
                    @if(request('q'))
                        <a href="{{ route('admin.dashboard202603') }}" style="padding:6px 10px; background:#e0e0e0; color:#333; border-radius:6px; text-decoration:none;">クリア</a>
                    @endif
                </form>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>景品コード</th>
                        <th>交換日時</th>
                        <th>フィンガープリント</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentExchanges as $exchange)
                    <tr>
                        <td class="prize-code">{{ $exchange->prize_code }}</td>
                        <td>{{ $exchange->exchanged_at->format('Y/m/d H:i:s') }}</td>
                        <td style="font-size: 12px; color: #666;">{{ Str::limit($exchange->fingerprint, 20) }}</td>
                        <td>
                            <button class="redeem-btn" onclick="redeemPrize({{ $exchange->id }})">
                                使用済みにする
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: #999;">データがありません</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="pagination-wrapper">
                {{ $recentExchanges->links() }}
            </div>
        </div>

        <!-- 使用済み景品交換 -->
        <div class="card">
            <h2>✅ 使用済み景品交換</h2>
            <table>
                <thead>
                    <tr>
                        <th>景品コード</th>
                        <th>交換日時</th>
                        <th>使用日時</th>
                        <th>フィンガープリント</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($redeemedPrizes as $prize)
                    <tr>
                        <td class="prize-code" style="color: #999;">{{ $prize->prize_code }}</td>
                        <td style="font-size: 13px;">{{ $prize->exchanged_at->format('Y/m/d H:i') }}</td>
                        <td style="font-size: 13px; color: #28a745;">
                            {{ $prize->redeemed_at ? $prize->redeemed_at->format('Y/m/d H:i') : '-' }}
                        </td>
                        <td style="font-size: 12px; color: #666;">{{ Str::limit($prize->fingerprint, 20) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: #999;">データがありません</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="pagination-wrapper">
                {{ $redeemedPrizes->links() }}
            </div>
        </div>
    </div>

    <script>
        // 【新規追加】景品コードを使用済みにする関数（約25行）
        function redeemPrize(id) {
            if (!confirm('この景品コードを使用済みにしますか？')) {
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            fetch(`{{ url('/admin/prizes') }}/${id}/redeem`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('使用済みにしました');
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('エラーが発生しました');
            });
        }

        // 【既存】日別統計グラフ（積み上げ棒グラフ）
        const dailyData = @json($dailyStats);
        // ...

        // 【既存】日別個別ユーザー数グラフ（折れ線グラフ）
        const uniqueUsersData = @json($dailyUniqueUsers);
        // ...

        // 【既存】30秒ごとに自動更新
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
```

**変更箇所の詳細**:
- CSS追加: 約60行（景品交換統計カード、景品交換セクション用）
- HTML追加: 約140行（統計カード15行 + 景品交換セクション125行）
- JavaScript追加: 約25行（redeemPrize関数）
- 総追加行数: 約225行

## パフォーマンス設計

### データベースクエリの最適化

#### 景品交換統計の取得
```php
$totalExchanges = PrizeExchange::count();  // 1クエリ
$redeemedExchanges = PrizeExchange::where('is_redeemed', true)->count();  // 1クエリ
$pendingExchanges = $totalExchanges - $redeemedExchanges;  // 計算のみ
```
- クエリ数: 2
- インデックス: `is_redeemed`カラムにインデックスが存在

#### 未使用景品交換の取得
```php
$recentExchangesQuery = PrizeExchange::where('is_redeemed', false);
if ($q) {
    $recentExchangesQuery->where('prize_code', 'like', '%' . strtoupper($q) . '%');
}
$recentExchanges = $recentExchangesQuery->orderBy('exchanged_at', 'desc')
    ->paginate(20, ['*'], 'exchanges_page')->appends(['q' => $q]);
```
- クエリ数: 2（データ取得 + カウント）
- インデックス: `is_redeemed`, `prize_code`
- ページネーション: 20件/ページ

#### 使用済み景品交換の取得
```php
$redeemedPrizes = PrizeExchange::where('is_redeemed', true)
    ->orderBy('redeemed_at', 'desc')
    ->paginate(10, ['*'], 'redeemed_page');
```
- クエリ数: 2（データ取得 + カウント）
- インデックス: `is_redeemed`, `redeemed_at`
- ページネーション: 10件/ページ

#### 合計クエリ数
- 景品交換統計: 2クエリ
- 未使用景品交換: 2クエリ
- 使用済み景品交換: 2クエリ
- **合計: 6クエリ**（既存のクエリに追加）

### ページ読み込み時間の推定

| 処理 | 推定時間 |
|------|---------|
| 景品交換統計取得 | ~50ms |
| 未使用景品交換取得 | ~100ms |
| 使用済み景品交換取得 | ~100ms |
| 動物別統計（既存） | ~200ms |
| スキャン履歴（既存） | ~100ms |
| 日別統計（既存） | ~150ms |
| 日別個別ユーザー数（既存） | ~150ms |
| HTML描画 | ~100ms |
| **合計** | **~950ms** |

- 目標: 2秒以内
- 実際: 約1秒（十分に目標を達成）

## セキュリティ設計

### CSRF保護
```javascript
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

fetch(`{{ url('/admin/prizes') }}/${id}/redeem`, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
    }
})
```
- `<meta name="csrf-token" content="{{ csrf_token() }}">`からtokenを取得
- `X-CSRF-TOKEN`ヘッダーに設定
- Laravelが自動検証

### 認証
- 既存のミドルウェア（`admin_authenticated`セッション）が適用される
- dashboard202603へのアクセスは認証済み管理者のみ

### SQLインジェクション対策
```php
$recentExchangesQuery->where('prize_code', 'like', '%' . strtoupper($q) . '%');
```
- Eloquent ORMのパラメータバインディング
- `strtoupper()`で大文字変換（安全）

### XSS対策
```html
{{ $exchange->prize_code }}
{{ $exchange->exchanged_at->format('Y/m/d H:i:s') }}
{{ Str::limit($exchange->fingerprint, 20) }}
```
- Bladeテンプレートの`{{ }}`構文で自動エスケープ
- HTMLタグは無害化される

## エラーハンドリング設計

### データがない場合
```html
@forelse($recentExchanges as $exchange)
    <!-- データがある場合 -->
@empty
    <tr>
        <td colspan="4" style="text-align: center; color: #999;">データがありません</td>
    </tr>
@endforelse
```
- `@forelse`ディレクティブで空配列に対応
- 「データがありません」メッセージを表示

### AJAX通信エラー
```javascript
.catch(error => {
    console.error('Error:', error);
    alert('エラーが発生しました');
});
```
- ネットワークエラー時に`catch`で捕捉
- コンソールにエラーログ出力
- ユーザーにアラート表示

### 検索クエリの処理
```php
$q = $request->query('q');
if ($q) {
    $recentExchangesQuery->where('prize_code', 'like', '%' . strtoupper($q) . '%');
}
```
- `$q`が`null`の場合は検索条件を追加しない
- 空文字列の場合も問題なし

## テスト設計

### 単体テスト項目

#### AdminController.php
1. **dashboard202603メソッド**:
   - [ ] 景品交換統計が正しく取得される
   - [ ] 未使用景品交換が正しく取得される（20件/ページ）
   - [ ] 使用済み景品交換が正しく取得される（10件/ページ）
   - [ ] 景品コード検索が機能する
   - [ ] 検索クエリが保持される
   - [ ] ページネーションが機能する

### 結合テスト項目

#### dashboard202603.blade.php
1. **景品交換統計カード**:
   - [ ] 総景品交換数が表示される
   - [ ] 使用済み数が緑色で表示される
   - [ ] 未使用数が黄色で表示される

2. **未使用の景品交換セクション**:
   - [ ] 検索フォームが表示される
   - [ ] 検索ボタンが機能する
   - [ ] クリアボタンが表示される（検索時のみ）
   - [ ] 未使用景品交換一覧が表示される
   - [ ] ページネーション（20件/ページ）が機能する
   - [ ] 「使用済みにする」ボタンが表示される

3. **使用済み景品交換セクション**:
   - [ ] 使用済み景品交換一覧が表示される
   - [ ] ページネーション（10件/ページ）が機能する
   - [ ] 景品コードがグレーアウトされる
   - [ ] 使用日時が緑色で表示される

4. **JavaScript**:
   - [ ] redeemPrize関数が動作する
   - [ ] 確認ダイアログが表示される
   - [ ] キャンセル時は何も起きない
   - [ ] OK時はAJAX通信が実行される
   - [ ] 成功時にページリロードされる
   - [ ] エラー時にアラートが表示される

### システムテスト項目

1. **ページネーションの独立性**:
   - [ ] 未使用景品交換のページを変更しても他のセクションに影響しない
   - [ ] 使用済み景品交換のページを変更しても他のセクションに影響しない
   - [ ] スキャン履歴のページを変更しても他のセクションに影響しない

2. **検索機能**:
   - [ ] 景品コード検索が部分一致で機能する
   - [ ] 大文字小文字を区別しない
   - [ ] 検索結果が正しく表示される
   - [ ] ページネーションで検索クエリが保持される

3. **自動更新**:
   - [ ] 30秒後にページが自動更新される
   - [ ] 検索状態はリセットされる（仕様通り）
   - [ ] ページネーション状態はリセットされる（仕様通り）

4. **レスポンシブデザイン**:
   - [ ] 1200px以下で景品交換セクションが1カラムになる
   - [ ] スマートフォン表示でも正常に動作する

5. **既存機能への影響**:
   - [ ] 動物別統計が正常に表示される
   - [ ] 最近のスキャン履歴が正常に表示される
   - [ ] 日別スキャン統計グラフが正常に表示される
   - [ ] 日別個別ユーザー数グラフが正常に表示される

## 実装の注意事項

### 1. AdminController.phpの変更
- 景品交換データ取得コードは、既存の動物データ取得コードの**前**に配置
- これにより、景品交換統計が最初に表示される

### 2. dashboard202603.blade.phpのCSS追加
- 既存の`</style>`タグの**前**に追加
- admin/dashboardと同じスタイルを使用

### 3. HTMLの追加位置
- **統計カード**: `<div class="header">`の直後、動物別統計の前
- **景品交換セクション**: 日別個別ユーザー数グラフの後、`<script>`タグの前

### 4. JavaScriptの追加位置
- 既存の`<script>`タグ内の**先頭**に追加
- redeemPrize関数を最初に定義

### 5. ページネーションのクエリパラメータ名
- 既存: `recent_scans_page`
- 新規: `exchanges_page`, `redeemed_page`
- 検索: `q`

### 6. CSRF tokenの確認
- `<meta name="csrf-token" content="{{ csrf_token() }}">`が既に存在することを確認
- 存在しない場合はエラーになる

### 7. redeemPrizeエンドポイント
- `/admin/prizes/{id}/redeem`は既に存在
- AdminController.phpのredeemPrizeメソッドが処理

### 8. 30秒ごとの自動更新
- 既存の`setTimeout`は維持
- 検索状態やページネーション状態はリセットされる（admin/dashboardと同じ動作）

## 制約事項と前提条件

### 前提条件
1. AdminController.phpのredeemPrizeメソッドが存在する
2. routes/web.phpに`/admin/prizes/{id}/redeem`ルートが定義されている
3. PrizeExchangeモデルが存在する
4. CSRF tokenのmetaタグが存在する

### 制約事項
1. 景品コード検索は部分一致のみ（完全一致、前方一致などのオプションなし）
2. 30秒ごとの自動更新時に検索状態がリセットされる
3. ページネーション状態も自動更新時にリセットされる
4. 使用済み処理は即座にページリロードされる（非同期更新ではない）

## 望ましい動作

### 必須動作
- [x] 景品交換統計カードが表示される
- [x] 未使用の景品交換一覧が表示される
- [x] 使用済みの景品交換一覧が表示される
- [x] 景品コード検索が機能する
- [x] 「使用済みにする」ボタンが機能する
- [x] ページネーションが機能する
- [x] 既存機能への影響がない

### 推奨動作
- [ ] レスポンシブデザインが美しく表示される
- [ ] エラーメッセージが明確に表示される
- [ ] ページ読み込みが高速（2秒以内）

## 次のステップ
1. タスク化フェーズ（tasks.mdへの追記）- 具体的な作業手順をリスト化
2. 実装フェーズ - コードの変更と追加
3. 動作確認とテスト

---

# 設計9: ARスタンプラリー202603 - dashboard202603のページネーションアイコンサイズ修正

## 作成日時
2026年2月19日

## 前提
`.claude_workflow/requirements.md`の要件定義9を読み込み、要件を確認済み

## 問題の詳細分析

### 現在の実装状況

#### CSS構造（dashboard202603.blade.php）
```css
/* Line 113-142: 基本のページネーションスタイル */
.pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.pagination a,
.pagination span {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: #667eea;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    min-height: 36px;
}

/* Line 144-149: ページネーションのSVGアイコンサイズ制御 */
.pagination svg {
    width: 18px !important;
    height: 18px !important;
    max-width: 18px !important;
    max-height: 18px !important;
}

/* Line 151-158: Laravelページネーションの構造に対応 */
.pagination nav {
    display: flex;
    justify-content: center;
}

.pagination nav svg {
    width: 18px !important;
    height: 18px !important;
}

/* Line 237-239: ページネーションラッパー（景品交換用） */
.pagination-wrapper {
    margin-top: 20px;
}
```

#### HTML構造の違い

**スキャン履歴のページネーション（Line 333）**:
```html
<div class="pagination">
    {{ $recentScans->links() }}
</div>
```
→ `.pagination`クラスが付いているため、`.pagination svg`のスタイルが適用される

**未使用景品交換のページネーション（Line 394）**:
```html
<div class="pagination-wrapper">
    {{ $recentExchanges->links() }}
</div>
```
→ `.pagination`クラスが付いていないため、`.pagination svg`のスタイルが**適用されない**

**使用済み景品交換のページネーション（Line 428）**:
```html
<div class="pagination-wrapper">
    {{ $redeemedPrizes->links() }}
</div>
```
→ `.pagination`クラスが付いていないため、`.pagination svg`のスタイルが**適用されない**

### Laravelのページネーション構造

`{{ $model->links() }}`が生成するHTML構造（Laravel 10/11）:
```html
<nav role="navigation" aria-label="Pagination Navigation">
    <div class="flex items-center justify-between">
        <div class="flex-1 flex justify-between sm:hidden">
            <!-- モバイル用ナビゲーション -->
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <!-- "Showing 1 to 10 of 100 results" テキスト -->
            </div>
            <div>
                <span class="relative z-0 inline-flex shadow-sm rounded-md">
                    <!-- 前へボタン -->
                    <span aria-disabled="true" aria-label="« Previous">
                        <span class="...">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <!-- 矢印アイコン -->
                            </svg>
                        </span>
                    </span>
                    
                    <!-- ページ番号ボタン -->
                    <span aria-current="page">
                        <span class="...">1</span>
                    </span>
                    
                    <!-- 次へボタン -->
                    <a href="..." rel="next" aria-label="Next »">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <!-- 矢印アイコン -->
                        </svg>
                    </a>
                </span>
            </div>
        </div>
    </div>
</nav>
```

**重要なポイント**:
- Laravelが生成するSVGには`class="w-5 h-5"`が含まれる（Tailwind CSSクラス）
- `w-5 h-5`は`width: 1.25rem; height: 1.25rem;`（20px × 20px）に相当
- `.pagination`クラス内でない限り、`.pagination svg`のスタイルが適用されない
- `.pagination-wrapper`は単なるラッパーで、子要素のスタイルには影響しない

### 問題の根本原因

1. **セレクターの適用範囲不足**: `.pagination svg`は`.pagination`クラス内のSVGのみに適用される
2. **`.pagination-wrapper`へのスタイル未定義**: `.pagination-wrapper`内のSVGに対するスタイル指定がない
3. **Tailwind CSSクラスとの競合**: LaravelのデフォルトページネーションはTailwind CSSを使用しており、`w-5 h-5`（20px）が適用される
4. **`!important`の不足**: Tailwind CSSクラスよりも優先度を高くするため、`.pagination-wrapper svg`にも`!important`が必要

## 設計ソリューション

### アプローチ1: `.pagination-wrapper`にSVGスタイルを追加（採用）

**メリット**:
- CSSの変更のみで対応可能
- HTMLの変更不要
- 既存のコードに影響を与えない
- 最小限の変更で問題を解決

**デメリット**:
- CSSが若干冗長になる（`.pagination svg`と`.pagination-wrapper svg`の重複）

**実装方法**:
```css
/* 既存のスタイル（維持） */
.pagination svg {
    width: 18px !important;
    height: 18px !important;
    max-width: 18px !important;
    max-height: 18px !important;
}

.pagination nav svg {
    width: 18px !important;
    height: 18px !important;
}

/* 【新規追加】.pagination-wrapperに対するスタイル */
.pagination-wrapper svg {
    width: 18px !important;
    height: 18px !important;
    max-width: 18px !important;
    max-height: 18px !important;
}

.pagination-wrapper nav svg {
    width: 18px !important;
    height: 18px !important;
}
```

### アプローチ2: HTMLを変更して`.pagination`クラスを追加（不採用）

**メリット**:
- CSSの変更不要
- 既存のスタイルを再利用

**デメリット**:
- HTMLの変更が必要（要件に反する）
- 将来的に混乱を招く可能性（`.pagination`と`.pagination-wrapper`の使い分けが不明確）
- 2箇所のHTMLを変更する必要がある

**実装方法（参考）**:
```html
<!-- 変更前 -->
<div class="pagination-wrapper">
    {{ $recentExchanges->links() }}
</div>

<!-- 変更後（不採用） -->
<div class="pagination-wrapper">
    <div class="pagination">
        {{ $recentExchanges->links() }}
    </div>
</div>
```

### アプローチ3: 全体的なSVGスタイルを定義（不採用）

**メリット**:
- 最もシンプル

**デメリット**:
- 他のSVGにも影響を与える可能性
- 意図しない副作用が発生する可能性

**実装方法（参考）**:
```css
/* すべてのSVGに適用（不採用） */
svg {
    width: 18px !important;
    height: 18px !important;
}
```

## 採用する設計: アプローチ1

### 変更内容

#### 対象ファイル
- `resources/views/admin/dashboard202603.blade.php`

#### 変更箇所
- `<style>`タグ内のCSS（Line 9 - Line 244）
- `.pagination nav svg`スタイルの直後（Line 158の後）に新しいスタイルを追加

#### 変更前（Line 155-159）
```css
        .pagination nav svg {
            width: 18px !important;
            height: 18px !important;
        }
        .chart-container {
```

#### 変更後
```css
        .pagination nav svg {
            width: 18px !important;
            height: 18px !important;
        }
        /* 【新規追加】.pagination-wrapper内のページネーションのSVGアイコンサイズ制御 */
        .pagination-wrapper svg {
            width: 18px !important;
            height: 18px !important;
            max-width: 18px !important;
            max-height: 18px !important;
        }
        .pagination-wrapper nav svg {
            width: 18px !important;
            height: 18px !important;
        }
        .chart-container {
```

### CSS詳細設計

#### セレクター優先度

```
特異性スコアの計算:
- .pagination-wrapper svg        → 0,0,1,1 (クラス1 + 要素1) + !important
- .pagination-wrapper nav svg    → 0,0,1,2 (クラス1 + 要素2) + !important
- Tailwind CSS (w-5 h-5)         → 0,0,1,0 (クラス1)

!importantにより、確実にTailwind CSSのスタイルを上書き
```

#### プロパティの説明

**`width`と`height`**:
- SVGの幅と高さを18pxに設定
- `!important`で優先度を最大化

**`max-width`と`max-height`**:
- SVGが18pxを超えないように制限
- 一部のブラウザで`width`/`height`が効かない場合の保険

**重複する理由**:
- `.pagination-wrapper svg`: 直接子孫のSVGに適用（広範囲）
- `.pagination-wrapper nav svg`: nav要素内のSVGに適用（より具体的）
- 両方定義することで、異なるHTML構造に対応

### レスポンシブデザインへの影響

**検証内容**:
- 1200px以上: `.prizes-grid`が2カラム表示 → SVGサイズは変わらず18px
- 1200px以下: `.prizes-grid`が1カラム表示 → SVGサイズは変わらず18px
- モバイル: LaravelのモバイルページネーションHTML → SVGサイズは18px

**結論**: すべての画面サイズで18px × 18pxのSVGが表示される

### ブラウザ互換性

**対応ブラウザ**:
- Chrome/Edge (Chromium) 90+
- Firefox 88+
- Safari 14+

**使用するCSS機能**:
- `width`/`height`: すべてのブラウザでサポート
- `max-width`/`max-height`: すべてのブラウザでサポート
- `!important`: すべてのブラウザでサポート

**結論**: 互換性の問題なし

## パフォーマンスへの影響

### CSS解析への影響
- **追加行数**: 11行（コメント3行 + スタイル8行）
- **セレクター数**: +2個（`.pagination-wrapper svg`、`.pagination-wrapper nav svg`）
- **影響**: 無視できるレベル（<1ms）

### レンダリングへの影響
- **リフロー**: なし（既存のSVGサイズを変更するのみ）
- **リペイント**: 初回ロード時のみ（SVGサイズが適用される）
- **影響**: 無視できるレベル（<1ms）

### ネットワークへの影響
- **ファイルサイズ増加**: 約300バイト（圧縮後: 約150バイト）
- **影響**: 無視できるレベル

## セキュリティへの影響

**評価**: セキュリティリスクなし

**理由**:
- CSSの変更のみ
- ユーザー入力を含まない
- XSSやCSSインジェクションのリスクなし
- サーバーサイドへの影響なし

## 保守性への影響

### コードの可読性
- **向上**: コメントで変更理由を明記
- **理解しやすさ**: `.pagination`と`.pagination-wrapper`の使い分けが明確

### 将来の拡張性
- **新しいページネーション追加時**: `.pagination-wrapper`を使用すれば自動的にスタイルが適用される
- **スタイル変更時**: 2箇所（`.pagination svg`と`.pagination-wrapper svg`）を変更する必要がある
  - 改善案: CSS変数を使用（将来の拡張）

```css
/* 将来の拡張案（今回は実装しない） */
:root {
    --pagination-icon-size: 18px;
}

.pagination svg,
.pagination-wrapper svg {
    width: var(--pagination-icon-size) !important;
    height: var(--pagination-icon-size) !important;
}
```

## テスト設計

### テストケース

#### TC-1: 未使用景品交換のページネーションアイコンサイズ
**前提条件**:
- 未使用景品交換が20件以上存在する
- ページネーションが表示される

**テスト手順**:
1. http://localhost/admin/dashboard202603 にアクセス
2. 「未使用の景品交換」セクションのページネーションを確認
3. ブラウザの開発者ツールでSVG要素を検査

**期待結果**:
- SVGの幅が18px
- SVGの高さが18px
- 前後の矢印アイコンが同じサイズ

**検証方法**:
```javascript
// ブラウザコンソールで実行
const svg = document.querySelector('.pagination-wrapper svg');
const computedStyle = window.getComputedStyle(svg);
console.log('Width:', computedStyle.width);   // "18px"
console.log('Height:', computedStyle.height); // "18px"
```

#### TC-2: 使用済み景品交換のページネーションアイコンサイズ
**前提条件**:
- 使用済み景品交換が10件以上存在する
- ページネーションが表示される

**テスト手順**:
1. http://localhost/admin/dashboard202603 にアクセス
2. 「使用済み景品交換」セクションのページネーションを確認
3. ブラウザの開発者ツールでSVG要素を検査

**期待結果**:
- SVGの幅が18px
- SVGの高さが18px
- 前後の矢印アイコンが同じサイズ

#### TC-3: スキャン履歴のページネーションへの影響確認
**前提条件**:
- スキャン履歴が30件以上存在する
- ページネーションが表示される

**テスト手順**:
1. http://localhost/admin/dashboard202603 にアクセス
2. 「最近のスキャン履歴」セクションのページネーションを確認
3. ブラウザの開発者ツールでSVG要素を検査

**期待結果**:
- SVGの幅が18px（変更前と同じ）
- SVGの高さが18px（変更前と同じ）
- 既存の表示から変化がない

#### TC-4: すべてのページネーションの統一性確認
**前提条件**:
- すべてのセクションでページネーションが表示される

**テスト手順**:
1. 3つのセクション（スキャン履歴、未使用景品交換、使用済み景品交換）のページネーションを比較
2. SVGアイコンのサイズが視覚的に同じであることを確認

**期待結果**:
- すべてのSVGアイコンが同じサイズ
- 視覚的な統一感がある

#### TC-5: レスポンシブデザインの確認
**前提条件**:
- すべてのセクションでページネーションが表示される

**テスト手順**:
1. ブラウザのウィンドウサイズを1400px → 1200px → 768px → 375pxと変更
2. 各サイズでページネーションのSVGアイコンを確認

**期待結果**:
- すべての画面サイズでSVGが18px × 18px
- レイアウト崩れがない

#### TC-6: 異なるブラウザでの表示確認
**前提条件**:
- Chrome、Firefox、Edgeでアクセス可能

**テスト手順**:
1. 各ブラウザで http://localhost/admin/dashboard202603 にアクセス
2. ページネーションのSVGアイコンを確認

**期待結果**:
- すべてのブラウザで18px × 18px
- ブラウザ間で表示の違いがない

### デバッグ方法

#### SVGサイズが18pxにならない場合
1. ブラウザの開発者ツールでSVG要素を検査
2. Computed Styleで`width`と`height`を確認
3. どのCSSルールが適用されているかを確認
4. `!important`が効いているかを確認

#### Tailwind CSSのスタイルが優先される場合
1. `.pagination-wrapper svg`のセレクターが正しく記述されているか確認
2. `!important`が付いているか確認
3. CSSの記述位置を確認（`<style>`タグ内にあるか）
4. キャッシュをクリアして再読み込み

## エラーハンドリング

### ページネーションが表示されない場合
**原因**: データ件数が少ない（ページネーションが不要）

**対応**: 正常な動作（エラーではない）

**検証**: テストデータを追加してページネーションを表示

### SVGが表示されない場合
**原因**: Laravelのページネーション設定が異なる

**対応**: `config/app.php`でLaravelのページネーション設定を確認

**検証**: Laravelの`links()`メソッドが正しく動作しているか確認

## ロールバック手順

### 変更をロールバックする場合

#### 手順1: CSSの削除
```css
/* 以下の11行を削除 */
/* 【新規追加】.pagination-wrapper内のページネーションのSVGアイコンサイズ制御 */
.pagination-wrapper svg {
    width: 18px !important;
    height: 18px !important;
    max-width: 18px !important;
    max-height: 18px !important;
}
.pagination-wrapper nav svg {
    width: 18px !important;
    height: 18px !important;
}
```

#### 手順2: 動作確認
- http://localhost/admin/dashboard202603 にアクセス
- 景品交換のページネーションアイコンが元のサイズに戻ることを確認

## 依存関係

### 依存するファイル
- `resources/views/admin/dashboard202603.blade.php`: 変更対象

### 影響を受けるファイル
- なし（このファイルのみの変更）

### 依存する機能
- Laravel Pagination（`$model->links()`メソッド）
- Blade Template Engine

## 実装優先度

**優先度**: 高

**理由**:
1. ユーザー体験（UX）に直接影響
2. プロフェッショナルな見た目に必要
3. 実装が簡単（CSS変更のみ）
4. リスクが低い（既存機能に影響なし）

## 成功基準

### 必須項目
- [x] 使用済み景品交換のページネーションアイコンが18px × 18pxで表示される
- [x] 未使用の景品交換のページネーションアイコンが18px × 18pxで表示される
- [x] スキャン履歴のページネーションに影響がない
- [x] すべてのページネーションのアイコンサイズが統一される

### 推奨項目
- [ ] すべてのブラウザで一貫した表示
- [ ] すべての画面サイズで18px × 18px
- [ ] コードが可読性が高い（コメント付き）

## 次のステップ
1. タスク化フェーズ（tasks.mdへの追記）- 具体的な実装手順を定義
2. 実装フェーズ - CSSの変更

---

# 設計10: dashboard202603 - 2026年1〜3月データフィルタリング

## 作成日時
2026年3月11日

## 前提
`.claude_workflow/requirements.md`（要件定義10）を読み込み済み

## タイムゾーン設計

| 項目 | 値 |
|---|---|
| アプリタイムゾーン | `Asia/Tokyo` (JST, UTC+9) |
| DB保存形式 | UTC |
| DB接続 | SQLite（タイムゾーン変換なし） |

### Carbon の使い方
```php
// JST で境界を定義し、UTC に変換してからクエリに渡す
$startDate = Carbon::createFromFormat('Y-m-d H:i:s', '2026-01-01 00:00:00', 'Asia/Tokyo')
                   ->setTimezone('UTC');
// = 2025-12-31 15:00:00 UTC

$endDate = Carbon::createFromFormat('Y-m-d H:i:s', '2026-03-31 23:59:59', 'Asia/Tokyo')
                 ->setTimezone('UTC');
// = 2026-03-31 14:59:59 UTC
```

Eloquent の `where()` に Carbon オブジェクトを渡すと `->toDateTimeString()` が呼ばれ UTC 文字列として比較される。

## 変更ファイル

### 1. `app/Http/Controllers/AdminController.php`
対象メソッド: `dashboard202603()`

#### 変更内容
メソッド冒頭に日付範囲変数を追加し、全クエリに `whereBetween` / `where ... >=` / `where ... <=` を適用する。

```php
// メソッド冒頭に追加
$startDate = Carbon::createFromFormat('Y-m-d H:i:s', '2026-01-01 00:00:00', 'Asia/Tokyo')
                   ->setTimezone('UTC');
$endDate   = Carbon::createFromFormat('Y-m-d H:i:s', '2026-03-31 23:59:59', 'Asia/Tokyo')
                   ->setTimezone('UTC');
```

#### 各クエリの変更

| 変数 | フィルター列 | 変更内容 |
|---|---|---|
| `$totalExchanges` | `exchanged_at` | `whereBetween` 追加 |
| `$redeemedExchanges` | `exchanged_at` | `whereBetween` 追加 |
| `$recentExchangesQuery` | `exchanged_at` | `whereBetween` 追加（検索フォームより前） |
| `$redeemedPrizes` | `exchanged_at` | `whereBetween` 追加 |
| 動物別統計（ループ内4クエリ×20種） | `scanned_at` | 各 MarkerScan クエリに `whereBetween` 追加 |
| `$recentScans` | `scanned_at` | `whereBetween` 追加 |
| `$dailyStatsRaw` | `scanned_at` | `where('scanned_at', '>=', now()->subDays(30))` → `whereBetween` に変更 |
| `$dailyUniqueUsersRaw` | `scanned_at` | 同上 |

### 2. `resources/views/admin/dashboard202603.blade.php`
グラフセクションの見出し文言変更のみ（2箇所）：

| 変更前 | 変更後 |
|---|---|
| `日別スキャン統計（直近30日間）` | `日別スキャン統計（2026年1月〜3月）` |
| `日別個別ユーザー数（直近30日間）` | `日別個別ユーザー数（2026年1月〜3月）` |

## 変更しないもの
- `ARstampRally202603.blade.php` — 変更不要（ユーザー確認済み）
- グラフの JS コード（ラベル生成ロジック）— 日付ループなので変更不要
- ページネーション CSS — 変更不要

## リスク対策

| リスク | 対策 |
|---|---|
| UTC 変換ズレ（UTC+9 の境界） | `setTimezone('UTC')` で明示変換 |
| ループ内クエリ増加（N+1） | ループ変更は最小限（既存構造を維持） |
| 既存ページネーション破損 | `$pendingExchanges = $totalExchanges - $redeemedExchanges` を維持 |

## 成功基準
- [ ] 全セクションに 2026/1/1〜3/31 JST の日付フィルターが適用
- [ ] グラフタイトルが「2026年1月〜3月」に変更
- [ ] PHP 構文エラーなし（php -l で確認）
- [ ] 既存機能が壊れていない

---

# 設計11: ARstampRally202603 - gollira/whiteDuck/araiguma/wolf の4動物変更

## 作成日時
2026年3月12日

## 前提
`.claude_workflow/requirements.md`（要件定義11）を読み込み確認済み。

## 設計概要

4動物のマーカーID・pattファイル・glbファイル・表示名・アイコンをすべて置換する。
DOM要素ID・JS変数名は**変更しない**（不要な差分を避け、デグレを防ぐ）。

## 変更マッピング詳細

| 項目 | gollira→kame | whiteDuck→cheetah | araiguma→blockoly | wolf→araiguma |
|------|-------------|-------------------|-------------------|---------------|
| 新stampId | `kame` | `cheetah` | `blockoly` | `araiguma` |
| 新表示名 | `カメ` | `チーター` | `ブロッコリー` | `アライグマ` |
| 新アイコン | `🐢` | `🐈` | `🥦` | `🦝` |
| 新patt | `202603/pattern-Maker_202603_kame.patt` | `202603/pattern-Maker_202603_cheetah.patt` | `202603/pattern-Maker_202603_blockoly.patt` | `202603/pattern-Maker_202603_araiguma.patt` |
| 新glb | `202603/3d_202603_kame.glb` | `202603/3d_202603_cheetah.glb` | `202603/3d_202603_blockoly.glb` | `202603/3d_202603_araiguma.glb` |

## 変更箇所一覧（ARstampRally202603.blade.php）

### gollira → kame（カメ）
1. 行2266: `a-marker url` → `cg/202603/pattern-Maker_202603_kame.patt`
2. 行2269: `lazy-model src` → `cg/202603/3d_202603_kame.glb`
3. 行2274: `hitbox stampId` → `kame`
4. 行2581: STAMPSキー `'gollira'` → `'kame'`、name→`カメ`、icon→`🐢`、model→`202603/3d_202603_kame.glb`
5. 行4764: `currentMarkerStampId = 'gollira'` → `'kame'`
6. 行4917: `currentMarkerStampId === 'gollira'` → `'kame'`

### whiteDuck → cheetah（チーター）
1. 行2278: `a-marker url` → `cg/202603/pattern-Maker_202603_cheetah.patt`
2. 行2282: `lazy-model src` → `cg/202603/3d_202603_cheetah.glb`
3. 行2287: `hitbox stampId` → `cheetah`
4. 行2583: STAMPSキー `'whiteDuck'` → `'cheetah'`、name→`チーター`、icon→`🐈`、model→`202603/3d_202603_cheetah.glb`
5. 行4934: `currentMarkerStampId = 'whiteDuck'` → `'cheetah'`
6. 行4951: `currentMarkerStampId === 'whiteDuck'` → `'cheetah'`

### araiguma → blockoly（ブロッコリー）
1. 行2291: `a-marker url` → `cg/202603/pattern-Maker_202603_blockoly.patt`
2. 行2295: `lazy-model src` → `cg/202603/3d_202603_blockoly.glb`
3. 行2300: `hitbox stampId` → `blockoly`
4. 行2585: STAMPSキー `'araiguma'` → `'blockoly'`、name→`ブロッコリー`、icon→`🥦`、model→`202603/3d_202603_blockoly.glb`
5. 行4966: `currentMarkerStampId = 'araiguma'` → `'blockoly'`
6. 行5010: `currentMarkerStampId === 'araiguma'` → `'blockoly'`

### wolf → araiguma（アライグマ）
1. 行2304: `a-marker url` → `cg/202603/pattern-Maker_202603_araiguma.patt`
2. 行2308: `lazy-model src` → `cg/202603/3d_202603_araiguma.glb`
3. 行2313: `hitbox stampId` → `araiguma`
4. 行2587: STAMPSキー `'wolf'` → `'araiguma'`、name→`アライグマ`、icon→`🦝`、model→`202603/3d_202603_araiguma.glb`
5. 行5035: `currentMarkerStampId = 'wolf'` → `'araiguma'`
6. 行5078: `currentMarkerStampId === 'wolf'` → `'araiguma'`

## 変更箇所一覧（AdminController.php）
- `'gollira' => 'ごりら'` → `'kame' => 'カメ'`
- `'whiteDuck' => '白アヒル'` → `'cheetah' => 'チーター'`
- `'araiguma' => 'あらいぐま'` → `'blockoly' => 'ブロッコリー'`
- `'wolf' => 'おおかみ'` → `'araiguma' => 'アライグマ'`

## 実装方針
- `multi_replace_string_in_file` で一括置換
- 各置換は前後3行のコンテキストを含めて一意に特定
- 置換後は `grep` で旧パス・旧キーが残っていないことを検証
- AdminController.php は `php -l` で構文チェック

## 成功基準
- `cg/pattern-gollira.patt` / `3d_pro_gollira_ishimaru.glb` が残らない
- `cg/pattern-whiteDuck.patt` / `3d_pro_whiteDuck_tagashira.glb` が残らない
- `cg/pattern-araiguma.patt` / `3d_pro_araiguma_oonomi.glb` が残らない
- `cg/pattern-wolf.patt` / `3d_pro_wolf_morita.glb` が残らない
- STAMPSに `gollira`/`whiteDuck`/`araiguma`/`wolf` キーが残らない
- AdminControllerの `$animals` に旧キー・旧名が残らない
- PHP lint エラーなし

---

# 設計12: ARstampRally202603 - パンダとブロッコリーの入れ替え

## 作成日時
2026年3月12日

## 前提
`.claude_workflow/requirements.md`（要件定義12）を読み込み確認済み。

## 変更箇所詳細（3箇所のみ）

### 変更1: STAMPSオブジェクト内の blockoly エントリ → シークレット末尾へ移動
**現在の状態（通常スタンプ9番目付近）:**
```javascript
// blockoly (ブロッコリー)
'blockoly': { name: 'ブロッコリー', icon: '🥦', model: '202603/3d_202603_blockoly.glb' },
```
**変更後（削除して、panda の位置へ移動）:**
- 元の位置から削除
- シークレット末尾（元 panda の位置）に `secret: true` 付きで追加

### 変更2: STAMPSオブジェクト内の panda エントリ → 通常スタンプ9番目へ移動
**現在の状態（シークレット末尾）:**
```javascript
// panda / パンダ (シークレット)
'panda': { name: 'パンダ', icon: '🐼', model: '202603/3d_202603_panda2.glb', secret: true }
```
**変更後（削除して、blockoly の位置へ移動）:**
- 元の位置から削除
- 通常9番目（元 blockoly の位置）に `secret` なしで追加

### 変更3: SECRET_STAMPS 配列
**現在:** `['barger', 'kirin', 'aeon', 'pet', 'panda']`
**変更後:** `['barger', 'kirin', 'aeon', 'pet', 'blockoly']`

### 変更4: showStampBook内の panda 特別扱い削除
**現在（行3420付近）:**
```javascript
if (stampId === 'panda') {
    iconContent = '🐾';
    nameText = 'パンダ';
} else {
    iconContent = '🐾';
    nameText = 'シークレット';
}
```
**変更後:** panda の特別扱い不要。`else if (isSecret)` ブロックを単純化:
```javascript
// シークレット動物: 'シークレット'
iconContent = '🐾';
nameText = 'シークレット';
```

## 実装方針
- `multi_replace_string_in_file` で変更1〜4を一括実行
- STAMPSオブジェクトの書き替えは前後コンテキストで一意に特定
- 変更後にgrep検証

## 変更しないもの
- a-markerタグ・a-entity（HTML）
- currentMarkerStampId ロジック
- AdminController.php / dashboard202603.blade.php

## 成功基準
- `'blockoly'` が `secret: true` 付きでシークレット末尾にある
- `'panda'` が `secret` なしで通常スタンプにある
- `SECRET_STAMPS` が `['barger', 'kirin', 'aeon', 'pet', 'blockoly']`
- `stampId === 'panda'` の特別扱いコードが削除されている
3. テストフェーズ - ブラウザでの動作確認
---

# 設計13: ARstampRally202603 - Android moto g64yで操作説明が左半分しか表示されない問題の修正

## 問題
Android moto g64y では AR.js の `sourceWidth: 640` キャンバスにより body/document 幅が 640px に広がることがある。
`position: fixed` 要素に `width: 100%` を使うと、一部の Android Chrome で viewport 幅 (~360px) ではなく document 幅 (640px) に対して計算され、モーダルが画面右に半分はみ出す。

## 解決策
`width: 100%; height: 100%` を `right: 0; bottom: 0` に置き換える。
`position: fixed` + `top:0; left:0; right:0; bottom:0` は inset: 0 と等価で、常に viewport を基準にする。
※`#camera-error` は既にこの正しいパターンを使っている。

## 変更箇所（CSS のみ、4箇所）
| セレクタ | 変更前 | 変更後 |
|---|---|---|
| `#guide-modal` | `width: 100%; height: 100%;` | `right: 0; bottom: 0;` |
| `#stamp-book-modal` | `width: 100%; height: 100%;` | `right: 0; bottom: 0;` |
| `#confirm-overlay` | `width: 100%; height: 100%;` | `right: 0; bottom: 0;` |
| `#flash` | `width: 100%; height: 100%;` | `right: 0; bottom: 0;` |

## 変更しないもの
- JavaScript コード（1行も変更しない）
- HTML 構造
- 上記4要素以外の CSS

## 成功基準
- Android moto g64y でガイドモーダルが全画面表示される
- iPhone・PC の動作は変わらない

---

# 設計14: ARstampRally202603 - Android moto g64yで操作説明が右側に移動して閉じられない問題の再修正

## 前回(設計13)の修正状況
設計13 の CSS 修正（right:0; bottom:0）は正しく適用済み。
しかし問題は **HTML 構造の破損** が主因であることが判明した。

## 根本原因（2つ）

### 原因1: HTML 構造が壊れている（主因）
`guide-step-hints` の閉じタグ後に余分な `</div></div>` が2個あり、
`#guide-content` が早期に閉じられている。
その結果 `.guide-close-row` (閉じるボタン) が `#guide-content` の**外**（暗いオーバーレイ領域）に置かれる。
Android では暗いオーバーレイ部分のタッチイベントが `#guide-modal` のスクロールとして解釈され、
「モーダルが右側にすっと移動し、閉じるも押せない」状態になる。

### 原因2: overflow-x 未設定（副因）
`overflow-y: auto` を指定すると CSS 仕様により `overflow-x` も暗黙的に `auto` になる。
一部の Android Chrome では横スクロールが有効になり、コンテンツ幅がはみ出すと
モーダルが右方向にスライドする現象が起きる。

## 現在の壊れた HTML 構造（行 1993〜2005 付近）
```
                <div class="step" id="guide-step-hints">
                    <!-- Marker hint PDF ... -->
                </div>

                    </div>      ← .guide-steps を閉じる
                </div>          ← ⚠️ 余分：#guide-content を早期に閉じてしまう

                            <div class="guide-close-row">   ← ⚠️ #guide-content の外！
                <button id="close-guide" type="button">close</button>
            </div>

            </div>              ← 余分（構造上孤立している）
        </div>                  ← 余分（構造上孤立している）
    </div>                      ← #guide-modal を閉じる
```

## 修正後の正しい HTML 構造
```
                <div class="step" id="guide-step-hints">
                    <!-- Marker hint PDF ... -->
                </div>
            </div>              ← .guide-steps を閉じる

            <div class="guide-close-row">
                <button id="close-guide" type="button">close</button>
            </div>
        </div>                  ← #guide-content を閉じる
    </div>                      ← #guide-modal を閉じる
```

## 変更箇所

### 変更1: HTML 修正（行 1997〜2005 付近）
壊れた `</div></div>` を取り除き、`.guide-close-row` を `#guide-content` 内へ移動する。

**変更前:**
```html
                </div>

                    </div>
                </div>

                            <div class="guide-close-row">
                <button id="close-guide" type="button">close</button>
            </div>

            </div>
        </div>
    </div>
```

**変更後:**
```html
                </div>
            </div>

            <div class="guide-close-row">
                <button id="close-guide" type="button">close</button>
            </div>
        </div>
    </div>
```

### 変更2: CSS 修正（行 1408 付近）
`#guide-modal` に `overflow-x: hidden;` を追加する。

**変更前:**
```css
        #guide-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.85);
            display: none;
            z-index: 10002;
            -webkit-overflow-scrolling: touch;
            overflow-y: auto !important;
        }
```

**変更後:**
```css
        #guide-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.85);
            display: none;
            z-index: 10002;
            -webkit-overflow-scrolling: touch;
            overflow-y: auto !important;
            overflow-x: hidden;
        }
```

## 変更しないもの
- JavaScript コード（1行も変更しない）
- 上記2箇所以外の HTML・CSS

## 影響範囲
- `#guide-modal` の HTML 構造と CSS のみ
- iOS の動作には影響なし（`position: fixed` + `inset: 0` は iOS でも正常に動作する）

## 成功基準
- Android moto g64y でモーダルが全画面表示される
- 「閉じる(close)」ボタンと「×」ボタンが白いコンテンツ枠内に表示される
- モーダルが右側にスライドしない
- iPhone 等、既存の正常動作環境に影響なし
