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
    </style>
</head>
<body>
    <div class="arjs-loader">
        <div>カメラを起動中...</div>
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
                scale="2 2 2"
                rotation="0 0 0"
                click-animation="clip: anime01">
            </a-entity>
            
            <!-- ライトを追加して明るくする -->
            <a-light type="ambient" intensity="1.2"></a-light>
            <a-light type="directional" intensity="0.6" position="1 1 1"></a-light>
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
            
            scene.addEventListener('loaded', function() {
                sceneReady = true;
                console.log('Scene loaded');
            });
            
            // 画面全体のタップを検出
            document.body.addEventListener('touchstart', function(e) {
                console.log('Screen tapped');
                if (sceneReady && model) {
                    const clickEvent = new Event('click');
                    model.dispatchEvent(clickEvent);
                }
            }, {passive: false});
            
            // マウスクリックも対応
            document.body.addEventListener('click', function(e) {
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
