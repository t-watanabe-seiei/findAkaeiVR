<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
        window.comboCount = 0; // 連続ヒット数
        window.maxComboCount = 0; // 最大連続ヒット数
        window.lastBallHit = false; // 最後のボールがヒットしたかどうか
        
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
        
        // デバッグ表示用のヘルパー関数
        window.updateDebug = function(message) {
            const debugText = document.getElementById('debugText');
            if (debugText) {
                const timestamp = new Date().toLocaleTimeString();
                debugText.setAttribute('value', `${timestamp}: ${message}`);
            }
            console.log('DEBUG:', message);
        };
        
        // スタートメニューコンポーネント
        AFRAME.registerComponent('start-menu', {
            init: function() {
                this.startGame = this.startGame.bind(this);
                this.handleClick = this.handleClick.bind(this);
                this.handleTouch = this.handleTouch.bind(this);
                this.clickBlocked = false; // クリックブロックフラグ
                this.controllersUpdated = false; // コントローラー更新フラグ
                
                // メニュー内のクリック可能な要素のみにイベントを追加（メニュー全体には追加しない）
                const clickableElements = this.el.querySelectorAll('.clickable');
                clickableElements.forEach(element => {
                    element.addEventListener('click', this.handleClick);
                    element.addEventListener('touchstart', this.handleTouch, { passive: false, capture: true }); // captureフェーズで処理
                    console.log('Click and Touch listeners added to:', element.id || element.tagName);
                });
                
                console.log('Start menu initialized with', clickableElements.length, 'clickable elements');
            },
            
            tick: function() {
                // ゲーム中はメニューを完全に非表示・無効化
                if (window.gameStarted && !window.gameEnded) {
                    const isVisible = this.el.getAttribute('visible');
                    
                    // メニューが表示されている場合のみ非表示にする（無限ループ防止）
                    // visible属性はブーリアンまたは文字列で返される可能性があるため厳密にチェック
                    if (isVisible === true || isVisible === 'true') {
                        console.log('WARNING: Menu visible during game! Force hiding...');
                        this.el.setAttribute('visible', false);
                        this.el.setAttribute('scale', '0 0 0');
                    }
                    
                    // マウスカーソルとVRコントローラーのraycasterターゲットから.clickableを除外（初回のみ）
                    if (!this.controllersUpdated) {
                        const mouseCursor = document.getElementById('mouseCursor');
                        const leftController = document.getElementById('leftController');
                        const rightController = document.getElementById('rightController');
                        
                        if (mouseCursor) {
                            mouseCursor.setAttribute('raycaster', 'objects: .collidable');
                            console.log('Removed .clickable from mouse cursor raycaster');
                        }
                        if (leftController) {
                            leftController.setAttribute('raycaster', 'objects: .collidable; far: 5');
                            console.log('Removed .clickable from left controller raycaster');
                        }
                        if (rightController) {
                            rightController.setAttribute('raycaster', 'objects: .collidable; far: 5');
                            console.log('Removed .clickable from right controller raycaster');
                        }
                        
                        this.controllersUpdated = true;
                    }
                    
                    this.clickBlocked = true;
                } else {
                    // ゲーム中でない場合（開始前またはゲーム終了後）は.clickableを復元
                    if (this.controllersUpdated) {
                        const mouseCursor = document.getElementById('mouseCursor');
                        const leftController = document.getElementById('leftController');
                        const rightController = document.getElementById('rightController');
                        
                        if (mouseCursor) {
                            mouseCursor.setAttribute('raycaster', 'objects: .clickable, .collidable');
                            console.log('Restored .clickable to mouse cursor raycaster');
                        }
                        if (leftController) {
                            leftController.setAttribute('raycaster', 'objects: .collidable, .clickable; far: 5');
                            console.log('Restored .clickable to left controller raycaster');
                        }
                        if (rightController) {
                            rightController.setAttribute('raycaster', 'objects: .collidable, .clickable; far: 5');
                            console.log('Restored .clickable to right controller raycaster');
                        }
                        
                        this.controllersUpdated = false;
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
                
                // ゲーム中は完全にブロック（最優先チェック）
                if (window.gameStarted && !window.gameEnded) {
                    console.log('Click BLOCKED - Game in progress');
                    event.stopPropagation();
                    event.preventDefault();
                    return false;
                }
                
                // クリックがブロックされている場合は即座に拒否
                if (this.clickBlocked) {
                    console.log('Click BLOCKED by flag');
                    event.stopPropagation();
                    event.preventDefault();
                    return false;
                }
                
                // メニューが非表示の場合は無視
                const isVisible = this.el.getAttribute('visible');
                if (isVisible === false || isVisible === 'false') {
                    console.log('Click BLOCKED - Menu not visible');
                    event.stopPropagation();
                    event.preventDefault();
                    return false;
                }
                
                // ゲーム終了時は無視
                if (window.gameEnded) {
                    console.log('Click BLOCKED - Game ended');
                    event.stopPropagation();
                    event.preventDefault();
                    return false;
                }
                
                this.startGame(event);
            },
            
            handleTouch: function(event) {
                console.log('=== Menu Touch Detected ===');
                console.log('Menu visible:', this.el.getAttribute('visible'));
                console.log('Game started:', window.gameStarted);
                console.log('Game ended:', window.gameEnded);
                console.log('Click blocked:', this.clickBlocked);
                
                // ゲーム中は完全にブロック（最優先チェック）
                if (window.gameStarted && !window.gameEnded) {
                    console.log('Touch BLOCKED - Game in progress');
                    event.preventDefault();
                    event.stopPropagation();
                    return false;
                }
                
                // クリックがブロックされている場合は即座に拒否
                if (this.clickBlocked) {
                    console.log('Touch BLOCKED by flag');
                    event.preventDefault();
                    event.stopPropagation();
                    return false;
                }
                
                // メニューが非表示の場合は無視
                const isVisible = this.el.getAttribute('visible');
                if (isVisible === false || isVisible === 'false') {
                    console.log('Touch BLOCKED - Menu not visible');
                    event.preventDefault();
                    event.stopPropagation();
                    return false;
                }
                
                // ゲーム終了時は無視
                if (window.gameEnded) {
                    console.log('Touch BLOCKED - Game ended');
                    event.preventDefault();
                    event.stopPropagation();
                    return false;
                }
                
                // 有効なタッチの場合はイベントを停止してゲーム開始
                console.log('Valid touch - starting game!');
                event.preventDefault();
                event.stopPropagation();
                this.startGame(event);
                return true;
            },
            
            startGame: function(event) {
                console.log('Game Start triggered!');
                window.updateDebug('Game Started!');
                
                // クリックブロックを有効化（ゲーム中のメニュークリックを防ぐ）
                this.clickBlocked = true;
                
                // メニューを即座に完全に非表示（visible + scale + raycastable）
                this.el.setAttribute('visible', false);
                this.el.setAttribute('scale', '0 0 0');
                this.el.object3D.visible = false; // THREE.jsレベルでも非表示
                
                // メニュー内のすべてのクリック可能要素のクラスを削除
                const clickableElements = this.el.querySelectorAll('.clickable');
                clickableElements.forEach(element => {
                    element.classList.remove('clickable');
                    element.classList.add('non-clickable'); // 一時的なクラス
                });
                
                console.log('Start menu hidden immediately (visible=false, scale=0, class removed)');
                
                // マウスカーソルとVRコントローラーから.clickableを即座に除外
                const mouseCursor = document.getElementById('mouseCursor');
                const leftController = document.getElementById('leftController');
                const rightController = document.getElementById('rightController');
                
                if (mouseCursor) {
                    mouseCursor.setAttribute('raycaster', 'objects: .collidable');
                    console.log('Removed .clickable from mouse cursor');
                }
                if (leftController) {
                    leftController.setAttribute('raycaster', 'objects: .collidable; far: 5');
                    console.log('Removed .clickable from left controller');
                }
                if (rightController) {
                    rightController.setAttribute('raycaster', 'objects: .collidable; far: 5');
                    console.log('Removed .clickable from right controller');
                }
                this.controllersUpdated = true;
                
                // BGMを再生（音量70%）
                const bgmSound = document.getElementById('sound_bgm');
                if (bgmSound) {
                    bgmSound.volume = 0.7; // 音量を70%に設定
                    bgmSound.currentTime = 0; // 最初から再生
                    bgmSound.play().then(() => {
                        console.log('BGM started playing at 70% volume');
                    }).catch(err => {
                        console.log('BGM play failed:', err);
                    });
                }
                
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
                window.comboCount = 0; // コンボカウントをリセット
                window.maxComboCount = 0; // 最大コンボカウントをリセット
                window.lastBallHit = false; // ヒット状態をリセット
                
                // 初期の3つのモデルのみを表示して移動開始
                const initialModelIds = ['modelGroup_01', 'modelGroup_02', 'modelGroup_03'];
                console.log('Showing initial 3 models');
                initialModelIds.forEach(modelId => {
                    const model = document.getElementById(modelId);
                    if (model) {
                        model.setAttribute('visible', true);
                        console.log('Model visible:', modelId);
                    } else {
                        console.error('Model not found:', modelId);
                    }
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
                const self = this; // thisのコンテキストを保存
                
                console.log('=== Starting timer ===');
                console.log('ResultMenu element:', resultMenu);
                console.log('ResultMenu exists:', resultMenu ? 'YES' : 'NO');
                console.log('Current gameEnded:', window.gameEnded);
                console.log('Current gameStarted:', window.gameStarted);
                
                // 既存のタイマーがあればクリア
                if (window.gameTimer) {
                    console.log('Clearing existing timer before start');
                    clearInterval(window.gameTimer);
                    window.gameTimer = null;
                }
                
                window.gameTimer = setInterval(() => {
                    window.gameTimeLeft--;
                    
                    // タイマー表示を更新
                    if (timerText) {
                        timerText.setAttribute('value', `TIME: ${window.gameTimeLeft}s`);
                    }
                    
                    // 時間切れ
                    if (window.gameTimeLeft <= 0) {
                        console.log('=== Timer reached 0 ===');
                        console.log('gameEnded before set:', window.gameEnded);
                        window.updateDebug('Timer ended!');
                        
                        clearInterval(window.gameTimer);
                        window.gameTimer = null; // タイマーをクリア
                        window.gameEnded = true;
                        window.gameStarted = false;
                        
                        console.log('gameEnded after set:', window.gameEnded);
                        console.log('Total Score:', window.totalScore);
                        
                        // すべてのモデルを非表示
                        const models = document.querySelectorAll('[id^="modelGroup_"]');
                        console.log('Hiding models, count:', models.length);
                        models.forEach(model => {
                            model.setAttribute('visible', false);
                        });
                        
                        // タイマー非表示
                        const timerDisplay = document.getElementById('timerDisplay');
                        if (timerDisplay) {
                            timerDisplay.setAttribute('visible', false);
                        }
                        
                        // リザルト画面を表示（再度取得して確実に存在することを確認）
                        const currentResultMenu = document.getElementById('resultMenu');
                        console.log('=== Looking for result menu ===');
                        console.log('Result menu element:', currentResultMenu);
                        console.log('Result menu exists:', currentResultMenu ? 'YES' : 'NO');
                        
                        if (currentResultMenu) {
                            console.log('Calling showResult...');
                            window.updateDebug('Showing result...');
                            self.showResult(currentResultMenu);
                        } else {
                            console.error('ERROR: Result menu element NOT FOUND!');
                            window.updateDebug('ERROR: Result menu NOT FOUND!');
                            // デバッグ: DOM内のすべての要素を確認
                            const allEntities = document.querySelectorAll('a-entity');
                            console.log('Total a-entity count:', allEntities.length);
                            const menuEntities = document.querySelectorAll('[result-menu]');
                            console.log('Entities with result-menu attribute:', menuEntities.length);
                        }
                    }
                }, 1000);
                
                console.log('Timer started, interval ID:', window.gameTimer);
            },
            
            showResult: function(resultMenu) {
                console.log('=== showResult function called ===');
                window.updateDebug(`Result: Score ${window.totalScore.toFixed(1)}`);
                console.log('resultMenu parameter:', resultMenu);
                console.log('resultMenu is null?', resultMenu === null);
                console.log('resultMenu is undefined?', resultMenu === undefined);
                
                if (!resultMenu) {
                    console.error('ERROR: resultMenu is null or undefined!');
                    return;
                }
                
                console.log('Result menu visible attribute before:', resultMenu.getAttribute('visible'));
                console.log('Result menu position:', resultMenu.getAttribute('position'));
                
                const scoreText = document.getElementById('resultScore');
                const commentText = document.getElementById('resultComment');
                const maxComboText = document.getElementById('maxComboText');
                
                console.log('Score text element:', scoreText ? 'found' : 'NOT FOUND');
                console.log('Comment text element:', commentText ? 'found' : 'NOT FOUND');
                
                console.log('Score text:', scoreText ? 'found' : 'NOT FOUND');
                console.log('Comment text:', commentText ? 'found' : 'NOT FOUND');
                
                // スコアを表示（小数第一位まで）
                if (scoreText) {
                    scoreText.setAttribute('value', `SCORE: ${window.totalScore.toFixed(1)}`);
                    console.log('Score updated:', window.totalScore.toFixed(1));
                }
                
                // 最大コンボ数を表示
                if (maxComboText) {
                    maxComboText.setAttribute('value', `MAX COMBO: ${window.maxComboCount}`);
                    console.log('Max Combo updated:', window.maxComboCount);
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
                    console.log('Comment updated:', comment);
                }
                
                // スタートメニューを確実に非表示
                const startMenu = document.getElementById('startMenu');
                if (startMenu) {
                    startMenu.setAttribute('visible', false);
                    console.log('Start menu hidden in showResult');
                }
                
                // リザルトメニューを表示
                console.log('Setting result menu visible and animating...');
                resultMenu.setAttribute('visible', true);
                resultMenu.setAttribute('scale', '0 0 0');
                resultMenu.setAttribute('animation', {
                    property: 'scale',
                    to: '1 1 1',
                    dur: 500,
                    easing: 'easeOutBack'
                });
                console.log('Result menu should be visible now');
                
                // スコアをデータベースに保存
                this.saveScoreToDatabase(window.totalScore);
            },
            
            saveScoreToDatabase: function(score) {
                console.log('Saving score to database:', score);
                
                fetch('/api/shooting-scores', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({
                        name: 'noName', // デフォルト名
                        score: score,
                    })
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Score saved successfully:', data);
                    window.updateDebug('Score saved to DB');
                    // スコア保存後にランキングを取得
                    this.fetchAndDisplayRankings();
                })
                .catch(error => {
                    console.error('Error saving score:', error);
                    window.updateDebug('Score save failed');
                });
            },
            
            fetchAndDisplayRankings: function() {
                console.log('Fetching top 5 rankings...');
                
                fetch('/api/shooting-scores/top5')
                    .then(response => response.json())
                    .then(data => {
                        console.log('Rankings fetched:', data);
                        if (data.success && data.data) {
                            this.displayRankings(data.data);
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching rankings:', error);
                    });
            },
            
            displayRankings: function(rankings) {
                const rankingDisplay = document.querySelector('#rankingDisplay');
                if (!rankingDisplay) {
                    console.error('Ranking display element not found');
                    return;
                }
                
                // 既存のランキング表示をクリア
                while (rankingDisplay.firstChild) {
                    rankingDisplay.removeChild(rankingDisplay.firstChild);
                }
                
                // 現在のプレイヤーのスコア
                const currentScore = window.totalScore;
                
                // ランキングを表示（上から順に5位まで）
                rankings.forEach((item, index) => {
                    const rank = index + 1;
                    const yPosition = 0.1 - (index * 0.25); // 0.25間隔で配置
                    
                    // 日付をフォーマット
                    const date = new Date(item.created_at);
                    const dateStr = `${date.getMonth() + 1}/${date.getDate()} ${date.getHours()}:${String(date.getMinutes()).padStart(2, '0')}`;
                    
                    // ランキング行のテキスト
                    const rankingText = `${rank}. ${item.score.toFixed(1)}pt  ${item.name}  ${dateStr}`;
                    
                    // 自分のスコアかどうかを判定（スコアが一致し、名前が一致する場合）
                    const isCurrentPlayer = (Math.abs(item.score - currentScore) < 0.01) && (item.name === 'noName');
                    
                    // 色を決定
                    let textColor = '#FFFFFF'; // デフォルトは白
                    if (isCurrentPlayer) {
                        textColor = '#FF1493'; // 自分のスコアはピンク（DeepPink）
                    }
                    // 1位も白色で表示
                    
                    // A-Frameテキストエンティティを作成
                    const textEntity = document.createElement('a-text');
                    textEntity.setAttribute('value', rankingText);
                    textEntity.setAttribute('position', `0 ${yPosition} 0`);
                    textEntity.setAttribute('align', 'center');
                    textEntity.setAttribute('color', textColor);
                    textEntity.setAttribute('width', '4.5');
                    textEntity.setAttribute('font', 'roboto');
                    textEntity.setAttribute('shader', 'msdf');
                    
                    rankingDisplay.appendChild(textEntity);
                });
                
                console.log('Rankings displayed:', rankings.length, 'entries');
            },
            
            restartGame: function() {
                console.log('=== Restarting game - Full reload ===');
                window.updateDebug('Restarting...');
                
                // ページを完全にリロードして初期状態に戻す
                // これにより、すべての状態がクリーンにリセットされる
                location.reload();
            }
        });
        
        // リザルト画面コンポーネント
        AFRAME.registerComponent('result-menu', {
            init: function() {
                this.restart = this.restart.bind(this);
                this.restartTouch = this.restartTouch.bind(this);
                
                // RESTARTボタンにクリックイベントを追加
                const restartButton = this.el.querySelector('#restartButton');
                if (restartButton) {
                    restartButton.addEventListener('click', this.restart);
                    restartButton.addEventListener('touchstart', this.restartTouch); // スマホ対応
                    console.log('Restart button click and touch listeners added');
                }
            },
            
            restart: function(event) {
                console.log('Restart button clicked');
                
                // スタートメニューコンポーネントのrestartGame関数を呼び出す
                const startMenu = document.getElementById('startMenu');
                if (startMenu && startMenu.components['start-menu']) {
                    startMenu.components['start-menu'].restartGame();
                }
            },
            
            restartTouch: function(event) {
                console.log('Restart button touched');
                event.preventDefault();
                event.stopPropagation();
                
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
                    
                    // ヒットしなかった場合、コンボをリセット
                    if (!ballData.hasHit) {
                        console.log('Ball missed - Resetting combo');
                        window.comboCount = 0;
                        window.lastBallHit = false;
                        // コンボ表示を非表示
                        const comboDisplay = document.getElementById('comboDisplay');
                        if (comboDisplay) {
                            comboDisplay.setAttribute('visible', false);
                        }
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
                    canvas.removeEventListener('click', this.onClick, true);
                    canvas.removeEventListener('touchstart', this.onTouchStart, true);
                    
                    // 通常のバブリングフェーズで捕捉（captureを使わない）
                    canvas.addEventListener('click', this.onClick);
                    canvas.addEventListener('touchstart', this.onTouchStart, { passive: false });
                    console.log('Canvas listeners set up (bubble phase)');
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
                        
                        // ヒット音を再生
                        const hitSound = document.getElementById('sound_hit');
                        if (hitSound) {
                            hitSound.currentTime = 0; // 最初から再生
                            hitSound.play().then(() => {
                                console.log('Hit sound played');
                            }).catch(err => {
                                console.log('Hit sound play failed:', err);
                            });
                        }
                        
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
                        
                        // 基本スコアを計算（距離を10倍して小数第一位まで）
                        let baseScore = Math.round(distance * 100) / 10; // 小数第一位まで
                        
                        // コンボカウントを増やす（スコア計算前に）
                        window.comboCount++;
                        window.lastBallHit = true;
                        console.log('Combo Count:', window.comboCount);
                        
                        // コンボ倍率を計算
                        let comboMultiplier = 1.0;
                        let comboBonus = '';
                        let bonusTier = 0; // ボーナスレベル（0=なし, 1=1.1x, 2=1.2x, 3=1.3x）
                        if (window.comboCount >= 6) {
                            comboMultiplier = 1.3;
                            comboBonus = 'x1.3';
                            bonusTier = 3;
                        } else if (window.comboCount >= 4) {
                            comboMultiplier = 1.2;
                            comboBonus = 'x1.2';
                            bonusTier = 2;
                        } else if (window.comboCount >= 2) {
                            comboMultiplier = 1.1;
                            comboBonus = 'x1.1';
                            bonusTier = 1;
                        }
                        
                        // 最終スコアを計算
                        const finalScore = baseScore * comboMultiplier;
                        console.log('Base Score:', baseScore, 'Multiplier:', comboMultiplier, 'Final Score:', finalScore);
                        
                        // 合計スコアに加算
                        window.totalScore += finalScore;
                        console.log('Total Score:', window.totalScore.toFixed(1));
                        
                        // 最大コンボ数を更新
                        if (window.comboCount > window.maxComboCount) {
                            window.maxComboCount = window.comboCount;
                            console.log('New Max Combo:', window.maxComboCount);
                        }
                        
                        // コンボ表示を更新（2連続以上の場合）
                        if (window.comboCount >= 2) {
                            this.showCombo(window.comboCount, modelGroup);
                        }
                        
                        // リアルタイムスコア表示を更新
                        const currentScoreText = document.getElementById('currentScore');
                        if (currentScoreText) {
                            currentScoreText.setAttribute('value', `SCORE: ${window.totalScore.toFixed(1)}`);
                        }
                        
                        // スコアテキストをモデルの上に表示
                        const scoreText = document.createElement('a-text');
                        const scoreDisplay = comboBonus ? `${finalScore.toFixed(1)} (${comboBonus})` : `${finalScore.toFixed(1)}`;
                        scoreText.setAttribute('value', scoreDisplay);
                        scoreText.setAttribute('position', '0 0.5 0'); // モデルの上0.5m
                        scoreText.setAttribute('align', 'center');
                        scoreText.setAttribute('color', comboBonus ? '#FF6600' : '#FFD700'); // ボーナス時はオレンジ、通常は金色
                        scoreText.setAttribute('width', comboBonus ? '6.6' : '6'); // ボーナス時は1.1倍大きく（6→6.6）
                        scoreText.setAttribute('font', comboBonus ? 'mozillavr' : 'roboto'); // ボーナス時はフォント変更
                        scoreText.setAttribute('shader', 'msdf');
                        scoreText.setAttribute('anchor', 'center');
                        modelGroup.appendChild(scoreText);
                        
                        // ボーナス時のエフェクト
                        if (comboBonus) {
                            // ボーナスレベルに応じて使用するパーティクルを選択
                            let particleId = 'particle-tier1'; // デフォルト
                            
                            if (bonusTier === 1) {
                                // 1.1倍: Tier1パーティクル（シアン、サイズ0.1、20個）
                                particleId = 'particle-tier1';
                            } else if (bonusTier === 2) {
                                // 1.2倍: Tier2パーティクル（オレンジ、サイズ0.15、30個）
                                particleId = 'particle-tier2';
                            } else if (bonusTier === 3) {
                                // 1.3倍: Tier3パーティクル（マゼンタ、サイズ0.2、40個）
                                particleId = 'particle-tier3';
                            }
                            
                            // パーティクルエフェクトを表示
                            const particle = document.getElementById(particleId);
                            if (particle) {
                                const modelPos = new THREE.Vector3();
                                modelGroup.object3D.getWorldPosition(modelPos);
                                particle.setAttribute('position', `${modelPos.x} ${modelPos.y + 0.5} ${modelPos.z}`);
                                particle.setAttribute('visible', true);
                                
                                // 1.5秒後に非表示
                                setTimeout(() => {
                                    particle.setAttribute('visible', false);
                                }, 1500);
                            }
                            
                            // スコアテキストを拡大縮小アニメーション（ボーナスレベルに応じて拡大率を変更）
                            const scaleMultiplier = 1.1 + (bonusTier * 0.2); // 1.3, 1.5, 1.7
                            scoreText.setAttribute('scale', `${scaleMultiplier} ${scaleMultiplier} ${scaleMultiplier}`);
                            scoreText.setAttribute('animation__scale', {
                                property: 'scale',
                                to: '1 1 1',
                                dur: 400,
                                easing: 'easeOutElastic'
                            });
                            
                            // 追加エフェクト: スコアテキストを回転
                            scoreText.setAttribute('animation__rotate', {
                                property: 'rotation',
                                from: '0 0 -15',
                                to: '0 0 15',
                                dur: 400,
                                easing: 'easeInOutSine',
                                loop: 2,
                                dir: 'alternate'
                            });
                        }
                        
                        // スコアテキストをフェードアウトさせる
                        setTimeout(() => {
                            scoreText.setAttribute('animation__scoreup', {
                                property: 'position',
                                to: '0 1 0', // 0.5m上から1m上に移動
                                dur: 1500,
                                easing: 'easeOutQuad'
                            });
                            scoreText.setAttribute('animation__scorefade', {
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
                                console.log('Starting fadeout for modelGroup');
                                // フェードアウトアニメーション（0.5秒かけて縮小）
                                modelGroup.setAttribute('animation__modelfadeout', {
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
            
            showCombo: function(comboCount, modelGroup) {
                // モデルの上にコンボテキストを表示
                if (modelGroup) {
                    // 既存のコンボテキストを削除
                    const existingCombo = modelGroup.querySelector('.combo-text');
                    if (existingCombo) {
                        modelGroup.removeChild(existingCombo);
                    }
                    
                    // コンボテキストを作成
                    const comboText = document.createElement('a-text');
                    comboText.setAttribute('value', `Combo ${comboCount}!`);
                    comboText.setAttribute('position', '0 1.0 0'); // スコアの上（スコアは0.5なので1.0）
                    comboText.setAttribute('align', 'center');
                    comboText.setAttribute('color', '#FF6600'); // オレンジ色
                    comboText.setAttribute('width', '6');
                    comboText.setAttribute('font', 'roboto');
                    comboText.setAttribute('shader', 'msdf');
                    comboText.setAttribute('anchor', 'center');
                    comboText.classList.add('combo-text');
                    modelGroup.appendChild(comboText);
                    
                    // コンボテキストをフェードアウトさせる
                    setTimeout(() => {
                        comboText.setAttribute('animation__fadeup', {
                            property: 'position',
                            to: '0 1.5 0', // 1.0m上から1.5m上に移動
                            dur: 1500,
                            easing: 'easeOutQuad'
                        });
                        comboText.setAttribute('animation__fadeout', {
                            property: 'material.opacity',
                            from: 1,
                            to: 0,
                            dur: 1500,
                            easing: 'linear'
                        });
                        
                        // アニメーション後に削除
                        setTimeout(() => {
                            if (comboText.parentNode) {
                                comboText.parentNode.removeChild(comboText);
                            }
                        }, 1500);
                    }, 100);
                    
                    console.log('Combo displayed on model:', comboCount);
                }
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
                    // { x: -2, y: 0, z: -5, rotation: 30 },
                    // { x: 2, y: 0, z: -5, rotation: -30 },
                    // { x: 4, y: 0, z: -3, rotation: -45 },
                    // { x: -3, y: 0, z: -2, rotation: 45 },
                    // { x: 0, y: 0, z: -4, rotation: 0 },
                    // { x: 3, y: 0, z: -2, rotation: -45 },
                    // 新規追加の5か所（遠く：9m〜15m）
                    // { x: -8, y: 0, z: -12, rotation: 60 },   // 距離: 約14.4m
                    // { x: 8, y: 0, z: -12, rotation: -60 },   // 距離: 約14.4m
                    // { x: -3, y: 0, z: -15, rotation: 20 },   // 距離: 約15.3m
                    // { x: 3, y: 0, z: -15, rotation: -20 },   // 距離: 約15.3m
                    // { x: 0, y: 0, z: -10, rotation: 0 }      // 距離: 10m
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
                newHitBox.setAttribute('position', '0 0.3 0'); // HTMLと同じ位置に修正
                
                const hitBoxCylinder = document.createElement('a-entity');
                hitBoxCylinder.setAttribute('geometry', 'primitive: cylinder');
                hitBoxCylinder.setAttribute('material', 'color: blue; opacity: 0.0; transparent: true');
                hitBoxCylinder.setAttribute('scale', '0.3 0.4 0.3'); // HTMLと同じスケールに修正
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




        // 自動VRモード切り替えコンポーネント
        AFRAME.registerComponent('auto-enter-vr', {
            init: function () {
                const sceneEl = this.el;
                
                // シーンが読み込まれたら実行
                sceneEl.addEventListener('loaded', () => {
                    console.log('Scene loaded, checking for VR device...');
                    window.updateDebug('Checking VR device...');
                    
                    // VRデバイスが利用可能かチェック
                    if (navigator.xr) {
                        navigator.xr.isSessionSupported('immersive-vr').then((supported) => {
                            if (supported) {
                                console.log('VR device detected! Auto-entering VR mode...');
                                window.updateDebug('VR device found! Entering VR...');
                                
                                // 少し待ってからVRモードに入る（アセット読み込み完了を待つ）
                                setTimeout(() => {
                                    sceneEl.enterVR();
                                    console.log('Entered VR mode automatically');
                                    window.updateDebug('VR mode activated');
                                }, 1000);
                            } else {
                                console.log('VR not supported on this device');
                                window.updateDebug('VR not supported');
                            }
                        }).catch((err) => {
                            console.log('Error checking VR support:', err);
                            window.updateDebug('VR check failed');
                        });
                    } else {
                        console.log('WebXR not available');
                        window.updateDebug('WebXR not available');
                    }
                });
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
                  toneMapping: ACESFilmic"
        vr-mode-ui="enabled: true"
        auto-enter-vr>
        <a-assets>
            <!-- 3Dモデル -->
            <a-asset-item id="model_01" src={{ asset('cg/ishimaru.glb') }}></a-asset-item>
            <a-asset-item id="model_02" src={{ asset('cg/oda.glb') }}></a-asset-item>
            <a-asset-item id="model_03" src={{ asset('cg/ohnomi.glb') }}></a-asset-item>
            
            <!-- サウンド -->
            <audio id="sound_hit" src={{ asset('cg/sound_hit01.mp3') }} preload="auto"></audio>
            <audio id="sound_bgm" src={{ asset('cg/sound_bgm01.mp3') }} preload="auto"></audio>
            
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

        <!-- デバッグ情報表示（VRゴーグル用） -->
        <a-entity id="debugDisplay" position="0 2.75 -3" visible="true">
            <a-text 
                id="debugText"
                value="DEBUG: Ready" 
                position="0 0 0" 
                align="center" 
                color="#FF00FF" 
                width="2.0"
                font="roboto"
                shader="msdf">
            </a-text>
        </a-entity>

        <!-- リザルト画面（半透明） -->
        <a-entity id="resultMenu" position="0 1.6 -3" visible="false" result-menu>
            <!-- 背景パネル（半透明・拡大） -->
            <a-plane 
                position="0 0 0" 
                width="6" 
                height="5" 
                color="#000000" 
                opacity="0.8" 
                material="transparent: true">
            </a-plane>
            
            <!-- GAME OVERテキスト -->
            <a-text 
                value="GAME OVER" 
                position="0 2.0 0.01" 
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
                position="0 1.4 0.01" 
                align="center" 
                color="#FFD700" 
                width="4"
                font="roboto"
                shader="msdf">
            </a-text>
            
            <!-- 最大コンボ表示（スコアのすぐ下） -->
            <a-text 
                id="maxComboText"
                value="MAX COMBO: 0" 
                position="0 1.0 0.01" 
                align="center" 
                color="#FF6600" 
                width="3"
                font="roboto"
                shader="msdf">
            </a-text>
            
            <!-- コメント表示（コンボの下） -->
            <a-text 
                id="resultComment"
                value="KEEP PRACTICING!" 
                position="0 0.6 0.01" 
                align="center" 
                color="#FFFFFF" 
                width="3"
                font="roboto"
                shader="msdf">
            </a-text>
            
            <!-- ランキングタイトル（0.2上げる：-0.1→0.1） -->
            <a-text 
                value="TOP 5 RANKING" 
                position="0 0.1 0.01" 
                align="center" 
                color="#FFD700" 
                width="3"
                font="roboto"
                shader="msdf">
            </a-text>
            
            <!-- ランキング表示エリア（0.2上げる：-0.5→-0.3） -->
            <a-entity id="rankingDisplay" position="0 -0.3 0.01">
                <!-- JavaScriptで動的に生成 -->
            </a-entity>
            
            <!-- RESTARTボタンの背景 -->
            <a-plane 
                id="restartButton"
                position="0 -2.0 0.01" 
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
                position="0 -2.0 0.02" 
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
            <a-entity id="hit-boxed_01" hit-box position="0 0.3 0">
                <a-entity geometry="primitive: cylinder" material="color: blue; opacity: 0.0; transparent: true" 
                          scale="0.3 0.4 0.3" class="collidable"></a-entity>
            </a-entity>
        </a-entity>

        <!-- モデル02グループ（初期非表示） -->
        <a-entity id="modelGroup_02" position="0 0 -4" rotation="0 0 0" scale="1 1 1" approach-camera visible="false">
            <a-entity gltf-model="#model_02" animation-mixer="clip: anime01; loop: repeat" enhance-materials></a-entity>
            <a-entity id="hit-boxed_02" hit-box position="0 0.3 0">
                <a-entity geometry="primitive: cylinder" material="color: blue; opacity: 0.0; transparent: true" 
                          scale="0.3 0.4 0.3" class="collidable"></a-entity>
            </a-entity>
        </a-entity>

        <!-- モデル03グループ（初期非表示） -->
        <a-entity id="modelGroup_03" position="3 0 -2" rotation="0 -45 0" scale="1 1 1" approach-camera visible="false">
            <a-entity gltf-model="#model_03" animation-mixer="clip: anime01; loop: repeat" enhance-materials></a-entity>
            <a-entity id="hit-boxed_03" hit-box position="0 0.3 0">
                <a-entity geometry="primitive: cylinder" material="color: blue; opacity: 0.0; transparent: true" 
                          scale="0.3 0.4 0.3" class="collidable"></a-entity>
            </a-entity>
        </a-entity>

        <!-- 360度画像を表示 -->
        <a-sky id="aSky" src="#sky02"></a-sky>

        <!-- Particle Effects - 3 Tiers -->
        <!-- Tier 1: 1.1x (2-3 combo) - Cyan, size 0.1, 20 particles -->
        <a-entity id="particle-tier1" visible="false" position="0 3 0" 
                  particle-system="preset: default; color: #00FFFF; particleCount: 20; size: 0.1; maxAge: 1.5; velocityValue: 2 2 2; velocitySpread: 3 3 3; accelerationValue: 0 -2 0; accelerationSpread: 1 1 1"></a-entity>
        
        <!-- Tier 2: 1.2x (4-5 combo) - Orange, size 0.15, 30 particles -->
        <a-entity id="particle-tier2" visible="false" position="0 3 0" 
                  particle-system="preset: default; color: #FF6600; particleCount: 30; size: 0.15; maxAge: 1.5; velocityValue: 2 2 2; velocitySpread: 3 3 3; accelerationValue: 0 -2 0; accelerationSpread: 1 1 1"></a-entity>
        
        <!-- Tier 3: 1.3x (6+ combo) - Magenta, size 0.2, 40 particles -->
        <a-entity id="particle-tier3" visible="false" position="0 3 0" 
                  particle-system="preset: default; color: #FF00FF; particleCount: 40; size: 0.2; maxAge: 1.5; velocityValue: 2 2 2; velocitySpread: 3 3 3; accelerationValue: 0 -2 0; accelerationSpread: 1 1 1"></a-entity>
        

        <a-camera id="my_camera" shoot>
        </a-camera>
    </a-scene>
</body>

</html>