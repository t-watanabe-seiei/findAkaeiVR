<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, shrink-to-fit=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>AR名刺</title>
    <!-- Polyfill for older devices (iPhone 7, Android 7) -->
    <script src="https://polyfill.io/v3/polyfill.min.js?features=Promise%2CObject.assign%2CArray.from%2CArray.prototype.find%2CArray.prototype.includes%2CString.prototype.includes%2CNumber.isNaN"></script>
    <script src="https://aframe.io/releases/1.4.2/aframe.min.js"></script>
    <script src="https://raw.githack.com/AR-js-org/AR.js/master/aframe/build/aframe-ar.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html {
            height: 100%;
            overflow: hidden;
            touch-action: none;
            -webkit-touch-callout: none;
            -webkit-tap-highlight-color: transparent;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            overflow: hidden;
            position: fixed;
            width: 100%;
            height: 100%;
            background: #000;
            touch-action: none;
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        
        /* ローディング画面 */
        .arjs-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            color: white;
            font-size: 18px;
            font-weight: 500;
        }
        
        /* A-Frameシーン */
        a-scene {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: block;
            touch-action: none;
        }
        
        a-scene canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100% !important;
            height: 100% !important;
        }
        
        /* AR.js video element - スマホの黒画面を防ぐ */
        .a-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100% !important;
            height: 100% !important;
        }
        
        video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100% !important;
            height: 100% !important;
            object-fit: contain;
        }
        
    </style>
</head>
<body>
    <div class="arjs-loader">
        <div>カメラを起動中...</div>
    </div>
    
    <a-scene
        embedded
        arjs="sourceType: webcam; debugUIEnabled: false; detectionMode: mono; maxDetectionRate: 15;"
        vr-mode-ui="enabled: false"
        renderer="logarithmicDepthBuffer: false; antialias: false; alpha: true; precision: lowp; powerPreference: low-power; colorManagement: false;"
        ar-aspect-fix>
        
        <a-entity camera="near: 0.2; far: 800; fov: 65;"></a-entity>
        
        <!-- ライト -->
        <a-light type="ambient" intensity="1.5"></a-light>
        <a-light type="directional" intensity="0.8" position="1 1 1"></a-light>
        
        <!-- ARマーカー -->
        <a-marker type="pattern" url="{{ asset('cg/pattern-ar-meishi03.patt') }}" id="pattern-meishi-marker">
            <a-entity
                id="cat-model"
                gltf-model="{{ asset('cg/202606/10_snails.glb') }}"
                position="0 0 0"
                scale="1.5 0.8 1.5"
                rotation="0 0 0"
                meishi-animation="clip: anime01"
                hitbox="width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>
        
    </a-scene>

    <script>
        // ローディング画面の非表示
        window.arjsVideoReady = false;
        window.addEventListener('arjs-video-loaded', function() {
            window.arjsVideoReady = true;
            console.log('AR.js ready');
            const loader = document.querySelector('.arjs-loader');
            if (loader) loader.style.display = 'none';
        });
        
        setTimeout(function() {
            const loader = document.querySelector('.arjs-loader');
            if (loader) loader.style.display = 'none';
        }, 3000);
        
        // ========== A-Frameカスタムコンポーネント ==========
        
        // ar-aspect-fixコンポーネント: ARレンダラーとカメラのアスペクト比を補正して縦伸びを防ぐ
        AFRAME.registerComponent('ar-aspect-fix', {
            init: function() {
                this.correctAspect = this.correctAspect.bind(this);
                this.appliedCorrection = false;
                
                // ビデオが読み込まれたときに補正
                window.addEventListener('arjs-video-loaded', () => {
                    setTimeout(() => this.correctAspect(), 200);
                    setTimeout(() => this.correctAspect(), 500);
                    setTimeout(() => this.correctAspect(), 1000);
                });
                
                // リサイズ時にも補正
                window.addEventListener('resize', () => {
                    this.appliedCorrection = false;
                    this.correctAspect();
                });
                
                // 初期化時にも実行
                setTimeout(() => this.correctAspect(), 100);
                setTimeout(() => this.correctAspect(), 500);
                setTimeout(() => this.correctAspect(), 1000);
            },
            
            correctAspect: function() {
                const sceneEl = this.el;
                const camera = sceneEl.camera;
                
                if (!camera) {
                    setTimeout(() => this.correctAspect(), 200);
                    return;
                }
                
                // カメラのFOVを取得して縦方向を圧縮
                const originalFov = camera.fov;
                const compressionFactor = 0.75; // 1.2倍の伸びを補正
                const newFov = originalFov * compressionFactor;
                
                camera.fov = newFov;
                camera.updateProjectionMatrix();
                
                console.log('Camera FOV adjusted from', originalFov, 'to', newFov, 'for vertical compression');
                this.appliedCorrection = true;
            },
            
            remove: function() {
                window.removeEventListener('resize', this.correctAspect);
            }
        });
        
        // meishi-animationコンポーネント: anime01ループ再生、ダブルタップでanime02再生→anime01に戻る
        AFRAME.registerComponent('meishi-animation', {
            schema: {
                clip: { type: 'string', default: 'anime01' }
            },
            
            init: function() {
                this.mixer = null;
                this.actions = {};
                this.currentAction = null;
                this.isPlayingHitAnimation = false;
                this.shouldPlayIdle = false;
                this.model = null;
                
                this.el.addEventListener('model-loaded', () => {
                    this.setupAnimations();
                });
            },
            
            setupAnimations: function() {
                this.model = this.el.getObject3D('mesh');
                if (!this.model) return;
                
                this.mixer = new THREE.AnimationMixer(this.model);
                
                // アニメーションクリップを取得
                const animations = this.model.animations;
                if (animations && animations.length > 0) {
                    animations.forEach((clip) => {
                        const action = this.mixer.clipAction(clip);
                        this.actions[clip.name] = action;
                        console.log('Animation clip found:', clip.name);
                    });
                    
                    if (this.shouldPlayIdle) {
                        this.playIdleAnimation();
                    }
                }
            },
            
            setRotationForAnime01: function() {
                this.el.setAttribute('rotation', '0 180 0');
            },
            
            setRotationForAnime02: function() {
                this.el.setAttribute('rotation', '0 0 0');
            },
            
            playIdleAnimation: function() {
                this.shouldPlayIdle = true;
                if (this.isPlayingHitAnimation) return;
                if (!this.actions['anime01']) return;
                
                if (this.currentAction && this.currentAction !== this.actions['anime01']) {
                    this.currentAction.stop();
                }

                const idle = this.actions['anime01'];
                idle.setLoop(THREE.LoopRepeat);
                idle.clampWhenFinished = false;
                idle.reset();
                idle.play();
                this.currentAction = idle;
                this.setRotationForAnime01();
                console.log('Playing anime01 in loop with 180° rotation');
            },
            
            stopIdleAnimation: function() {
                this.shouldPlayIdle = false;
                if (this.actions['anime01']) {
                    this.actions['anime01'].stop();
                }
            },
            
            playHitAnimation: function() {
                if (this.isPlayingHitAnimation) return Promise.resolve();
                if (!this.actions['anime02']) return Promise.resolve();
                
                this.isPlayingHitAnimation = true;
                
                return new Promise((resolve) => {
                    if (this.actions['anime01']) {
                        this.actions['anime01'].stop();
                    }
                    
                    this.setRotationForAnime02();
                    const anime02 = this.actions['anime02'];
                    anime02.setLoop(THREE.LoopOnce);
                    anime02.clampWhenFinished = true;
                    anime02.reset();
                    anime02.play();
                    this.currentAction = anime02;
                    
                    console.log('Playing anime02 (hit animation) with 0° rotation');
                    
                    const duration = anime02.getClip().duration;
                    
                    setTimeout(() => {
                        console.log('anime02 finished, waiting 1 seconds...');
                        
                        setTimeout(() => {
                            console.log('anime02 completed, returning to anime01 if marker visible');
                            anime02.stop();
                            this.isPlayingHitAnimation = false;
                            if (this.shouldPlayIdle) {
                                this.playIdleAnimation();
                            }
                            resolve();
                        }, 1000);
                    }, duration * 1000);
                });
            },
            
            tick: function(time, deltaTime) {
                if (this.mixer) {
                    this.mixer.update(deltaTime / 1000);
                }
            }
        });
        
        // hitboxコンポーネント: 当たり判定
        AFRAME.registerComponent('hitbox', {
            schema: {
                width: { type: 'number', default: 1.6 },
                height: { type: 'number', default: 3.2 },
                depth: { type: 'number', default: 1.6 }
            },
            
            init: function() {
                this.boundingBox = new THREE.Box3();
            },
            
            tick: function() {
                // ヒットボックスの位置を更新
                const position = new THREE.Vector3();
                this.el.object3D.getWorldPosition(position);
                
                const halfWidth = this.data.width / 2;
                const halfHeight = this.data.height / 2;
                const halfDepth = this.data.depth / 2;
                
                this.boundingBox.min.set(
                    position.x - halfWidth,
                    position.y - halfHeight,
                    position.z - halfDepth
                );
                
                this.boundingBox.max.set(
                    position.x + halfWidth,
                    position.y + halfHeight,
                    position.z + halfDepth
                );
            },
            
            checkCollision: function(point) {
                return this.boundingBox.containsPoint(point);
            }
        });
        
        // ========== メインロジック ==========
        
        document.addEventListener('DOMContentLoaded', function() {
            const scene = document.querySelector('a-scene');
            const catModel = document.querySelector('#cat-model');
            const marker = document.querySelector('#pattern-meishi-marker');
            
            let isMarkerVisible = false;
            
            // マーカー検出イベント
            marker.addEventListener('markerFound', function() {
                console.log('Marker found');
                isMarkerVisible = true;
            });
            
            marker.addEventListener('markerLost', function() {
                console.log('Marker lost');
                isMarkerVisible = false;
            });
            
            let lastTapTime = 0;
            let tapTimeout = null;
            let isPinching = false;
            let pinchStartDistance = 0;
            let pinchStartScale = new THREE.Vector3(1, 1, 1);

            function getTouchDistance(touchA, touchB) {
                const dx = touchA.clientX - touchB.clientX;
                const dy = touchA.clientY - touchB.clientY;
                return Math.sqrt(dx * dx + dy * dy);
            }

            function getCurrentModelScale() {
                const scale = new THREE.Vector3(1, 1, 1);
                if (!catModel) return scale;

                const currentScale = catModel.getAttribute('scale');
                if (currentScale) {
                    const parts = currentScale.split(' ').map(Number);
                    if (parts.length === 3 && parts.every((n) => !Number.isNaN(n))) {
                        scale.set(parts[0], parts[1], parts[2]);
                    }
                }
                return scale;
            }

            function setModelScale(scaleVector) {
                if (!catModel) return;
                const clampedX = Math.max(0.5, Math.min(2.5, scaleVector.x));
                const clampedY = Math.max(0.5, Math.min(2.5, scaleVector.y));
                const clampedZ = Math.max(0.5, Math.min(2.5, scaleVector.z));
                catModel.setAttribute('scale', `${clampedX} ${clampedY} ${clampedZ}`);
            }

            function triggerDoubleTap() {
                if (!isMarkerVisible || !catModel || !catModel.components['meishi-animation']) return;
                catModel.components['meishi-animation'].playHitAnimation();
            }

            scene.addEventListener('touchstart', function(e) {
                if (e.touches && e.touches.length === 2) {
                    isPinching = true;
                    pinchStartDistance = getTouchDistance(e.touches[0], e.touches[1]);
                    pinchStartScale = getCurrentModelScale();
                    if (e.cancelable) e.preventDefault();

                    if (tapTimeout) {
                        clearTimeout(tapTimeout);
                        tapTimeout = null;
                        lastTapTime = 0;
                    }
                }
            }, { passive: false });

            scene.addEventListener('touchmove', function(e) {
                if (!isPinching || !e.touches || e.touches.length !== 2) return;
                if (e.cancelable) e.preventDefault();

                const currentDistance = getTouchDistance(e.touches[0], e.touches[1]);
                if (pinchStartDistance <= 0) return;

                const scaleRatio = currentDistance / pinchStartDistance;
                const targetScale = pinchStartScale.clone().multiplyScalar(scaleRatio);
                setModelScale(targetScale);
            }, { passive: false });

            function resetPinch() {
                isPinching = false;
                pinchStartDistance = 0;
            }

            scene.addEventListener('touchend', function(e) {
                if (isPinching && e.touches.length < 2) {
                    resetPinch();
                }

                if (isPinching || !e.changedTouches || e.changedTouches.length !== 1 || e.touches.length > 0) return;

                const now = Date.now();
                if (lastTapTime && now - lastTapTime < 300) {
                    if (tapTimeout) {
                        clearTimeout(tapTimeout);
                        tapTimeout = null;
                    }
                    lastTapTime = 0;
                    triggerDoubleTap();
                } else {
                    lastTapTime = now;
                    tapTimeout = setTimeout(() => {
                        lastTapTime = 0;
                        tapTimeout = null;
                    }, 350);
                }
            }, { passive: false });

            scene.addEventListener('touchcancel', function() {
                resetPinch();
            }, { passive: false });

            scene.addEventListener('dblclick', function() {
                if (!isPinching) {
                    triggerDoubleTap();
                }
            });

            marker.addEventListener('markerFound', function() {
                if (catModel && catModel.components['meishi-animation']) {
                    catModel.components['meishi-animation'].playIdleAnimation();
                }
            });

            marker.addEventListener('markerLost', function() {
                if (catModel && catModel.components['meishi-animation']) {
                    catModel.components['meishi-animation'].stopIdleAnimation();
                }
            });

            if (catModel) {
                catModel.addEventListener('model-loaded', function() {
                    if (isMarkerVisible && catModel.components['meishi-animation']) {
                        catModel.components['meishi-animation'].playIdleAnimation();
                    }
                });
            }
        });
    </script>
</body>
</html>
