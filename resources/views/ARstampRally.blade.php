<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>AR Stamp Rally</title>
    <script src="https://aframe.io/releases/1.4.2/aframe.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/mind-ar@1.2.2/dist/mindar-image-aframe.prod.js"></script>
    <script src="https://raw.githack.com/AR-js-org/AR.js/3.4.5/aframe/build/aframe-ar.js"></script>
    <script>
        // デバイスとブラウザの検出
        window.addEventListener('load', function() {
            console.log('User Agent:', navigator.userAgent);
            console.log('Platform:', navigator.platform);
        });

        // クリック/タップでアニメーション再生
        AFRAME.registerComponent('click-to-play-animation', {
            schema: {
                clip: {type: 'string', default: 'anime01'}
            },
            init: function() {
                const el = this.el;
                const clipName = this.data.clip;
                let mixer = null;
                let action = null;
                let isPlaying = false;
                
                el.addEventListener('model-loaded', () => {
                    console.log('=== Model Loaded ===');
                    const model = el.getObject3D('mesh');
                    
                    if (!model) {
                        console.error('Model not found');
                        return;
                    }
                    
                    console.log('Model loaded successfully');
                    
                    // アニメーションクリップを取得
                    const animations = model.animations;
                    console.log('Animations:', animations);
                    console.log('Number of animations:', animations ? animations.length : 0);
                    
                    if (animations && animations.length > 0) {
                        // アニメーション名をログ出力
                        animations.forEach((clip, index) => {
                            console.log(`Animation ${index}: ${clip.name}, duration: ${clip.duration}s`);
                        });
                        
                        // Three.js AnimationMixerを作成
                        mixer = new THREE.AnimationMixer(model);
                        this.mixer = mixer;
                        
                        let clipToPlay = null;
                        
                        if (clipName === '*') {
                            clipToPlay = animations[0];
                            console.log('Selected first animation:', clipToPlay.name);
                        } else {
                            clipToPlay = THREE.AnimationClip.findByName(animations, clipName);
                            if (!clipToPlay) {
                                console.warn(`Animation "${clipName}" not found. Available animations:`, 
                                    animations.map(a => a.name));
                                clipToPlay = animations[0];
                                console.log('Selected first animation instead:', clipToPlay.name);
                            } else {
                                console.log('Selected animation:', clipName);
                            }
                        }
                        
                        if (clipToPlay) {
                            action = mixer.clipAction(clipToPlay);
                            this.action = action;
                            // ループ設定
                            action.setLoop(THREE.LoopRepeat, Infinity);
                            // 最初は停止状態
                            action.stop();
                            console.log('Animation ready (stopped)');
                        }
                    } else {
                        console.log('No animations found in the model');
                    }
                });
                
                el.addEventListener('model-error', (error) => {
                    console.error('Model loading error:', error);
                });
                
                // クリック/タップイベント
                el.addEventListener('click', () => {
                    console.log('Model clicked/tapped');
                    if (action) {
                        if (!isPlaying) {
                            // アニメーション開始
                            action.reset();
                            action.play();
                            isPlaying = true;
                            console.log('Animation started by click/tap');
                        } else {
                            // アニメーション停止
                            action.stop();
                            isPlaying = false;
                            console.log('Animation stopped by click/tap');
                        }
                    } else {
                        console.warn('No action available to play');
                    }
                });
            },
            tick: function(time, deltaTime) {
                if (this.mixer) {
                    this.mixer.update(deltaTime / 1000);
                }
            }
        });

        // マーカー検出のデバッグ
        AFRAME.registerComponent('marker-debug', {
            init: function() {
                const el = this.el;
                
                el.addEventListener('markerFound', () => {
                    console.log('✓ Marker found!');
                });
                
                el.addEventListener('markerLost', () => {
                    console.log('✗ Marker lost');
                });
            }
        });
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
    </style>
</head>
<body>
    <div class="arjs-loader">
        <div>カメラを起動中...</div>
    </div>
    
    <a-scene
        embedded
        renderer="logarithmicDepthBuffer: true; precision: medium; antialias: true; alpha: true;"
        arjs="sourceType: webcam; debugUIEnabled: false; detectionMode: mono_and_matrix; matrixCodeType: 3x3; trackingMethod: best;"
        vr-mode-ui="enabled: false"
        gesture-detector>
        
        <!-- カメラ -->
        <a-entity camera></a-entity>
        
        <!-- マーカー -->
        <a-marker 
            preset="hiro"
            marker-debug
            raycaster="objects: .clickable"
            emitevents="true"
            cursor="fuse: false; rayOrigin: mouse;">
            
            <!-- 3Dモデル -->
            <a-entity
                gltf-model="url(/cg/3d_isobe_fox5.glb)"
                position="0 0 0"
                scale="2 2 2"
                rotation="0 0 0"
                click-to-play-animation="clip: anime01"
                class="clickable"
                gesture-handler>
            </a-entity>
        </a-marker>
        
    </a-scene>

    <script>
        // ローディング画面を非表示
        window.addEventListener('arjs-video-loaded', function() {
            console.log('AR.js video loaded');
            document.querySelector('.arjs-loader').style.display = 'none';
        });

        // タイムアウトでローディング画面を消す（フォールバック）
        setTimeout(function() {
            const loader = document.querySelector('.arjs-loader');
            if (loader) {
                loader.style.display = 'none';
            }
        }, 5000);

        // ジェスチャーハンドラー（タップ対応）
        AFRAME.registerComponent('gesture-detector', {
            schema: {
                element: {default: ''}
            },
            init: function() {
                this.targetElement = this.data.element && document.querySelector(this.data.element);
                if (!this.targetElement) {
                    this.targetElement = this.el;
                }
                
                this.internalState = {
                    previousState: null
                };
                
                this.emitGestureEvent = this.emitGestureEvent.bind(this);
                
                this.targetElement.addEventListener('touchstart', this.emitGestureEvent);
                this.targetElement.addEventListener('touchend', this.emitGestureEvent);
                this.targetElement.addEventListener('touchmove', this.emitGestureEvent);
            },
            remove: function() {
                this.targetElement.removeEventListener('touchstart', this.emitGestureEvent);
                this.targetElement.removeEventListener('touchend', this.emitGestureEvent);
                this.targetElement.removeEventListener('touchmove', this.emitGestureEvent);
            },
            emitGestureEvent(event) {
                const currentState = this.getTouchState(event);
                const previousState = this.internalState.previousState;
                
                const gestureContinues = previousState && currentState && currentState.touchCount === previousState.touchCount;
                
                const gestureEnded = previousState && !gestureContinues;
                const gestureStarted = currentState && !gestureContinues;
                
                if (gestureEnded) {
                    const eventName = this.getEventPrefix(previousState.touchCount) + 'ended';
                    this.el.emit(eventName, previousState);
                    this.internalState.previousState = null;
                }
                
                if (gestureStarted) {
                    currentState.startTime = performance.now();
                    currentState.startPosition = currentState.position;
                    currentState.startSpread = currentState.spread;
                    const eventName = this.getEventPrefix(currentState.touchCount) + 'started';
                    this.el.emit(eventName, currentState);
                    this.internalState.previousState = currentState;
                }
                
                if (gestureContinues) {
                    const eventDetail = {
                        positionChange: {
                            x: currentState.position.x - previousState.position.x,
                            y: currentState.position.y - previousState.position.y
                        }
                    };
                    
                    if (currentState.spread) {
                        eventDetail.spreadChange = currentState.spread - previousState.spread;
                    }
                    
                    Object.assign(eventDetail, currentState);
                    const eventName = this.getEventPrefix(currentState.touchCount) + 'moved';
                    this.el.emit(eventName, eventDetail);
                    this.internalState.previousState = currentState;
                }
            },
            getTouchState: function(event) {
                if (event.touches.length === 0) {
                    return null;
                }
                
                const touchList = [];
                for (let i = 0; i < event.touches.length; i++) {
                    touchList.push(event.touches[i]);
                }
                
                const touchCount = touchList.length;
                const touchCenterX = touchList.reduce((sum, touch) => sum + touch.clientX, 0) / touchCount;
                const touchCenterY = touchList.reduce((sum, touch) => sum + touch.clientY, 0) / touchCount;
                
                let spread = 0;
                if (touchList.length >= 2) {
                    const dx = touchList[0].clientX - touchList[1].clientX;
                    const dy = touchList[0].clientY - touchList[1].clientY;
                    spread = Math.sqrt(dx * dx + dy * dy);
                }
                
                return {
                    touchCount: touchCount,
                    position: {x: touchCenterX, y: touchCenterY},
                    spread: spread,
                    targetTouches: event.targetTouches
                };
            },
            getEventPrefix(touchCount) {
                const numberNames = ['one', 'two', 'three', 'many'];
                return numberNames[Math.min(touchCount, 4) - 1] + 'finger';
            }
        });

        AFRAME.registerComponent('gesture-handler', {
            schema: {
                enabled: {default: true},
                rotationFactor: {default: 5},
                minScale: {default: 0.3},
                maxScale: {default: 8}
            },
            init: function() {
                this.handleTap = this.handleTap.bind(this);
                this.el.sceneEl.addEventListener('onefingertapped', this.handleTap);
            },
            remove: function() {
                this.el.sceneEl.removeEventListener('onefingertapped', this.handleTap);
            },
            handleTap: function(event) {
                console.log('Tap detected on model');
                this.el.emit('click');
            }
        });

        // タップイベント検出
        AFRAME.registerComponent('gesture-detector', {
            schema: {
                element: {default: ''}
            },
            init: function() {
                this.targetElement = this.data.element && document.querySelector(this.data.element);
                if (!this.targetElement) {
                    this.targetElement = this.el;
                }
                
                this.internalState = {
                    previousState: null
                };
                
                this.emitGestureEvent = this.emitGestureEvent.bind(this);
                
                this.targetElement.addEventListener('touchstart', this.emitGestureEvent);
                this.targetElement.addEventListener('touchend', this.emitGestureEvent);
            },
            remove: function() {
                this.targetElement.removeEventListener('touchstart', this.emitGestureEvent);
                this.targetElement.removeEventListener('touchend', this.emitGestureEvent);
            },
            emitGestureEvent(event) {
                const currentState = this.getTouchState(event);
                const previousState = this.internalState.previousState;
                
                if (!currentState && previousState) {
                    // タップ終了
                    if (previousState.touchCount === 1) {
                        const tapDuration = performance.now() - previousState.startTime;
                        if (tapDuration < 300) {
                            this.el.emit('onefingertapped', previousState);
                        }
                    }
                    this.internalState.previousState = null;
                } else if (currentState && !previousState) {
                    // タップ開始
                    currentState.startTime = performance.now();
                    this.internalState.previousState = currentState;
                }
            },
            getTouchState: function(event) {
                if (event.touches.length === 0) {
                    return null;
                }
                
                return {
                    touchCount: event.touches.length,
                    startTime: performance.now()
                };
            }
        });
    </script>
</body>
</html>