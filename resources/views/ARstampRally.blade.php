<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AR Stamp Rally</title>
    <script src="https://aframe.io/releases/1.4.2/aframe.min.js"></script>
    <script src="https://raw.githack.com/AR-js-org/AR.js/master/aframe/build/aframe-ar.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/donmccurdy/aframe-extras@v6.1.1/dist/aframe-extras.min.js"></script>
    <script>
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
                    
                    console.log('Model:', model);
                    
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
                        console.error('No animations found in the model');
                    }
                });
                
                // クリック/タップイベント
                el.addEventListener('click', () => {
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
                    }
                });
            },
            tick: function(time, deltaTime) {
                if (this.mixer) {
                    this.mixer.update(deltaTime / 1000);
                }
            }
        });
    </script>
</head>
<body style="margin: 0; overflow: hidden;">
    <a-scene
        embedded
        arjs="sourceType: webcam; debugUIEnabled: false;"
        vr-mode-ui="enabled: false">
        
        <!-- カメラ -->
        <a-entity camera></a-entity>
        
        <!-- マーカー -->
        <a-marker preset="hiro">
            <!-- 3Dモデル -->
            <a-entity
                gltf-model="url(/cg/3d_isobe_fox5.glb)"
                position="0 0 0"
                scale="2 2 2"
                rotation="0 0 0"
                click-to-play-animation="clip: anime01"
                class="clickable">
            </a-entity>
        </a-marker>
        
    </a-scene>
</body>
</html>