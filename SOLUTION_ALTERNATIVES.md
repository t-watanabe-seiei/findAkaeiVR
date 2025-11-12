# 画面フリーズ問題の解決策

## 現在実装: 解決策1 - ページリロード
- `window.location.reload()` でページ全体をリロード
- 最もシンプルで確実
- データはLocalStorageに保存済みなので問題なし

## 解決策2: メッセージDOMを削除して再作成（リロードなし）

```javascript
// 「逃がす」ボタンのイベントリスナー
document.getElementById('release-button').addEventListener('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    if (currentCapturedAnimal) {
        const stampId = currentCapturedAnimal;
        const modelId = stampId + '-model';
        
        if (confirm(`${STAMPS[stampId].name}を逃がしますか？\nスタンプも削除されます。`)) {
            // 動物を逃がす
            releaseAnimal(stampId);
            
            // モデルをリセット
            const model = document.getElementById(modelId);
            if (model) {
                model.setAttribute('visible', 'false');
                if (model.resetCaptureState) {
                    model.resetCaptureState();
                }
            }
            
            // メッセージ要素を完全に削除
            const message = document.getElementById('captured-message');
            const parent = message.parentNode;
            parent.removeChild(message);
            
            // 新しいメッセージ要素を作成
            const newMessage = document.createElement('div');
            newMessage.id = 'captured-message';
            newMessage.className = 'captured-message';
            newMessage.innerHTML = `
                <h2>🎉 すでに捕まえています 🎉</h2>
                <div class="animal-name" id="captured-animal-name"></div>
                <button class="release-button" id="release-button">
                    この動物を逃がす 🔓
                </button>
            `;
            parent.appendChild(newMessage);
            
            // イベントリスナーを再登録（再帰的に呼び出し）
            setupReleaseButton();
            
            currentCapturedAnimal = null;
            
            alert(`${STAMPS[stampId].name}を逃がしました！`);
        }
    }
});
```

## 解決策3: A-Frameシーンを強制再描画

```javascript
// アラート後にA-Frameを強制的に再描画
const scene = document.querySelector('a-scene');
if (scene && scene.renderer) {
    scene.renderer.render(scene.object3D, scene.camera);
}

// またはカメラを少し動かす
const camera = document.querySelector('a-camera');
if (camera) {
    const pos = camera.getAttribute('position');
    camera.setAttribute('position', {x: pos.x + 0.001, y: pos.y, z: pos.z});
    setTimeout(() => {
        camera.setAttribute('position', pos);
    }, 10);
}
```

## 解決策4: マーカーを強制的にリセット

```javascript
// マーカーを一時的に無効化して再有効化
const marker = model.parentElement;
if (marker) {
    marker.setAttribute('type', 'pattern');
    marker.setAttribute('url', marker.getAttribute('url'));
}
```

## 推奨
**解決策1（ページリロード）**が最も確実で安全です。
