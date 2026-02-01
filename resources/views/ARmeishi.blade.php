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
        
        /* フラッシュエフェクト */
        #flash {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            opacity: 0;
            pointer-events: none;
            z-index: 9998;
            -webkit-transition: opacity 0.1s ease-out;
            -moz-transition: opacity 0.1s ease-out;
            -o-transition: opacity 0.1s ease-out;
            transition: opacity 0.1s ease-out;
        }
        
        #flash.active {
            opacity: 0.8;
        }
        
        /* ヒットパーティクル */
        @-webkit-keyframes hitParticle {
            0% {
                -webkit-transform: translate(0, 0) scale(1);
                transform: translate(0, 0) scale(1);
                opacity: 1;
            }
            100% {
                -webkit-transform: translate(calc(var(--random-x) * 100px), calc(var(--random-y) * 1px)) scale(0.5);
                transform: translate(calc(var(--random-x) * 100px), calc(var(--random-y) * 1px)) scale(0.5);
                opacity: 0;
            }
        }
        @keyframes hitParticle {
            0% {
                -webkit-transform: translate(0, 0) scale(1);
                transform: translate(0, 0) scale(1);
                opacity: 1;
            }
            100% {
                -webkit-transform: translate(calc(var(--random-x) * 100px), calc(var(--random-y) * 1px)) scale(0.5);
                transform: translate(calc(var(--random-x) * 100px), calc(var(--random-y) * 1px)) scale(0.5);
                opacity: 0;
            }
        }
        
        .hit-particle {
            position: fixed;
            font-size: 30px;
            pointer-events: none;
            z-index: 10000;
            -webkit-animation: hitParticle 0.8s ease-out forwards;
            animation: hitParticle 0.8s ease-out forwards;
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
            object-fit: cover;
        }
        
        /* Pokeball HUD element styling */
        #holding-pokeball {
            cursor: pointer;
            touch-action: none;
        }
    </style>
</head>
<body>
    <div class="arjs-loader">
        <div>カメラを起動中...</div>
    </div>
    
    <!-- フラッシュエフェクト -->
    <div id="flash"></div>
    
    <a-scene
        embedded
        arjs="sourceType: webcam; debugUIEnabled: false; sourceWidth: 640; sourceHeight: 480; detectionMode: mono; maxDetectionRate: 15;"
        vr-mode-ui="enabled: false"
        renderer="logarithmicDepthBuffer: false; antialias: false; alpha: true; precision: lowp; powerPreference: low-power; colorManagement: false;">
        
        <a-entity camera="near: 0.2; far: 800; fov: 80;">
            <!-- 手持ちのポケボール (HUD) - クリック可能 -->
            <a-entity 
                id="holding-pokeball"
                gltf-model="{{ asset('cg/poke_ball_05.glb') }}"
                position="0 -0.24 -0.5"
                scale="0.095 0.075 0.075"
                rotation="0 0 0"
                visible="true"
                class="clickable"
                pokeball-aspect-fix>
            </a-entity>
        </a-entity>
        
        <!-- ライト -->
        <a-light type="ambient" intensity="1.5"></a-light>
        <a-light type="directional" intensity="0.8" position="1 1 1"></a-light>
        
        <!-- ARマーカー -->
        <a-marker type="pattern" url="{{ asset('cg/pattern-ar-meishi03.patt') }}" id="pattern-meishi-marker">
            <a-entity
                id="cat-model"
                gltf-model="{{ asset('cg/3D_bio_cat.glb') }}"
                position="0 0 0.5"
                scale="1 0.88 1"
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
        
        // pokeball-aspect-fixコンポーネント: ポケボールを正円に保つ
        AFRAME.registerComponent('pokeball-aspect-fix', {
            init: function() {
                this.updateAspect = this.updateAspect.bind(this);
                this.updateAspect();
                window.addEventListener('resize', this.updateAspect);
            },
            
            updateAspect: function() {
                // 画面のアスペクト比を取得
                const aspect = window.innerWidth / window.innerHeight;
                
                // 基本スケール
                const baseScale = 0.075;
                
                // アスペクト比が1より小さい（縦長画面）場合、横方向を補正
                let scaleX, scaleY, scaleZ;
                if (aspect < 1) {
                    // 縦長画面の場合、横方向を拡大して正円に
                    scaleX = baseScale / aspect;
                    scaleY = baseScale;
                    scaleZ = baseScale;
                } else {
                    // 横長画面の場合、そのまま
                    scaleX = baseScale;
                    scaleY = baseScale;
                    scaleZ = baseScale;
                }
                
                this.el.setAttribute('scale', `${scaleX} ${scaleY} ${scaleZ}`);
            },
            
            remove: function() {
                window.removeEventListener('resize', this.updateAspect);
            }
        });
        
        // meishi-animationコンポーネント: anime01ループ再生、ヒット時にanime02再生→2秒停止→anime01に戻る
        AFRAME.registerComponent('meishi-animation', {
            schema: {
                clip: { type: 'string', default: 'anime01' }
            },
            
            init: function() {
                this.mixer = null;
                this.actions = {};
                this.currentAction = null;
                this.isPlayingHitAnimation = false;
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
                    
                    // anime01をループ再生（180度回転）
                    if (this.actions['anime01']) {
                        this.actions['anime01'].setLoop(THREE.LoopRepeat);
                        this.actions['anime01'].play();
                        this.currentAction = this.actions['anime01'];
                        this.setRotationForAnime01();
                        console.log('Playing anime01 in loop with 180° rotation');
                    }
                }
            },
            
            setRotationForAnime01: function() {
                // anime01用の回転: Y軸180度
                this.el.setAttribute('rotation', '0 180 0');
            },
            
            setRotationForAnime02: function() {
                // anime02用の回転: 元の向き
                this.el.setAttribute('rotation', '0 0 0');
            },
            
            playHitAnimation: function() {
                if (this.isPlayingHitAnimation) return Promise.resolve();
                if (!this.actions['anime02']) return Promise.resolve();
                
                this.isPlayingHitAnimation = true;
                
                return new Promise((resolve) => {
                    // anime01を停止
                    if (this.actions['anime01']) {
                        this.actions['anime01'].stop();
                    }
                    
                    // anime02を1回だけ再生（元の向きに回転）
                    this.setRotationForAnime02();
                    const anime02 = this.actions['anime02'];
                    anime02.setLoop(THREE.LoopOnce);
                    anime02.clampWhenFinished = true; // 最終フレームで停止
                    anime02.reset();
                    anime02.play();
                    this.currentAction = anime02;
                    
                    console.log('Playing anime02 (hit animation) with 0° rotation');
                    
                    // anime02の長さを取得
                    const duration = anime02.getClip().duration;
                    
                    // anime02再生完了後、1秒停止してからanime01に戻る
                    setTimeout(() => {
                        console.log('anime02 finished, waiting 1 seconds...');
                        
                        setTimeout(() => {
                            console.log('Returning to anime01 loop with 180° rotation');
                            
                            // anime02を停止
                            anime02.stop();
                            
                            // anime01を再開（180度回転に戻す）
                            if (this.actions['anime01']) {
                                this.setRotationForAnime01();
                                this.actions['anime01'].reset();
                                this.actions['anime01'].play();
                                this.currentAction = this.actions['anime01'];
                            }
                            
                            this.isPlayingHitAnimation = false;
                            resolve();
                        }, 1000); // 1秒停止
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
        
        // pokeball-throwableコンポーネント: ポケボールの物理挙動
        AFRAME.registerComponent('pokeball-throwable', {
            init: function() {
                this.velocity = new THREE.Vector3();
                this.gravity = -3.5; // 重力を弱く（元: -9.8）でふわっとした放物線に
                this.isThrown = false;
            },
            
            throw: function(direction, speed) {
                // 上向きの成分を増やして放物線を描くように
                const upwardBoost = new THREE.Vector3(0, 0.4, 0); // 上向きのブースト
                const adjustedDirection = direction.clone().add(upwardBoost).normalize();
                this.velocity.copy(adjustedDirection).multiplyScalar(speed);
                this.isThrown = true;
            },
            
            tick: function(time, deltaTime) {
                if (!this.isThrown) return;
                
                const dt = deltaTime / 1000;
                
                // 重力を適用（ふわっとした動き）
                this.velocity.y += this.gravity * dt;
                
                // 位置を更新
                const position = this.el.object3D.position;
                position.add(this.velocity.clone().multiplyScalar(dt));
                
                // 地面に落ちたら削除
                if (position.y < -5) {
                    this.el.parentNode.removeChild(this.el);
                }
            }
        });
        
        // ========== メインロジック ==========
        
        document.addEventListener('DOMContentLoaded', function() {
            const scene = document.querySelector('a-scene');
            const catModel = document.querySelector('#cat-model');
            const holdingPokeball = document.querySelector('#holding-pokeball');
            const flash = document.getElementById('flash');
            const marker = document.querySelector('#pattern-meishi-marker');
            
            let isMarkerVisible = false;
            let currentScale = 1;
            let isPinching = false;
            let pinchStartDistance = 0;
            let pinchInitialScale = 1;
            
            // マーカー検出イベント
            marker.addEventListener('markerFound', function() {
                console.log('Marker found');
                isMarkerVisible = true;
            });
            
            marker.addEventListener('markerLost', function() {
                console.log('Marker lost');
                isMarkerVisible = false;
            });
            
            // ピンチズーム機能
            function getTouchesDistance(t0, t1) {
                const dx = t0.clientX - t1.clientX;
                const dy = t0.clientY - t1.clientY;
                return Math.hypot(dx, dy);
            }
            
            scene.addEventListener('touchstart', function(event) {
                if (event.touches && event.touches.length >= 2) {
                    isPinching = true;
                    pinchStartDistance = getTouchesDistance(event.touches[0], event.touches[1]);
                    pinchInitialScale = currentScale;
                    if (event.cancelable) event.preventDefault();
                }
            }, { passive: false });
            
            scene.addEventListener('touchmove', function(event) {
                if (!isPinching) return;
                if (!(event.touches && event.touches.length >= 2)) return;
                
                const d = getTouchesDistance(event.touches[0], event.touches[1]);
                if (pinchStartDistance <= 0) return;
                
                const factor = d / pinchStartDistance;
                currentScale = Math.max(1, Math.min(3, pinchInitialScale * factor));
                
                // catModelにスケールを適用
                if (catModel) {
                    const baseScale = 1;
                    catModel.setAttribute('scale', `${baseScale * currentScale} ${baseScale * currentScale} ${baseScale * currentScale}`);
                }
                
                if (event.cancelable) event.preventDefault();
            }, { passive: false });
            
            scene.addEventListener('touchend', function(event) {
                if (isPinching) {
                    if (!event.touches || event.touches.length < 2) {
                        isPinching = false;
                    }
                }
            }, { passive: true });
            
            // ホイールでズーム（PC）
            scene.addEventListener('wheel', function(e) {
                const delta = -e.deltaY;
                const step = delta * 0.0018;
                currentScale = Math.max(1, Math.min(3, currentScale + step));
                
                // catModelにスケールを適用
                if (catModel) {
                    const baseScale = 1;
                    catModel.setAttribute('scale', `${baseScale * currentScale} ${baseScale * currentScale} ${baseScale * currentScale}`);
                }
                
                if (e.cancelable) e.preventDefault();
            }, { passive: false });
            
            // Pokeballクリック時の処理（タッチとクリック両方対応）
            function handlePokeballThrow(e) {
                if (!isMarkerVisible || !catModel) {
                    console.log('Cannot throw: marker not visible or model not found');
                    return;
                }
                
                if (e.cancelable) e.preventDefault();
                if (e.type === 'touchstart') e.stopPropagation();
                
                throwPokeballToModel();
            }
            
            // タッチイベントを優先的に追加（モバイル対応）
            holdingPokeball.addEventListener('touchstart', handlePokeballThrow, { passive: false });
            holdingPokeball.addEventListener('click', handlePokeballThrow);
            
            // タッチ処理を確実にするためにシーンレベルでもハンドル
            let lastTapTime = 0;
            scene.addEventListener('touchstart', function(e) {
                if (isPinching) return;
                if (!e.touches || e.touches.length !== 1) return;
                
                const now = Date.now();
                // ダブルタップ防止（300ms以内の連続タップは無視）
                if (now - lastTapTime < 300) return;
                lastTapTime = now;
                
                // タッチ位置がPokeball HUD付近かチェック
                const touch = e.touches[0];
                const screenX = touch.clientX / window.innerWidth;
                const screenY = touch.clientY / window.innerHeight;
                
                // 画面下部中央付近（Pokeball HUDの位置）
                if (screenX > 0.3 && screenX < 0.7 && screenY > 0.6 && screenY < 0.95) {
                    if (!isMarkerVisible || !catModel) {
                        console.log('Cannot throw: marker not visible');
                        return;
                    }
                    
                    if (e.cancelable) e.preventDefault();
                    e.stopPropagation();
                    throwPokeballToModel();
                }
            }, { passive: false });
            
            // モデル方向にPokeballを投げる
            function throwPokeballToModel() {
                const camera = scene.camera;
                if (!camera) return;
                
                // カメラの位置
                const cameraPos = camera.getWorldPosition(new THREE.Vector3());
                
                // モデルの位置
                const modelPos = catModel.object3D.getWorldPosition(new THREE.Vector3());
                
                // カメラからモデルへの方向ベクトル
                const direction = new THREE.Vector3().subVectors(modelPos, cameraPos).normalize();
                
                console.log('Throwing pokeball to model. Camera:', cameraPos, 'Model:', modelPos, 'Direction:', direction);
                
                // Pokeballを生成
                const pokeball = document.createElement('a-entity');
                pokeball.setAttribute('gltf-model', '{{ asset("cg/poke_ball_05.glb") }}');
                pokeball.setAttribute('scale', '0.15 0.15 0.15');
                pokeball.setAttribute('pokeball-throwable', '');
                pokeball.setAttribute('position', `${cameraPos.x} ${cameraPos.y} ${cameraPos.z}`);
                
                scene.appendChild(pokeball);
                
                // ボールが読み込まれたら投げる
                pokeball.addEventListener('loaded', function() {
                    // マテリアル設定
                    const model = pokeball.getObject3D('mesh');
                    if (model) {
                        model.traverse(function(node) {
                            if (node.isMesh && node.material) {
                                const materials = Array.isArray(node.material) ? node.material : [node.material];
                                materials.forEach(mat => {
                                    mat.side = THREE.DoubleSide;
                                    mat.depthWrite = true;
                                    mat.depthTest = true;
                                    mat.needsUpdate = true;
                                });
                            }
                        });
                    }
                    
                    const speed = 8; // スピードを遅く（元: 15）でふわっとした軌道に
                    pokeball.components['pokeball-throwable'].throw(direction, speed);
                    
                    // 当たり判定チェック
                    let hasHit = false;
                    const checkInterval = setInterval(() => {
                        if (hasHit) return;
                        
                        const ballPos = pokeball.object3D.getWorldPosition(new THREE.Vector3());
                        
                        // ヒットボックスとの衝突判定
                        if (catModel.components.hitbox && catModel.components.hitbox.checkCollision(ballPos)) {
                            hasHit = true;
                            console.log('✓ Hit!');
                            
                            // ヒットエフェクト
                            showHitEffect(ballPos);
                            
                            // anime02を再生
                            if (catModel.components['meishi-animation']) {
                                catModel.components['meishi-animation'].playHitAnimation();
                            }
                            
                            // ボールを削除
                            pokeball.parentNode.removeChild(pokeball);
                        }
                    }, 16);
                    
                    // 8秒後にチェック終了
                    setTimeout(() => {
                        clearInterval(checkInterval);
                        // ボールがまだ存在していたら削除
                        if (pokeball.parentNode) {
                            pokeball.parentNode.removeChild(pokeball);
                        }
                    }, 8000);
                });
            }
            
            // ヒットエフェクトを表示
            function showHitEffect(ballPos) {
                // 1. パーティクルエフェクト
                const particleIcons = ['💥', '⭐', '✨', '💫', '🌟'];
                
                for (let i = 0; i < 10; i++) {
                    setTimeout(() => {
                        const particle = document.createElement('div');
                        particle.className = 'hit-particle';
                        particle.textContent = particleIcons[Math.floor(Math.random() * particleIcons.length)];
                        
                        const randomX = (Math.random() - 0.5) * 2;
                        const randomY = -Math.random() * 150;
                        
                        particle.style.cssText = `
                            position: fixed;
                            font-size: 30px;
                            pointer-events: none;
                            z-index: 10000;
                            --random-x: ${randomX};
                            --random-y: ${randomY};
                        `;
                        
                        // 3D座標を2D座標に変換
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
                
                // 2. 画面フラッシュ
                if (flash) {
                    flash.classList.add('active');
                    setTimeout(() => {
                        flash.classList.remove('active');
                    }, 100);
                }
            }
        });
    </script>
</body>
</html>
