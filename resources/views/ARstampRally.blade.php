<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>AR Stamp Rally</title>
    <script src="https://aframe.io/releases/1.4.2/aframe.min.js"></script>
    <script src="https://raw.githack.com/AR-js-org/AR.js/3.4.5/aframe/build/aframe-ar.js"></script>
    <script>
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
                
                el.addEventListener('model-loaded', () => {
                    console.log('Model loaded');
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
                    action02.setLoop(THREE.LoopRepeat, Infinity);
                    action02.stop();
                    
                    this.action01 = action01;
                    this.action02 = action02;
                    
                    console.log('Animations ready:');
                    console.log('  anime01:', clip01.name);
                    console.log('  anime02:', clip02.name);
                });
                
                // マーカー検出時にanime01を自動再生
                const marker = el.parentElement;
                marker.addEventListener('markerFound', () => {
                    console.log('✓ Marker found - Starting anime01');
                    markerVisible = true;
                    if (action01) {
                        action01.reset();
                        action01.play();
                        currentAnimation = 1;
                        console.log('anime01 started automatically');
                    }
                });
                
                marker.addEventListener('markerLost', () => {
                    console.log('✗ Marker lost - Stopping animations');
                    markerVisible = false;
                    if (action01) action01.stop();
                    if (action02) action02.stop();
                    currentAnimation = 1; // リセット
                });
                
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
            },
            tick: function(time, deltaTime) {
                if (this.mixer) {
                    this.mixer.update(deltaTime / 1000);
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
        
        #photo-preview img {
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
    
    <!-- フラッシュエフェクト -->
    <div id="flash"></div>
    
    <!-- カメラボタン -->
    <button id="camera-button" title="写真を撮る">📷</button>
    
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
        arjs="sourceType: webcam; debugUIEnabled: false;"
        vr-mode-ui="enabled: false">
        
        <a-entity camera></a-entity>
        
        <a-marker preset="hiro" id="hiro-marker">
            <a-entity
                id="fox-model"
                gltf-model="{{ asset('cg/3d_isobe_fox5.glb') }}"
                position="0 0 0"
                scale="3 3 3"
                rotation="0 0 0"
                click-animation="clip: anime01">
            </a-entity>
            
            <!-- ライトを追加して明るくする -->
            <a-light type="ambient" intensity="1.3"></a-light>
            <a-light type="directional" intensity="0.7" position="1 1 1"></a-light>
        </a-marker>
        
    </a-scene>

    <script>
        window.addEventListener('arjs-video-loaded', function() {
            console.log('AR.js ready');
            const loader = document.querySelector('.arjs-loader');
            if (loader) loader.style.display = 'none';
        });
        
        setTimeout(function() {
            const loader = document.querySelector('.arjs-loader');
            if (loader) loader.style.display = 'none';
        }, 3000);
        
        // 画面タップでアニメーションを再生
        let sceneReady = false;
        document.addEventListener('DOMContentLoaded', function() {
            const scene = document.querySelector('a-scene');
            const model = document.querySelector('#fox-model');
            const cameraButton = document.getElementById('camera-button');
            const flash = document.getElementById('flash');
            const photoPreview = document.getElementById('photo-preview');
            const previewImage = document.getElementById('preview-image');
            const downloadButton = document.getElementById('download-button');
            const closeButton = document.getElementById('close-button');
            let capturedImageData = null;
            
            scene.addEventListener('loaded', function() {
                sceneReady = true;
                console.log('Scene loaded');
            });
            
            // 写真撮影機能
            cameraButton.addEventListener('click', function(e) {
                e.stopPropagation();
                console.log('Taking photo...');
                
                // フラッシュエフェクト
                flash.classList.add('active');
                setTimeout(() => {
                    flash.classList.remove('active');
                }, 200);
                
                // 背景とARコンテンツを含めて撮影
                setTimeout(() => {
                    const video = document.querySelector('video');
                    const arCanvas = scene.canvas;
                    
                    if (video && arCanvas) {
                        // 新しいキャンバスを作成
                        const canvas = document.createElement('canvas');
                        canvas.width = arCanvas.width;
                        canvas.height = arCanvas.height;
                        const ctx = canvas.getContext('2d');
                        
                        // 1. まず背景（カメラ映像）を描画
                        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                        
                        // 2. その上にARコンテンツを重ねる
                        ctx.drawImage(arCanvas, 0, 0, canvas.width, canvas.height);
                        
                        // 画像データを取得
                        capturedImageData = canvas.toDataURL('image/png');
                        previewImage.src = capturedImageData;
                        photoPreview.style.display = 'flex';
                        console.log('Photo captured with background!');
                    } else {
                        console.error('Failed to capture photo: video or canvas not found');
                    }
                }, 300);
            });
            
            // ダウンロードボタン
            downloadButton.addEventListener('click', function() {
                if (capturedImageData) {
                    const link = document.createElement('a');
                    link.download = 'AR_photo_' + new Date().getTime() + '.png';
                    link.href = capturedImageData;
                    link.click();
                    console.log('Photo downloaded');
                }
            });
            
            // 閉じるボタン
            closeButton.addEventListener('click', function() {
                photoPreview.style.display = 'none';
            });
            
            // 画面全体のタップを検出（アニメーション切り替え用）
            document.body.addEventListener('touchstart', function(e) {
                // カメラボタンやプレビューをタップした場合は除外
                if (e.target.closest('#camera-button') || 
                    e.target.closest('#photo-preview')) {
                    return;
                }
                
                console.log('Screen tapped');
                if (sceneReady && model) {
                    const clickEvent = new Event('click');
                    model.dispatchEvent(clickEvent);
                }
            }, {passive: false});
            
            // マウスクリックも対応
            document.body.addEventListener('click', function(e) {
                // カメラボタンやプレビューをクリックした場合は除外
                if (e.target.closest('#camera-button') || 
                    e.target.closest('#photo-preview')) {
                    return;
                }
                
                console.log('Screen clicked');
                if (sceneReady && model) {
                    const clickEvent = new Event('click');
                    model.dispatchEvent(clickEvent);
                }
            });
        });
    </script>
</body>
</html>
