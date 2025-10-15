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
        // ゲーム状態管理
        window.gameStarted = false;
        window.gameEnded = false;
        window.totalScore = 0;
        window.gameTimer = null;
        window.gameTimeLeft = 60; // 60秒
        
        // GLBモデルの品質を向上させるコンポーネント
        AFRAME.registerComponent('enhance-materials', {
            init: function () {
                this.el.addEventListener('model-loaded', () => {
                    const mesh = this.el.getObject3D('mesh');
                    if (mesh) {
                        mesh.traverse((node) => {
                            if (node.isMesh && node.material) {
                                // マテリアルの品質設定
                                if (node.material.map) {
                                    node.material.map.anisotropy = 16; // テクスチャのアニソトロピックフィルタリング
                                }
                                node.material.needsUpdate = true;
                                
                                // メタルネスとラフネスマップがあれば設定
                                if (node.material.metalnessMap) {
                                    node.material.metalnessMap.anisotropy = 16;
                                }
                                if (node.material.roughnessMap) {
                                    node.material.roughnessMap.anisotropy = 16;
                                }
                                if (node.material.normalMap) {
                                    node.material.normalMap.anisotropy = 16;
                                }
                            }
                        });
                        console.log('Model materials enhanced');
                    }
                });
            }
        });
        
        // ボール管理用のグローバル配列
        window.activeBalls = [];
        
        // スタートメニューコンポーネント
        AFRAME.registerComponent('start-menu', {
            init: function() {
                this.startGame = this.startGame.bind(this);
                this.handleClick = this.handleClick.bind(this);
                this.clickBlocked = false; // クリックブロックフラグ
                
                // メニュー内のすべてのクリック可能な要素にイベントを追加
                const clickableElements = this.el.querySelectorAll('.clickable');
                clickableElements.forEach(element => {
                    element.addEventListener('click', this.handleClick);
                    console.log('Click listener added to:', element.id || element.tagName);
                });
                
                // メニュー全体にもイベントを追加
                this.el.addEventListener('click', this.handleClick);
                
                console.log('Start menu initialized with', clickableElements.length, 'clickable elements');
            },
            
            tick: function() {
                // ゲーム中はメニューを完全に非表示・無効化
                if (window.gameStarted && !window.gameEnded) {
                    const isVisible = this.el.getAttribute('visible');
                    const scale = this.el.getAttribute('scale');
                    
                    // メニューが表示されている場合のみ非表示にする（無限ループ防止）
                    if (isVisible !== false) {
                        console.log('WARNING: Menu visible during game! Force hiding...');
                        this.el.setAttribute('visible', false);
                    }
                    if (scale && (scale.x !== 0 || scale.y !== 0 || scale.z !== 0)) {
                        this.el.setAttribute('scale', '0 0 0');
                    }
                    
                    // VRコントローラーのraycasterターゲットから.clickableを除外
                    const leftController = document.getElementById('leftController');
                    const rightController = document.getElementById('rightController');
                    
                    if (leftController) {
                        const currentObjects = leftController.getAttribute('raycaster').objects;
                        if (currentObjects && currentObjects.includes('.clickable')) {
                            leftController.setAttribute('raycaster', 'objects: .collidable; far: 5');
                            console.log('Removed .clickable from left controller raycaster');
                        }
                    }
                    if (rightController) {
                        const currentObjects = rightController.getAttribute('raycaster').objects;
                        if (currentObjects && currentObjects.includes('.clickable')) {
                            rightController.setAttribute('raycaster', 'objects: .collidable; far: 5');
                            console.log('Removed .clickable from right controller raycaster');
                        }
                    }
                    
                    this.clickBlocked = true;
                } else {
                    // ゲーム中でない場合（開始前またはゲーム終了後）は.clickableを復元
                    const leftController = document.getElementById('leftController');
                    const rightController = document.getElementById('rightController');
                    
                    if (leftController) {
                        const currentObjects = leftController.getAttribute('raycaster').objects;
                        if (currentObjects && !currentObjects.includes('.clickable')) {
                            leftController.setAttribute('raycaster', 'objects: .collidable, .clickable; far: 5');
                            console.log('Restored .clickable to left controller raycaster');
                        }
                    }
                    if (rightController) {
                        const currentObjects = rightController.getAttribute('raycaster').objects;
                        if (currentObjects && !currentObjects.includes('.clickable')) {
                            rightController.setAttribute('raycaster', 'objects: .collidable, .clickable; far: 5');
                            console.log('Restored .clickable to right controller raycaster');
                        }
                    }
                    
                    // スタートメニューに対してのみクリックをブロック
                    if (window.gameEnded) {
                        this.clickBlocked = true; // スタートメニューはブロック（リザルトメニューは別）
                    } else {
                        this.clickBlocked = false;
                    }
                }
            },
            
            handleClick: function(event) {
                console.log('=== Menu Click Detected ===');
                console.log('Menu visible:', this.el.getAttribute('visible'));
                console.log('Game started:', window.gameStarted);
                console.log('Game ended:', window.gameEnded);
                console.log('Click blocked:', this.clickBlocked);
                
                // クリックがブロックされている場合は即座に拒否
                if (this.clickBlocked) {
                    console.log('Click BLOCKED by flag');
                    event.stopPropagation();
                    event.preventDefault();
                    return false;
                }
                
                // メニューが非表示またはゲーム中の場合は無視
                if (!this.el.getAttribute('visible') || window.gameStarted || window.gameEnded) {
                    console.log('Ignoring click - menu not visible or game in progress');
                    event.stopPropagation();
                    event.preventDefault();
                    return false;
                }
                
                this.startGame(event);
            },
            
            startGame: function(event) {
                console.log('Game Start triggered!');
                
                // 既存のタイマーがあればクリア
                if (window.gameTimer) {
                    clearInterval(window.gameTimer);
                    window.gameTimer = null;
                    console.log('Cleared existing timer');
                }
                
                window.gameStarted = true;
                window.gameEnded = false; // ゲーム終了フラグもリセット
                window.totalScore = 0; // スコアをリセット
                window.gameTimeLeft = 60; // タイマーを60秒に設定
                
                // メニューを非表示（フェードアウト効果付き）
                this.el.setAttribute('animation', {
                    property: 'scale',
                    to: '0 0 0',
                    dur: 500,
                    easing: 'easeInQuad'
                });
                
                setTimeout(() => {
                    this.el.setAttribute('visible', false);
                }, 500);
                
                // モデルを表示して移動開始
                const models = document.querySelectorAll('[id^="modelGroup_"]');
                console.log('Found', models.length, 'models to show');
                models.forEach(model => {
                    model.setAttribute('visible', true);
                    console.log('Model visible:', model.id);
                });
                
                // タイマー表示を表示
                const timerDisplay = document.getElementById('timerDisplay');
                if (timerDisplay) {
                    timerDisplay.setAttribute('visible', true);
                }
                
                // スコア表示を初期化
                const currentScoreText = document.getElementById('currentScore');
                if (currentScoreText) {
                    currentScoreText.setAttribute('value', 'SCORE: 0.0');
                }
                
                // 1分タイマーを開始
                this.startTimer();
            },
            
            startTimer: function() {
                const timerText = document.getElementById('timerText');
                const resultMenu = document.getElementById('resultMenu');
                
                window.gameTimer = setInterval(() => {
                    window.gameTimeLeft--;
                    
                    // タイマー表示を更新
                    if (timerText) {
                        timerText.setAttribute('value', `TIME: ${window.gameTimeLeft}s`);
                    }
                    
                    // 時間切れ
                    if (window.gameTimeLeft <= 0) {
                        clearInterval(window.gameTimer);
                        window.gameEnded = true;
                        window.gameStarted = false;
                        
                        console.log('Game Over! Total Score:', window.totalScore);
                        
                        // すべてのモデルを非表示
                        const models = document.querySelectorAll('[id^="modelGroup_"]');
                        models.forEach(model => {
                            model.setAttribute('visible', false);
                        });
                        
                        // タイマー非表示
                        const timerDisplay = document.getElementById('timerDisplay');
                        if (timerDisplay) {
                            timerDisplay.setAttribute('visible', false);
                        }
                        
                        // リザルト画面を表示
                        if (resultMenu) {
                            this.showResult(resultMenu);
                        }
                    }
                }, 1000);
            },
            
            showResult: function(resultMenu) {
                const scoreText = document.getElementById('resultScore');
                const commentText = document.getElementById('resultComment');
                
                // スコアを表示（小数第一位まで）
                if (scoreText) {
                    scoreText.setAttribute('value', `SCORE: ${window.totalScore.toFixed(1)}`);
                }
                
                // スコアに応じたコメント
                let comment = '';
                if (window.totalScore >= 1000) {
                    comment = 'AMAZING! PERFECT SNIPER!';
                } else if (window.totalScore >= 800) {
                    comment = 'EXCELLENT! GREAT JOB!';
                } else if (window.totalScore >= 600) {
                    comment = 'VERY GOOD! NICE SHOOTING!';
                } else if (window.totalScore >= 400) {
                    comment = 'GOOD! KEEP IT UP!';
                } else if (window.totalScore >= 200) {
                    comment = 'NOT BAD! TRY AGAIN!';
                } else {
                    comment = 'KEEP PRACTICING!';
                }
                
                if (commentText) {
                    commentText.setAttribute('value', comment);
                }
                
                // リザルトメニューを表示
                resultMenu.setAttribute('visible', true);
                resultMenu.setAttribute('scale', '0 0 0');
                resultMenu.setAttribute('animation', {
                    property: 'scale',
                    to: '1 1 1',
                    dur: 500,
                    easing: 'easeOutBack'
                });
            },
            
            restartGame: function() {
                console.log('Restarting game...');
                
                // 既存のタイマーをクリア
                if (window.gameTimer) {
                    clearInterval(window.gameTimer);
                    window.gameTimer = null;
                    console.log('Cleared timer in restart');
                }
                
                // ゲーム状態をリセット
                window.gameStarted = false;
                window.gameEnded = false;
                window.totalScore = 0;
                window.gameTimeLeft = 60;
                
                // クリックブロックフラグを解除
                this.clickBlocked = false;
                
                // 動的に生成されたモデルを削除（IDに"modelGroup_"を含むがオリジナルの3つ以外）
                const sceneEl = document.querySelector('a-scene');
                const allModels = sceneEl.querySelectorAll('[id^="modelGroup_"]');
                allModels.forEach(model => {
                    const modelId = model.getAttribute('id');
                    // オリジナルの3つ以外を削除
                    if (modelId !== 'modelGroup_01' && modelId !== 'modelGroup_02' && modelId !== 'modelGroup_03') {
                        console.log('Removing dynamically created model:', modelId);
                        model.parentNode.removeChild(model);
                    }
                });
                
                // 初期の3つのモデルを初期位置にリセット
                const initialModels = [
                    { id: 'modelGroup_01', position: '-3 0 -2', rotation: '0 45 0' },
                    { id: 'modelGroup_02', position: '0 0 -4', rotation: '0 0 0' },
                    { id: 'modelGroup_03', position: '3 0 -2', rotation: '0 -45 0' }
                ];
                
                initialModels.forEach(modelInfo => {
                    const model = document.getElementById(modelInfo.id);
                    if (model) {
                        // 位置と回転をリセット
                        model.setAttribute('position', modelInfo.position);
                        model.setAttribute('rotation', modelInfo.rotation);
                        model.setAttribute('scale', '1 1 1');
                        model.setAttribute('visible', false);
                        
                        // approach-cameraコンポーネントをリセット
                        const approachComponent = model.components['approach-camera'];
                        if (approachComponent) {
                            approachComponent.reset();
                        }
                        
                        // アニメーションをリセット（anime01に戻す）
                        const gltfEntity = model.querySelector('[gltf-model]');
                        if (gltfEntity) {
                            gltfEntity.setAttribute('animation-mixer', 'clip: anime01; loop: repeat');
                        }
                        
                        console.log('Reset model to initial position:', modelInfo.id);
                    }
                });
                
                // すべてのモデルを非表示にする（念のため）
                const models = document.querySelectorAll('[id^="modelGroup_"]');
                console.log('Hiding', models.length, 'models for restart');
                models.forEach(model => {
                    model.setAttribute('visible', false);
                });
                
                // タイマー表示を非表示
                const timerDisplay = document.getElementById('timerDisplay');
                if (timerDisplay) {
                    timerDisplay.setAttribute('visible', false);
                }
                
                // リザルトメニューを非表示
                const resultMenu = document.getElementById('resultMenu');
                if (resultMenu) {
                    resultMenu.setAttribute('visible', false);
                }
                
                // スタートメニューを再表示
                const startMenu = document.getElementById('startMenu');
                if (startMenu) {
                    startMenu.setAttribute('visible', true);
                    startMenu.setAttribute('scale', '1 1 1');
                }
            }
        });
        
        // リザルト画面コンポーネント
        AFRAME.registerComponent('result-menu', {
            init: function() {
                this.restart = this.restart.bind(this);
                
                // RESTARTボタンにクリックイベントを追加
                const restartButton = this.el.querySelector('#restartButton');
                if (restartButton) {
                    restartButton.addEventListener('click', this.restart);
                    console.log('Restart button listener added');
                }
            },
            
            restart: function() {
                console.log('Restart button clicked');
                
                // スタートメニューコンポーネントのrestartGame関数を呼び出す
                const startMenu = document.getElementById('startMenu');
                if (startMenu && startMenu.components['start-menu']) {
                    startMenu.components['start-menu'].restartGame();
                }
            }
        });
        
        // ボールを撃つコンポーネント
        AFRAME.registerComponent('shoot', {
            init: function () {
                this.shoot = this.shoot.bind(this);
                this.onKeyDown = this.onKeyDown.bind(this);
                this.onClick = this.onClick.bind(this);
                this.onTouchStart = this.onTouchStart.bind(this);
                
                // 重複発火防止用のタイマー
                this.lastShootTime = 0;
                this.shootCooldown = 200; // 200ms以内の重複を防ぐ
                
                // スペースキーのイベントリスナーを追加
                window.addEventListener('keydown', this.onKeyDown);
                
                // A-Frameのcanvasエレメントにのみイベントリスナーを追加
                this.setupCanvasListeners();
                
                // VRコントローラーのイベントリスナーを追加
                this.setupControllerListeners();
                
                // VRモードの変更を監視
                this.el.sceneEl.addEventListener('enter-vr', () => {
                    console.log('Entered VR mode');
                    // VRモードに入ったらリスナーを再設定
                    setTimeout(() => {
                        this.setupCanvasListeners();
                        this.setupControllerListeners();
                    }, 100);
                });
                
                this.el.sceneEl.addEventListener('exit-vr', () => {
                    console.log('Exited VR mode');
                    // VRモードを出たらリスナーを再設定
                    setTimeout(() => {
                        this.setupCanvasListeners();
                    }, 100);
                });
            },
            
            tick: function(time, timeDelta) {
                // アクティブなボールを更新（VRモード対応）
                if (window.activeBalls.length > 0) {
                    const currentTime = Date.now();
                    for (let i = window.activeBalls.length - 1; i >= 0; i--) {
                        const ballData = window.activeBalls[i];
                        if (ballData && ballData.ball && ballData.ball.parentNode) {
                            this.updateBallPosition(ballData, currentTime);
                        } else {
                            // ボールが削除されている場合は配列から削除
                            window.activeBalls.splice(i, 1);
                        }
                    }
                }
            },
            
            updateBallPosition: function(ballData, currentTime) {
                if (ballData.hasHit) {
                    return; // 既に当たった場合は終了
                }
                
                const { ball, startPos, velocity, startTime, direction, frameCount } = ballData;
                const gravity = -4.9;
                
                // 経過時間（秒）
                const elapsedTime = (currentTime - startTime) / 1000;
                
                // 放物線運動の計算
                const currentPos = new THREE.Vector3(
                    startPos.x + velocity.x * elapsedTime,
                    startPos.y + velocity.y * elapsedTime + 0.5 * gravity * elapsedTime * elapsedTime,
                    startPos.z + velocity.z * elapsedTime
                );
                
                // ボールの位置を更新
                ball.setAttribute('position', `${currentPos.x} ${currentPos.y} ${currentPos.z}`);
                
                ballData.frameCount++;
                if (ballData.frameCount <= 3) {
                    console.log(`Frame ${ballData.frameCount}: Ball at (${currentPos.x.toFixed(2)}, ${currentPos.y.toFixed(2)}, ${currentPos.z.toFixed(2)})`);
                }
                
                // 各モデルの位置を取得して衝突判定
                const models = [
                    { id: 'modelGroup_01', hitBoxId: 'hit-boxed_01' },
                    { id: 'modelGroup_02', hitBoxId: 'hit-boxed_02' },
                    { id: 'modelGroup_03', hitBoxId: 'hit-boxed_03' }
                ];
                
                for (let modelInfo of models) {
                    const modelGroup = document.getElementById(modelInfo.id);
                    if (modelGroup && modelGroup.parentNode) {
                        const modelPos = modelGroup.getAttribute('position');
                        
                        const distance = new THREE.Vector3(
                            currentPos.x - modelPos.x,
                            currentPos.y - modelPos.y,
                            currentPos.z - modelPos.z
                        ).length();
                        
                        if (distance < 0.5) {
                            ballData.hasHit = true;
                            console.log(`Ball hit ${modelInfo.id}!`);
                            const hitBoxComponent = modelGroup.querySelector(`#${modelInfo.hitBoxId}`);
                            if (hitBoxComponent) {
                                hitBoxComponent.emit('ball-hit');
                            }
                            
                            // ボールが跳ね返るアニメーション
                            const bounceDirection = direction.clone().multiplyScalar(-2);
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
                    if (elapsedTime > 3) {
                        console.log('Ball timeout after 3 seconds');
                    } else {
                        console.log('Ball fell to ground');
                    }
                    if (ball.parentNode) {
                        ball.parentNode.removeChild(ball);
                    }
                    ballData.hasHit = true; // 削除済みフラグ
                    return;
                }
            },
            
            setupCanvasListeners: function() {
                const canvas = this.el.sceneEl.canvas;
                if (canvas) {
                    // 既存のリスナーを削除してから再追加
                    canvas.removeEventListener('click', this.onClick);
                    canvas.removeEventListener('touchstart', this.onTouchStart);
                    
                    // captureフェーズで捕捉して、バブリングを防ぐ
                    canvas.addEventListener('click', this.onClick, true);
                    canvas.addEventListener('touchstart', this.onTouchStart, { passive: false, capture: true });
                    console.log('Canvas listeners set up (with capture)');
                }
            },
            
            setupControllerListeners: function() {
                const leftController = document.getElementById('leftController');
                const rightController = document.getElementById('rightController');
                
                if (leftController) {
                    leftController.removeEventListener('triggerdown', this.shoot);
                    leftController.addEventListener('triggerdown', this.shoot);
                    console.log('Left controller listener set up');
                }
                if (rightController) {
                    rightController.removeEventListener('triggerdown', this.shoot);
                    rightController.addEventListener('triggerdown', this.shoot);
                    console.log('Right controller listener set up');
                }
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
                // 重複発火を防ぐ（touchstartとclickの両方が発火する場合に対応）
                const currentTime = Date.now();
                if (currentTime - this.lastShootTime < this.shootCooldown) {
                    console.log('Click ignored (too soon after last shoot)');
                    event.stopPropagation();
                    event.preventDefault();
                    return;
                }
                
                // マウスクリックの場合（A-Frameのcanvas上でのみ）
                this.shoot(event);
                console.log('mouse clicked on canvas');
            },
            
            onTouchStart: function (event) {
                // 重複発火を防ぐ
                const currentTime = Date.now();
                if (currentTime - this.lastShootTime < this.shootCooldown) {
                    console.log('Touch ignored (too soon after last shoot)');
                    event.stopPropagation();
                    event.preventDefault();
                    return;
                }
                
                // スマホタップの場合（A-Frameのcanvas上でのみ）
                event.preventDefault(); // デフォルトのタッチ動作を防ぐ
                event.stopPropagation(); // イベントの伝播を防ぐ
                this.shoot(event);
                console.log('screen tapped on canvas');
            },
            
            shoot: function (event) {
                // イベントの伝播を完全に停止（メニューへの影響を防ぐ）
                if (event && event.stopPropagation) {
                    event.stopPropagation();
                }
                if (event && event.preventDefault) {
                    event.preventDefault();
                }
                
                // ゲームが開始されていない場合は撃てない
                if (!window.gameStarted) {
                    console.log('Game not started, ignoring shoot');
                    return;
                }
                
                // ゲーム終了後は撃てない
                if (window.gameEnded) {
                    console.log('Game ended, ignoring shoot');
                    return;
                }
                
                // 最後のshoot時刻を更新
                this.lastShootTime = Date.now();
                
                console.log('=== Shoot function called ===');
                console.log('Event type:', event.type);
                
                const sceneEl = this.el.sceneEl;
                const camera = this.el;
                
                // ボールエンティティを作成
                const ball = document.createElement('a-sphere');
                ball.setAttribute('radius', 0.1);
                ball.setAttribute('color', 'red');
                ball.setAttribute('material', 'color: red; metalness: 0.1; roughness: 0.8; opacity: 1; transparent: true;');
                
                // 位置と方向を計算
                let position = new THREE.Vector3();
                let direction = new THREE.Vector3();
                
                if (event.type === 'triggerdown') {
                    // VRコントローラーからの発射
                    console.log('Shooting from VR controller');
                    const controller = event.target;
                    
                    // コントローラーの位置を取得
                    controller.object3D.getWorldPosition(position);
                    console.log('Controller position:', position);
                    
                    // raycasterコンポーネントから方向を取得
                    const raycasterComponent = controller.components.raycaster;
                    if (raycasterComponent && raycasterComponent.raycaster) {
                        // raycasterの方向をコピー
                        direction.copy(raycasterComponent.raycaster.ray.direction).normalize();
                        console.log('Using raycaster direction:', direction);
                    } else {
                        // raycasterがない場合はコントローラーのローカル前方向を使用
                        direction.set(0, 0, -1);
                        direction.applyQuaternion(controller.object3D.quaternion);
                        direction.normalize();
                        console.log('Using controller quaternion direction:', direction);
                    }
                } else {
                    // スペースキー、マウスクリック、スマホタップからの発射
                    console.log('Shooting from camera/input');
                    
                    // VRモードかどうかを確認
                    const isVRMode = sceneEl.is('vr-mode');
                    console.log('Is VR Mode:', isVRMode);
                    
                    if (isVRMode) {
                        // VRモード時
                        if (sceneEl.camera) {
                            // シーンのアクティブカメラから取得
                            sceneEl.camera.getWorldPosition(position);
                            direction.set(0, 0, -1);
                            direction.applyQuaternion(sceneEl.camera.quaternion);
                            direction.normalize();
                            console.log('VR Mode - Using scene.camera');
                        } else {
                            // フォールバック
                            camera.object3D.getWorldPosition(position);
                            direction.set(0, 0, -1);
                            direction.applyQuaternion(camera.object3D.quaternion);
                            direction.normalize();
                            console.log('VR Mode - Using camera.object3D');
                        }
                    } else {
                        // 通常モード時
                        camera.object3D.getWorldPosition(position);
                        direction.set(0, 0, -1);
                        direction.applyQuaternion(camera.object3D.quaternion);
                        direction.normalize();
                        console.log('Normal Mode - Using camera.object3D');
                    }
                    
                    console.log('Camera position:', position);
                    console.log('Camera direction:', direction);
                }
                
                // コントローラー/カメラの少し前にボールを配置
                const startPos = position.clone().add(direction.clone().multiplyScalar(0.3));
                ball.setAttribute('position', `${startPos.x} ${startPos.y} ${startPos.z}`);
                sceneEl.appendChild(ball);
                
                console.log('Ball created at:', startPos);
                
                // 物理演算で放物線を描く
                const gravity = -4.9; // 重力加速度 (m/s^2)
                const initialSpeed = 10; // 初速度 (m/s)
                const velocity = direction.clone().multiplyScalar(initialSpeed); // 初速度ベクトル
                
                console.log('Initial velocity:', velocity);
                console.log('=== Ball added to activeBalls array ===');
                
                // ボールデータを配列に追加（A-Frameのtickで更新される）
                window.activeBalls.push({
                    ball: ball,
                    startPos: startPos,
                    velocity: velocity,
                    direction: direction,
                    startTime: Date.now(),
                    hasHit: false,
                    frameCount: 0
                });
            }
        });
        
        // モデルをカメラに向かって移動させるコンポーネント
        AFRAME.registerComponent('approach-camera', {
            init: function() {
                this.speed = 0.25; // 毎秒0.25メートル（以前の半分）
                this.camera = null;
                this.isMoving = true;
            },
            
            tick: function(time, timeDelta) {
                // ゲームが開始されていない場合は移動しない
                if (!window.gameStarted) return;
                if (!this.isMoving) return;
                
                // カメラの取得（初回または未設定の場合）
                if (!this.camera) {
                    const sceneEl = this.el.sceneEl;
                    this.camera = sceneEl.camera ? sceneEl.camera.el : document.querySelector('[camera]');
                    if (!this.camera) return;
                }
                
                // モデルとカメラの位置を取得
                const modelPos = this.el.object3D.position;
                const cameraPos = new THREE.Vector3();
                this.camera.object3D.getWorldPosition(cameraPos);
                
                // カメラへの方向ベクトルを計算
                const direction = new THREE.Vector3();
                direction.subVectors(cameraPos, modelPos);
                direction.y = 0; // Y軸方向は移動しない（地面を滑るように）
                
                const distance = direction.length();
                
                // カメラに十分近づいたら停止（0.9m以内）
                if (distance < 0.9) {
                    this.isMoving = false;
                    console.log('Model stopped: reached 0.9m from camera');
                    return;
                }
                
                // 方向を正規化して速度を適用
                direction.normalize();
                const moveDistance = this.speed * (timeDelta / 1000); // timeDeltaはミリ秒
                direction.multiplyScalar(moveDistance);
                
                // 新しい位置を設定
                modelPos.add(direction);
                
                // カメラと反対方向を向くように回転（Y軸のみ、180度回転）
                const angle = Math.atan2(direction.x, direction.z);
                this.el.object3D.rotation.y = angle; // カメラの反対方向を向く（Math.PIを削除）
            },
            
            // 外部から移動を停止できるメソッド
            stop: function() {
                this.isMoving = false;
            },
            
            // リセットメソッド（リスタート時に使用）
            reset: function() {
                this.isMoving = true;
                this.camera = null;
                console.log('Approach-camera component reset');
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
                        
                        // カメラとモデルの距離を計算してスコア化
                        const sceneEl = document.querySelector('a-scene');
                        const camera = sceneEl.camera ? sceneEl.camera.el : document.querySelector('[camera]');
                        
                        let distance = 0;
                        if (camera) {
                            const modelPos = new THREE.Vector3();
                            const cameraPos = new THREE.Vector3();
                            
                            modelGroup.object3D.getWorldPosition(modelPos);
                            camera.object3D.getWorldPosition(cameraPos);
                            
                            // 距離を計算（メートル単位）
                            distance = modelPos.distanceTo(cameraPos);
                            console.log('Hit distance from camera:', distance.toFixed(2), 'm');
                        }
                        
                        // スコアを表示（距離を10倍して小数第一位まで）
                        const score = Math.round(distance * 100) / 10; // 小数第一位まで
                        console.log('Score:', score);
                        
                        // 合計スコアに加算
                        window.totalScore += score;
                        console.log('Total Score:', window.totalScore.toFixed(1));
                        
                        // リアルタイムスコア表示を更新
                        const currentScoreText = document.getElementById('currentScore');
                        if (currentScoreText) {
                            currentScoreText.setAttribute('value', `SCORE: ${window.totalScore.toFixed(1)}`);
                        }
                        
                        // スコアテキストをモデルの上に表示
                        const scoreText = document.createElement('a-text');
                        scoreText.setAttribute('value', `${score.toFixed(1)}`);
                        scoreText.setAttribute('position', '0 0.5 0'); // モデルの上0.5m
                        scoreText.setAttribute('align', 'center');
                        scoreText.setAttribute('color', '#FFD700'); // ゴールド色
                        scoreText.setAttribute('width', '6'); // フォントサイズを大きく（4→6）
                        scoreText.setAttribute('font', 'roboto');
                        scoreText.setAttribute('shader', 'msdf');
                        scoreText.setAttribute('anchor', 'center');
                        modelGroup.appendChild(scoreText);
                        
                        // スコアテキストをフェードアウトさせる
                        setTimeout(() => {
                            scoreText.setAttribute('animation__fadeup', {
                                property: 'position',
                                to: '0 1 0', // 0.5m上から1m上に移動
                                dur: 1500,
                                easing: 'easeOutQuad'
                            });
                            scoreText.setAttribute('animation__fadeout', {
                                property: 'material.opacity',
                                from: 1,
                                to: 0,
                                dur: 1500,
                                easing: 'linear'
                            });
                        }, 100);
                        
                        // カメラへの移動を停止
                        const approachComponent = modelGroup.components['approach-camera'];
                        if (approachComponent) {
                            approachComponent.stop();
                            console.log('Stopped approaching camera');
                        }

                        // anime02に切り替え（1.5秒間再生）
                        if (modelEntity) {
                            modelEntity.removeAttribute('animation-mixer'); // 一旦削除
                            setTimeout(() => {
                                modelEntity.setAttribute('animation-mixer', 'clip: anime02; loop: repeat; timeScale: 1');
                                console.log('Playing anime02 for 1.5 seconds');
                            }, 50);
                        }
                        
                        // 1.5秒後にフェードアウト開始（anime03はスキップ）
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
                        }, 1500); // anime02を1.5秒間再生
                    }
                });
            },

            // モデルを再描画する関数
            respawnModel: function(modelId, gltfModelSrc) {
                // ゲームが終了している場合はリスポーンしない
                if (window.gameEnded || !window.gameStarted) {
                    console.log('Game ended, no respawn');
                    return;
                }
                
                console.log('Respawning model:', modelId);
                const sceneEl = document.querySelector('a-scene');
                
                // ランダムな位置を生成（12か所）
                const positions = [
                    { x: -4, y: 0, z: -3, rotation: 45 },
                    { x: -2, y: 0, z: -5, rotation: 30 },
                    { x: 2, y: 0, z: -5, rotation: -30 },
                    { x: 4, y: 0, z: -3, rotation: -45 },
                    { x: -3, y: 0, z: -2, rotation: 45 },
                    { x: 0, y: 0, z: -4, rotation: 0 },
                    { x: 3, y: 0, z: -2, rotation: -45 },
                    // 新規追加の5か所（遠く：9m〜15m）
                    { x: -8, y: 0, z: -12, rotation: 60 },   // 距離: 約14.4m
                    { x: 8, y: 0, z: -12, rotation: -60 },   // 距離: 約14.4m
                    { x: -3, y: 0, z: -15, rotation: 20 },   // 距離: 約15.3m
                    { x: 3, y: 0, z: -15, rotation: -20 },   // 距離: 約15.3m
                    { x: 0, y: 0, z: -10, rotation: 0 }      // 距離: 10m
                ];
                const randomPos = positions[Math.floor(Math.random() * positions.length)];
                
                // 新しいモデルグループを作成
                const newModelGroup = document.createElement('a-entity');
                newModelGroup.setAttribute('id', modelId);
                newModelGroup.setAttribute('position', `${randomPos.x} ${randomPos.y} ${randomPos.z}`);
                newModelGroup.setAttribute('rotation', `0 ${randomPos.rotation} 0`);
                newModelGroup.setAttribute('scale', '0 0 0'); // 最初は見えない状態
                newModelGroup.setAttribute('approach-camera', ''); // カメラに向かって移動
                
                // 3Dモデルエンティティを作成
                const newModelEntity = document.createElement('a-entity');
                newModelEntity.setAttribute('gltf-model', gltfModelSrc);
                newModelEntity.setAttribute('animation-mixer', 'clip: anime01; loop: repeat');
                newModelEntity.setAttribute('enhance-materials', ''); // マテリアル品質向上
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
    <a-scene 
        physics="gravity: -9.8"
        renderer="antialias: true; 
                  colorManagement: true; 
                  sortObjects: true; 
                  physicallyCorrectLights: true; 
                  exposure: 1;
                  toneMapping: ACESFilmic">
        <a-assets>
            <!-- 3Dモデル -->
            <a-asset-item id="model_01" src={{ asset('cg/ishimaru.glb') }}></a-asset-item>
            <a-asset-item id="model_02" src={{ asset('cg/oda.glb') }}></a-asset-item>
            <a-asset-item id="model_03" src={{ asset('cg/ohnomi.glb') }}></a-asset-item>
            
            <!-- 背景画像 -->
            <img id="sky02" src={{ asset('cg/R0010186.JPG') }} crossorigin="anonymous" >
            <!-- <img id="sky02" src={{ asset('cg/IMG_20251012_155122_00_048.jpg') }} crossorigin="anonymous" > -->
        </a-assets>

        <!-- ライティング設定（3Dモデルをきれいに表示） -->
        <a-entity light="type: ambient; color: #DDD; intensity: 1.2"></a-entity>
        <a-entity light="type: directional; color: #FFF; intensity: 1.5" position="1 2 1"></a-entity>
        <a-entity light="type: directional; color: #FFF; intensity: 0.8" position="-1 1 -1"></a-entity>
        <a-entity light="type: directional; color: #FFF; intensity: 0.6" position="0 1 2"></a-entity>

        <!-- マウスカーソル（スタートメニューとゲームオブジェクトをターゲット） -->
        <a-entity id="mouseCursor" cursor="rayOrigin: mouse" raycaster="objects: .clickable, .collidable"></a-entity>

        <!-- Controller -->
        <a-entity id="leftController" laser-controls="hand: left" raycaster="objects: .collidable, .clickable; far: 5" vr-controller></a-entity>
        <a-entity id="rightController" laser-controls="hand: right" raycaster="objects: .collidable, .clickable; far: 5" vr-controller></a-entity>

        <!-- スタートメニュー（半透明） -->
        <a-entity id="startMenu" position="0 1.6 -3" start-menu>
            <!-- 背景パネル（半透明） -->
            <a-plane 
                id="menuBackground"
                position="0 0 0" 
                width="3" 
                height="2" 
                color="#000000" 
                opacity="0.7" 
                material="transparent: true"
                class="clickable">
            </a-plane>
            
            <!-- タイトルテキスト -->
            <a-text 
                value="VR SHOOTING GAME" 
                position="0 0.5 0.01" 
                align="center" 
                color="#FFFFFF" 
                width="2.5"
                font="roboto"
                shader="msdf">
            </a-text>
            
            <!-- スタートボタンの背景 -->
            <a-plane 
                id="startButton"
                position="0 -0.3 0.01" 
                width="1.5" 
                height="0.5" 
                color="#FFD700" 
                opacity="0.9"
                material="transparent: true"
                class="clickable">
            </a-plane>
            
            <!-- スタートボタンテキスト -->
            <a-text 
                value="START" 
                position="0 -0.3 0.02" 
                align="center" 
                color="#000000" 
                width="2.5"
                font="roboto"
                shader="msdf"
                baseline="center">
            </a-text>
        </a-entity>

        <!-- タイマーとスコア表示 -->
        <a-entity id="timerDisplay" position="0 2.0 -3" visible="false">
            <!-- タイマー（左側） -->
            <a-text 
                id="timerText"
                value="TIME: 60s" 
                position="-0.8 0 0" 
                align="center" 
                color="#FFFF00" 
                width="4"
                font="roboto"
                shader="msdf">
            </a-text>
            
            <!-- スコア（右側） -->
            <a-text 
                id="currentScore"
                value="SCORE: 0.0" 
                position="0.8 0 0" 
                align="center" 
                color="#00FF00" 
                width="4"
                font="roboto"
                shader="msdf">
            </a-text>
        </a-entity>

        <!-- リザルト画面（半透明） -->
        <a-entity id="resultMenu" position="0 1.6 -3" visible="false" result-menu>
            <!-- 背景パネル（半透明） -->
            <a-plane 
                position="0 0 0" 
                width="4" 
                height="3.5" 
                color="#000000" 
                opacity="0.8" 
                material="transparent: true">
            </a-plane>
            
            <!-- GAME OVERテキスト -->
            <a-text 
                value="GAME OVER" 
                position="0 1.2 0.01" 
                align="center" 
                color="#FF0000" 
                width="3.5"
                font="roboto"
                shader="msdf">
            </a-text>
            
            <!-- スコア表示 -->
            <a-text 
                id="resultScore"
                value="SCORE: 0.0" 
                position="0 0.5 0.01" 
                align="center" 
                color="#FFD700" 
                width="4"
                font="roboto"
                shader="msdf">
            </a-text>
            
            <!-- コメント表示 -->
            <a-text 
                id="resultComment"
                value="KEEP PRACTICING!" 
                position="0 -0.2 0.01" 
                align="center" 
                color="#FFFFFF" 
                width="3"
                font="roboto"
                shader="msdf">
            </a-text>
            
            <!-- RESTARTボタンの背景 -->
            <a-plane 
                id="restartButton"
                position="0 -1 0.01" 
                width="2" 
                height="0.6" 
                color="#00FF00" 
                opacity="0.9"
                material="transparent: true"
                class="clickable">
            </a-plane>
            
            <!-- RESTARTボタンテキスト -->
            <a-text 
                value="RESTART" 
                position="0 -1 0.02" 
                align="center" 
                color="#000000" 
                width="3"
                font="roboto"
                shader="msdf"
                baseline="center">
            </a-text>
        </a-entity>

        <!-- モデル01グループ（初期非表示） -->
        <a-entity id="modelGroup_01" position="-3 0 -2" rotation="0 45 0" scale="1 1 1" approach-camera visible="false">
            <a-entity gltf-model="#model_01" animation-mixer="clip: anime01; loop: repeat" enhance-materials></a-entity>
            <a-entity id="hit-boxed_01" hit-box position="0 0.5 0">
                <a-entity geometry="primitive: cylinder" material="color: blue; opacity: 0.0; transparent: true" 
                          scale="0.3 1 0.3" class="collidable"></a-entity>
            </a-entity>
        </a-entity>

        <!-- モデル02グループ（初期非表示） -->
        <a-entity id="modelGroup_02" position="0 0 -4" rotation="0 0 0" scale="1 1 1" approach-camera visible="false">
            <a-entity gltf-model="#model_02" animation-mixer="clip: anime01; loop: repeat" enhance-materials></a-entity>
            <a-entity id="hit-boxed_02" hit-box position="0 0.5 0">
                <a-entity geometry="primitive: cylinder" material="color: blue; opacity: 0.0; transparent: true" 
                          scale="0.3 1 0.3" class="collidable"></a-entity>
            </a-entity>
        </a-entity>

        <!-- モデル03グループ（初期非表示） -->
        <a-entity id="modelGroup_03" position="3 0 -2" rotation="0 -45 0" scale="1 1 1" approach-camera visible="false">
            <a-entity gltf-model="#model_03" animation-mixer="clip: anime01; loop: repeat" enhance-materials></a-entity>
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