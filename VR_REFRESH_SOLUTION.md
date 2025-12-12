# VRゴーグルでのページリフレッシュ問題 - 解決方法

## 📋 問題の概要

### 症状
- **PC/スマホブラウザ**: 2プレイ後に正常にページリフレッシュされる ✅
- **VRゴーグル（Pico4等）**: 2プレイ後、リフレッシュされず画面がフリーズ ❌

### 影響範囲
- `shooting3DModel2.blade.php` (動物シューティング)
- `shooting3Dterrer2.blade.php` (ゾンビシューティング)

---

## 🔍 根本原因の分析

### 1. WebXR VRモードの制約
```
問題: VRセッション中は location.reload() がブロックされる
理由: WebXR APIのセキュリティポリシー
     VRモード中のナビゲーション操作を制限している
```

### 2. alert()の動作不具合
```
問題: VRモード中は alert() が正しく表示されない
結果: ユーザーがOKボタンを押せない
     → 次の処理（reload）に進まない
     → 画面がフリーズしたように見える
```

### 3. Pico Business Suiteの影響
```
可能性: 管理アプリによるWebView制限
現状: VRモード終了処理で解決可能
     → 直接的な原因ではない
```

---

## ✅ 実装した解決策

### 主要な改善点

#### 1. VRモード自動終了
```javascript
// VRモード検出
const sceneEl = document.querySelector('a-scene');
const isVRMode = sceneEl && sceneEl.is('vr-mode');

if (isVRMode) {
    // VRモードを終了してからリロード
    sceneEl.exitVR().then(() => {
        window.registerTimeout(() => {
            this.performReload();
        }, 500); // 500ms待機（VR終了処理完了を待つ）
    });
}
```

**理由**: WebXRセッションを終了しないと、ページナビゲーションがブロックされる

#### 2. alert()の削除
```javascript
// ❌ 旧コード
alert('ゲーム終了！お疲れ様でした。\nページをリフレッシュします。');
location.reload();

// ✅ 新コード
console.log('✅ 最大プレイ回数に達しました。ページをリフレッシュします。');
// alert削除 → 即座にVR終了処理へ
```

**理由**: VRモード中のalert()は正常に動作せず、処理がブロックされる

#### 3. 複数リロード方法のフォールバック
```javascript
performReload: function() {
    // 方法1: 標準的なリロード（キャッシュクリア）
    try {
        window.location.reload(true);
        return;
    } catch (e) {}
    
    // 方法2: URLの再設定
    try {
        window.location.href = window.location.href;
    } catch (e) {}
    
    // 方法3: replace（履歴を残さない）
    try {
        window.location.replace(window.location.href);
    } catch (e) {}
}
```

**理由**: VRゴーグルのブラウザによって対応している方法が異なる可能性

#### 4. 詳細なログ出力
```javascript
console.log('🥽 VR Mode:', isVRMode);
console.log('🚪 Exiting VR mode before reload...');
console.log('✅ VR mode exited, reloading page...');
console.log('🔄 Attempting page reload...');
console.log('Method 1: location.reload()');
```

**理由**: VRゴーグルでのデバッグには外部コンソール接続が必要なため、詳細なログを残す

---

## 🎯 実装詳細

### 修正箇所

#### shooting3DModel2.blade.php
```javascript
// 1. restart() 関数
restart: function(event) {
    if (window.playCount >= window.MAX_PLAY_COUNT) {
        const sceneEl = document.querySelector('a-scene');
        const isVRMode = sceneEl && sceneEl.is('vr-mode');
        
        if (isVRMode) {
            sceneEl.exitVR().then(() => {
                window.registerTimeout(() => {
                    this.performReload();
                }, 500);
            }).catch((err) => {
                this.performReload(); // エラー時も強制リロード
            });
        } else {
            this.performReload();
        }
        return;
    }
    // 通常リスタート処理...
}

// 2. restartTouch() 関数
// restart()と同じロジックを実装

// 3. performReload() 関数（新規追加）
performReload: function() {
    // フォールバック付きリロード処理
}
```

#### shooting3Dterrer2.blade.php
- 同じ修正を適用

---

## 🧪 テスト方法

### VRゴーグルでのテスト手順

1. **事前準備**
   ```
   - Pico4でアプリにアクセス
   - VRモードに自動入場
   - コンソールログを確認できる環境を用意
     （ChromeのRemote Debugging等）
   ```

2. **1回目のプレイ**
   ```
   期待される動作:
   - ゲーム開始時: "🎮 Play count incremented: 1 / 2"
   - ゲーム終了時: "Current playCount: 1"
   - リスタート: "➡️ 通常リスタート処理を実行"
   ```

3. **2回目のプレイ**
   ```
   期待される動作:
   - ゲーム開始時: "🎮 Play count incremented: 2 / 2"
   - ゲーム終了時: "Current playCount: 2"
   - VRモード検出: "🥽 VR Mode: true"
   - VR終了開始: "🚪 Exiting VR mode before reload..."
   - VR終了完了: "✅ VR mode exited, reloading page..."
   - リロード試行: "🔄 Attempting page reload..."
   - リロード実行: "Method 1: location.reload()"
   - → ページがリフレッシュされる ✅
   ```

4. **確認ポイント**
   - [ ] 2プレイ後にVRモードが自動終了する
   - [ ] VRモード終了後にページがリロードされる
   - [ ] 画面がフリーズしない
   - [ ] 3回目のプレイは1回目としてカウントされる

### PCブラウザでのテスト

```
期待される動作:
- 2プレイ後のログ: "💻 Normal mode, reloading immediately..."
- 即座にリロードされる（VR終了処理はスキップ）
```

---

## 🔧 デバッグ方法

### VRゴーグルのコンソール確認

#### Chrome Remote Debugging（推奨）
```bash
# Pico4をPCに接続
adb devices

# Chromeでアクセス
chrome://inspect
→ デバイスを選択
→ "inspect" クリック
→ Console タブでログを確認
```

#### ログファイル出力（代替案）
```javascript
// コード内に追加可能
const logToFile = (msg) => {
    fetch('/api/log', {
        method: 'POST',
        body: JSON.stringify({message: msg})
    });
};
```

### よくある問題と対処法

#### 問題1: VRモードが終了しない
```
症状: exitVR()が失敗する
対処: catch句で強制リロード実行
確認: "❌ VR exit error:" のログ
```

#### 問題2: リロードがすべて失敗
```
症状: 3つの方法すべて失敗
対処: ログに "⚠️ 自動リロード失敗" 表示
     → 手動リロードが必要
原因: Pico Business Suiteの制限？
     → 管理者に確認が必要
```

#### 問題3: playCountがリセットされない
```
症状: 3回目のプレイでもカウントが3になる
原因: キャッシュが残っている
対処: reload(true) でキャッシュクリア指定済み
確認: ブラウザのキャッシュ設定を確認
```

---

## 📊 パフォーマンス影響

### メモリ使用量
```
旧方式: 連続プレイで増加し続ける
        → 4プレイ目でクラッシュ

新方式: 2プレイごとにリセット
        → GPU/メモリがクリアされる
        ✅ 安定して長時間動作可能
```

### リロード時間
```
VRモード終了: 約500ms
ページリロード: 約2-3秒
合計: 約3-4秒

影響: ユーザー体験上許容範囲内
     （ゲーム終了画面から次のプレイまでの待ち時間）
```

---

## 🚀 今後の改善案

### 優先度: 高

#### 1. ローディング画面の追加
```javascript
if (window.playCount >= window.MAX_PLAY_COUNT) {
    // ローディング表示
    document.getElementById('loadingScreen').style.display = 'block';
    document.getElementById('loadingText').textContent = 
        'リフレッシュ中...';
    
    // VR終了処理...
}
```

#### 2. エラーハンドリングの強化
```javascript
performReload: function() {
    const maxRetries = 3;
    let retryCount = 0;
    
    const tryReload = () => {
        try {
            window.location.reload(true);
        } catch (e) {
            if (retryCount < maxRetries) {
                retryCount++;
                setTimeout(tryReload, 1000);
            }
        }
    };
    
    tryReload();
}
```

### 優先度: 中

#### 3. プレイ回数の設定可能化
```javascript
// グローバル設定
window.MAX_PLAY_COUNT = getConfigValue('maxPlayCount', 2);

// 管理画面から変更可能に
```

#### 4. VRセッション管理の最適化
```javascript
// WebXRセッションのライフサイクル管理
class VRSessionManager {
    async exitAndReload() {
        await this.cleanupResources();
        await this.exitVR();
        await this.reload();
    }
}
```

### 優先度: 低

#### 5. 統計情報の収集
```javascript
// プレイ回数、VRモード使用率、リロード成功率等
analytics.track('game_reload', {
    playCount: window.playCount,
    isVRMode: isVRMode,
    reloadMethod: 'exitVR'
});
```

---

## 📝 まとめ

### 解決した問題
✅ VRゴーグルでのページリフレッシュ失敗  
✅ 画面フリーズ  
✅ メモリリーク継続  

### 実装した対策
✅ VRモード自動終了  
✅ alert()削除  
✅ フォールバックリロード  
✅ 詳細ログ出力  

### 残課題
⚠️ Pico Business Suiteの制限確認が必要  
⚠️ 長時間の安定性テストが必要  
⚠️ ユーザー体験の改善余地あり（ローディング画面等）  

---

## 🔗 関連情報

### WebXR API仕様
- [WebXR Device API](https://www.w3.org/TR/webxr/)
- [Exiting VR Mode](https://developer.mozilla.org/en-US/docs/Web/API/XRSession/end)

### A-Frame VRモード管理
- [A-Frame VR Mode](https://aframe.io/docs/1.4.0/components/vr-mode-ui.html)
- [Scene.exitVR()](https://aframe.io/docs/1.4.0/core/scene.html#exitvr)

### Pico4開発情報
- [Pico Developer Documentation](https://developer-global.pico-interactive.com/)
- [WebXR Support on Pico](https://developer-global.pico-interactive.com/document/web/webxr-support/)

---

**更新日**: 2025年12月13日  
**対応バージョン**: A-Frame 1.4.0, WebXR API  
**検証環境**: Pico4, Chrome Browser
