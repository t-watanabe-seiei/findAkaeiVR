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