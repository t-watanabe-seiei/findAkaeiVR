<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <title>seieiVR</title>
    <script src="https://aframe.io/releases/1.2.0/aframe.min.js"></script>
    <script src="{{ asset('js/aframe-particle-system-component.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/gh/c-frame/aframe-extras@7.2.0/dist/aframe-extras.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/n5ro/aframe-physics-system@v4.2.2/dist/aframe-physics-system.min.js"></script>
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>

    <script>  
        // ボールを撃つコンポーネント
        AFRAME.registerComponent('shoot', {
            init: function () {
                this.shoot = this.shoot.bind(this);
                this.onKeyDown = this.onKeyDown.bind(this);
                this.onClick = this.onClick.bind(this);
                this.onTouchStart = this.onTouchStart.bind(this);
                
                // スペースキーのイベントリスナーを追加
                window.addEventListener('keydown', this.onKeyDown);
                
                // A-Frameのcanvasエレメントにのみイベントリスナーを追加
                const canvas = this.el.sceneEl.canvas;
                if (canvas) {
                    // マウスクリックのイベントリスナーを追加
                    canvas.addEventListener('click', this.onClick);
                    
                    // スマホタップのイベントリスナーを追加
                    canvas.addEventListener('touchstart', this.onTouchStart);
                }
                
                // VRコントローラーのイベントリスナーを追加
                const leftController = document.getElementById('leftController');
                const rightController = document.getElementById('rightController');
                if (leftController) leftController.addEventListener('triggerdown', this.shoot);
                if (rightController) rightController.addEventListener('triggerdown', this.shoot);
            },
            
            onKeyDown: function (event) {
                // スペースキーが押された場合
                if (event.code === 'Space') {
                    event.preventDefault(); // デフォルトのスペースキー動作を防ぐ
                    this.shoot(event);
                    console.log('space key pressed');
                }
            },
            
            onClick: function (event) {
                // マウスクリックの場合（A-Frameのcanvas上でのみ）
                this.shoot(event);
                console.log('mouse clicked on canvas');
            },
            
            onTouchStart: function (event) {
                // スマホタップの場合（A-Frameのcanvas上でのみ）
                event.preventDefault(); // デフォルトのタッチ動作を防ぐ
                this.shoot(event);
                console.log('screen tapped on canvas');
            },
            
            shoot: function (event) {
                console.log('Shoot function called, event type:', event.type);
                
                const sceneEl = this.el.sceneEl;
                const camera = this.el;
                
                // ボールエンティティを作成
                const ball = document.createElement('a-sphere');
                ball.setAttribute('radius', 0.1);
                ball.setAttribute('color', 'red');
                ball.setAttribute('material', 'color: red; metalness: 0.1; roughness: 0.8; opacity: 1; transparent: true;');
                
                // 位置と方向を計算
                const position = new THREE.Vector3();
                const direction = new THREE.Vector3();
                
                if (event.type === 'triggerdown') {
                    // VRコントローラーからの発射
                    event.target.object3D.getWorldPosition(position);
                    event.target.object3D.getWorldDirection(direction);
                    console.log('Shooting from VR controller');
                } else {
                    // スペースキー、マウスクリック、スマホタップからの発射（カメラの向いている方向）
                    camera.object3D.getWorldPosition(position);
                    camera.object3D.getWorldDirection(direction);
                    console.log('Shooting from camera, position:', position, 'direction:', direction);
                }
                
                // カメラの少し前にボールを配置
                const startPos = position.clone().add(direction.clone().multiplyScalar(-0.5));
                ball.setAttribute('position', `${startPos.x} ${startPos.y} ${startPos.z}`);
                sceneEl.appendChild(ball);
                
                console.log('Ball created at position:', startPos);
                
                // 物理演算で放物線を描く
                const gravity = -4.9; // 重力加速度 (m/s^2)
                const initialSpeed = 10; // 初速度 (m/s)
                const velocity = direction.clone().multiplyScalar(-initialSpeed); // 初速度ベクトル
                
                let animationFrameId;
                let hasHit = false;
                let startTime = Date.now();
                let lastPosition = startPos.clone();
                
                const updateBallPosition = () => {
                    if (hasHit || !ball.parentNode) {
                        return; // 既に当たったか削除されている場合は終了
                    }
                    
                    // 経過時間（秒）
                    const elapsedTime = (Date.now() - startTime) / 1000;
                    
                    // 放物線運動の計算
                    // x, z方向は等速直線運動
                    // y方向は重力による等加速度運動: y = y0 + v0*t + 0.5*g*t^2
                    const currentPos = new THREE.Vector3(
                        startPos.x + velocity.x * elapsedTime,
                        startPos.y + velocity.y * elapsedTime + 0.5 * gravity * elapsedTime * elapsedTime,
                        startPos.z + velocity.z * elapsedTime
                    );
                    
                    // ボールの位置を更新
                    ball.setAttribute('position', `${currentPos.x} ${currentPos.y} ${currentPos.z}`);
                    
                    // 各モデルの位置を取得して衝突判定
                    const models = [
                        { id: 'modelGroup_01', hitBoxId: 'hit-boxed_01' },
                        { id: 'modelGroup_02', hitBoxId: 'hit-boxed_02' },
                        { id: 'modelGroup_03', hitBoxId: 'hit-boxed_03' }
                    ];
                    
                    for (let modelInfo of models) {
                        const modelGroup = document.getElementById(modelInfo.id);
                        if (modelGroup && modelGroup.parentNode) { // モデルが存在し、まだ削除されていない場合
                            const modelPos = modelGroup.getAttribute('position');
                            
                            // リアルタイムでボールとモデルの距離を計算
                            const distance = new THREE.Vector3(
                                currentPos.x - modelPos.x,
                                currentPos.y - modelPos.y,
                                currentPos.z - modelPos.z
                            ).length();
                            
                            console.log(`Distance to ${modelInfo.id}:`, distance.toFixed(2));
                            
                            if (distance < 0.5) { // 0.5m以内なら当たり判定
                                hasHit = true;
                                console.log(`Ball hit ${modelInfo.id}!`);
                                const hitBoxComponent = modelGroup.querySelector(`#${modelInfo.hitBoxId}`);
                                if (hitBoxComponent) {
                                    // 独自の'ball-hit'イベントを発火
                                    hitBoxComponent.emit('ball-hit');
                                }
                                
                                // ボールが跳ね返るアニメーション
                                const bounceDirection = direction.clone().multiplyScalar(2);
                                const bouncePos = currentPos.clone().add(bounceDirection);
                                
                                ball.setAttribute('animation__bounce', {
                                    property: 'position',
                                    to: `${bouncePos.x} ${bouncePos.y} ${bouncePos.z}`,
                                    dur: 300,
                                    easing: 'easeOutQuad'
                                });
                                
                                ball.setAttribute('animation__fade', {
                                    property: 'material.opacity',
                                    to: 0,
                                    dur: 300,
                                    easing: 'linear'
                                });
                                
                                ball.setAttribute('color', 'yellow');
                                
                                setTimeout(() => {
                                    if (ball.parentNode) {
                                        ball.parentNode.removeChild(ball);
                                        console.log('Ball removed after bounce');
                                    }
                                }, 300);
                                
                                return;
                            }
                        }
                    }
                    
                    // 地面に落ちたら削除（y < -2）
                    if (currentPos.y < -2 || elapsedTime > 3) {
                        console.log('Ball fell to ground or timeout');
                        if (ball.parentNode) {
                            ball.parentNode.removeChild(ball);
                            console.log('Ball removed');
                        }
                        return;
                    }
                    
                    lastPosition = currentPos.clone();
                    
                    // 次のフレームでも位置更新を継続
                    animationFrameId = requestAnimationFrame(updateBallPosition);
                };
                
                // 物理演算開始
                animationFrameId = requestAnimationFrame(updateBallPosition);
            }
        });
        
        AFRAME.registerComponent('hit-box', {
            init: function () {
                const modelGroup = this.el.parentEl; // 親エンティティ（modelGroup）を取得
                const modelEntity = modelGroup.querySelector('[gltf-model]'); // gltf-modelを持つエンティティを取得
                let hitFlag = false;

                // モデルの情報を保存
                const modelId = modelGroup.id;
                const gltfModelSrc = modelEntity ? modelEntity.getAttribute('gltf-model') : null;

                // ボールがヒットしたときのみ発火する独自イベント 'ball-hit' を監視
                this.el.addEventListener('ball-hit', () => {
                    if(!hitFlag) {
                        hitFlag = true;
                        console.log('Model hit!', modelEntity);

                        // anime02に切り替え（2.5秒間再生）
                        if (modelEntity) {
                            modelEntity.removeAttribute('animation-mixer'); // 一旦削除
                            setTimeout(() => {
                                modelEntity.setAttribute('animation-mixer', 'clip: anime02; loop: repeat; timeScale: 1');
                                console.log('Playing anime02 for 2.5 seconds');
                            }, 50);
                        }
                        
                        // 2.5秒後にanime03に切り替え（1秒間再生）
                        setTimeout(() => {
                            if (modelEntity && modelEntity.parentNode) {
                                modelEntity.removeAttribute('animation-mixer'); // 一旦削除
                                setTimeout(() => {
                                    modelEntity.setAttribute('animation-mixer', 'clip: anime03; loop: repeat; timeScale: 1');
                                    console.log('Playing anime03 for 1 second');
                                }, 50);
                            }
                            
                            // 1秒後にフェードアウト開始
                            setTimeout(() => {
                                if (modelGroup && modelGroup.parentNode) {
                                    console.log('Starting fadeout');
                                    // フェードアウトアニメーション（0.5秒かけて縮小）
                                    modelGroup.setAttribute('animation__fadeout', {
                                        property: 'scale',
                                        to: '0 0 0',
                                        dur: 500,
                                        easing: 'easeInQuad'
                                    });
                                    
                                    // フェードアウト完了後に削除して、3秒後に再描画
                                    setTimeout(() => {
                                        if (modelGroup.parentNode) {
                                            modelGroup.parentNode.removeChild(modelGroup);
                                            console.log('Model removed');
                                            
                                            // 3秒後に別の場所に再描画
                                            setTimeout(() => {
                                                this.respawnModel(modelId, gltfModelSrc);
                                            }, 3000);
                                        }
                                    }, 500);
                                }
                            }, 1000); // anime03を1秒間再生
                        }, 2500); // anime02を2.5秒間再生
                    }
                });
            },

            // モデルを再描画する関数
            respawnModel: function(modelId, gltfModelSrc) {
                console.log('Respawning model:', modelId);
                const sceneEl = document.querySelector('a-scene');
                
                // ランダムな位置を生成
                const positions = [
                    { x: -4, y: 0, z: -3, rotation: 45 },
                    { x: -2, y: 0, z: -5, rotation: 30 },
                    { x: 2, y: 0, z: -5, rotation: -30 },
                    { x: 4, y: 0, z: -3, rotation: -45 },
                    { x: -3, y: 0, z: -2, rotation: 45 },
                    { x: 0, y: 0, z: -4, rotation: 0 },
                    { x: 3, y: 0, z: -2, rotation: -45 }
                ];
                const randomPos = positions[Math.floor(Math.random() * positions.length)];
                
                // 新しいモデルグループを作成
                const newModelGroup = document.createElement('a-entity');
                newModelGroup.setAttribute('id', modelId);
                newModelGroup.setAttribute('position', `${randomPos.x} ${randomPos.y} ${randomPos.z}`);
                newModelGroup.setAttribute('rotation', `0 ${randomPos.rotation} 0`);
                newModelGroup.setAttribute('scale', '0 0 0'); // 最初は見えない状態
                
                // 3Dモデルエンティティを作成
                const newModelEntity = document.createElement('a-entity');
                newModelEntity.setAttribute('gltf-model', gltfModelSrc);
                newModelEntity.setAttribute('animation-mixer', 'clip: anime01; loop: repeat');
                newModelGroup.appendChild(newModelEntity);
                
                // 当たり判定オブジェクトを作成
                const hitBoxId = modelId.replace('modelGroup', 'hit-boxed');
                const newHitBox = document.createElement('a-entity');
                newHitBox.setAttribute('id', hitBoxId);
                newHitBox.setAttribute('hit-box', '');
                newHitBox.setAttribute('position', '0 0.5 0');
                
                const hitBoxCylinder = document.createElement('a-entity');
                hitBoxCylinder.setAttribute('geometry', 'primitive: cylinder');
                hitBoxCylinder.setAttribute('material', 'color: blue; opacity: 0.0; transparent: true');
                hitBoxCylinder.setAttribute('scale', '0.3 1 0.3');
                hitBoxCylinder.setAttribute('class', 'collidable');
                
                newHitBox.appendChild(hitBoxCylinder);
                newModelGroup.appendChild(newHitBox);
                
                // シーンに追加
                sceneEl.appendChild(newModelGroup);
                console.log('Model added to scene');
                
                // フェードインアニメーション
                setTimeout(() => {
                    newModelGroup.setAttribute('animation__fadein', {
                        property: 'scale',
                        to: '1 1 1',
                        dur: 1000,
                        easing: 'easeOutQuad'
                    });
                    console.log('Model fading in');
                }, 100);
            }
        });




        // Controller
        AFRAME.registerComponent("vr-controller", {
            dependencies: ["raycaster"],// Important
            init: function () {
                // console.log("vr-controller");
                // const text = document.getElementById("my_text");
                // text.setAttribute("value", "Controller is ready!?");

                // this.el.addEventListener("gripdown", function(e) {
                //     text.setAttribute("value", "GripDown!!");
                // });
            }
        });


    </script>
</head>

<body>
    <a-scene physics="gravity: -9.8">
        <a-assets>
            <!-- 3Dモデル -->
            <a-asset-item id="model_01" src={{ asset('cg/ishimaru.glb') }}></a-asset-item>
            <a-asset-item id="model_02" src={{ asset('cg/oda.glb') }}></a-asset-item>
            <a-asset-item id="model_03" src={{ asset('cg/ohnomi.glb') }}></a-asset-item>
            
            <!-- 背景画像 -->
            <img id="sky02" src={{ asset('cg/R0010186.JPG') }} crossorigin="anonymous" >
        </a-assets>

        <!-- マウスカーソル（raycasterによるクリックイベントは無効化） -->
        <a-entity id="mouseCursor" cursor="rayOrigin: mouse" raycaster="objects: .disabled-raycast"></a-entity>

        <!-- Controller -->
        <a-entity id="leftController" laser-controls="hand: left" raycaster="objects: .collidable; far: 5" vr-controller></a-entity>
        <a-entity id="rightController" laser-controls="hand: right" raycaster="objects: .collidable; far: 5" vr-controller></a-entity>

        <!-- モデル01グループ -->
        <a-entity id="modelGroup_01" position="-3 0 -2" rotation="0 45 0" scale="1 1 1">
            <a-entity gltf-model="#model_01" animation-mixer="clip: anime01; loop: repeat"></a-entity>
            <a-entity id="hit-boxed_01" hit-box position="0 0.5 0">
                <a-entity geometry="primitive: cylinder" material="color: blue; opacity: 0.0; transparent: true" 
                          scale="0.3 1 0.3" class="collidable"></a-entity>
            </a-entity>
        </a-entity>

        <!-- モデル02グループ -->
        <a-entity id="modelGroup_02" position="0 0 -4" rotation="0 0 0" scale="1 1 1">
            <a-entity gltf-model="#model_02" animation-mixer="clip: anime01; loop: repeat"></a-entity>
            <a-entity id="hit-boxed_02" hit-box position="0 0.5 0">
                <a-entity geometry="primitive: cylinder" material="color: blue; opacity: 0.0; transparent: true" 
                          scale="0.3 1 0.3" class="collidable"></a-entity>
            </a-entity>
        </a-entity>

        <!-- モデル03グループ -->
        <a-entity id="modelGroup_03" position="3 0 -2" rotation="0 -45 0" scale="1 1 1">
            <a-entity gltf-model="#model_03" animation-mixer="clip: anime01; loop: repeat"></a-entity>
            <a-entity id="hit-boxed_03" hit-box position="0 0.5 0">
                <a-entity geometry="primitive: cylinder" material="color: blue; opacity: 0.0; transparent: true" 
                          scale="0.3 1 0.3" class="collidable"></a-entity>
            </a-entity>
        </a-entity>

        <!-- 360度画像を表示 -->
        <a-sky id="aSky" src="#sky02"></a-sky>

        <!-- Particle -->
        <a-entity id="particle" visible="false" position="0 3 0" particle-system="preset: star; color: #f216b0,#f24535"></a-entity>
        

        <a-camera id="my_camera" shoot>
        </a-camera>
    </a-scene>
</body>

</html>