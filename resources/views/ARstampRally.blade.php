<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>AR Stamp Rally</title>
    <script>
        // 最優先でキーボードイベントをブロック（キャプチャフェーズで捕捉）
        document.addEventListener('keydown', function(e) {
            // Ctrl+U, Cmd+U (ソースコード表示)
            if ((e.ctrlKey || e.metaKey) && (e.key === 'u' || e.key === 'U' || e.keyCode === 85)) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
            // F12 (開発者ツール)
            if (e.key === 'F12' || e.keyCode === 123) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
            // Ctrl+Shift+I, Cmd+Option+I (検証ツール)
            if ((e.ctrlKey && e.shiftKey && (e.key === 'I' || e.keyCode === 73)) ||
                (e.metaKey && e.altKey && (e.key === 'I' || e.keyCode === 73))) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
            // Ctrl+Shift+J, Cmd+Option+J (コンソール)
            if ((e.ctrlKey && e.shiftKey && (e.key === 'J' || e.keyCode === 74)) ||
                (e.metaKey && e.altKey && (e.key === 'J' || e.keyCode === 74))) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
            // Ctrl+Shift+C, Cmd+Option+C (要素選択)
            if ((e.ctrlKey && e.shiftKey && (e.key === 'C' || e.keyCode === 67)) ||
                (e.metaKey && e.altKey && (e.key === 'C' || e.keyCode === 67))) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
            // Ctrl+S, Cmd+S (保存)
            if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S' || e.keyCode === 83)) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        }, true); // キャプチャフェーズで処理
        
        // 右クリック無効化
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }, true);
    </script>
    <script src="{{ asset('js/ar-engine.min.js') }}"></script>
    <script src="{{ asset('js/ar-tracking.min.js') }}"></script>
    <script>
        // ポケボール投擲コンポーネント
        AFRAME.registerComponent('pokeball-throwable', {
            init: function() {
                this.velocity = new THREE.Vector3();
                this.gravity = -4.5; // 重力を弱めて遠くまで飛ぶように
                this.isThrown = false;
                this.lifetime = 0;
                this.maxLifetime = 8; // 8秒後に消滅（より長く）
            },
            
            throw: function(direction, speed) {
                this.velocity.copy(direction).multiplyScalar(speed);
                // 上向きの初速を追加（放物線を描く）
                this.velocity.y += 2.5; // 上方向への追加速度
                this.isThrown = true;
                this.lifetime = 0;
                console.log('Pokeball thrown with velocity:', this.velocity);
            },
            
            tick: function(time, deltaTime) {
                if (!this.isThrown) return;
                
                const delta = deltaTime / 1000;
                this.lifetime += delta;
                
                // 重力を適用
                this.velocity.y += this.gravity * delta;
                
                // 位置を更新
                const pos = this.el.object3D.position;
                pos.x += this.velocity.x * delta;
                pos.y += this.velocity.y * delta;
                pos.z += this.velocity.z * delta;
                
                // 回転させる（投げた感じを出す）- Android向けに速度調整
                this.el.object3D.rotation.x += delta * 4; // 8 → 4に減速（ちらつき軽減）
                this.el.object3D.rotation.z += delta * 2.5; // 5 → 2.5に減速
                
                // 寿命チェック
                if (this.lifetime > this.maxLifetime || pos.y < -5) {
                    this.el.parentNode.removeChild(this.el);
                }
            }
        });
        
        // 当たり判定ボックスコンポーネント
        AFRAME.registerComponent('hitbox', {
            schema: {
                stampId: {type: 'string', default: ''},
                width: {type: 'number', default: 1},
                height: {type: 'number', default: 1},
                depth: {type: 'number', default: 1}
            },
            
            init: function() {
                const data = this.data;
                
                // Three.jsのバウンディングボックスを作成
                this.box = new THREE.Box3();
                this.updateBox();
                
                // デバッグ用のボックス表示（開発時のみ）
                if (false) { // trueにするとボックスが見える
                    const geometry = new THREE.BoxGeometry(data.width, data.height, data.depth);
                    const material = new THREE.MeshBasicMaterial({ 
                        color: 0x00ff00, 
                        wireframe: true,
                        opacity: 0.3,
                        transparent: true
                    });
                    const mesh = new THREE.Mesh(geometry, material);
                    this.el.object3D.add(mesh);
                }
            },
            
            updateBox: function() {
                const data = this.data;
                const pos = this.el.object3D.getWorldPosition(new THREE.Vector3());
                const halfWidth = data.width / 2;
                const halfHeight = data.height / 2;
                const halfDepth = data.depth / 2;
                
                this.box.min.set(
                    pos.x - halfWidth,
                    pos.y - halfHeight,
                    pos.z - halfDepth
                );
                this.box.max.set(
                    pos.x + halfWidth,
                    pos.y + halfHeight,
                    pos.z + halfDepth
                );
            },
            
            tick: function() {
                this.updateBox();
            },
            
            checkCollision: function(point) {
                return this.box.containsPoint(point);
            }
        });
        
        // クリック/タップでアニメーション再生
        AFRAME.registerComponent('click-animation', {
            schema: {
                clip: {type: 'string', default: 'anime01'}
            },
            init: function() {
                const el = this.el;
                const clipName = this.data.clip;
                let mixer = null;
                let action01 = null;
                let action02 = null;
                let currentAnimation = 1; // 1=anime01, 2=anime02
                let markerVisible = false;
                let modelCaptured = false; // モデルが捕獲されたか
                
                // stampIdを最初に取得
                const hitboxAttr = el.getAttribute('hitbox');
                const stampId = hitboxAttr ? hitboxAttr.split(':')[1].split(';')[0].trim() : '';
                console.log('Initializing model for stampId:', stampId);
                
                el.addEventListener('model-loaded', () => {
                    console.log('Model loaded for:', stampId);
                    const model = el.getObject3D('mesh');
                    
                    if (!model || !model.animations || model.animations.length === 0) {
                        console.log('No animations in model');
                        return;
                    }
                    
                    console.log('Animations found:', model.animations.length);
                    model.animations.forEach((clip, i) => {
                        console.log(`  ${i}: ${clip.name} (${clip.duration}s)`);
                    });
                    
                    mixer = new THREE.AnimationMixer(model);
                    this.mixer = mixer;
                    
                    // anime01を探す
                    let clip01 = THREE.AnimationClip.findByName(model.animations, 'anime01');
                    if (!clip01) {
                        clip01 = model.animations[0];
                        console.log('anime01 not found, using first animation');
                    }
                    
                    // anime02を探す
                    let clip02 = THREE.AnimationClip.findByName(model.animations, 'anime02');
                    if (!clip02) {
                        // anime02が見つからない場合は2番目のアニメーションを使用
                        clip02 = model.animations.length > 1 ? model.animations[1] : model.animations[0];
                        console.log('anime02 not found, using animation:', clip02.name);
                    }
                    
                    // 両方のアクションを作成
                    action01 = mixer.clipAction(clip01);
                    action01.setLoop(THREE.LoopRepeat, Infinity);
                    action01.stop();
                    
                    action02 = mixer.clipAction(clip02);
                    action02.setLoop(THREE.LoopOnce, 1); // 1回のみ再生
                    action02.clampWhenFinished = true; // 終了時に最後のフレームで停止
                    action02.stop();
                    
                    this.action01 = action01;
                    this.action02 = action02;
                    
                    console.log('Animations ready:');
                    console.log('  anime01:', clip01.name);
                    console.log('  anime02:', clip02.name);
                    
                    // anime02の終了イベントを監視（モデル非表示のみ）
                    mixer.addEventListener('finished', (e) => {
                        if (e.action === action02) {
                            console.log('anime02 finished for', stampId, '- hiding model');
                            // モデルを非表示（捕獲状態は既に保存済み）
                            el.setAttribute('visible', 'false');
                        }
                    });
                });
                
                // マーカー検出時の処理
                const marker = el.parentElement;
                
                // 外部からリセットできる関数
                el.resetCaptureState = () => {
                    console.log('=== RESET CAPTURE STATE for:', stampId, '===');
                    modelCaptured = false;
                    // visible状態は変更しない（markerFoundで制御）
                    if (action01) {
                        action01.reset();
                        action01.play();
                        currentAnimation = 1;
                    }
                    console.log('Capture state reset completed for:', stampId);
                };
                
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
                
                marker.addEventListener('markerLost', () => {
                    console.log('✗ Marker lost');
                    markerVisible = false;
                    
                    // 捕獲済みメッセージを非表示
                    hideCapturedMessage();
                    
                    if (!modelCaptured) {
                        if (action01) action01.stop();
                        if (action02) action02.stop();
                    }
                });
                
                // ボールヒット時にanime02を再生する関数（外部から呼び出し可能）
                el.playHitAnimation = () => {
                    console.log('=== BALL HIT! for', stampId, '===');
                    
                    // 即座に捕獲状態にする
                    modelCaptured = true;
                    
                    // 捕獲状態をLocalStorageに保存
                    if (typeof markAnimalCaptured === 'function' && stampId) {
                        markAnimalCaptured(stampId);
                        console.log('Marked as captured immediately:', stampId);
                    }
                    
                    // anime02を再生（視覚効果のみ）
                    if (action01) action01.stop();
                    if (action02) {
                        action02.reset();
                        action02.play();
                        currentAnimation = 2;
                    }
                    
                    console.log('Model captured! Will be hidden on next marker detection');
                };
                
                // タップでのアニメーション切替機能は廃止（コメントアウト）
                /*
                const handleInteraction = (e) => {
                    console.log('Interaction detected:', e.type);
                    
                    if (!markerVisible) {
                        console.log('Marker not visible, ignoring interaction');
                        return;
                    }
                    
                    if (!action01 || !action02) {
                        console.log('Actions not ready yet');
                        return;
                    }
                    
                    if (currentAnimation === 1) {
                        // anime01 → anime02に切り替え
                        action01.stop();
                        action02.reset();
                        action02.play();
                        currentAnimation = 2;
                        console.log('✓ Switched to anime02');
                    } else {
                        // anime02 → anime01に切り替え
                        action02.stop();
                        action01.reset();
                        action01.play();
                        currentAnimation = 1;
                        console.log('✓ Switched to anime01');
                    }
                };
                
                // 複数のイベントリスナーを追加
                el.addEventListener('click', handleInteraction);
                el.addEventListener('mousedown', handleInteraction);
                el.addEventListener('touchstart', (e) => {
                    e.preventDefault();
                    handleInteraction(e);
                });
                el.addEventListener('touchend', (e) => {
                    e.preventDefault();
                });
                */
            },
            tick: function(time, deltaTime) {
                // mixerが存在する場合のみ更新
                if (this.mixer) {
                    // deltaTimeを秒に変換（ミリ秒 → 秒）
                    // deltaTimeが異常に大きい場合は制限（フレームドロップ対策）
                    const dt = Math.min(deltaTime / 1000, 0.1);
                    this.mixer.update(dt);
                }
            }
        });
        
        // グローバルタッチイベントのデバッグ
        document.addEventListener('touchstart', function(e) {
            console.log('Touch detected on document');
        }, {passive: false});
    </script>
    <style>
        body {
            margin: 0;
            overflow: hidden;
        }
        .arjs-loader {
            height: 100%;
            width: 100%;
            position: absolute;
            top: 0;
            left: 0;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .arjs-loader div {
            text-align: center;
            font-size: 1.25em;
            color: white;
        }
        
        /* カメラボタンのスタイル */
        #camera-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 70px;
            height: 70px;
            background-color: rgba(255, 255, 255, 0.9);
            border: 3px solid #333;
            border-radius: 50%;
            cursor: pointer;
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 35px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            transition: transform 0.1s, background-color 0.2s;
        }
        
        #camera-button:active {
            transform: scale(0.9);
            background-color: rgba(200, 200, 200, 0.9);
        }
        
        #camera-button:hover {
            background-color: rgba(240, 240, 240, 0.9);
        }
        
        /* カメラ切り替えボタン */
        #switch-camera-button {
            position: fixed;
            top: 30px;
            left: 30px;
            width: 60px;
            height: 60px;
            background-color: rgba(255, 255, 255, 0.9);
            border: 3px solid #333;
            border-radius: 50%;
            cursor: pointer;
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 28px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            transition: transform 0.1s, background-color 0.2s;
        }
        
        #switch-camera-button:active {
            transform: scale(0.9) rotate(180deg);
            background-color: rgba(200, 200, 200, 0.9);
        }
        
        #switch-camera-button:hover {
            background-color: rgba(240, 240, 240, 0.9);
        }
        
        /* 動画撮影ボタン */
        #video-button {
            position: fixed;
            bottom: 110px;
            right: 30px;
            width: 60px;
            height: 60px;
            background-color: rgba(255, 255, 255, 0.9);
            border: 3px solid #333;
            border-radius: 50%;
            cursor: pointer;
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 28px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            transition: transform 0.1s, background-color 0.2s;
        }
        
        #video-button:active {
            transform: scale(0.9);
        }
        
        #video-button:hover {
            background-color: rgba(240, 240, 240, 0.9);
        }
        
        #video-button.recording {
            background-color: rgba(255, 100, 100, 0.9);
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        /* 撮影フラッシュエフェクト */
        #flash {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: white;
            opacity: 0;
            pointer-events: none;
            z-index: 9998;
            transition: opacity 0.2s;
        }
        
        #flash.active {
            opacity: 0.8;
        }
        
        /* スタンプ帳ボタン */
        #stamp-book-button {
            position: fixed;
            top: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background-color: rgba(255, 255, 255, 0.9);
            border: 3px solid #333;
            border-radius: 50%;
            cursor: pointer;
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 28px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            transition: transform 0.1s, background-color 0.2s;
        }
        
        #stamp-book-button:active {
            transform: scale(0.9);
            background-color: rgba(200, 200, 200, 0.9);
        }
        
        #stamp-book-button:hover {
            background-color: rgba(240, 240, 240, 0.9);
        }
        
        #stamp-book-button .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ff4444;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 12px;
            font-weight: bold;
            display: flex;
            justify-content: center;
            align-items: center;
            border: 2px solid white;
        }
        
        /* 捕まえるボタン（廃止） */
        #catch-button {
            display: none;
        }
        
        /* 回転ボタン */
        .rotation-buttons {
            position: fixed;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            flex-direction: column;
            gap: 15px;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }
        
        .rotation-buttons.visible {
            opacity: 1;
            visibility: visible;
        }
        
        .rotation-button {
            width: 60px;
            height: 60px;
            background-color: rgba(255, 255, 255, 0.9);
            border: 3px solid #333;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 32px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            transition: transform 0.1s, background-color 0.2s;
            user-select: none;
            -webkit-user-select: none;
            -webkit-tap-highlight-color: transparent;
        }
        
        .rotation-button:active {
            transform: scale(0.9);
            background-color: rgba(200, 200, 200, 0.9);
        }
        
        .rotation-button:hover {
            background-color: rgba(240, 240, 240, 0.9);
        }
        
        /* 捕獲済みメッセージ */
        .captured-message {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.85);
            color: white;
            padding: 30px 40px;
            border-radius: 15px;
            text-align: center;
            z-index: 2000;
            display: none;
            pointer-events: none;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
            opacity: 0;
            transition: opacity 0.3s;
            -webkit-transform: translate(-50%, -50%);
            -webkit-transition: opacity 0.3s;
            max-width: 80vw;
        }
        
        .captured-message.show {
            display: block;
            pointer-events: auto;
            opacity: 1;
            touch-action: auto;
            -webkit-touch-callout: default;
            -webkit-user-select: auto;
            user-select: auto;
        }
        
        .captured-message h2 {
            font-size: 24px;
            margin: 0 0 20px 0;
            color: #ffeb3b;
        }
        
        .captured-message .animal-name {
            font-size: 28px;
            margin: 10px 0;
            font-weight: bold;
            color: #4CAF50;
        }
        
        .captured-message p {
            margin: 15px 0 0 0;
            font-size: 14px;
            color: #ccc;
            line-height: 1.6;
        }
        
        /* スタンプ帳モーダル */
        #stamp-book-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            display: none;
            z-index: 10001;
            overflow-y: scroll !important;
            -webkit-overflow-scrolling: touch !important;
        }
        
        #stamp-book-content {
            background-color: white;
            border-radius: 15px;
            padding: 18px 12px;
            max-width: 500px;
            margin: 20px auto;
            box-sizing: border-box;
        }
        
        #stamp-book-content h2 {
            text-align: center;
            color: #333;
            margin: 0 0 6px 0;
            font-size: 20px;
        }
        
        #stamp-book-content .progress {
            text-align: center;
            color: #666;
            margin-bottom: 12px;
            font-size: 14px;
        }
        
        #stamp-book-content .complete-message {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 12px;
            font-weight: bold;
            font-size: 13px;
            animation: celebrate 1s ease-in-out;
        }
        
        @keyframes celebrate {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        #stamp-book-content .stamps-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 15px;
        }
        
        #stamp-book-content .stamp-item {
            background-color: #f5f5f5;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 8px 4px;
            text-align: center;
            transition: all 0.3s;
        }
        
        #stamp-book-content .stamp-item.collected {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
            border-color: #4CAF50;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        #stamp-book-content .stamp-item .stamp-icon {
            font-size: 28px;
            margin-bottom: 3px;
            width: 100%;
            height: 60px;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            border-radius: 4px;
            background-color: white;
        }
        
        #stamp-book-content .stamp-item .stamp-icon img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        #stamp-book-content .stamp-item .stamp-name {
            font-size: 11px;
            font-weight: bold;
            color: #333;
        }
        
        #stamp-book-content .stamp-item .stamp-date {
            font-size: 8px;
            color: #666;
            margin-top: 2px;
        }
        
        #stamp-book-content .stamp-item.not-collected {
            opacity: 0.4;
        }
        
        #stamp-book-content .stamp-item.not-collected .stamp-icon {
            filter: grayscale(100%);
        }
        
        #close-stamp-book {
            flex: 1;
            padding: 12px;
            background-color: #999;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            margin: 0;
            box-sizing: border-box;
        }
        
        #close-stamp-book:hover {
            background-color: #777;
        }
        
        #clear-stamps {
            flex: 1;
            padding: 12px;
            background-color: #f44336;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            margin: 0;
            box-sizing: border-box;
        }
        
        #clear-stamps:hover {
            background-color: #d32f2f;
        }
        
        .button-row {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        /* 確認ダイアログ */
        #confirm-dialog {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            z-index: 10003;
            display: none;
            min-width: 280px;
            text-align: center;
        }
        
        #confirm-dialog .confirm-message {
            font-size: 16px;
            color: #333;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        #confirm-dialog .confirm-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        #confirm-dialog .confirm-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
        }
        
        #confirm-dialog .confirm-yes {
            background-color: #f44336;
            color: white;
        }
        
        #confirm-dialog .confirm-no {
            background-color: #999;
            color: white;
        }
        
        #confirm-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 10002;
            display: none;
        }
        
        /* パーティクルエフェクト */
        .particle {
            position: fixed;
            pointer-events: none;
            z-index: 9998;
            font-size: 30px;
            animation: particle-float 2s ease-out forwards;
        }
        
        @keyframes particle-float {
            0% {
                opacity: 1;
                transform: translateY(0) rotate(0deg);
            }
            100% {
                opacity: 0;
                transform: translateY(-200px) rotate(360deg);
            }
        }
        
        .particle.large {
            font-size: 50px;
            animation: particle-float-large 3s ease-out forwards;
        }
        
        @keyframes particle-float-large {
            0% {
                opacity: 1;
                transform: translateY(0) rotate(0deg) scale(0.5);
            }
            50% {
                transform: translateY(-100px) rotate(180deg) scale(1.2);
            }
            100% {
                opacity: 0;
                transform: translateY(-300px) rotate(360deg) scale(0.5);
            }
        }
        
        /* ヒットパーティクルアニメーション */
        @keyframes hitParticle {
            0% {
                opacity: 1;
                transform: translate(0, 0) scale(1);
            }
            100% {
                opacity: 0;
                transform: translate(
                    calc(var(--random-x, 0) * 100px),
                    calc(var(--random-y, -150) * 1px)
                ) scale(0.3) rotate(360deg);
            }
        }
        
        .hit-particle {
            --random-x: calc(Math.random() * 2 - 1);
            --random-y: calc(Math.random() * -150);
        }
        
        /* 撮影した画像のプレビュー */
        #photo-preview {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-width: 90%;
            max-height: 90%;
            background-color: rgba(0, 0, 0, 0.9);
            padding: 10px;
            border-radius: 10px;
            display: none;
            z-index: 10000;
            flex-direction: column;
            align-items: center;
        }
        
        #photo-preview img,
        #photo-preview video {
            max-width: 100%;
            max-height: 70vh;
            border-radius: 5px;
        }
        
        #photo-preview .buttons {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        
        #photo-preview button {
            padding: 12px 24px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            color: white;
            font-weight: bold;
        }
        
        #download-button {
            background-color: #4CAF50;
        }
        
        #close-button {
            background-color: #f44336;
        }
    </style>
</head>
<body>
    <div class="arjs-loader">
        <div>カメラを起動中...</div>
    </div>
    
    <!-- 確認ダイアログ -->
    <div id="confirm-overlay"></div>
    <div id="confirm-dialog">
        <div class="confirm-message">本当に動物たちを逃がしますか？</div>
        <div class="confirm-buttons">
            <button class="confirm-yes" type="button">逃がす</button>
            <button class="confirm-no" type="button">キャンセル</button>
        </div>
    </div>
    
    <!-- フラッシュエフェクト -->
    <div id="flash"></div>
    
    <!-- スタンプ帳ボタン -->
    <button id="stamp-book-button" type="button" title="コレクションを見る">
        <div class="icon">🎁</div>
        <span class="badge">0</span>
    </button>
    
    <!-- 捕まえるボタン -->
    <button id="catch-button" type="button" title="タップで捕獲モード">
        <div class="icon">⚾</div>
        <div class="text">捕まえる</div>
    </button>
    
    <!-- 回転ボタン -->
    <div class="rotation-buttons" id="rotation-buttons">
        <button class="rotation-button" id="rotate-up" type="button" title="上に回転">⬆️</button>
        <button class="rotation-button" id="rotate-down" type="button" title="下に回転">⬇️</button>
    </div>
    
    <!-- スタンプ帳モーダル -->
    <div id="stamp-book-modal">
        <div id="stamp-book-content">
            <h2>🎯 コレクション 🎯</h2>
            <div class="progress">
                <span id="collected-count">0</span> / 5 種類コンプリート
            </div>
            <div id="complete-message-container"></div>
            <div class="stamps-grid" id="stamps-grid">
                <!-- スタンプアイテムはJavaScriptで動的生成 -->
            </div>
            <div class="button-row">
                <button id="close-stamp-book" type="button">閉じる</button>
                <button id="clear-stamps" type="button">動物たちを逃がす</button>
            </div>
        </div>
    </div>
    
    <!-- 捕獲済みメッセージ -->
    <div id="captured-message" class="captured-message">
        <h2>🎉 捕まえました！ 🎉</h2>
        <div class="animal-name" id="captured-animal-name"></div>
        <p style="margin-top: 15px; font-size: 14px; color: #ccc;">
            コレクションの「動物たちを逃がす」ボタンで<br>全てリセットできます
        </p>
    </div>
    
    <!-- カメラ切り替えボタン -->
    <button id="switch-camera-button" type="button" title="カメラを切り替え">🔄</button>
    
    <!-- 動画撮影ボタン -->
    <button id="video-button" type="button" title="動画を撮る">📹</button>
    
    <!-- カメラボタン -->
    <button id="camera-button" type="button" title="写真を撮る">📷</button>
    
    <!-- 撮影した写真のプレビュー -->
    <div id="photo-preview">
        <img id="preview-image" src="" alt="撮影した写真">
        <div class="buttons">
            <button id="download-button">ダウンロード</button>
            <button id="close-button">閉じる</button>
        </div>
    </div>
    
    <a-scene
        embedded
        arjs="sourceType: webcam; debugUIEnabled: false; sourceWidth: 1280; sourceHeight: 960;"
        vr-mode-ui="enabled: false"
        renderer="logarithmicDepthBuffer: true; antialias: true; alpha: true; precision: highp; powerPreference: high-performance;">
        
        <a-entity camera="near: 0.2; far: 800;"></a-entity>
        
        <!-- iPhone対応：シーン全体で1つのライトのみ使用（パフォーマンス向上） -->
        <a-light type="ambient" intensity="1.5"></a-light>
        <a-light type="directional" intensity="0.8" position="1 1 1"></a-light>
        
        <a-marker type="pattern" url="{{ asset('cg/pattern-sheep.patt') }}" id="pattern-sheep-marker">
            <a-entity
                id="sheep-model"
                gltf-model="{{ asset('cg/3d_matsubara_sheep.glb') }}"
                position="0 0 0"
                scale="2.5 2.5 2.5"
                rotation="0 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: sheep; width: 1.5; height: 2; depth: 1.5">
            </a-entity>
        </a-marker>
        
        <a-marker type="pattern" url="{{ asset('cg/pattern-fox.patt') }}" id="pattern-fox-marker">
            <a-entity
                id="fox-model"
                gltf-model="{{ asset('cg/3d_isobe_fox5.glb') }}"
                position="0 0 0"
                scale="2.5 2.5 2.5"
                rotation="0 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: fox; width: 1.5; height: 2; depth: 1.5">
            </a-entity>
        </a-marker>
        
        <a-marker type="pattern" url="{{ asset('cg/pattern-pengin.patt') }}" id="pattern-pengin-marker">
            <a-entity
                id="pengin-model"
                gltf-model="{{ asset('cg/3d_morita_pengin.glb') }}"
                position="0 0 0"
                scale="2.5 2.5 2.5"
                rotation="0 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: pengin; width: 1.5; height: 2; depth: 1.5">
            </a-entity>
        </a-marker>
        
        <a-marker type="pattern" url="{{ asset('cg/pattern-tonakai.patt') }}" id="pattern-tonakai-marker">
            <a-entity
                id="tonakai-model"
                gltf-model="{{ asset('cg/3d_matsumura_tonakai.glb') }}"
                position="0 0 0"
                scale="2.5 2.5 2.5"
                rotation="0 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: tonakai; width: 1.5; height: 2; depth: 1.5">
            </a-entity>
        </a-marker>
        
        <a-marker type="pattern" url="{{ asset('cg/pattern-pig.patt') }}" id="pattern-pig-marker">
            <a-entity
                id="pig-model"
                gltf-model="{{ asset('cg/3d_matsubara_pig.glb') }}"
                position="0 0 0"
                scale="2.5 2.5 2.5"
                rotation="0 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: pig; width: 1.5; height: 2; depth: 1.5">
            </a-entity>
        </a-marker>
        
    </a-scene>

    <script>
        // ソースコード保護: 右クリック・キーボードショートカット無効化（バブリングフェーズでも処理）
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            return false;
        }, false);
        
        document.addEventListener('keydown', function(e) {
            // Ctrl+U, Cmd+U（ソース表示）
            if ((e.ctrlKey || e.metaKey) && (e.key === 'u' || e.key === 'U' || e.keyCode === 85)) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
            
            // F12（開発者ツール）
            if (e.key === 'F12' || e.keyCode === 123) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
            
            // Ctrl+Shift+I, Cmd+Option+I（検証ツール）
            if ((e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.keyCode === 73)) ||
                (e.metaKey && e.altKey && (e.key === 'I' || e.key === 'i' || e.keyCode === 73))) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
            
            // Ctrl+Shift+J, Cmd+Option+J（コンソール）
            if ((e.ctrlKey && e.shiftKey && (e.key === 'J' || e.key === 'j' || e.keyCode === 74)) ||
                (e.metaKey && e.altKey && (e.key === 'J' || e.key === 'j' || e.keyCode === 74))) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
            
            // Ctrl+Shift+C, Cmd+Option+C（要素選択）
            if ((e.ctrlKey && e.shiftKey && (e.key === 'C' || e.key === 'c' || e.keyCode === 67)) ||
                (e.metaKey && e.altKey && (e.key === 'C' || e.key === 'c' || e.keyCode === 67))) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
            
            // Ctrl+S, Cmd+S（保存）
            if ((e.ctrlKey || e.metaKey) && (e.key === 'S' || e.key === 's' || e.keyCode === 83)) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        }, false);
        
        // キャプチャフェーズでも追加処理
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'u' || e.key === 'U' || e.keyCode === 85)) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        }, true);
        
        document.addEventListener('keyup', function(e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'u' || e.key === 'U' || e.keyCode === 85)) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        }, true);
        
        // テキスト選択の無効化
        document.addEventListener('selectstart', function(e) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        });
        
        // ドラッグ操作の無効化
        document.addEventListener('dragstart', function(e) {
            e.preventDefault();
            return false;
        });
        
        // コピー操作の無効化
        document.addEventListener('copy', function(e) {
            e.preventDefault();
            return false;
        });
        
        // 開発者ツール検知（簡易版）
        (function() {
            const devtools = /./;
            devtools.toString = function() {
                this.opened = true;
            }
            console.log('%c', devtools);
            
            setInterval(function() {
                if (devtools.opened) {
                    console.clear();
                    devtools.opened = false;
                }
            }, 1000);
        })();
        
        // A-Frameの痕跡を削除
        window.addEventListener('load', function() {
            // コンソールログを無効化（A-Frameのデバッグ情報を隠す）
            const originalLog = console.log;
            const originalWarn = console.warn;
            const originalInfo = console.info;
            
            console.log = function() {
                const args = Array.from(arguments);
                const text = args.join(' ');
                // A-Frame関連のログをフィルタリング
                if (text.includes('A-Frame') || text.includes('AFRAME') || text.includes('three.js')) {
                    return;
                }
                originalLog.apply(console, arguments);
            };
            
            console.warn = function() {
                const args = Array.from(arguments);
                const text = args.join(' ');
                if (text.includes('A-Frame') || text.includes('AFRAME')) {
                    return;
                }
                originalWarn.apply(console, arguments);
            };
            
            console.info = function() {
                const args = Array.from(arguments);
                const text = args.join(' ');
                if (text.includes('A-Frame') || text.includes('AFRAME')) {
                    return;
                }
                originalInfo.apply(console, arguments);
            };
            
            // A-Frame固有の属性を削除
            setTimeout(function() {
                document.querySelectorAll('[data-aframe-inspector]').forEach(el => {
                    el.removeAttribute('data-aframe-inspector');
                });
                
                document.querySelectorAll('[data-aframe-default-camera]').forEach(el => {
                    el.removeAttribute('data-aframe-default-camera');
                });
                
                // シーン要素から不要な属性を削除
                const scene = document.querySelector('a-scene');
                if (scene) {
                    scene.removeAttribute('inspector');
                    scene.removeAttribute('keyboard-shortcuts');
                }
            }, 2000);
            
            // AFRAMEオブジェクトのバージョン情報を削除
            if (window.AFRAME) {
                delete window.AFRAME.version;
            }
        });
        
        window.addEventListener('arjs-video-loaded', function() {
            console.log('AR.js ready');
            const loader = document.querySelector('.arjs-loader');
            if (loader) loader.style.display = 'none';
        });
        
        setTimeout(function() {
            const loader = document.querySelector('.arjs-loader');
            if (loader) loader.style.display = 'none';
        }, 3000);
        
        // スタンプラリー機能
        const STAMPS = {
            'sheep': { name: 'ひつじ', icon: '🐑', model: '3d_matsubara_sheep.glb' },
            'fox': { name: 'きつね', icon: '🦊', model: '3d_isobe_fox5.glb' },
            'pengin': { name: 'ペンギン', icon: '🐧', model: '3d_morita_pengin.glb' },
            'tonakai': { name: 'トナカイ', icon: '🦌', model: '3d_matsumura_tonakai.glb' },
            'pig': { name: 'ぶた', icon: '🐷', model: '3d_matsubara_pig.glb' }
        };
        
        // 音声ファイルをプリロード
        const soundStamp01 = new Audio("{{ asset('cg/sound_stamp01.mp3') }}");
        const soundStamp02 = new Audio("{{ asset('cg/sound_stamp02.mp3') }}");
        soundStamp01.preload = 'auto';
        soundStamp02.preload = 'auto';
        
        // LocalStorageからスタンプデータを取得
        function getCollectedStamps() {
            const stored = localStorage.getItem('ar-stamp-rally');
            return stored ? JSON.parse(stored) : {};
        }
        
        // LocalStorageにスタンプデータを保存
        function saveCollectedStamps(stamps) {
            localStorage.setItem('ar-stamp-rally', JSON.stringify(stamps));
        }
        
        // 捕獲済み動物の管理（モデル非表示用）
        function getCapturedAnimals() {
            const stored = localStorage.getItem('ar-captured-animals');
            return stored ? JSON.parse(stored) : {};
        }
        
        function saveCapturedAnimals(captured) {
            localStorage.setItem('ar-captured-animals', JSON.stringify(captured));
        }
        
        function markAnimalCaptured(stampId) {
            const captured = getCapturedAnimals();
            captured[stampId] = true;
            saveCapturedAnimals(captured);
            console.log('Animal marked as captured:', stampId);
        }
        
        function isAnimalCaptured(stampId) {
            const captured = getCapturedAnimals();
            return captured[stampId] === true;
        }
        
        // スタンプを登録
        function collectStamp(stampId, screenshot = null) {
            const collectedStamps = getCollectedStamps();
            
            if (!collectedStamps[stampId]) {
                collectedStamps[stampId] = {
                    collectedAt: new Date().toISOString(),
                    name: STAMPS[stampId].name,
                    screenshot: screenshot // スクリーンショットのBase64データ
                };
                saveCollectedStamps(collectedStamps);
                updateStampBadge();
                
                // 新規取得の処理
                const totalCollected = Object.keys(collectedStamps).length;
                const isComplete = totalCollected === Object.keys(STAMPS).length;
                
                // 音声再生
                if (isComplete) {
                    // 全種類コンプリート！
                    playSound(soundStamp02);
                    showCompleteParticles();
                } else {
                    // 通常の取得
                    playSound(soundStamp01);
                    showNormalParticles();
                }
                
                // 通知表示
                showStampNotification(stampId, isComplete);
                
                console.log('✓ Stamp collected:', stampId, 'Total:', totalCollected);
                return true;
            } else {
                console.log('Already collected:', stampId);
                return false;
            }
        }
        
        // 音声を再生
        function playSound(audioElement) {
            try {
                audioElement.currentTime = 0; // 最初から再生
                audioElement.play().catch(err => {
                    console.log('Audio play prevented:', err);
                });
            } catch (error) {
                console.error('Error playing sound:', error);
            }
        }
        
        // 捕獲済みメッセージを表示
        let currentCapturedAnimal = null;
        
        function showCapturedMessage(stampId) {
            const message = document.getElementById('captured-message');
            const animalName = document.getElementById('captured-animal-name');
            
            if (STAMPS[stampId]) {
                animalName.textContent = `${STAMPS[stampId].icon} ${STAMPS[stampId].name}`;
                currentCapturedAnimal = stampId;
                // インラインスタイルをリセット
                message.style.display = '';
                message.style.pointerEvents = '';
                message.style.visibility = '';
                // showクラスを追加
                message.classList.add('show');
            }
        }
        
        function hideCapturedMessage() {
            const message = document.getElementById('captured-message');
            if (!message) return;
            
            console.log('Hiding captured message');
            
            // showクラスを削除
            message.classList.remove('show');
            
            // 即座に非表示
            message.style.display = 'none';
            message.style.opacity = '0';
            message.style.pointerEvents = 'none';
            message.style.visibility = 'hidden';
            
            // currentCapturedAnimalをクリア
            currentCapturedAnimal = null;
        }
        
        // 通常パーティクル（スタンプ取得時）
        function showNormalParticles() {
            const particleIcons = ['✨', '⭐', '💫', '🌟', '💥'];
            const particleCount = 15;
            
            for (let i = 0; i < particleCount; i++) {
                setTimeout(() => {
                    createParticle(
                        particleIcons[Math.floor(Math.random() * particleIcons.length)],
                        false
                    );
                }, i * 50);
            }
        }
        
        // 豪華パーティクル（コンプリート時）
        function showCompleteParticles() {
            const particleIcons = ['🎉', '🎊', '🎈', '✨', '⭐', '💫', '🌟', '💥', '🎆', '🎇'];
            const particleCount = 40;
            
            for (let i = 0; i < particleCount; i++) {
                setTimeout(() => {
                    createParticle(
                        particleIcons[Math.floor(Math.random() * particleIcons.length)],
                        true // 大きいサイズ
                    );
                }, i * 30);
            }
            
            // 追加の連続パーティクル
            setTimeout(() => {
                for (let i = 0; i < 20; i++) {
                    setTimeout(() => {
                        createParticle(
                            particleIcons[Math.floor(Math.random() * particleIcons.length)],
                            true
                        );
                    }, i * 40);
                }
            }, 500);
        }
        
        // パーティクルを生成
        function createParticle(icon, isLarge = false) {
            const particle = document.createElement('div');
            particle.className = isLarge ? 'particle large' : 'particle';
            particle.textContent = icon;
            
            // ランダムな位置に配置
            const startX = Math.random() * window.innerWidth;
            const startY = Math.random() * window.innerHeight * 0.7 + window.innerHeight * 0.15;
            
            particle.style.left = startX + 'px';
            particle.style.top = startY + 'px';
            
            document.body.appendChild(particle);
            
            // アニメーション終了後に削除
            setTimeout(() => {
                if (particle.parentNode) {
                    document.body.removeChild(particle);
                }
            }, isLarge ? 3000 : 2000);
        }
        
        // スタンプ取得通知を表示
        function showStampNotification(stampId, isComplete = false) {
            const notification = document.createElement('div');
            
            if (isComplete) {
                // コンプリート通知
                notification.style.cssText = `
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                    color: white;
                    padding: 30px 40px;
                    border-radius: 20px;
                    font-size: 24px;
                    font-weight: bold;
                    z-index: 10002;
                    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
                    text-align: center;
                    animation: celebratePop 0.6s ease-out;
                `;
                notification.innerHTML = `
                    🎊 全種類コンプリート！ 🎊<br>
                    <div style="font-size: 18px; margin-top: 10px;">おめでとうございます！</div>
                `;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.style.animation = 'fadeOut 0.5s ease-in';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            document.body.removeChild(notification);
                        }
                    }, 500);
                }, 3000);
            } else {
                // 通常の取得通知
                notification.style.cssText = `
                    position: fixed;
                    top: 100px;
                    left: 50%;
                    transform: translateX(-50%);
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 15px 30px;
                    border-radius: 10px;
                    font-size: 18px;
                    font-weight: bold;
                    z-index: 9999;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                    animation: slideDown 0.5s ease-out;
                `;
                notification.innerHTML = `🎉 ${STAMPS[stampId].icon} ${STAMPS[stampId].name} をゲット！`;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.style.animation = 'slideUp 0.5s ease-in';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            document.body.removeChild(notification);
                        }
                    }, 500);
                }, 2000);
            }
        }
        
        // バッジの数字を更新
        function updateStampBadge() {
            const collectedStamps = getCollectedStamps();
            const count = Object.keys(collectedStamps).length;
            const badge = document.querySelector('#stamp-book-button .badge');
            if (badge) {
                badge.textContent = count;
                badge.style.display = count > 0 ? 'flex' : 'none';
            }
        }
        
        // モデルのスクリーンショットを撮影（背景を除く）
        function captureModelScreenshot(callback) {
            try {
                const scene = document.querySelector('a-scene');
                if (!scene || !scene.canvas) {
                    console.error('Scene canvas not found');
                    callback(null);
                    return;
                }
                
                // シーンのcanvasから画像を取得
                const sceneCanvas = scene.canvas;
                
                console.log('Canvas info:', {
                    width: sceneCanvas.width,
                    height: sceneCanvas.height,
                    exists: !!sceneCanvas
                });
                
                // レンダリングが完了するのを待つ
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        try {
                            // 一時的なcanvasを作成（元の画像用）
                            const tempCanvas = document.createElement('canvas');
                            tempCanvas.width = sceneCanvas.width;
                            tempCanvas.height = sceneCanvas.height;
                            const ctx = tempCanvas.getContext('2d');
                            
                            // シーン全体を描画
                            ctx.drawImage(sceneCanvas, 0, 0);
                            console.log('✓ Canvas drawn successfully');
                            
                            // 画像データを取得してモデル部分を検出
                            const imageData = ctx.getImageData(0, 0, tempCanvas.width, tempCanvas.height);
                            const bounds = detectModelBounds(imageData);
                            
                            console.log('Model bounds:', bounds);
                            
                            if (!bounds) {
                                console.warn('No model detected in image');
                                callback(null);
                                return;
                            }
                            
                            // モデル部分をクロップして拡大
                            const croppedCanvas = cropAndResize(tempCanvas, bounds, 400, 400);
                            
                            // Base64に変換
                            const screenshot = croppedCanvas.toDataURL('image/png');
                            console.log('Screenshot created:', {
                                length: screenshot.length,
                                bounds: bounds
                            });
                            
                            callback(screenshot);
                            
                        } catch (drawError) {
                            console.error('Draw error:', drawError);
                            callback(null);
                        }
                    });
                });
                
            } catch (error) {
                console.error('Screenshot capture error:', error);
                callback(null);
            }
        }
        
        // モデルの境界を検出（背景以外の部分を見つける）
        function detectModelBounds(imageData) {
            const data = imageData.data;
            const width = imageData.width;
            const height = imageData.height;
            
            let minX = width, minY = height, maxX = 0, maxY = 0;
            let foundPixel = false;
            
            // 背景色（ほぼ白または透明）を除外してモデルのピクセルを探す
            for (let y = 0; y < height; y++) {
                for (let x = 0; x < width; x++) {
                    const i = (y * width + x) * 4;
                    const r = data[i];
                    const g = data[i + 1];
                    const b = data[i + 2];
                    const a = data[i + 3];
                    
                    // 背景でないピクセルを判定
                    // 白(255,255,255)や透明(a=0)でない、または色がついているピクセル
                    const isNotBackground = a > 10 && (
                        r < 240 || g < 240 || b < 240 || // 真っ白でない
                        Math.abs(r - g) > 10 || Math.abs(g - b) > 10 // 色がついている
                    );
                    
                    if (isNotBackground) {
                        foundPixel = true;
                        minX = Math.min(minX, x);
                        minY = Math.min(minY, y);
                        maxX = Math.max(maxX, x);
                        maxY = Math.max(maxY, y);
                    }
                }
            }
            
            if (!foundPixel) {
                return null;
            }
            
            // パディングを追加（モデルの周りに余白を持たせる）
            const padding = 20;
            minX = Math.max(0, minX - padding);
            minY = Math.max(0, minY - padding);
            maxX = Math.min(width - 1, maxX + padding);
            maxY = Math.min(height - 1, maxY + padding);
            
            return {
                x: minX,
                y: minY,
                width: maxX - minX + 1,
                height: maxY - minY + 1
            };
        }
        
        // 画像をクロップして指定サイズにリサイズ
        function cropAndResize(sourceCanvas, bounds, targetWidth, targetHeight) {
            const resultCanvas = document.createElement('canvas');
            resultCanvas.width = targetWidth;
            resultCanvas.height = targetHeight;
            const ctx = resultCanvas.getContext('2d');
            
            // 透明背景
            ctx.clearRect(0, 0, targetWidth, targetHeight);
            
            // アスペクト比を維持して最大限に拡大
            const sourceAspect = bounds.width / bounds.height;
            const targetAspect = targetWidth / targetHeight;
            
            let drawWidth, drawHeight, offsetX, offsetY;
            
            if (sourceAspect > targetAspect) {
                // 横長の画像
                drawWidth = targetWidth;
                drawHeight = targetWidth / sourceAspect;
                offsetX = 0;
                offsetY = (targetHeight - drawHeight) / 2;
            } else {
                // 縦長の画像
                drawHeight = targetHeight;
                drawWidth = targetHeight * sourceAspect;
                offsetX = (targetWidth - drawWidth) / 2;
                offsetY = 0;
            }
            
            // クロップした部分を描画
            ctx.drawImage(
                sourceCanvas,
                bounds.x, bounds.y, bounds.width, bounds.height,
                offsetX, offsetY, drawWidth, drawHeight
            );
            
            return resultCanvas;
        }
        
        // スタンプ帳を表示
        function showStampBook() {
            const collectedStamps = getCollectedStamps();
            const stampsGrid = document.getElementById('stamps-grid');
            const collectedCount = document.getElementById('collected-count');
            const completeMessageContainer = document.getElementById('complete-message-container');
            
            // グリッドをクリア
            stampsGrid.innerHTML = '';
            
            // 各スタンプを表示
            Object.keys(STAMPS).forEach(stampId => {
                const stamp = STAMPS[stampId];
                const isCollected = collectedStamps[stampId] !== undefined;
                
                const stampItem = document.createElement('div');
                stampItem.className = `stamp-item ${isCollected ? 'collected' : 'not-collected'}`;
                
                let dateText = '';
                let iconContent = stamp.icon; // デフォルトは絵文字
                
                if (isCollected) {
                    const date = new Date(collectedStamps[stampId].collectedAt);
                    dateText = `<div class="stamp-date">${date.getMonth() + 1}/${date.getDate()} ${date.getHours()}:${String(date.getMinutes()).padStart(2, '0')}</div>`;
                    
                    // スクリーンショットがあれば画像を表示
                    if (collectedStamps[stampId].screenshot) {
                        const screenshotData = collectedStamps[stampId].screenshot;
                        console.log(`Stamp ${stampId} screenshot:`, {
                            hasData: !!screenshotData,
                            length: screenshotData ? screenshotData.length : 0,
                            prefix: screenshotData ? screenshotData.substring(0, 50) : 'none'
                        });
                        iconContent = `<img src="${screenshotData}" alt="${stamp.name}" style="width:100%; height:100%; object-fit:contain;">`;
                    } else {
                        console.log(`Stamp ${stampId} has no screenshot`);
                    }
                }
                
                stampItem.innerHTML = `
                    <div class="stamp-icon">${iconContent}</div>
                    <div class="stamp-name">${stamp.name}</div>
                    ${dateText}
                `;
                
                stampsGrid.appendChild(stampItem);
            });
            
            // 進捗を更新
            const count = Object.keys(collectedStamps).length;
            collectedCount.textContent = count;
            
            // コンプリートメッセージ
            if (count === Object.keys(STAMPS).length) {
                completeMessageContainer.innerHTML = `
                    <div class="complete-message">
                        🎊 おめでとうございます！ 🎊<br>
                        全${Object.keys(STAMPS).length}種類コンプリート！
                    </div>
                `;
            } else {
                completeMessageContainer.innerHTML = '';
            }
            
            // モーダルを表示
            const modal = document.getElementById('stamp-book-modal');
            modal.style.display = 'block';
            
            // 明示的にスクロール位置をリセット
            requestAnimationFrame(() => {
                modal.scrollTop = 0;
                console.log('Modal scrollTop set to 0');
            });
        }
        
        // アニメーション追加
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateX(-50%) translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateX(-50%) translateY(0);
                }
            }
            @keyframes slideUp {
                from {
                    opacity: 1;
                    transform: translateX(-50%) translateY(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(-50%) translateY(-20px);
                }
            }
            @keyframes celebratePop {
                0% {
                    opacity: 0;
                    transform: translate(-50%, -50%) scale(0.5);
                }
                50% {
                    transform: translate(-50%, -50%) scale(1.1);
                }
                100% {
                    opacity: 1;
                    transform: translate(-50%, -50%) scale(1);
                }
            }
            @keyframes fadeOut {
                from {
                    opacity: 1;
                }
                to {
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
        
        // 画面タップでアニメーションを再生
        let sceneReady = false;
        let currentFacingMode = 'environment'; // 'environment' = アウトカメラ, 'user' = インカメラ
        
        // 拡大縮小用の変数
        let currentScale = 1;
        
        // 回転用の変数
        let currentRotationX = 0;
        let currentRotationY = 0; // Y軸回転（回転ボタン用）
        
        // ダブルタップ検出用の変数
        let lastTapTime = 0;
        const doubleTapDelay = 300; // 300ms以内の2回タップでダブルタップ
        
        document.addEventListener('DOMContentLoaded', function() {
            const scene = document.querySelector('a-scene');
            const sheepModel = document.querySelector('#sheep-model');
            const foxModel = document.querySelector('#fox-model');
            const penginModel = document.querySelector('#pengin-model');
            const tonakaiModel = document.querySelector('#tonakai-model');
            const pigModel = document.querySelector('#pig-model');
            let activeModel = null; // 現在アクティブなモデル
            const cameraButton = document.getElementById('camera-button');
            const videoButton = document.getElementById('video-button');
            const switchCameraButton = document.getElementById('switch-camera-button');
            const flash = document.getElementById('flash');
            const photoPreview = document.getElementById('photo-preview');
            const previewImage = document.getElementById('preview-image');
            const downloadButton = document.getElementById('download-button');
            const closeButton = document.getElementById('close-button');
            let capturedImageData = null;
            let mediaRecorder = null;
            let recordedChunks = [];
            let isRecording = false;
            let recordingStartTime = 0;
            
            // マーカー検出時にアクティブモデルを設定
            const patternSheepMarker = document.querySelector('#pattern-sheep-marker');
            const patternFoxMarker = document.querySelector('#pattern-fox-marker');
            const patternPenginMarker = document.querySelector('#pattern-pengin-marker');
            const patternTonakaiMarker = document.querySelector('#pattern-tonakai-marker');
            const patternPigMarker = document.querySelector('#pattern-pig-marker');
            
            let currentMarkerStampId = null; // 現在検出中のマーカーのスタンプID
            let allHitboxes = []; // すべてのヒットボックス
            
            // ヒットエフェクトを表示
            function showHitEffect(pokeball, hitbox) {
                // 1. パーティクルエフェクト（星）
                const ballPos = pokeball.object3D.getWorldPosition(new THREE.Vector3());
                const particleIcons = ['💥', '⭐', '✨', '💫', '🌟'];
                
                for (let i = 0; i < 10; i++) {
                    setTimeout(() => {
                        const particle = document.createElement('div');
                        particle.className = 'hit-particle';
                        particle.textContent = particleIcons[Math.floor(Math.random() * particleIcons.length)];
                        
                        // ランダムな方向を設定
                        const randomX = (Math.random() - 0.5) * 2; // -1 ~ 1
                        const randomY = -Math.random() * 150; // -150 ~ 0
                        
                        particle.style.cssText = `
                            position: fixed;
                            font-size: 30px;
                            pointer-events: none;
                            z-index: 10000;
                            --random-x: ${randomX};
                            --random-y: ${randomY};
                        `;
                        particle.style.animation = 'hitParticle 0.8s ease-out forwards';
                        
                        // 画面上の位置を計算（3D → 2D変換）
                        const camera = scene.camera;
                        const vector = ballPos.clone();
                        vector.project(camera);
                        
                        const x = (vector.x * 0.5 + 0.5) * window.innerWidth;
                        const y = (vector.y * -0.5 + 0.5) * window.innerHeight;
                        
                        particle.style.left = x + 'px';
                        particle.style.top = y + 'px';
                        
                        document.body.appendChild(particle);
                        
                        setTimeout(() => {
                            if (particle.parentNode) {
                                document.body.removeChild(particle);
                            }
                        }, 800);
                    }, i * 30);
                }
                
                // 2. ヒット音を再生（既存のスタンプ音を使用）
                playSound(soundStamp01);
                
                // 3. 画面フラッシュ
                const flash = document.getElementById('flash');
                if (flash) {
                    flash.classList.add('active');
                    setTimeout(() => {
                        flash.classList.remove('active');
                    }, 100);
                }
            }
            
            // ポケボールを投げる関数
            function throwPokeball(event) {
                // タップ位置からカメラ方向へのレイを計算
                const camera = scene.camera;
                if (!camera) return;
                
                const touch = event.changedTouches ? event.changedTouches[0] : event;
                const x = (touch.clientX / window.innerWidth) * 2 - 1;
                const y = -(touch.clientY / window.innerHeight) * 2 + 1;
                
                // レイキャスター（タップ位置から3D空間への線）
                const raycaster = new THREE.Raycaster();
                raycaster.setFromCamera(new THREE.Vector2(x, y), camera);
                
                // ポケボールを生成
                const pokeball = document.createElement('a-entity');
                pokeball.setAttribute('gltf-model', '{{ asset("cg/poke_ball_04.glb") }}');
                pokeball.setAttribute('scale', '0.1 0.1 0.1'); // サイズを小さく（0.5 → 0.1、元の5分の1）
                pokeball.setAttribute('pokeball-throwable', '');
                
                // カメラの位置から開始
                const cameraPos = camera.getWorldPosition(new THREE.Vector3());
                pokeball.setAttribute('position', `${cameraPos.x} ${cameraPos.y} ${cameraPos.z}`);
                
                scene.appendChild(pokeball);
                
                // レイの方向に投げる
                pokeball.addEventListener('loaded', function() {
                    // モデルのマテリアルを修正してちらつきを防ぐ
                    const model = pokeball.getObject3D('mesh');
                    if (model) {
                        model.traverse(function(node) {
                            if (node.isMesh) {
                                // ジオメトリのスムージングを有効化
                                if (node.geometry) {
                                    // 法線を再計算してスムーズに見せる
                                    node.geometry.computeVertexNormals();
                                }
                                
                                // マテリアルの設定
                                if (node.material) {
                                    const materials = Array.isArray(node.material) ? node.material : [node.material];
                                    materials.forEach(mat => {
                                        // 両面レンダリング
                                        mat.side = THREE.DoubleSide;
                                        mat.depthWrite = true;
                                        mat.depthTest = true;
                                        
                                        // Z-fightingを防ぐためのポリゴンオフセット
                                        mat.polygonOffset = true;
                                        mat.polygonOffsetFactor = 1;
                                        mat.polygonOffsetUnits = 1;
                                        
                                        // フラットシェーディングを無効化（スムーズに見せる）
                                        mat.flatShading = false;
                                        
                                        // アンチエイリアス効果を高める
                                        mat.precision = 'highp';
                                        
                                        // 金属質感を調整（ポケボールらしく）
                                        if (mat.metalness !== undefined) {
                                            mat.metalness = 0.3;
                                            mat.roughness = 0.4;
                                        }
                                        
                                        // アルファ値を完全不透明に
                                        mat.transparent = false;
                                        mat.opacity = 1.0;
                                        
                                        // デプスバイアスを設定
                                        mat.depthFunc = THREE.LessEqualDepth;
                                        
                                        // マテリアルの更新を強制
                                        mat.needsUpdate = true;
                                    });
                                }
                                
                                // メッシュのレンダリング順序を設定
                                node.renderOrder = 1000;
                                
                                // フラスタムカリングを無効化（遠くでも消えない）
                                node.frustumCulled = false;
                            }
                        });
                    }
                    
                    const direction = raycaster.ray.direction.clone();
                    const speed = 15; // 投げる速さを大幅に増加（8 → 15）
                    pokeball.components['pokeball-throwable'].throw(direction, speed);
                    
                    // 当たり判定チェック（フレームごと）
                    let hasHit = false; // 重複ヒット防止
                    const checkInterval = setInterval(() => {
                        if (hasHit) return;
                        
                        const ballPos = pokeball.object3D.getWorldPosition(new THREE.Vector3());
                        
                        // すべてのヒットボックスと衝突判定
                        for (let i = 0; i < allHitboxes.length; i++) {
                            const hitbox = allHitboxes[i];
                            if (hitbox.checkCollision(ballPos)) {
                                hasHit = true;
                                const stampId = hitbox.data.stampId;
                                console.log('✓ Hit!', stampId);
                                
                                // ヒットしたモデルのanime02を再生
                                const hitModel = hitbox.el;
                                if (hitModel && hitModel.playHitAnimation) {
                                    hitModel.playHitAnimation();
                                    console.log('Playing hit animation on model:', stampId);
                                }
                                
                                // 衝突エフェクト
                                showHitEffect(pokeball, hitbox);
                                
                                // 跳ね返りアニメーション
                                const throwableComponent = pokeball.components['pokeball-throwable'];
                                if (throwableComponent) {
                                    // 速度を反転させて跳ね返り
                                    throwableComponent.velocity.multiplyScalar(-0.6); // 60%の速度で跳ね返る
                                    throwableComponent.velocity.y += 3; // 上向きに跳ねる
                                    
                                    // 回転速度を上げる
                                    const model = pokeball.getObject3D('mesh');
                                    if (model) {
                                        model.traverse(function(node) {
                                            if (node.isMesh) {
                                                // ヒット時に一瞬拡大
                                                const originalScale = pokeball.object3D.scale.clone();
                                                pokeball.object3D.scale.multiplyScalar(1.3);
                                                setTimeout(() => {
                                                    pokeball.object3D.scale.copy(originalScale);
                                                }, 100);
                                            }
                                        });
                                    }
                                }
                                
                                // ボールを消すまでの時間を設定
                                setTimeout(() => {
                                    clearInterval(checkInterval);
                                    if (pokeball.parentNode) {
                                        // フェードアウトアニメーション
                                        let opacity = 1;
                                        const fadeInterval = setInterval(() => {
                                            opacity -= 0.1;
                                            const model = pokeball.getObject3D('mesh');
                                            if (model) {
                                                model.traverse(function(node) {
                                                    if (node.isMesh && node.material) {
                                                        const materials = Array.isArray(node.material) ? node.material : [node.material];
                                                        materials.forEach(mat => {
                                                            mat.transparent = true;
                                                            mat.opacity = opacity;
                                                        });
                                                    }
                                                });
                                            }
                                            
                                            if (opacity <= 0) {
                                                clearInterval(fadeInterval);
                                                if (pokeball.parentNode) {
                                                    pokeball.parentNode.removeChild(pokeball);
                                                }
                                            }
                                        }, 50);
                                    }
                                }, 1000); // 1秒後にフェードアウト開始
                                
                                // スクリーンショット撮影してスタンプ登録
                                // ヒット後0.2秒間ボールを表示し、その後非表示にしてモデルのみ撮影
                                setTimeout(() => {
                                    // ボールを非表示
                                    pokeball.setAttribute('visible', 'false');
                                    
                                    // 次のフレームでスクリーンショット撮影（背景透過）
                                    setTimeout(() => {
                                        captureModelScreenshot(function(screenshot) {
                                            collectStamp(stampId, screenshot);
                                            
                                            // スクリーンショット後、ボールを再表示してフェードアウト継続
                                            pokeball.setAttribute('visible', 'true');
                                        });
                                    }, 16); // 1フレーム後
                                }, 200); // ヒット後0.2秒
                                
                                break;
                            }
                        }
                    }, 16); // 約60FPS
                    
                    // 8秒後にチェック終了
                    setTimeout(() => clearInterval(checkInterval), 8000);
                });
            }
            
            // タップ時間によるボール投げシステム
            let tapStartTime = 0;
            let isTapping = false;
            let isThrowing = false; // ボール投げ中フラグ（重複防止）
            
            // 捕まえるボタンは廃止し、常に投げられる状態に
            const catchModeActive = true;
            
            // UIボタンかどうかをチェックする関数
            function isUIButton(element) {
                return element && (element.id === 'stamp-book-button' || 
                    element.id === 'camera-button' ||
                    element.id === 'video-button' ||
                    element.id === 'switch-camera-button' ||
                    element.id === 'rotate-up' ||
                    element.id === 'rotate-down' ||
                    element.closest('#stamp-book-button') ||
                    element.closest('#camera-button') ||
                    element.closest('#video-button') ||
                    element.closest('#switch-camera-button') ||
                    element.closest('#rotate-up') ||
                    element.closest('#rotate-down') ||
                    element.closest('.rotation-buttons'));
            }
            
            // タップ/クリック開始検出（タッチデバイス）
            scene.addEventListener('touchstart', function(event) {
                const touch = event.touches[0];
                const element = document.elementFromPoint(touch.clientX, touch.clientY);
                
                // UIボタンのタップは無視
                if (isUIButton(element)) {
                    return;
                }
                
                // タップ開始時間を記録
                tapStartTime = Date.now();
                isTapping = true;
                
                console.log('タップ開始');
            });
            
            // マウスダウン検出（PC）
            scene.addEventListener('mousedown', function(event) {
                const element = document.elementFromPoint(event.clientX, event.clientY);
                
                // UIボタンのクリックは無視
                if (isUIButton(element)) {
                    return;
                }
                
                // クリック開始時間を記録
                tapStartTime = Date.now();
                isTapping = true;
                
                console.log('マウスダウン開始');
            });
            
            // タップ終了時にボールを投げる（タッチデバイス）
            scene.addEventListener('touchend', function(event) {
                if (!isTapping) return;
                if (isThrowing) {
                    console.log('Already throwing - ignoring');
                    isTapping = false;
                    return;
                }
                
                const touch = event.changedTouches[0];
                const element = document.elementFromPoint(touch.clientX, touch.clientY);
                
                // UIボタンのタップは無視
                if (isUIButton(element)) {
                    isTapping = false;
                    return;
                }
                
                // タップ時間を計算（ミリ秒）
                const tapEndTime = Date.now();
                const tapDuration = tapEndTime - tapStartTime;
                
                console.log('タップ時間:', tapDuration, 'ms');
                
                // タップ時間に応じた速度を計算
                // 最小: 100ms → 速度10, 最大: 1000ms → 速度30
                const minTapTime = 100;
                const maxTapTime = 1000;
                const minSpeed = 10;
                const maxSpeed = 30;
                
                const clampedDuration = Math.max(minTapTime, Math.min(maxTapTime, tapDuration));
                const speed = minSpeed + ((clampedDuration - minTapTime) / (maxTapTime - minTapTime)) * (maxSpeed - minSpeed);
                
                console.log('投げる速度:', speed);
                
                // ボール投げ中フラグを立てる
                isThrowing = true;
                
                // 画面中央方向に投げる
                throwPokeballToCenter(speed);
                
                // 500ms後にフラグをリセット
                setTimeout(() => {
                    isThrowing = false;
                }, 500);
                
                isTapping = false;
            });
            
            // マウスアップ時にボールを投げる（PC）
            scene.addEventListener('mouseup', function(event) {
                if (!isTapping) return;
                if (isThrowing) {
                    console.log('Already throwing - ignoring');
                    isTapping = false;
                    return;
                }
                
                const element = document.elementFromPoint(event.clientX, event.clientY);
                
                // UIボタンのクリックは無視
                if (isUIButton(element)) {
                    isTapping = false;
                    return;
                }
                
                // クリック時間を計算（ミリ秒）
                const tapEndTime = Date.now();
                const tapDuration = tapEndTime - tapStartTime;
                
                console.log('マウスクリック時間:', tapDuration, 'ms');
                
                // クリック時間に応じた速度を計算
                const minTapTime = 100;
                const maxTapTime = 1000;
                const minSpeed = 10;
                const maxSpeed = 30;
                
                const clampedDuration = Math.max(minTapTime, Math.min(maxTapTime, tapDuration));
                const speed = minSpeed + ((clampedDuration - minTapTime) / (maxTapTime - minTapTime)) * (maxSpeed - minSpeed);
                
                console.log('投げる速度:', speed);
                
                // ボール投げ中フラグを立てる
                isThrowing = true;
                
                // 画面中央方向に投げる
                throwPokeballToCenter(speed);
                
                // 500ms後にフラグをリセット
                setTimeout(() => {
                    isThrowing = false;
                }, 500);
                
                isTapping = false;
            });
            
            // 画面中央方向にポケボールを投げる（タップ時間で速度調整）
            function throwPokeballToCenter(speed) {
                const camera = scene.camera;
                if (!camera) return;
                
                // カメラの位置から開始
                const cameraPos = camera.getWorldPosition(new THREE.Vector3());
                
                // ポケボールを生成（サイズを半分に: 0.2 → 0.1）
                const pokeball = document.createElement('a-entity');
                pokeball.setAttribute('gltf-model', '{{ asset("cg/poke_ball_04.glb") }}');
                pokeball.setAttribute('scale', '0.1 0.1 0.1');
                pokeball.setAttribute('pokeball-throwable', '');
                pokeball.setAttribute('position', `${cameraPos.x} ${cameraPos.y} ${cameraPos.z}`);
                
                scene.appendChild(pokeball);
                
                // 画面中央方向（カメラの前方向）を取得
                const cameraQuaternion = camera.quaternion.clone();
                const forward = new THREE.Vector3(0, 0, -1);
                forward.applyQuaternion(cameraQuaternion);
                forward.normalize();
                
                console.log('Throw to center - Speed:', speed, 'Direction:', forward);
                
                // ボールが読み込まれたら投げる
                pokeball.addEventListener('loaded', function() {
                    // モデルのマテリアルを修正してちらつきを防ぐ（Android対策強化）
                    const model = pokeball.getObject3D('mesh');
                    if (model) {
                        let meshIndex = 0;
                        model.traverse(function(node) {
                            if (node.isMesh) {
                                // ジオメトリのスムージングを有効化
                                if (node.geometry) {
                                    node.geometry.computeVertexNormals();
                                }
                                
                                // マテリアルの設定（Android向けちらつき対策強化）
                                if (node.material) {
                                    const materials = Array.isArray(node.material) ? node.material : [node.material];
                                    materials.forEach((mat, index) => {
                                        // 基本設定
                                        mat.side = THREE.FrontSide;
                                        mat.depthWrite = true;
                                        mat.depthTest = true;
                                        
                                        // Android向け：polygonOffsetを大幅に強化
                                        mat.polygonOffset = true;
                                        mat.polygonOffsetFactor = (meshIndex * 1.0 + index * 1.0); // 0.1 → 1.0に増加
                                        mat.polygonOffsetUnits = (meshIndex * 1.0 + index * 1.0); // 0.1 → 1.0に増加
                                        
                                        mat.flatShading = false;
                                        mat.precision = 'highp';
                                        
                                        // PBRマテリアルの設定
                                        if (mat.metalness !== undefined) {
                                            mat.metalness = 0.2;
                                            mat.roughness = 0.5;
                                        }
                                        
                                        // 透明度設定
                                        mat.transparent = false;
                                        mat.opacity = 1.0;
                                        mat.alphaTest = 0.5;
                                        
                                        // 深度関数（Android向け）
                                        mat.depthFunc = THREE.LessEqualDepth;
                                        
                                        // Android向け：dithering有効化（ちらつきを拡散）
                                        mat.dithering = true;
                                        
                                        mat.needsUpdate = true;
                                    });
                                    
                                    // renderOrderを大きく設定（Android向け）
                                    node.renderOrder = 1000 + meshIndex * 10;
                                }
                                meshIndex++;
                            }
                        });
                    }
                    
                    pokeball.components['pokeball-throwable'].throw(forward, speed);
                    
                    // 当たり判定チェック（フレームごと）
                    let hasHit = false;
                    const checkInterval = setInterval(() => {
                        if (hasHit) return;
                        
                        const ballPos = pokeball.object3D.getWorldPosition(new THREE.Vector3());
                        
                        // すべてのヒットボックスと衝突判定
                        for (let i = 0; i < allHitboxes.length; i++) {
                            const hitbox = allHitboxes[i];
                            if (hitbox.checkCollision(ballPos)) {
                                hasHit = true;
                                const stampId = hitbox.data.stampId;
                                console.log('✓ Hit!', stampId);
                                
                                // ヒットしたモデルのanime02を再生
                                const hitModel = hitbox.el;
                                if (hitModel && hitModel.playHitAnimation) {
                                    hitModel.playHitAnimation();
                                    console.log('Playing hit animation on model:', stampId);
                                }
                                
                                // 衝突エフェクト
                                showHitEffect(pokeball, hitbox);
                                
                                // 跳ね返りアニメーション
                                const throwableComponent = pokeball.components['pokeball-throwable'];
                                if (throwableComponent) {
                                    throwableComponent.velocity.multiplyScalar(-0.6);
                                    throwableComponent.velocity.y += 3;
                                    
                                    const model = pokeball.getObject3D('mesh');
                                    if (model) {
                                        model.traverse(function(node) {
                                            if (node.isMesh) {
                                                const originalScale = pokeball.object3D.scale.clone();
                                                pokeball.object3D.scale.multiplyScalar(1.3);
                                                setTimeout(() => {
                                                    pokeball.object3D.scale.copy(originalScale);
                                                }, 100);
                                            }
                                        });
                                    }
                                    
                                    // フェードアウト処理
                                    setTimeout(() => {
                                        let opacity = 1.0;
                                        const fadeInterval = setInterval(() => {
                                            opacity -= 0.05;
                                            const model = pokeball.getObject3D('mesh');
                                            if (model) {
                                                model.traverse(function(node) {
                                                    if (node.material) {
                                                        const materials = Array.isArray(node.material) ? node.material : [node.material];
                                                        materials.forEach(mat => {
                                                            mat.transparent = true;
                                                            mat.opacity = Math.max(0, opacity);
                                                            mat.needsUpdate = true;
                                                        });
                                                    }
                                                });
                                            }
                                            
                                            if (opacity <= 0) {
                                                clearInterval(fadeInterval);
                                                if (pokeball.parentNode) {
                                                    pokeball.parentNode.removeChild(pokeball);
                                                }
                                            }
                                        }, 50);
                                    }, 1000);
                                }
                                
                                // スクリーンショット撮影してスタンプ登録
                                // ヒット後0.2秒間ボールを表示し、その後非表示にしてモデルのみ撮影
                                setTimeout(() => {
                                    // ボールを非表示
                                    pokeball.setAttribute('visible', 'false');
                                    
                                    // 次のフレームでスクリーンショット撮影（背景透過）
                                    setTimeout(() => {
                                        captureModelScreenshot(function(screenshot) {
                                            collectStamp(stampId, screenshot);
                                            
                                            // スクリーンショット後、ボールを再表示してフェードアウト継続
                                            pokeball.setAttribute('visible', 'true');
                                        });
                                    }, 16); // 1フレーム後
                                }, 200); // ヒット後0.2秒
                                
                                break;
                            }
                        }
                    }, 16); // 約60FPS
                    
                    // 8秒後にチェック終了
                    setTimeout(() => clearInterval(checkInterval), 8000);
                });
            }
            
            if (patternSheepMarker) {
                patternSheepMarker.addEventListener('markerFound', function() {
                    console.log('Pattern-sheep marker found');
                    activeModel = sheepModel;
                    currentMarkerStampId = 'sheep';
                    // 回転ボタンを表示
                    document.getElementById('rotation-buttons').classList.add('visible');
                    // ヒットボックスを登録
                    if (sheepModel.components.hitbox) {
                        if (!allHitboxes.includes(sheepModel.components.hitbox)) {
                            allHitboxes.push(sheepModel.components.hitbox);
                        }
                    }
                });
                patternSheepMarker.addEventListener('markerLost', function() {
                    console.log('Pattern-sheep marker lost');
                    if (activeModel === sheepModel) {
                        activeModel = null;
                        // 回転ボタンを非表示
                        document.getElementById('rotation-buttons').classList.remove('visible');
                    }
                    if (currentMarkerStampId === 'sheep') {
                        currentMarkerStampId = null;
                    }
                    // ヒットボックスを削除
                    if (sheepModel.components.hitbox) {
                        const index = allHitboxes.indexOf(sheepModel.components.hitbox);
                        if (index > -1) {
                            allHitboxes.splice(index, 1);
                        }
                    }
                });
            }
            
            if (patternFoxMarker) {
                patternFoxMarker.addEventListener('markerFound', function() {
                    console.log('Pattern-fox marker found');
                    activeModel = foxModel;
                    currentMarkerStampId = 'fox';
                    // 回転ボタンを表示
                    document.getElementById('rotation-buttons').classList.add('visible');
                    if (foxModel.components.hitbox) {
                        if (!allHitboxes.includes(foxModel.components.hitbox)) {
                            allHitboxes.push(foxModel.components.hitbox);
                        }
                    }
                });
                patternFoxMarker.addEventListener('markerLost', function() {
                    console.log('Pattern-fox marker lost');
                    if (activeModel === foxModel) {
                        activeModel = null;
                        // 回転ボタンを非表示
                        document.getElementById('rotation-buttons').classList.remove('visible');
                    }
                    if (currentMarkerStampId === 'fox') {
                        currentMarkerStampId = null;
                    }
                    if (foxModel.components.hitbox) {
                        const index = allHitboxes.indexOf(foxModel.components.hitbox);
                        if (index > -1) {
                            allHitboxes.splice(index, 1);
                        }
                    }
                });
            }
            
            if (patternPenginMarker) {
                patternPenginMarker.addEventListener('markerFound', function() {
                    console.log('Pattern-pengin marker found');
                    activeModel = penginModel;
                    currentMarkerStampId = 'pengin';
                    // 回転ボタンを表示
                    document.getElementById('rotation-buttons').classList.add('visible');
                    if (penginModel.components.hitbox) {
                        if (!allHitboxes.includes(penginModel.components.hitbox)) {
                            allHitboxes.push(penginModel.components.hitbox);
                        }
                    }
                });
                patternPenginMarker.addEventListener('markerLost', function() {
                    console.log('Pattern-pengin marker lost');
                    if (activeModel === penginModel) {
                        activeModel = null;
                        // 回転ボタンを非表示
                        document.getElementById('rotation-buttons').classList.remove('visible');
                    }
                    if (currentMarkerStampId === 'pengin') {
                        currentMarkerStampId = null;
                    }
                    if (penginModel.components.hitbox) {
                        const index = allHitboxes.indexOf(penginModel.components.hitbox);
                        if (index > -1) {
                            allHitboxes.splice(index, 1);
                        }
                    }
                });
            }
            
            if (patternTonakaiMarker) {
                patternTonakaiMarker.addEventListener('markerFound', function() {
                    console.log('Pattern-tonakai marker found');
                    activeModel = tonakaiModel;
                    currentMarkerStampId = 'tonakai';
                    // 回転ボタンを表示
                    document.getElementById('rotation-buttons').classList.add('visible');
                    if (tonakaiModel.components.hitbox) {
                        if (!allHitboxes.includes(tonakaiModel.components.hitbox)) {
                            allHitboxes.push(tonakaiModel.components.hitbox);
                        }
                    }
                });
                patternTonakaiMarker.addEventListener('markerLost', function() {
                    console.log('Pattern-tonakai marker lost');
                    if (activeModel === tonakaiModel) {
                        activeModel = null;
                        // 回転ボタンを非表示
                        document.getElementById('rotation-buttons').classList.remove('visible');
                    }
                    if (currentMarkerStampId === 'tonakai') {
                        currentMarkerStampId = null;
                    }
                    if (tonakaiModel.components.hitbox) {
                        const index = allHitboxes.indexOf(tonakaiModel.components.hitbox);
                        if (index > -1) {
                            allHitboxes.splice(index, 1);
                        }
                    }
                });
            }
            
            if (patternPigMarker) {
                patternPigMarker.addEventListener('markerFound', function() {
                    console.log('Pattern-pig marker found');
                    activeModel = pigModel;
                    currentMarkerStampId = 'pig';
                    // 回転ボタンを表示
                    document.getElementById('rotation-buttons').classList.add('visible');
                    if (pigModel.components.hitbox) {
                        if (!allHitboxes.includes(pigModel.components.hitbox)) {
                            allHitboxes.push(pigModel.components.hitbox);
                        }
                    }
                });
                patternPigMarker.addEventListener('markerLost', function() {
                    console.log('Pattern-pig marker lost');
                    if (activeModel === pigModel) {
                        activeModel = null;
                        // 回転ボタンを非表示
                        document.getElementById('rotation-buttons').classList.remove('visible');
                    }
                    if (currentMarkerStampId === 'pig') {
                        currentMarkerStampId = null;
                    }
                    if (pigModel.components.hitbox) {
                        const index = allHitboxes.indexOf(pigModel.components.hitbox);
                        if (index > -1) {
                            allHitboxes.splice(index, 1);
                        }
                    }
                });
            }
            
            scene.addEventListener('loaded', function() {
                sceneReady = true;
                console.log('Scene loaded');
            });
            
            // スタンプ帳ボタン
            const stampBookButton = document.getElementById('stamp-book-button');
            stampBookButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                showStampBook();
            });
            
            // スタンプ帳を閉じる
            const closeStampBookButton = document.getElementById('close-stamp-book');
            closeStampBookButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const modal = document.getElementById('stamp-book-modal');
                modal.style.display = 'none';
            });
            
            // スタンプリセットボタン
            const clearStampsButton = document.getElementById('clear-stamps');
            clearStampsButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // カスタム確認ダイアログを表示
                showConfirmDialog();
            }, false);
            
            // カスタム確認ダイアログ
            function showConfirmDialog() {
                const overlay = document.getElementById('confirm-overlay');
                const dialog = document.getElementById('confirm-dialog');
                
                overlay.style.display = 'block';
                dialog.style.display = 'block';
            }
            
            function hideConfirmDialog() {
                const overlay = document.getElementById('confirm-overlay');
                const dialog = document.getElementById('confirm-dialog');
                
                overlay.style.display = 'none';
                dialog.style.display = 'none';
            }
            
            // 確認ダイアログの「リセット」ボタン
            document.querySelector('#confirm-dialog .confirm-yes').addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Clear stamps confirmed');
                
                // ダイアログを閉じる
                hideConfirmDialog();
                
                // スタンプ帳モーダルを閉じる
                const modal = document.getElementById('stamp-book-modal');
                modal.style.display = 'none';
                
                // LocalStorageをクリア（スタンプ + 捕獲状態）
                localStorage.removeItem('ar-stamp-rally');
                localStorage.removeItem('ar-captured-animals');
                
                // 全てのモデルの状態をリセット
                const modelIds = ['sheep-model', 'fox-model', 'pengin-model', 'tonakai-model', 'pig-model'];
                modelIds.forEach(modelId => {
                    const model = document.getElementById(modelId);
                    if (model && model.resetCaptureState) {
                        model.resetCaptureState();
                        console.log('Model state reset:', modelId);
                    }
                });
                
                // 捕獲済みメッセージを非表示
                hideCapturedMessage();
                
                // バッジを更新
                updateStampBadge();
                
                console.log('✓ All stamps and capture states cleared successfully');
            }, false);
            
            // 確認ダイアログの「キャンセル」ボタン
            document.querySelector('#confirm-dialog .confirm-no').addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Clear stamps cancelled');
                hideConfirmDialog();
            }, false);
            
            // 回転ボタンのイベントリスナー
            const rotateUpButton = document.getElementById('rotate-up');
            const rotateDownButton = document.getElementById('rotate-down');
            
            rotateUpButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (activeModel) {
                    // 上に30度回転（X軸を-30度）
                    currentRotationX -= 30;
                    activeModel.setAttribute('rotation', {
                        x: currentRotationX,
                        y: currentRotationY,
                        z: 0
                    });
                    console.log('Rotated up. Current X rotation:', currentRotationX);
                }
            });
            
            rotateDownButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (activeModel) {
                    // 下に30度回転（X軸を+30度）
                    currentRotationX += 30;
                    activeModel.setAttribute('rotation', {
                        x: currentRotationX,
                        y: currentRotationY,
                        z: 0
                    });
                    console.log('Rotated down. Current X rotation:', currentRotationX);
                }
            });
            
            // オーバーレイクリックでダイアログを閉じる
            document.getElementById('confirm-overlay').addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                hideConfirmDialog();
            }, false);
            
            // モーダル背景クリックで閉じる
            const stampBookModal = document.getElementById('stamp-book-modal');
            stampBookModal.addEventListener('click', function(e) {
                // モーダルの背景部分のみクリック時に閉じる（コンテンツ部分は除外）
                if (e.target === stampBookModal) {
                    e.preventDefault();
                    e.stopPropagation();
                    stampBookModal.style.display = 'none';
                }
            });
            
            // モーダルコンテンツのクリックイベントが背景に伝播しないようにする
            const stampBookContent = document.getElementById('stamp-book-content');
            stampBookContent.addEventListener('click', function(e) {
                e.stopPropagation();
            });
            
            // 初期化：バッジを更新
            updateStampBadge();
            
            // カメラ切り替え機能
            switchCameraButton.addEventListener('click', async function(e) {
                e.stopPropagation();
                console.log('Switching camera...');
                
                try {
                    // 現在のビデオストリームを停止
                    const video = document.querySelector('video');
                    if (video && video.srcObject) {
                        const tracks = video.srcObject.getTracks();
                        tracks.forEach(track => track.stop());
                    }
                    
                    // カメラの向きを切り替え
                    currentFacingMode = currentFacingMode === 'environment' ? 'user' : 'environment';
                    console.log('New facing mode:', currentFacingMode);
                    
                    // 新しいカメラストリームを取得
                    const constraints = {
                        video: {
                            facingMode: currentFacingMode,
                            width: { ideal: 1280 },
                            height: { ideal: 960 }
                        }
                    };
                    
                    const stream = await navigator.mediaDevices.getUserMedia(constraints);
                    
                    // ビデオ要素に新しいストリームを設定
                    if (video) {
                        video.srcObject = stream;
                        await video.play();
                        console.log('Camera switched successfully to:', currentFacingMode);
                    }
                    
                    // AR.jsを再初期化（必要に応じて）
                    if (scene.systems['arjs']) {
                        const arjsSystem = scene.systems['arjs'];
                        if (arjsSystem.onVideoCanPlay) {
                            arjsSystem.onVideoCanPlay();
                        }
                    }
                    
                } catch (error) {
                    console.error('Error switching camera:', error);
                    alert('カメラの切り替えに失敗しました。\n' + error.message);
                }
            });
            
            // 動画撮影機能
            videoButton.addEventListener('click', function(e) {
                e.stopPropagation();
                
                if (!isRecording) {
                    startRecording();
                } else {
                    stopRecording();
                }
            });
            
            function startRecording() {
                try {
                    const scene = document.querySelector('a-scene');
                    const arCanvas = scene.canvas;
                    const video = document.querySelector('video');
                    
                    if (!arCanvas || !video) {
                        console.error('Canvas or video not found');
                        alert('動画撮影の準備ができていません');
                        return;
                    }
                    
                    // 合成用の新しいキャンバスを作成
                    const compositeCanvas = document.createElement('canvas');
                    const screenWidth = window.innerWidth;
                    const screenHeight = window.innerHeight;
                    // iPhoneでのパフォーマンス向上のため、解像度を調整
                    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
                    const dpr = isIOS ? Math.min(window.devicePixelRatio || 1, 2) : (window.devicePixelRatio || 1);
                    
                    compositeCanvas.width = screenWidth * dpr;
                    compositeCanvas.height = screenHeight * dpr;
                    const ctx = compositeCanvas.getContext('2d', { 
                        alpha: false,
                        desynchronized: true // パフォーマンス向上
                    });
                    
                    // 合成処理を定期的に実行
                    let lastFrameTime = 0;
                    const targetFPS = isIOS ? 24 : 30; // iOSでは24fpsに制限
                    const frameInterval = 1000 / targetFPS;
                    
                    function compositeFrame(timestamp) {
                        if (!isRecording) return;
                        
                        // フレームレート制御
                        if (timestamp - lastFrameTime < frameInterval) {
                            requestAnimationFrame(compositeFrame);
                            return;
                        }
                        lastFrameTime = timestamp;
                        
                        ctx.clearRect(0, 0, compositeCanvas.width, compositeCanvas.height);
                        ctx.save();
                        ctx.scale(dpr, dpr);
                        
                        // 1. 背景（カメラ映像）を描画
                        const videoAspect = video.videoWidth / video.videoHeight;
                        const screenAspect = screenWidth / screenHeight;
                        
                        let drawWidth, drawHeight, offsetX, offsetY;
                        
                        if (videoAspect > screenAspect) {
                            drawHeight = screenHeight;
                            drawWidth = drawHeight * videoAspect;
                            offsetX = (screenWidth - drawWidth) / 2;
                            offsetY = 0;
                        } else {
                            drawWidth = screenWidth;
                            drawHeight = drawWidth / videoAspect;
                            offsetX = 0;
                            offsetY = (screenHeight - drawHeight) / 2;
                        }
                        
                        ctx.drawImage(video, offsetX, offsetY, drawWidth, drawHeight);
                        
                        // 2. ARコンテンツを重ねる
                        const arAspect = arCanvas.width / arCanvas.height;
                        const targetAspect = screenWidth / screenHeight;
                        
                        let arDrawWidth, arDrawHeight, arOffsetX, arOffsetY;
                        
                        if (arAspect > targetAspect) {
                            arDrawHeight = screenHeight;
                            arDrawWidth = arDrawHeight * arAspect;
                            arOffsetX = (screenWidth - arDrawWidth) / 2;
                            arOffsetY = 0;
                        } else {
                            arDrawWidth = screenWidth;
                            arDrawHeight = arDrawWidth / arAspect;
                            arOffsetX = 0;
                            arOffsetY = (screenHeight - arDrawHeight) / 2;
                        }
                        
                        ctx.drawImage(arCanvas, arOffsetX, arOffsetY, arDrawWidth, arDrawHeight);
                        ctx.restore();
                        
                        requestAnimationFrame(compositeFrame);
                    }
                    
                    // ストリームを取得（フレームレートを調整）
                    const stream = compositeCanvas.captureStream(targetFPS);
                    
                    // MediaRecorderの設定（iPhoneでも再生可能な形式を優先）
                    let options = {};
                    
                    // ビットレートをデバイスに応じて調整
                    const bitrate = isIOS ? 3000000 : 5000000; // iOSは3Mbps、その他は5Mbps
                    
                    // iOSではMP4をサポート、AndroidではWebMをサポート
                    if (MediaRecorder.isTypeSupported('video/mp4')) {
                        options = {
                            mimeType: 'video/mp4',
                            videoBitsPerSecond: bitrate
                        };
                    } else if (MediaRecorder.isTypeSupported('video/webm;codecs=vp9')) {
                        options = {
                            mimeType: 'video/webm;codecs=vp9',
                            videoBitsPerSecond: bitrate
                        };
                    } else if (MediaRecorder.isTypeSupported('video/webm;codecs=vp8')) {
                        options = {
                            mimeType: 'video/webm;codecs=vp8',
                            videoBitsPerSecond: bitrate
                        };
                    } else if (MediaRecorder.isTypeSupported('video/webm')) {
                        options = {
                            mimeType: 'video/webm',
                            videoBitsPerSecond: bitrate
                        };
                    } else {
                        // デフォルト（ブラウザが自動選択）
                        options = {
                            videoBitsPerSecond: bitrate
                        };
                    }
                    
                    recordedChunks = [];
                    mediaRecorder = new MediaRecorder(stream, options);
                    
                    mediaRecorder.ondataavailable = function(event) {
                        if (event.data.size > 0) {
                            recordedChunks.push(event.data);
                        }
                    };
                    
                    mediaRecorder.onstop = function() {
                        const mimeType = mediaRecorder.mimeType || 'video/webm';
                        const blob = new Blob(recordedChunks, { type: mimeType });
                        console.log('Recording stopped, blob size:', blob.size, 'type:', mimeType);
                        console.log('FPS:', targetFPS, 'Bitrate:', bitrate, 'DPR:', dpr);
                        
                        if (blob.size === 0) {
                            console.error('❌ Recorded blob is empty!');
                            alert('動画の録画に失敗しました。データがありません。');
                            return;
                        }
                        
                        // ファイル拡張子を決定
                        let extension = 'webm';
                        if (mimeType.includes('mp4')) {
                            extension = 'mp4';
                        }
                        
                        console.log('Creating video preview...');
                        
                        // プレビューに動画を表示
                        const videoElement = document.createElement('video');
                        videoElement.src = URL.createObjectURL(blob);
                        videoElement.controls = true;
                        videoElement.playsinline = true; // iOSで重要
                        videoElement.style.maxWidth = '100%';
                        videoElement.style.maxHeight = '70vh';
                        videoElement.style.borderRadius = '5px';
                        
                        // 動画読み込みエラーハンドリング
                        videoElement.onerror = function(e) {
                            console.error('❌ Video element error:', e);
                            alert('動画プレビューの表示に失敗しました');
                        };
                        
                        videoElement.onloadedmetadata = function() {
                            console.log('✓ Video metadata loaded, duration:', videoElement.duration);
                        };
                        
                        // プレビュー画像を動画要素に置き換え
                        const previewContainer = document.getElementById('photo-preview');
                        const existingPreview = document.querySelector('#photo-preview img, #photo-preview video');
                        
                        if (!existingPreview) {
                            console.error('❌ Preview element not found!');
                            return;
                        }
                        
                        existingPreview.replaceWith(videoElement);
                        console.log('✓ Video element replaced in preview');
                        
                        // ダウンロードボタンの動作を変更（拡張子も保存）
                        capturedImageData = {
                            blob: blob,
                            extension: extension,
                            mimeType: mimeType
                        };
                        
                        photoPreview.style.display = 'flex';
                        console.log('✓ Preview displayed');
                        
                        recordedChunks = [];
                    };
                    
                    // 録画開始
                    mediaRecorder.start();
                    isRecording = true;
                    recordingStartTime = Date.now();
                    videoButton.classList.add('recording');
                    videoButton.textContent = '⏹️';
                    requestAnimationFrame(compositeFrame);
                    
                    console.log('Recording started with mimeType:', options.mimeType);
                    console.log('Target FPS:', targetFPS, 'Bitrate:', bitrate / 1000000 + 'Mbps');
                    
                } catch (error) {
                    console.error('Error starting recording:', error);
                    alert('動画撮影の開始に失敗しました\n' + error.message);
                }
            }
            
            function stopRecording() {
                if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                    mediaRecorder.stop();
                    isRecording = false;
                    videoButton.classList.remove('recording');
                    videoButton.textContent = '🎥';
                    
                    const duration = Math.round((Date.now() - recordingStartTime) / 1000);
                    console.log('Recording duration:', duration, 'seconds');
                }
            }
            
            // 写真撮影機能
            cameraButton.addEventListener('click', function(e) {
                e.stopPropagation();
                console.log('Taking photo...');
                
                // フラッシュエフェクト
                flash.classList.add('active');
                setTimeout(() => {
                    flash.classList.remove('active');
                }, 200);
                
                // レンダリングサイクルに合わせて撮影
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        try {
                            // ビデオ要素とcanvasを取得
                            const video = document.querySelector('video');
                            const arCanvas = scene.canvas;
                            
                            if (!video) {
                                console.error('Video element not found');
                                return;
                            }
                            
                            if (!arCanvas) {
                                console.error('AR Canvas not found');
                                return;
                            }
                            
                            // 実際の画面サイズを取得
                            const screenWidth = window.innerWidth;
                            const screenHeight = window.innerHeight;
                            
                            // デバイスピクセル比を考慮
                            const dpr = window.devicePixelRatio || 1;
                            
                            console.log('Screen:', screenWidth, 'x', screenHeight);
                            console.log('DPR:', dpr);
                            console.log('Video:', video.videoWidth, 'x', video.videoHeight);
                            console.log('Canvas:', arCanvas.width, 'x', arCanvas.height);
                            
                            // 撮影用の新しいキャンバスを作成（画面サイズに合わせる）
                            const outputCanvas = document.createElement('canvas');
                            outputCanvas.width = screenWidth * dpr;
                            outputCanvas.height = screenHeight * dpr;
                            const ctx = outputCanvas.getContext('2d');
                            
                            // スケーリングを設定
                            ctx.scale(dpr, dpr);
                            
                            // 1. 背景（カメラ映像）を描画
                            ctx.save();
                            
                            // ビデオのアスペクト比を計算
                            const videoAspect = video.videoWidth / video.videoHeight;
                            const screenAspect = screenWidth / screenHeight;
                            
                            let drawWidth, drawHeight, offsetX, offsetY;
                            
                            if (videoAspect > screenAspect) {
                                // ビデオが横長：高さを画面に合わせる
                                drawHeight = screenHeight;
                                drawWidth = drawHeight * videoAspect;
                                offsetX = (screenWidth - drawWidth) / 2;
                                offsetY = 0;
                            } else {
                                // ビデオが縦長：幅を画面に合わせる
                                drawWidth = screenWidth;
                                drawHeight = drawWidth / videoAspect;
                                offsetX = 0;
                                offsetY = (screenHeight - drawHeight) / 2;
                            }
                            
                            ctx.drawImage(video, offsetX, offsetY, drawWidth, drawHeight);
                            ctx.restore();
                            console.log('Background drawn');
                            
                            // 2. ARコンテンツ（3Dモデル）を重ねる
                            ctx.save();
                            ctx.globalCompositeOperation = 'source-over';
                            
                            // ARキャンバスのアスペクト比を計算
                            const arAspect = arCanvas.width / arCanvas.height;
                            const targetAspect = screenWidth / screenHeight;
                            
                            let arDrawWidth, arDrawHeight, arOffsetX, arOffsetY;
                            
                            if (arAspect > targetAspect) {
                                // ARキャンバスが横長：高さを画面に合わせる
                                arDrawHeight = screenHeight;
                                arDrawWidth = arDrawHeight * arAspect;
                                arOffsetX = (screenWidth - arDrawWidth) / 2;
                                arOffsetY = 0;
                            } else {
                                // ARキャンバスが縦長：幅を画面に合わせる
                                arDrawWidth = screenWidth;
                                arDrawHeight = arDrawWidth / arAspect;
                                arOffsetX = 0;
                                arOffsetY = (screenHeight - arDrawHeight) / 2;
                            }
                            
                            ctx.drawImage(arCanvas, arOffsetX, arOffsetY, arDrawWidth, arDrawHeight);
                            ctx.restore();
                            console.log('AR content drawn with correct aspect ratio');
                            console.log('AR draw size:', arDrawWidth, 'x', arDrawHeight, 'at', arOffsetX, arOffsetY);
                            
                            // 画像データを取得
                            capturedImageData = outputCanvas.toDataURL('image/jpeg', 0.92);
                            
                            if (capturedImageData && capturedImageData.length > 1000) {
                                console.log('✓ Photo captured! Size:', Math.round(capturedImageData.length / 1024), 'KB');
                                console.log('Output size:', outputCanvas.width, 'x', outputCanvas.height);
                                
                                previewImage.src = capturedImageData;
                                
                                // 画像読み込みエラーハンドリング
                                previewImage.onerror = function() {
                                    console.error('❌ Failed to load preview image');
                                    alert('写真プレビューの表示に失敗しました');
                                };
                                
                                previewImage.onload = function() {
                                    console.log('✓ Preview image loaded successfully');
                                    photoPreview.style.display = 'flex';
                                };
                                
                            } else {
                                console.error('❌ Image data too small, capture failed');
                                console.error('Data length:', capturedImageData ? capturedImageData.length : 'null');
                                alert('写真の撮影に失敗しました');
                            }
                            
                        } catch (error) {
                            console.error('Error capturing photo:', error);
                        }
                    });
                });
            });
            
            // ダウンロードボタン
            downloadButton.addEventListener('click', async function() {
                if (!capturedImageData) {
                    console.error('No data to download');
                    return;
                }
                
                try {
                    let blob;
                    let filename;
                    let mimeType;
                    
                    // 動画オブジェクトの場合
                    if (capturedImageData.blob) {
                        blob = capturedImageData.blob;
                        filename = 'AR_video_' + new Date().getTime() + '.' + capturedImageData.extension;
                        mimeType = capturedImageData.mimeType;
                    } 
                    // Blobオブジェクトの場合（古い形式、互換性のため残す）
                    else if (capturedImageData instanceof Blob) {
                        blob = capturedImageData;
                        filename = 'AR_video_' + new Date().getTime() + '.webm';
                        mimeType = 'video/webm';
                    } 
                    // Data URLの場合（写真）
                    else {
                        const response = await fetch(capturedImageData);
                        blob = await response.blob();
                        filename = 'AR_photo_' + new Date().getTime() + '.jpg';
                        mimeType = 'image/jpeg';
                    }
                    
                    console.log('Saving file:', filename, 'type:', mimeType, 'size:', blob.size);
                    
                    // iOSやAndroidでWeb Share APIが使える場合
                    if (navigator.share && navigator.canShare) {
                        const file = new File([blob], filename, { type: mimeType });
                        
                        if (navigator.canShare({ files: [file] })) {
                            try {
                                await navigator.share({
                                    files: [file],
                                    title: mimeType.startsWith('video') ? 'AR動画' : 'AR写真',
                                    text: mimeType.startsWith('video') ? 'ARで撮影した動画' : 'ARで撮影した写真'
                                });
                                console.log('File shared successfully');
                                return;
                            } catch (shareError) {
                                console.log('Share cancelled or failed:', shareError);
                            }
                        }
                    }
                    
                    // Web Share APIが使えない場合：従来のダウンロード方式
                    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                    
                    if (isIOS && mimeType.startsWith('image')) {
                        // iOSの場合：画像を長押しで保存を促す
                        alert('画像を長押しして「写真に追加」を選択してください');
                    } else {
                        // その他のデバイス：通常のダウンロード
                        const link = document.createElement('a');
                        link.download = filename;
                        link.href = URL.createObjectURL(blob);
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        URL.revokeObjectURL(link.href);
                        console.log('File downloaded:', filename);
                    }
                } catch (error) {
                    console.error('Error downloading file:', error);
                    alert('保存に失敗しました');
                }
            });
            
            // 閉じるボタン
            closeButton.addEventListener('click', function() {
                console.log('Closing preview');
                photoPreview.style.display = 'none';
                
                // 動画要素を画像要素に戻す
                const videoElement = document.querySelector('#photo-preview video');
                if (videoElement) {
                    console.log('Replacing video with image element');
                    // Blob URLを解放
                    if (videoElement.src && videoElement.src.startsWith('blob:')) {
                        URL.revokeObjectURL(videoElement.src);
                    }
                    
                    const imgElement = document.createElement('img');
                    imgElement.id = 'preview-image';
                    imgElement.src = '';
                    imgElement.alt = '撮影した写真';
                    imgElement.style.maxWidth = '100%';
                    imgElement.style.maxHeight = '70vh';
                    imgElement.style.borderRadius = '5px';
                    videoElement.replaceWith(imgElement);
                }
                
                capturedImageData = null;
                console.log('Preview closed and reset');
            });
            
            // タッチイベントハンドラー（ダブルタップのみ）
            let touchStartHandler = function(e) {
                // ボタンをタップした場合は除外
                if (e.target.closest('#camera-button') || 
                    e.target.closest('#video-button') ||
                    e.target.closest('#switch-camera-button') ||
                    e.target.closest('#photo-preview')) {
                    return;
                }
                
                if (e.touches.length === 1) {
                    // ダブルタップ検出のみ
                    const now = Date.now();
                    const timeSinceLastTap = now - lastTapTime;
                    
                    if (timeSinceLastTap < doubleTapDelay && timeSinceLastTap > 0) {
                        // ダブルタップ検出
                        e.preventDefault();
                        console.log('Double tap detected');
                        if (sceneReady && activeModel) {
                            const clickEvent = new Event('click');
                            activeModel.dispatchEvent(clickEvent);
                        }
                        lastTapTime = 0; // リセット
                    } else {
                        // シングルタップ
                        lastTapTime = now;
                    }
                }
            };
            
            let touchMoveHandler = function(e) {
                // スワイプでの回転機能は削除（何もしない）
            };
            
            let touchEndHandler = function(e) {
                // 何もしない（スワイプ機能削除）
            };
            
            // タッチイベントをリスナーに登録
            document.body.addEventListener('touchstart', touchStartHandler, { passive: false });
            document.body.addEventListener('touchmove', touchMoveHandler, { passive: false });
            document.body.addEventListener('touchend', touchEndHandler, { passive: false });
            
            // PC用：マウス操作は削除（回転ボタンのみ使用）
            
            // PC用：マウスホイールで拡大縮小
            document.body.addEventListener('wheel', function(e) {
                // ボタン上では無効
                if (e.target.closest('#camera-button') || 
                    e.target.closest('#video-button') ||
                    e.target.closest('#switch-camera-button') ||
                    e.target.closest('#photo-preview')) {
                    return;
                }
                
                e.preventDefault();
                
                // ホイールの方向に応じてスケール変更
                const delta = e.deltaY > 0 ? 0.9 : 1.1;
                currentScale *= delta;
                
                // スケールを0.5〜5の範囲に制限
                currentScale = Math.max(0.5, Math.min(5, currentScale));
                
                if (activeModel) {
                    activeModel.setAttribute('scale', {
                        x: currentScale,
                        y: currentScale,
                        z: currentScale
                    });
                }
            }, { passive: false });
            
            // 画面全体のタップを検出（削除：ダブルタップに置き換え）
            // シングルタップでのアニメーション切り替えは無効化
        });
    </script>
</body>
</html>
