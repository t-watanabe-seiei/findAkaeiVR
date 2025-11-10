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
        
        /* カメラ切り替えボタン */
        #switch-camera-button {
            position: fixed;
            bottom: 30px;
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
    
    <!-- フラッシュエフェクト -->
    <div id="flash"></div>
    
    <!-- カメラ切り替えボタン -->
    <button id="switch-camera-button" title="カメラを切り替え">🔄</button>
    
    <!-- 動画撮影ボタン -->
    <button id="video-button" title="動画を撮る">🎥</button>
    
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
        arjs="sourceType: webcam; debugUIEnabled: false; sourceWidth: 1280; sourceHeight: 960;"
        vr-mode-ui="enabled: false"
        renderer="preserveDrawingBuffer: true; alpha: true;">
        
        <a-entity camera></a-entity>
        
        <a-marker preset="hiro" id="hiro-marker">
            <a-entity
                id="fox-model"
                gltf-model="{{ asset('cg/3d_isobe_fox5.glb') }}"
                position="0 0 0"
                scale="1 1 1"
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
        let currentFacingMode = 'environment'; // 'environment' = アウトカメラ, 'user' = インカメラ
        
        // ピンチ操作用の変数
        let initialPinchDistance = 0;
        let initialScale = 1;
        let currentScale = 1;
        
        // ドラッグ回転用の変数
        let isDragging = false;
        let previousTouchX = 0;
        let currentRotationX = 0;
        
        // ダブルタップ検出用の変数
        let lastTapTime = 0;
        const doubleTapDelay = 300; // 300ms以内の2回タップでダブルタップ
        
        document.addEventListener('DOMContentLoaded', function() {
            const scene = document.querySelector('a-scene');
            const model = document.querySelector('#fox-model');
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
            
            scene.addEventListener('loaded', function() {
                sceneReady = true;
                console.log('Scene loaded');
            });
            
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
            
            // ピンチ操作（拡大縮小）
            let touchStartHandler = function(e) {
                // ボタンをタップした場合は除外
                if (e.target.closest('#camera-button') || 
                    e.target.closest('#video-button') ||
                    e.target.closest('#switch-camera-button') ||
                    e.target.closest('#photo-preview')) {
                    return;
                }
                
                if (e.touches.length === 2) {
                    // ピンチ操作開始
                    e.preventDefault();
                    const touch1 = e.touches[0];
                    const touch2 = e.touches[1];
                    initialPinchDistance = Math.hypot(
                        touch2.clientX - touch1.clientX,
                        touch2.clientY - touch1.clientY
                    );
                    initialScale = currentScale;
                    isDragging = false;
                } else if (e.touches.length === 1) {
                    // シングルタッチ（ドラッグ回転用）
                    const now = Date.now();
                    const timeSinceLastTap = now - lastTapTime;
                    
                    if (timeSinceLastTap < doubleTapDelay && timeSinceLastTap > 0) {
                        // ダブルタップ検出
                        e.preventDefault();
                        console.log('Double tap detected');
                        if (sceneReady && model) {
                            const clickEvent = new Event('click');
                            model.dispatchEvent(clickEvent);
                        }
                        lastTapTime = 0; // リセット
                    } else {
                        // シングルタップ（ドラッグ準備）
                        lastTapTime = now;
                        isDragging = true;
                        previousTouchX = e.touches[0].clientX;
                    }
                }
            };
            
            let touchMoveHandler = function(e) {
                if (e.touches.length === 2) {
                    // ピンチ操作中
                    e.preventDefault();
                    const touch1 = e.touches[0];
                    const touch2 = e.touches[1];
                    const currentDistance = Math.hypot(
                        touch2.clientX - touch1.clientX,
                        touch2.clientY - touch1.clientY
                    );
                    
                    if (initialPinchDistance > 0) {
                        const scaleChange = currentDistance / initialPinchDistance;
                        currentScale = initialScale * scaleChange;
                        
                        // スケールを0.5〜5の範囲に制限
                        currentScale = Math.max(0.5, Math.min(5, currentScale));
                        
                        if (model) {
                            model.setAttribute('scale', {
                                x: currentScale,
                                y: currentScale,
                                z: currentScale
                            });
                        }
                    }
                } else if (e.touches.length === 1 && isDragging) {
                    // ドラッグ回転
                    e.preventDefault();
                    const currentTouchX = e.touches[0].clientX;
                    const deltaX = currentTouchX - previousTouchX;
                    
                    // 回転速度を調整（感度）
                    const rotationSpeed = 0.5;
                    currentRotationX += deltaX * rotationSpeed;
                    
                    if (model) {
                        model.setAttribute('rotation', {
                            x: currentRotationX,
                            y: 0,
                            z: 0
                        });
                    }
                    
                    previousTouchX = currentTouchX;
                }
            };
            
            let touchEndHandler = function(e) {
                if (e.touches.length < 2) {
                    initialPinchDistance = 0;
                }
                if (e.touches.length === 0) {
                    isDragging = false;
                }
            };
            
            // タッチイベントをリスナーに登録
            document.body.addEventListener('touchstart', touchStartHandler, { passive: false });
            document.body.addEventListener('touchmove', touchMoveHandler, { passive: false });
            document.body.addEventListener('touchend', touchEndHandler, { passive: false });
            
            // PC用：マウスドラッグで回転
            let isMouseDragging = false;
            let previousMouseX = 0;
            
            document.body.addEventListener('mousedown', function(e) {
                // ボタンをクリックした場合は除外
                if (e.target.closest('#camera-button') || 
                    e.target.closest('#video-button') ||
                    e.target.closest('#switch-camera-button') ||
                    e.target.closest('#photo-preview')) {
                    return;
                }
                
                isMouseDragging = true;
                previousMouseX = e.clientX;
            });
            
            document.body.addEventListener('mousemove', function(e) {
                if (isMouseDragging) {
                    const deltaX = e.clientX - previousMouseX;
                    const rotationSpeed = 0.5;
                    currentRotationX += deltaX * rotationSpeed;
                    
                    if (model) {
                        model.setAttribute('rotation', {
                            x: currentRotationX,
                            y: 0,
                            z: 0
                        });
                    }
                    
                    previousMouseX = e.clientX;
                }
            });
            
            document.body.addEventListener('mouseup', function(e) {
                isMouseDragging = false;
            });
            
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
                
                if (model) {
                    model.setAttribute('scale', {
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
