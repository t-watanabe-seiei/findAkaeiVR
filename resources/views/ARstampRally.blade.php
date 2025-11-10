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
                let action = null;
                let isPlaying = false;
                
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
                    
                    let clipToPlay = THREE.AnimationClip.findByName(model.animations, clipName);
                    if (!clipToPlay) {
                        console.log(`Animation "${clipName}" not found, using first animation`);
                        clipToPlay = model.animations[0];
                    }
                    
                    action = mixer.clipAction(clipToPlay);
                    action.setLoop(THREE.LoopRepeat, Infinity);
                    action.stop();
                    this.action = action;
                    console.log('Animation ready:', clipToPlay.name);
                });
                
                const handleInteraction = (e) => {
                    console.log('Interaction detected:', e.type);
                    if (!action) {
                        console.log('Action not ready yet');
                        return;
                    }
                    
                    if (!isPlaying) {
                        action.reset();
                        action.play();
                        isPlaying = true;
                        console.log('✓ Animation started');
                    } else {
                        action.stop();
                        isPlaying = false;
                        console.log('✗ Animation stopped');
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
