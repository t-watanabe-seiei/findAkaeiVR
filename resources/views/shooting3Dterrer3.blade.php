<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>seieiVR Terrer</title>
    <script src="{{ asset('js/aframe.min.js') }}"></script>
    <script src="{{ asset('js/aframe-particle-system-component.min.js') }}"></script>
    <script src="{{ asset('js/aframe-extras.min.js') }}"></script>
    <script src="{{ asset('js/aframe-physics-system.min.js') }}"></script>
    <script src="{{ asset('js/axios.min.js') }}"></script>

    <script>  
        // 🚀🚀 パフォーマンス改善: デバッグモードの制御
        // 本番環境では false に設定してconsole.logを無効化
        window.DEBUG_MODE = false;
        
        // デバッグログ関数（DEBUG_MODE が true の時のみ出力）
        window.debugLog = function(...args) {
            if (window.DEBUG_MODE) {
                console.log(...args);
            }
        };
        
        if (window.DEBUG_MODE) {
            window.debugLog('========================================');
            window.debugLog('🚀 SCRIPT EXECUTION STARTED!');
            window.debugLog('AFRAME object exists:', typeof AFRAME !== 'undefined');
            window.debugLog('========================================');
        }
        
        // ゲーム状態管理
        window.gameStarted = false;
        window.gameEnded = false;
        window.totalScore = 0;
        window.gameTimer = null;
        window.gameTimeLeft = 75; // 75秒
        window.comboCount = 0; // 連続ヒット数
        window.maxComboCount = 0; // 最大連続ヒット数
        window.enemiesDefeated = 0; // 倒したゾンビの数
        window.lastBallHit = false; // 最後のボールがヒットしたかどうか
        window.gameLevel = 1; // ゲームレベル選択用（1 or 2）
        window.currentLevel = 1; // 現在プレイ中のレベル（1 or 2）
        
        // 🚀 メモリリーク対策: プレイ回数管理（2回で自動終了）
        window.playCount = 0;
        window.MAX_PLAY_COUNT = 2; // 最大プレイ回数
        
        // 🚀 パフォーマンス改善: THREE.js オブジェクトの事前キャッシュ
        window.cachedBallEmissiveColor = null; // THREE.Color は scene loaded 後に初期化
        window.cachedHitEmissiveColor = null; // ヒット時の白色
        window.cachedFlashColors = null; // フラッシュカラー（scene loaded後に初期化）
        window.cachedEnhanceMaterialColor = null; // enhance-materials用
        
        // GLBモデルの品質を向上させるコンポーネント
        AFRAME.registerComponent('enhance-materials', {
            init: function () {
                this.el.addEventListener('model-loaded', () => {
                    const mesh = this.el.getObject3D('mesh');
                    if (mesh) {
                        // fukuda.glbかどうかを判定（親のIDまたはgltf-model属性で判別）
                        const modelSrc = this.el.getAttribute('gltf-model');
                        const isMattsun = modelSrc && modelSrc.includes('fukuda.glb');
                        
                        mesh.traverse((node) => {
                            if (node.isMesh && node.material) {
                                // マテリアルの品質設定
                                if (node.material.map) {
                                    node.material.map.anisotropy = 16; // テクスチャのアニソトロピックフィルタリング
                                }
                                
                                // fukuda.glbの場合は明るさを増加
                                if (isMattsun) {
                                    // エミッシブカラーを追加して明るくする（🚀 キャッシュカラー使用）
                                    if (!node.material.emissive) {
                                        node.material.emissive = window.cachedEnhanceMaterialColor || new THREE.Color(0x444444);
                                    } else {
                                        node.material.emissive.multiplyScalar(1.5); // 既存のエミッシブを1.5倍
                                    }
                                    node.material.emissiveIntensity = 1.5; // エミッシブ強度
                                    
                                    // カラーも少し明るくする
                                    if (node.material.color) {
                                        node.material.color.multiplyScalar(1.3);
                                    }
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
                        window.debugLog('Model materials enhanced' + (isMattsun ? ' (mattsun2: brightness increased)' : ''));
                    }
                });
            }
        });

        // テキストを常にカメラの方向へ向けるコンポーネント（Y軸のみ回転: ビルボード）
        // 🚀 最適化: Vector3を事前生成、tickをスロットリング
        AFRAME.registerComponent('face-camera', {
            init: function() {
                this.cameraEl = null;
                // 🚀 最適化: Vector3を事前生成（毎フレームのnew回避）
                this._cameraPos = new THREE.Vector3();
                this._textPos = new THREE.Vector3();
                this._lastUpdate = 0;
                this._updateInterval = 50; // 50ms間隔（20fps）で更新
            },
            tick: function (time) {
                // 🚀 最適化: スロットリング（毎フレーム実行を回避）
                if (time - this._lastUpdate < this._updateInterval) return;
                this._lastUpdate = time;
                
                // カメラ要素をキャッシュ
                if (!this.cameraEl) {
                    const sceneEl = this.el.sceneEl;
                    if (sceneEl && sceneEl.camera) {
                        this.cameraEl = sceneEl.camera.el;
                    }
                    if (!this.cameraEl) return;
                }

                // 🚀 最適化: 事前生成したVector3を再利用
                this.cameraEl.object3D.getWorldPosition(this._cameraPos);
                this.el.object3D.getWorldPosition(this._textPos);

                // カメラ方向を向く（lookAt使用）
                this.el.object3D.lookAt(this._cameraPos);
                
                // X軸とZ軸の回転をリセット（Y軸のみ保持）
                const currentRotation = this.el.object3D.rotation;
                this.el.object3D.rotation.set(0, currentRotation.y, 0);
            }
        });
        
        // ボール管理用のグローバル配列
        window.activeBalls = [];
        
        // タイマーID管理用のグローバル配列（メモリリーク防止）
        window.activeTimers = [];
        
        // 🚀 最適化: THREE.Vector3のグローバルキャッシュ（hit-box用）
        window._cachedModelPos = new THREE.Vector3();
        window._cachedCameraPos = new THREE.Vector3();
        window._cachedScorePos = new THREE.Vector3();
        window._cachedParticlePos = new THREE.Vector3();
        
        // タイマー登録用のヘルパー関数
        window.registerTimeout = function(callback, delay) {
            const timerId = setTimeout(callback, delay);
            window.activeTimers.push(timerId);
            return timerId;
        };
        
        // アラート音の管理用グローバル変数
        window.alertSoundPlaying = false;
        window.currentAlertModel = null; // 現在アラート音を鳴らしているモデル
        
        // パターン使用状況の管理（重複スポーン防止）
        window.usedPatterns = {}; // { modelId: patternIndex } の形式で保存
        
        // 使用可能なパターンを取得する関数（他のモデルが使用中のパターンを除外）
        window.getAvailablePattern = function(patterns, modelId) {
            // 現在使用中のパターンインデックスを取得
            const usedIndices = Object.keys(window.usedPatterns)
                .filter(id => id !== modelId) // 自分自身は除外
                .map(id => window.usedPatterns[id]);
            
            // 使用可能なパターンをフィルタリング
            const availableIndices = [];
            for (let i = 0; i < patterns.length; i++) {
                if (!usedIndices.includes(i)) {
                    availableIndices.push(i);
                }
            }
            
            // 使用可能なパターンがない場合は全パターンから選択（安全策）
            if (availableIndices.length === 0) {
                window.debugLog('⚠️ All patterns in use, selecting random pattern');
                const randomIndex = Math.floor(Math.random() * patterns.length);
                window.usedPatterns[modelId] = randomIndex;
                return { pattern: patterns[randomIndex], index: randomIndex };
            }
            
            // 使用可能なパターンからランダムに選択
            const selectedIndex = availableIndices[Math.floor(Math.random() * availableIndices.length)];
            window.usedPatterns[modelId] = selectedIndex;
            
            window.debugLog(`📍 Model ${modelId}: Selected pattern ${selectedIndex}, Available: [${availableIndices.join(', ')}], Used by others: [${usedIndices.join(', ')}]`);
            
            return { pattern: patterns[selectedIndex], index: selectedIndex };
        };
        
        // デバッグ表示用のヘルパー関数
        window.updateDebug = function(message) {
            const debugText = document.getElementById('debugText');
            if (debugText) {
                const timestamp = new Date().toLocaleTimeString();
                debugText.setAttribute('value', `${timestamp}: ${message}`);
            }
            window.debugLog('DEBUG:', message);
        };
        
        // スタートメニューコンポーネント
        if (window.DEBUG_MODE) {
            window.debugLog('========================================');
            window.debugLog('🔧 About to register start-menu component');
            window.debugLog('AFRAME:', typeof AFRAME);
            window.debugLog('AFRAME.registerComponent:', typeof AFRAME?.registerComponent);
            window.debugLog('========================================');
        }
        
        AFRAME.registerComponent('start-menu', {
            init: function() {
                window.debugLog('🎮🎮🎮 START-MENU INIT CALLED! 🎮🎮🎮');
                window.debugLog('Element:', this.el);
                window.debugLog('Element ID:', this.el.id);
                
                this.startGame = this.startGame.bind(this);
                this.selectLevel = this.selectLevel.bind(this);
                this.handleClick = this.handleClick.bind(this);
                this.handleTouch = this.handleTouch.bind(this);
                this.clickBlocked = false; // クリックブロックフラグ
                this.controllersUpdated = false; // コントローラー更新フラグ
                
                window.debugLog('🎮 Start menu init - Setting up event listeners');
                
                // 🔧 修正: イベントハンドラをプロパティとして保存（関数参照を再利用）
                if (!this.level1Handler) {
                    this.level1Handler = (e) => {
                        window.debugLog('🎯 Level 1 button clicked!');
                        if (e) e.preventDefault();
                        this.selectLevel(1);
                    };
                }
                if (!this.level2Handler) {
                    this.level2Handler = (e) => {
                        window.debugLog('🎯 Level 2 button clicked!');
                        if (e) e.preventDefault();
                        this.selectLevel(2);
                    };
                }
                
                // レベル選択ボタンのイベントリスナーを追加
                const level1Button = document.getElementById('level1Button');
                const level2Button = document.getElementById('level2Button');
                
                window.debugLog('Level buttons:', {
                    level1: level1Button ? 'found' : 'NOT FOUND',
                    level2: level2Button ? 'found' : 'NOT FOUND'
                });
                
                if (level1Button) {
                    // 既存のリスナーを削除してから追加（重複防止）
                    level1Button.removeEventListener('click', this.level1Handler);
                    level1Button.removeEventListener('touchstart', this.level1Handler);
                    level1Button.addEventListener('click', this.level1Handler);
                    level1Button.addEventListener('touchstart', this.level1Handler, { passive: false });
                    window.debugLog('✅ Level 1 button listeners attached');
                } else {
                    window.debugLog('❌ Level 1 button NOT FOUND in DOM!');
                }
                
                if (level2Button) {
                    // 既存のリスナーを削除してから追加（重複防止）
                    level2Button.removeEventListener('click', this.level2Handler);
                    level2Button.removeEventListener('touchstart', this.level2Handler);
                    level2Button.addEventListener('click', this.level2Handler);
                    level2Button.addEventListener('touchstart', this.level2Handler, { passive: false });
                    window.debugLog('✅ Level 2 button listeners attached');
                } else {
                    window.debugLog('❌ Level 2 button NOT FOUND in DOM!');
                }
                
                // メニュー内のクリック可能な要素のみにイベントを追加（メニュー全体には追加しない）
                const clickableElements = this.el.querySelectorAll('.clickable');
                clickableElements.forEach(element => {
                    element.addEventListener('click', this.handleClick);
                    element.addEventListener('touchstart', this.handleTouch, { passive: false, capture: true }); // captureフェーズで処理
                    window.debugLog('Click and Touch listeners added to:', element.id || element.tagName);
                });
                
                window.debugLog('🎮 Start menu initialized with', clickableElements.length, 'clickable elements');
            },
            
            selectLevel: function(level) {
                window.debugLog('🎯 ========================================');
                window.debugLog('🎯 selectLevel called with level:', level);
                window.debugLog('🎯 Current gameStarted:', window.gameStarted);
                window.debugLog('🎯 Current gameEnded:', window.gameEnded);
                window.debugLog('🎯 ========================================');
                
                window.gameLevel = level;
                window.currentLevel = level;
                
                // レベルを保存してゲーム開始
                this.startGame({ type: 'level-select' });
            },
            
            tick: function(time) {
                // 🚀 最適化: スロットリング（100ms間隔で状態チェック）
                if (!this._lastTickTime) this._lastTickTime = 0;
                if (time - this._lastTickTime < 100) return;
                this._lastTickTime = time;
                
                // 🚀 改善: 状態フラグで制御（毎フレームのDOM操作を削減）
                if (window.gameStarted && !window.gameEnded) {
                    // 初回のみ実行
                    if (!this.menuHidden) {
                        this.el.setAttribute('visible', false);
                        this.el.setAttribute('scale', '0 0 0');
                        this.menuHidden = true;
                        window.debugLog('Menu hidden (one-time)');
                    }
                    
                    // マウスカーソルとVRコントローラーのraycasterターゲットから.clickableを除外（初回のみ）
                    if (!this.controllersUpdated) {
                        const mouseCursor = document.getElementById('mouseCursor');
                        const leftController = document.getElementById('leftController');
                        const rightController = document.getElementById('rightController');
                        
                        if (mouseCursor) {
                            mouseCursor.setAttribute('raycaster', 'objects: .collidable');
                            window.debugLog('Removed .clickable from mouse cursor raycaster');
                        }
                        if (leftController) {
                            leftController.setAttribute('raycaster', 'objects: .collidable; far: 5; showLine: false');
                            window.debugLog('Removed .clickable from left controller raycaster');
                        }
                        const rightLaserSuffix = window.currentLevel === 2 ? '; showLine: false' : '; showLine: true';
                        if (rightController) {
                            rightController.setAttribute('raycaster', `objects: .collidable; far: 5${rightLaserSuffix}`);
                            window.debugLog('Removed .clickable from right controller raycaster');
                        }
                        
                        this.controllersUpdated = true;
                    }
                    
                    this.clickBlocked = true;
                } else {
                    // 🚀 改善: メニュー非表示フラグをリセット
                    if (this.menuHidden) {
                        this.menuHidden = false;
                    }
                    
                    // ゲーム中でない場合（開始前またはゲーム終了後）は.clickableを復元
                    if (this.controllersUpdated) {
                        const mouseCursor = document.getElementById('mouseCursor');
                        const leftController = document.getElementById('leftController');
                        const rightController = document.getElementById('rightController');
                        
                        if (mouseCursor) {
                            mouseCursor.setAttribute('raycaster', 'objects: .clickable, .collidable');
                            window.debugLog('Restored .clickable to mouse cursor raycaster');
                        }
                        if (leftController) {
                            leftController.setAttribute('raycaster', 'objects: .collidable, .clickable; far: 5; showLine: false');
                            window.debugLog('Restored .clickable to left controller raycaster');
                        }
                        if (rightController) {
                            rightController.setAttribute('raycaster', 'objects: .collidable, .clickable; far: 5; showLine: true');
                            window.debugLog('Restored .clickable to right controller raycaster');
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
                window.debugLog('=== Menu Click Detected ===');
                window.debugLog('Target:', event.target ? event.target.id : 'unknown');
                window.debugLog('Menu visible:', this.el.getAttribute('visible'));
                window.debugLog('Game started:', window.gameStarted);
                window.debugLog('Game ended:', window.gameEnded);
                window.debugLog('Click blocked:', this.clickBlocked);
                
                // 🚀 修正: レベルボタンのクリックはここでは処理しない（level1Handler/level2Handlerで処理）
                if (event.target && (event.target.id === 'level1Button' || event.target.id === 'level2Button')) {
                    window.debugLog('Level button click - handled by dedicated handler');
                    return; // レベルボタンの専用ハンドラに任せる
                }
                
                // ゲーム中は完全にブロック（最優先チェック）
                if (window.gameStarted && !window.gameEnded) {
                    window.debugLog('Click BLOCKED - Game in progress');
                    event.stopPropagation();
                    event.preventDefault();
                    return false;
                }
                
                // クリックがブロックされている場合は即座に拒否
                if (this.clickBlocked) {
                    window.debugLog('Click BLOCKED by flag');
                    event.stopPropagation();
                    event.preventDefault();
                    return false;
                }
                
                // メニューが非表示の場合は無視
                const isVisible = this.el.getAttribute('visible');
                if (isVisible === false || isVisible === 'false') {
                    window.debugLog('Click BLOCKED - Menu not visible');
                    event.stopPropagation();
                    event.preventDefault();
                    return false;
                }
                
                // ゲーム終了時は無視
                if (window.gameEnded) {
                    window.debugLog('Click BLOCKED - Game ended');
                    event.stopPropagation();
                    event.preventDefault();
                    return false;
                }
                
                this.startGame(event);
            },
            
            handleTouch: function(event) {
                window.debugLog('=== Menu Touch Detected ===');
                window.debugLog('Target:', event.target ? event.target.id : 'unknown');
                window.debugLog('Menu visible:', this.el.getAttribute('visible'));
                window.debugLog('Game started:', window.gameStarted);
                window.debugLog('Game ended:', window.gameEnded);
                window.debugLog('Click blocked:', this.clickBlocked);
                
                // 🚀 修正: レベルボタンのタッチはここでは処理しない（level1Handler/level2Handlerで処理）
                if (event.target && (event.target.id === 'level1Button' || event.target.id === 'level2Button')) {
                    window.debugLog('Level button touch - handled by dedicated handler');
                    return; // レベルボタンの専用ハンドラに任せる
                }
                
                // ゲーム中は完全にブロック（最優先チェック）
                if (window.gameStarted && !window.gameEnded) {
                    window.debugLog('Touch BLOCKED - Game in progress');
                    event.preventDefault();
                    event.stopPropagation();
                    return false;
                }
                
                // クリックがブロックされている場合は即座に拒否
                if (this.clickBlocked) {
                    window.debugLog('Touch BLOCKED by flag');
                    event.preventDefault();
                    event.stopPropagation();
                    return false;
                }
                
                // メニューが非表示の場合は無視
                const isVisible = this.el.getAttribute('visible');
                if (isVisible === false || isVisible === 'false') {
                    window.debugLog('Touch BLOCKED - Menu not visible');
                    event.preventDefault();
                    event.stopPropagation();
                    return false;
                }
                
                // ゲーム終了時は無視
                if (window.gameEnded) {
                    window.debugLog('Touch BLOCKED - Game ended');
                    event.preventDefault();
                    event.stopPropagation();
                    return false;
                }
                
                // 有効なタッチの場合はイベントを停止してゲーム開始
                window.debugLog('Valid touch - starting game!');
                event.preventDefault();
                event.stopPropagation();
                this.startGame(event);
                return true;
            },
            
            startGame: function(event) {
                window.debugLog('Game Start triggered!');
                
                // 🚀 メモリリーク対策: プレイ回数をインクリメント
                window.playCount++;
                console.log('=== GAME START ===');
                console.log('🎮 Play count incremented:', window.playCount, '/', window.MAX_PLAY_COUNT);
                window.debugLog('Play count:', window.playCount, '/', window.MAX_PLAY_COUNT);
                
                // レベル選択イベントの場合、window.gameLevelを使用
                if (event && event.type === 'level-select' && window.gameLevel) {
                    window.currentLevel = window.gameLevel;
                    window.debugLog('Level set to:', window.currentLevel);
                }
                
                window.updateDebug('Game Started! Level: ' + window.currentLevel);
                
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
                
                window.debugLog('Start menu hidden immediately (visible=false, scale=0, class removed)');
                
                // マウスカーソルとVRコントローラーから.clickableを即座に除外
                const mouseCursor = document.getElementById('mouseCursor');
                const leftController = document.getElementById('leftController');
                const rightController = document.getElementById('rightController');
                
                if (mouseCursor) {
                    mouseCursor.setAttribute('raycaster', 'objects: .collidable');
                    window.debugLog('Removed .clickable from mouse cursor');
                }
                if (leftController) {
                    leftController.setAttribute('raycaster', 'objects: .collidable; far: 5; showLine: false');
                    window.debugLog('Removed .clickable from left controller');
                }
                const rightLaserSuffix = window.currentLevel === 2 ? '; showLine: false' : '; showLine: true';
                if (rightController) {
                    rightController.setAttribute('raycaster', `objects: .collidable; far: 5${rightLaserSuffix}`);
                    window.debugLog('Removed .clickable from right controller');
                }
                this.controllersUpdated = true;
                
                // BGMを再生（音量70%）
                const bgmSound = document.getElementById('sound_bgm');
                if (bgmSound) {
                    bgmSound.volume = 0.7; // 音量を70%に設定
                    bgmSound.currentTime = 0; // 最初から再生
                    bgmSound.play().then(() => {
                        window.debugLog('BGM started playing at 70% volume');
                    }).catch(err => {
                        window.debugLog('BGM play failed:', err);
                    });
                }
                
                // 既存のタイマーがあればクリア
                if (window.gameTimer) {
                    clearInterval(window.gameTimer);
                    window.gameTimer = null;
                    window.debugLog('Cleared existing timer');
                }
                
                window.gameStarted = true;
                window.gameEnded = false; // ゲーム終了フラグもリセット
                window.totalScore = 0; // スコアをリセット
                window.gameTimeLeft = 75; // タイマーを75秒に設定
                window.comboCount = 0; // コンボカウントをリセット
                window.maxComboCount = 0; // 最大コンボカウントをリセット
                window.enemiesDefeated = 0; // 倒したゾンビの数をリセット
                window.lastBallHit = false; // ヒット状態をリセット
                window.bossSpawned = false; // ボス出現フラグをリセット
                window.respawningModels = {}; // リスポーン中フラグを初期化
                window.usedPatterns = {}; // パターン使用状況をリセット
                
                // ランダムパターン設定（初期スポーン用：シンプルなパターンのみ）
                const movementPatterns = [
                    // パターン1: 左後方(-5, 0, -10)からカメラ(0, 0, 0)へ - 距離: 11.2m - 速い
                    { startPos: { x: -5, y: 0, z: -10 }, speed: 0.4, useCamera: true, waitTime: 4000 },
                    // パターン2: 右後方(5, 0, -10)からカメラ(0, 0, 0)へ - 距離: 11.2m - 普通
                    { startPos: { x: 5, y: 0, z: -10 }, speed: 0.3, useCamera: true, waitTime: 4000 },
                    // パターン3: 正面奥(0, 0, -12)からカメラ(0, 0, 0)へ - 距離: 12.0m - 遅い
                    { startPos: { x: 0, y: 0, z: -12 }, speed: 0.2, useCamera: true, waitTime: 4000 },
                    // パターン4: 左奥(-6, 0, -8)から右奥(6, 0, -8)へ横移動 - 距離: 12.0m - 固定終点
                    { startPos: { x: -6, y: 0, z: -8 }, endPos: { x: 6, y: 0, z: -8 }, speed: 0.35, useCamera: false, waitTime: 4000 },
                    // パターン5: 右奥(6, 0, -8)から左奥(-6, 0, -8)へ横移動 - 距離: 12.0m - 固定終点
                    { startPos: { x: 6, y: 0, z: -8 }, endPos: { x: -6, y: 0, z: -8 }, speed: 0.35, useCamera: false, waitTime: 4000 },
                    // パターン6: 左斜め後方(-7, 0, -7)からカメラ(0, 0, 0)へ - 距離: 9.9m - 中速
                    { startPos: { x: -7, y: 0, z: -7 }, speed: 0.28, useCamera: true, waitTime: 4000 }
                ];
                
                // 初期モデル数をレベルに応じて設定（Level 1: 3体、Level 2: 6体）
                const initialModelIds = window.currentLevel === 1 
                    ? ['modelGroup_01', 'modelGroup_02', 'modelGroup_03']
                    : ['modelGroup_01', 'modelGroup_02', 'modelGroup_03', 'modelGroup_04', 'modelGroup_05', 'modelGroup_06'];
                window.debugLog('Showing initial', initialModelIds.length, 'models with random patterns (Level', window.currentLevel, ')');
                
                // モデルを1秒ずつずらして出現させる - 🚀 registerTimeout使用
                initialModelIds.forEach((modelId, index) => {
                    window.registerTimeout(() => {
                        const model = document.getElementById(modelId);
                        if (model) {
                            // 使用可能なパターンを取得（他のモデルと重複しない）
                            const { pattern: randomPattern, index: patternIndex } = window.getAvailablePattern(movementPatterns, modelId);
                            
                            // 始点位置を設定
                            model.setAttribute('position', `${randomPattern.startPos.x} ${randomPattern.startPos.y} ${randomPattern.startPos.z}`);
                            
                            // 角度を計算
                            let rotation;
                            if (randomPattern.useCamera) {
                                rotation = Math.atan2(randomPattern.startPos.x, -randomPattern.startPos.z) * (180 / Math.PI);
                            } else {
                                const dx = randomPattern.endPos.x - randomPattern.startPos.x;
                                const dz = randomPattern.endPos.z - randomPattern.startPos.z;
                                rotation = Math.atan2(dx, -dz) * (180 / Math.PI);
                            }
                            model.setAttribute('rotation', `0 ${rotation} 0`);
                            
                            // approach-cameraコンポーネントの設定
                            const cameraConfig = {
                                speed: randomPattern.speed,
                                startPos: randomPattern.startPos,
                                useCamera: randomPattern.useCamera,
                                autoRespawn: true,
                                waitTime: randomPattern.waitTime
                            };
                            if (randomPattern.endPos) {
                                cameraConfig.endPos = randomPattern.endPos;
                            }
                            model.setAttribute('approach-camera', cameraConfig);
                            
                            model.setAttribute('visible', true);
                            window.debugLog(`Model ${modelId} appeared after ${index} seconds (pattern ${patternIndex}:`, randomPattern, ')');
                        } else {
                            window.debugLog('Model not found:', modelId);
                        }
                    }, index * 1000); // 0秒、1秒、2秒、3秒後に出現
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
                
                window.debugLog('=== Starting timer ===');
                window.debugLog('ResultMenu element:', resultMenu);
                window.debugLog('ResultMenu exists:', resultMenu ? 'YES' : 'NO');
                window.debugLog('Current gameEnded:', window.gameEnded);
                window.debugLog('Current gameStarted:', window.gameStarted);
                
                // 既存のタイマーがあればクリア
                if (window.gameTimer) {
                    window.debugLog('Clearing existing timer before start');
                    clearInterval(window.gameTimer);
                    window.gameTimer = null;
                }
                
                window.gameTimer = setInterval(() => {
                    window.gameTimeLeft--;
                    
                    // タイマー表示を更新
                    if (timerText) {
                        timerText.setAttribute('value', `TIME: ${window.gameTimeLeft}s`);
                    }
                    
                    // 60秒経過時（残り15秒）にボスを出現（1度のみ、Level 2のみ）
                    if (window.gameTimeLeft === 15 && !window.bossSpawned && window.currentLevel === 2) {
                        window.debugLog('=== 60 seconds elapsed - Spawning BOSS (Level 2) ===');
                        window.bossSpawned = true;
                        this.spawnBoss();
                    }
                    
                    // 時間切れ
                    if (window.gameTimeLeft <= 0) {
                        window.debugLog('=== Timer reached 0 ===');
                        window.debugLog('gameEnded before set:', window.gameEnded);
                        window.updateDebug('Timer ended!');
                        
                        clearInterval(window.gameTimer);
                        window.gameTimer = null; // タイマーをクリア
                        window.gameEnded = true;
                        window.gameStarted = false;
                        
                        window.debugLog('gameEnded after set:', window.gameEnded);
                        window.debugLog('Total Score:', window.totalScore);
                        
                        // すべてのモデルにanime02と死亡音を再生してからフェードアウト
                        const models = document.querySelectorAll('[id^="modelGroup_"]');
                        window.debugLog('Playing death animation for models, count:', models.length);
                        
                        // 死亡音を1回だけ再生
                        const dieSound = document.getElementById('sound_zombie_die');
                        if (dieSound) {
                            dieSound.currentTime = 0;
                            dieSound.play().then(() => {
                                window.debugLog('Zombie die sound played (time over)');
                            }).catch(err => {
                                window.debugLog('Zombie die sound play failed:', err);
                            });
                        }
                        
                        models.forEach(model => {
                            if (model.getAttribute('visible')) {
                                // anime02に切り替え - 🚀 registerTimeout使用
                                const modelEntity = model.querySelector('[gltf-model]');
                                if (modelEntity) {
                                    modelEntity.removeAttribute('animation-mixer');
                                    window.registerTimeout(() => {
                                        modelEntity.setAttribute('animation-mixer', 'clip: anime02; loop: repeat; timeScale: 1');
                                        window.debugLog('Playing anime02 for model:', model.id);
                                    }, 50);
                                }
                                
                                // 1.5秒後にフェードアウト - 🚀 registerTimeout使用
                                window.registerTimeout(() => {
                                    model.setAttribute('animation__timeoverfadeout', {
                                        property: 'scale',
                                        to: '0 0 0',
                                        dur: 500,
                                        easing: 'easeInQuad'
                                    });
                                }, 1500);
                                
                                // 2秒後に完全に非表示 - 🚀 registerTimeout使用
                                window.registerTimeout(() => {
                                    model.setAttribute('visible', false);
                                }, 2000);
                            }
                        });
                        
                        // タイマー非表示
                        const timerDisplay = document.getElementById('timerDisplay');
                        if (timerDisplay) {
                            timerDisplay.setAttribute('visible', false);
                        }
                        
                        // 2.5秒後にリザルト画面を表示（モデルのフェードアウト完了を待つ）- 🚀 registerTimeout使用
                        window.registerTimeout(() => {
                            const currentResultMenu = document.getElementById('resultMenu');
                            window.debugLog('=== Looking for result menu ===');
                            window.debugLog('Result menu element:', currentResultMenu);
                            window.debugLog('Result menu exists:', currentResultMenu ? 'YES' : 'NO');
                            
                            if (currentResultMenu) {
                                window.debugLog('Calling showResult...');
                                window.updateDebug('Showing result...');
                                self.showResult(currentResultMenu);
                            } else {
                                window.debugLog('ERROR: Result menu element NOT FOUND!');
                                window.updateDebug('ERROR: Result menu NOT FOUND!');
                                // デバッグ: DOM内のすべての要素を確認
                                const allEntities = document.querySelectorAll('a-entity');
                                window.debugLog('Total a-entity count:', allEntities.length);
                                const menuEntities = document.querySelectorAll('[result-menu]');
                                window.debugLog('Entities with result-menu attribute:', menuEntities.length);
                            }
                        }, 2500);
                    }
                }, 1000);
                
                window.debugLog('Timer started, interval ID:', window.gameTimer);
            },
            
            spawnBoss: function() {
                window.debugLog('=== Spawning BOSS ===');
                const sceneEl = document.querySelector('a-scene');
                
                // ランダムパターンから1つ選択（4パターン）
                const bossPatterns = [
                    { startPos: { x: -4, y: 0, z: 0 }, speed: 0.15 },
                    { startPos: { x: 4, y: 0, z: 0 }, speed: 0.15 },
                    { startPos: { x: 0, y: 0, z: 4 }, speed: 0.15 },
                    { startPos: { x: 0, y: 0, z: -4 }, speed: 0.15 }
                ];
                
                const randomPattern = bossPatterns[Math.floor(Math.random() * bossPatterns.length)];
                const startPos = randomPattern.startPos;
                const speed = randomPattern.speed;
                
                // カメラ方向への角度を計算
                const rotation = Math.atan2(startPos.x, -startPos.z) * (180 / Math.PI);
                
                // ボスモデルグループを作成
                const bossGroup = document.createElement('a-entity');
                bossGroup.setAttribute('id', 'modelGroup_boss');
                bossGroup.setAttribute('position', `${startPos.x} ${startPos.y} ${startPos.z}`);
                bossGroup.setAttribute('rotation', `0 ${rotation} 0`);
                bossGroup.setAttribute('scale', '0 0 0'); // 最初は見えない状態
                bossGroup.setAttribute('approach-camera', {
                    speed: speed,
                    startPos: startPos,
                    useCamera: true,
                    autoRespawn: false, // ボスは再出現しない
                    waitTime: 4000
                });
                
                // ボス3Dモデルエンティティを作成（1.8倍サイズ）
                const bossEntity = document.createElement('a-entity');
                bossEntity.setAttribute('gltf-model', '#model_boss');
                bossEntity.setAttribute('animation-mixer', 'clip: anime01; loop: repeat');
                bossEntity.setAttribute('enhance-materials', '');
                bossEntity.setAttribute('scale', '1.8 1.8 1.8'); // 1.8倍サイズ（1.5 × 1.2）
                bossGroup.appendChild(bossEntity);
                
                // ボス当たり判定オブジェクトを作成（1.8倍サイズ + すり抜け防止で拡大）
                const bossHitBox = document.createElement('a-entity');
                bossHitBox.setAttribute('id', 'hit-boxed_boss');
                bossHitBox.setAttribute('hit-box', 'isBoss: true'); // ボスフラグ
                bossHitBox.setAttribute('position', '0 1.0 0'); // 通常モデルと同じ高さに統一
                
                const bossHitBoxCylinder = document.createElement('a-entity');
                bossHitBoxCylinder.setAttribute('geometry', 'primitive: cylinder');
                bossHitBoxCylinder.setAttribute('material', 'color: red; opacity: 0.0; transparent: true');
                bossHitBoxCylinder.setAttribute('scale', '1.8 3.2 1.8'); // すり抜け防止のため拡大（半径1.8m、高さ3.2m）
                bossHitBoxCylinder.setAttribute('class', 'collidable');
                
                bossHitBox.appendChild(bossHitBoxCylinder);
                bossGroup.appendChild(bossHitBox);
                
                // シーンに追加
                sceneEl.appendChild(bossGroup);
                window.debugLog('BOSS added to scene at:', startPos);
                
                // BOSS出現音を再生
                const appearSound = document.getElementById('sound_zombie_appear');
                if (appearSound) {
                    appearSound.currentTime = 0;
                    appearSound.play().then(() => {
                        window.debugLog('BOSS appear sound played');
                    }).catch(err => {
                        window.debugLog('BOSS appear sound play failed:', err);
                    });
                }
                
                // フェードインアニメーション - 🚀 registerTimeout使用
                window.registerTimeout(() => {
                    bossGroup.setAttribute('animation__fadein', {
                        property: 'scale',
                        to: '1 1 1',
                        dur: 2000,
                        easing: 'easeOutQuad'
                    });
                    window.debugLog('BOSS fading in');
                }, 100);
            },
            
            showResult: function(resultMenu) {
                window.debugLog('=== showResult function called ===');
                window.updateDebug(`Result: Score ${window.totalScore.toFixed(1)}`);
                window.debugLog('resultMenu parameter:', resultMenu);
                window.debugLog('resultMenu is null?', resultMenu === null);
                window.debugLog('resultMenu is undefined?', resultMenu === undefined);
                
                if (!resultMenu) {
                    window.debugLog('ERROR: resultMenu is null or undefined!');
                    return;
                }
                
                window.debugLog('Result menu visible attribute before:', resultMenu.getAttribute('visible'));
                window.debugLog('Result menu position:', resultMenu.getAttribute('position'));
                
                const scoreText = document.getElementById('resultScore');
                const commentText = document.getElementById('resultComment');
                const maxComboText = document.getElementById('maxComboText');
                const levelText = document.getElementById('resultLevel');
                
                window.debugLog('Score text element:', scoreText ? 'found' : 'NOT FOUND');
                window.debugLog('Comment text element:', commentText ? 'found' : 'NOT FOUND');
                
                window.debugLog('Score text:', scoreText ? 'found' : 'NOT FOUND');
                window.debugLog('Comment text:', commentText ? 'found' : 'NOT FOUND');
                
                // レベルを表示
                if (levelText) {
                    const levelName = window.currentLevel === 2 ? 'Level 2 - HARD' : 'Level 1 - EASY';
                    const levelColor = window.currentLevel === 2 ? '#FF6600' : '#00FF00';
                    levelText.setAttribute('value', levelName);
                    levelText.setAttribute('color', levelColor);
                    window.debugLog('Level updated:', levelName);
                }
                
                // スコアを表示（小数第一位まで）
                if (scoreText) {
                    scoreText.setAttribute('value', `SCORE: ${window.totalScore.toFixed(1)}`);
                    window.debugLog('Score updated:', window.totalScore.toFixed(1));
                }
                
                // 最大コンボ数を表示
                if (maxComboText) {
                    maxComboText.setAttribute('value', `MAX COMBO: ${window.maxComboCount}`);
                    window.debugLog('Max Combo updated:', window.maxComboCount);
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
                    window.debugLog('Comment updated:', comment);
                }
                
                // スタートメニューを確実に非表示
                const startMenu = document.getElementById('startMenu');
                if (startMenu) {
                    startMenu.setAttribute('visible', false);
                    window.debugLog('Start menu hidden in showResult');
                }
                
                // リザルトメニューを表示
                window.debugLog('Setting result menu visible and animating...');
                resultMenu.setAttribute('visible', true);
                resultMenu.setAttribute('scale', '0 0 0');
                
                // 既存のアニメーションをクリア
                resultMenu.removeAttribute('animation');
                resultMenu.removeAttribute('animation__scale');
                
                // 少し待ってからアニメーション開始（確実に反映させる） - 🚀 registerTimeout使用
                window.registerTimeout(() => {
                    resultMenu.setAttribute('animation', {
                        property: 'scale',
                        to: '1 1 1',
                        dur: 500,
                        easing: 'easeOutBack'
                    });
                    window.debugLog('Result menu animation started');
                }, 50);
                
                window.debugLog('Result menu should be visible now');
                
                // スコアをデータベースに保存
                this.saveScoreToDatabase(window.totalScore);
            },
            
            saveScoreToDatabase: function(score) {
                window.debugLog('Saving score to database:', score, 'Level:', window.currentLevel, 'Game Mode: terrer');
                
                fetch('api/shooting-scores', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({
                        name: 'noName', // デフォルト名
                        score: score,
                        level: window.currentLevel || 1, // レベル情報を追加
                        game_mode: 'terrer', // ゲームモードを追加
                        max_combo: window.maxComboCount || 0, // 最大コンボ数
                        enemies_defeated: window.enemiesDefeated || 0, // 倒したゾンビの数
                    })
                })
                .then(response => response.json())
                .then(data => {
                    window.debugLog('Score saved successfully:', data);
                    if (data && data.data && data.data.id) {
                        window.lastSavedScoreId = data.data.id;
                    } else {
                        window.lastSavedScoreId = null;
                    }
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
                window.debugLog('Fetching top 5 rankings for Level:', window.currentLevel, 'Game Mode: terrer');
                
                fetch(`api/shooting-scores/top5?level=${window.currentLevel || 1}&game_mode=terrer`)
                    .then(response => response.json())
                    .then(data => {
                        window.debugLog('Rankings fetched:', data);
                        if (data.success && data.data) {
                            this.displayRankings(data.data);
                            
                            // トップ5に入っているかチェック
                            const currentScore = window.totalScore;
                            const currentId = window.lastSavedScoreId || null;
                            const isInTop5 = data.data.some(item => 
                                (currentId && item.id === currentId) ||
                                (Math.abs(item.score - currentScore) < 0.01 && item.name === 'noName')
                            );
                            
                            if (isInTop5) {
                                window.debugLog('🎉 Congratulations! You made it to Top 5!');
                                this.celebrateTop5();
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching rankings:', error);
                    });
            },
            
            celebrateTop5: function() {
                // リザルトメニューの位置を取得
                const resultMenu = document.querySelector('#resultMenu');
                if (!resultMenu) return;
                
                const position = resultMenu.getAttribute('position');
                
                // 豪華なパーティクルエフェクトを表示
                const celebrationParticle = document.querySelector('#particle-celebration');
                if (celebrationParticle) {
                    // リザルトメニューの位置に配置
                    celebrationParticle.setAttribute('position', `${position.x} ${position.y} ${position.z}`);
                    celebrationParticle.setAttribute('visible', 'true');
                    
                    // パーティクルを開始
                    const particleSystem = celebrationParticle.components['particle-system'];
                    if (particleSystem) {
                        particleSystem.startParticles();
                    }
                    
                    // 5秒後にパーティクルを停止 - 🚀 registerTimeout使用
                    window.registerTimeout(() => {
                        if (particleSystem) {
                            particleSystem.stopParticles();
                        }
                        celebrationParticle.setAttribute('visible', 'false');
                    }, 5000);
                }
                
                window.debugLog('Top 5 celebration particles activated!');
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
                
                // レベル表示を追加（一番上）
                const levelHeader = document.createElement('a-text');
                const levelName = window.currentLevel === 2 ? 'Terrer Lv2 Ranking' : 'Terrer Lv1 Ranking';
                const levelColor = window.currentLevel === 2 ? '#FF6600' : '#00FF00';
                levelHeader.setAttribute('value', levelName);
                levelHeader.setAttribute('position', '0 0.65 0');
                levelHeader.setAttribute('align', 'center');
                levelHeader.setAttribute('color', levelColor);
                levelHeader.setAttribute('width', '4');
                levelHeader.setAttribute('font', 'mozillavr');
                levelHeader.setAttribute('shader', 'msdf');
                rankingDisplay.appendChild(levelHeader);
                
                // 現在のプレイヤーのスコアと保存ID
                const currentScore = window.totalScore;
                const currentId = window.lastSavedScoreId || null;
                
                // ランキングを表示（上から順に5位まで）
                rankings.forEach((item, index) => {
                    const rank = index + 1;
                    const yPosition = 0.1 - (index * 0.25); // 0.25間隔で配置
                    
                    // ランキング行のテキスト（名前の代わりにコンボ数と倒したゾンビ数を表示）
                    const rankingText = `${rank}. ${item.score.toFixed(1)}pt  Combo:${item.max_combo || 0}  Zombies:${item.enemies_defeated || 0}`;
                    
                    // 自分のスコアかどうかを判定：ID一致があれば最優先、なければスコア±0.01
                    const isCurrentPlayer = (currentId && item.id === currentId) ||
                                             (Math.abs(item.score - currentScore) < 0.01);
                    
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
                
                window.debugLog('Rankings displayed:', rankings.length, 'entries');
            },
            
            recreateInitialModels: function(sceneEl) {
                window.debugLog('Recreating initial models with staggered creation...');
                
                // 🚀 最適化: モデル定義を配列化して時間分散で作成
                const modelConfigs = [
                    { id: 'modelGroup_01', gltf: '#model_01', pos: '-4 0 -8', rot: '0 45 0', approachConfig: 'speed: 0.3; useCamera: true; autoRespawn: true; waitTime: 4000', cylinderColor: 'blue' },
                    { id: 'modelGroup_02', gltf: '#model_02', pos: '0 0 -10', rot: '0 0 0', approachConfig: 'speed: 0.25; useCamera: true; autoRespawn: true; waitTime: 4000', cylinderColor: 'green' },
                    { id: 'modelGroup_03', gltf: '#model_03', pos: '4 0 -8', rot: '0 -45 0', approachConfig: 'speed: 0.35; useCamera: false; endPos: 2 0 -2; autoRespawn: true; waitTime: 4000', cylinderColor: 'red' },
                    { id: 'modelGroup_04', gltf: '#model_04', pos: '-4 0 -8', rot: '0 45 0', approachConfig: 'speed: 0.3; useCamera: true; autoRespawn: true; waitTime: 4000', cylinderColor: 'yellow' },
                    { id: 'modelGroup_05', gltf: '#model_05', pos: '-4 0 -8', rot: '0 45 0', approachConfig: 'speed: 0.3; useCamera: true; autoRespawn: true; waitTime: 4000', cylinderColor: 'purple' },
                    { id: 'modelGroup_06', gltf: '#model_06', pos: '4 0 -8', rot: '0 -45 0', approachConfig: 'speed: 0.3; useCamera: true; autoRespawn: true; waitTime: 4000', cylinderColor: 'cyan' }
                ];
                
                // 各モデルを時間分散で作成（100ms間隔）
                modelConfigs.forEach((config, index) => {
                    window.registerTimeout(() => {
                        this.createSingleModel(sceneEl, config);
                    }, index * 100); // 100ms間隔で1体ずつ作成
                });
                
                window.debugLog('Initial models creation scheduled (6 models, 100ms intervals)');
            },
            
            // 🚀 新規: 単一モデル作成関数（リファクタリング）
            createSingleModel: function(sceneEl, config) {
                const model = document.createElement('a-entity');
                model.setAttribute('id', config.id);
                model.setAttribute('position', config.pos);
                model.setAttribute('rotation', config.rot);
                model.setAttribute('scale', '1 1 1');
                model.setAttribute('approach-camera', config.approachConfig);
                model.setAttribute('visible', 'false');
                
                const modelEntity = document.createElement('a-entity');
                modelEntity.setAttribute('gltf-model', config.gltf);
                modelEntity.setAttribute('animation-mixer', 'clip: anime01; loop: repeat');
                modelEntity.setAttribute('enhance-materials', '');
                model.appendChild(modelEntity);
                
                const hitBoxId = config.id.replace('modelGroup', 'hit-boxed');
                const hitBox = document.createElement('a-entity');
                hitBox.setAttribute('id', hitBoxId);
                hitBox.setAttribute('hit-box', '');
                hitBox.setAttribute('position', '0 1.0 0');
                
                const cylinder = document.createElement('a-entity');
                cylinder.setAttribute('geometry', 'primitive: cylinder');
                cylinder.setAttribute('material', `color: ${config.cylinderColor}; opacity: 0.0; transparent: true`);
                cylinder.setAttribute('scale', '0.75 1.5 0.75');
                cylinder.setAttribute('class', 'collidable');
                hitBox.appendChild(cylinder);
                model.appendChild(hitBox);
                
                sceneEl.appendChild(model);
                window.debugLog('Model created:', config.id);
            },
            
            restartGame: function() {
                window.debugLog('=== Restarting game - Reset without reload ===');
                window.updateDebug('Restarting...');
                
                // ゲーム状態をリセット
                window.gameStarted = false;
                window.gameEnded = false;
                window.totalScore = 0;
                window.comboCount = 0;
                window.maxComboCount = 0;
                window.lastBallHit = false;
                window.currentLevel = 1; // デフォルトに戻す
                window.gameLevel = 1;
                window.activeBalls = [];
                window.respawningModels = {}; // リスポーン中フラグをリセット
                window.usedPatterns = {}; // パターン使用状況をリセット
                window.bossSpawned = false; // ボス出現フラグをリセット
                
                // クリックブロックフラグをリセット
                this.clickBlocked = false;
                this.controllersUpdated = false;
                window.debugLog('Click block flags reset');
                
                // 【重要】全ての未実行setTimeoutをクリア（途中からカクカク対策）
                if (window.activeTimers && window.activeTimers.length > 0) {
                    window.debugLog('Clearing', window.activeTimers.length, 'active timers');
                    window.activeTimers.forEach(timerId => {
                        clearTimeout(timerId);
                    });
                    window.activeTimers = [];
                }
                
                // タイマーをクリア
                if (window.gameTimer) {
                    clearInterval(window.gameTimer);
                    window.gameTimer = null;
                }
                
                // 🚀🚀 強化: THREE.jsの完全なGPUリソース解放（カクツキ対策）
                const sceneEl = document.querySelector('a-scene');
                if (sceneEl && sceneEl.renderer) {
                    const renderer = sceneEl.renderer;
                    
                    // 1. レンダーリストをクリア
                    if (renderer.renderLists) {
                        renderer.renderLists.dispose();
                    }
                    
                    // 2. レンダーターゲットをクリア（フレームバッファ等）
                    if (renderer.renderTarget) {
                        renderer.setRenderTarget(null);
                    }
                    
                    // 3. メモリ情報をリセット
                    if (renderer.info) {
                        renderer.info.reset();
                    }
                    
                    // 4. THREE.jsのグローバルキャッシュをクリア（テクスチャ等）
                    if (THREE.Cache) {
                        THREE.Cache.clear();
                    }
                    
                    // 5. シーン内の全オブジェクトのgeometry/materialを解放
                    if (sceneEl.object3D) {
                        sceneEl.object3D.traverse((node) => {
                            if (node.geometry) {
                                node.geometry.dispose();
                            }
                            if (node.material) {
                                if (Array.isArray(node.material)) {
                                    node.material.forEach(mat => {
                                        if (mat.map) mat.map.dispose();
                                        if (mat.lightMap) mat.lightMap.dispose();
                                        if (mat.bumpMap) mat.bumpMap.dispose();
                                        if (mat.normalMap) mat.normalMap.dispose();
                                        if (mat.specularMap) mat.specularMap.dispose();
                                        if (mat.envMap) mat.envMap.dispose();
                                        mat.dispose();
                                    });
                                } else {
                                    if (node.material.map) node.material.map.dispose();
                                    if (node.material.lightMap) node.material.lightMap.dispose();
                                    if (node.material.bumpMap) node.material.bumpMap.dispose();
                                    if (node.material.normalMap) node.material.normalMap.dispose();
                                    if (node.material.specularMap) node.material.specularMap.dispose();
                                    if (node.material.envMap) node.material.envMap.dispose();
                                    node.material.dispose();
                                }
                            }
                        });
                    }
                    
                    window.debugLog('🚀 THREE.js GPU resource cleanup done (safe mode)');
                }
                
                // BGMを停止
                const bgm = document.getElementById('sound_bgm');
                if (bgm) {
                    bgm.pause();
                    bgm.currentTime = 0;
                }
                
                // 全パーティクルシステムを強制停止（パフォーマンス向上）
                const particleIds = ['particle-normal', 'particle-tier1', 'particle-tier2', 'particle-tier3', 'particle-celebration'];
                particleIds.forEach(particleId => {
                    const particle = document.getElementById(particleId);
                    if (particle) {
                        const particleSystem = particle.components['particle-system'];
                        if (particleSystem) {
                            particleSystem.stopParticles();
                        }
                        particle.setAttribute('visible', 'false');
                        window.debugLog('Stopped particle system:', particleId);
                    }
                });
                
                // すべてのモデルを完全に削除（アニメーションとコンポーネントもクリア）
                const allModels = ['modelGroup_01', 'modelGroup_02', 'modelGroup_03', 'modelGroup_04', 'modelGroup_05', 'modelGroup_06', 'modelGroup_boss'];
                allModels.forEach(modelId => {
                    // 既存のモデルを全て削除（IDで検索して複数ある場合も対応）
                    const models = sceneEl.querySelectorAll(`#${modelId}`);
                    models.forEach(model => {
                        if (model && model.parentNode) {
                            // 🚀 改善: approach-cameraコンポーネントを先に削除（メモリリーク対策）
                            if (model.components && model.components['approach-camera']) {
                                model.removeAttribute('approach-camera');
                            }
                            
                            // アニメーションを全て停止
                            model.removeAttribute('animation__fadein');
                            model.removeAttribute('animation__fadeout');
                            model.removeAttribute('animation__timeoverfadeout');
                            model.removeAttribute('animation-mixer');
                            
                            // THREE.jsレベルのクリーンアップ
                            if (model.object3D) {
                                model.object3D.traverse((node) => {
                                    if (node.geometry) {
                                        node.geometry.dispose();
                                    }
                                    if (node.material) {
                                        if (Array.isArray(node.material)) {
                                            node.material.forEach(mat => mat.dispose());
                                        } else {
                                            node.material.dispose();
                                        }
                                    }
                                    // 🚀 追加: テクスチャも破棄
                                    if (node.material && node.material.map) {
                                        node.material.map.dispose();
                                    }
                                });
                            }
                            
                            window.debugLog('Removing model with cleanup:', modelId);
                            model.parentNode.removeChild(model);
                        }
                    });
                });
                
                // 🚀 改善: respawnModelGlobalをリセット（次回のモデル作成時に再バインド）
                window.respawnModelGlobal = null;
                
                // すべてのボールを完全削除（アニメーション停止とメモリ解放）
                window.activeBalls.forEach(ballData => {
                    if (ballData.ball) {
                        // アニメーションを停止
                        ballData.ball.removeAttribute('animation__spin');
                        // 🚀 追加: model-loadedリスナーのクリーンアップ
                        ballData.ball.removeAttribute('gltf-model');
                        // DOMから削除
                        if (ballData.ball.parentNode) {
                            ballData.ball.parentNode.removeChild(ballData.ball);
                        }
                    }
                });
                window.activeBalls = [];
                
                // GLBボールの残骸を徹底的にクリーンアップ
                const remainingBalls = sceneEl.querySelectorAll('[gltf-model*="poke_ball"]');
                remainingBalls.forEach(ball => {
                    if (ball.parentNode) {
                        window.debugLog('Removing remaining ball');
                        ball.removeAttribute('gltf-model');
                        ball.parentNode.removeChild(ball);
                    }
                });
                
                // シーン上の動的に生成されたテキスト（スコア・コンボ）を削除
                const scoreTexts = sceneEl.querySelectorAll('a-text[face-camera]');
                scoreTexts.forEach(text => {
                    if (text.parentNode) {
                        text.parentNode.removeChild(text);
                    }
                });
                
                // ランキング表示をクリア
                const rankingDisplay = document.getElementById('rankingDisplay');
                if (rankingDisplay) {
                    while (rankingDisplay.firstChild) {
                        rankingDisplay.removeChild(rankingDisplay.firstChild);
                    }
                }
                
                // 🚀 変更: モデル再作成は最後に遅延実行（GCに時間を与えるため）
                // this.recreateInitialModels(sceneEl); は restartGame の最後で遅延呼び出し
                
                // UI表示を初期状態に戻す
                const startMenu = document.getElementById('startMenu');
                const resultMenu = document.getElementById('resultMenu');
                const timerDisplay = document.getElementById('timerDisplay');
                
                window.debugLog('Checking UI elements:');
                window.debugLog('- startMenu:', startMenu ? 'found' : 'NOT FOUND');
                window.debugLog('- resultMenu:', resultMenu ? 'found' : 'NOT FOUND');
                window.debugLog('- timerDisplay:', timerDisplay ? 'found' : 'NOT FOUND');
                
                if (startMenu) {
                    startMenu.setAttribute('visible', 'true');
                    window.debugLog('Start menu set to visible');
                    
                    // 強制的に前面に配置
                    startMenu.setAttribute('position', '0 1.6 -3');
                    startMenu.setAttribute('scale', '1 1 1');
                    startMenu.object3D.visible = true; // THREE.jsレベルでも表示
                    
                    // clickableクラスを復元（startGameで削除されているため）
                    const nonClickableElements = startMenu.querySelectorAll('.non-clickable');
                    nonClickableElements.forEach(element => {
                        element.classList.remove('non-clickable');
                        element.classList.add('clickable');
                    });
                    window.debugLog('Restored clickable classes to', nonClickableElements.length, 'elements');
                    
                    // 子要素も確認
                    const menuBg = startMenu.querySelector('#menuBackground');
                    const level1Btn = startMenu.querySelector('#level1Button');
                    const level2Btn = startMenu.querySelector('#level2Button');
                    window.debugLog('Start menu children:', {
                        background: menuBg ? 'found' : 'missing',
                        level1Button: level1Btn ? 'found' : 'missing',
                        level2Button: level2Btn ? 'found' : 'missing'
                    });
                }
                
                if (resultMenu) {
                    resultMenu.setAttribute('visible', 'false');
                    resultMenu.setAttribute('scale', '1 1 1'); // スケールを元に戻す
                    window.debugLog('Result menu set to hidden');
                    
                    // アニメーションをクリア（次回の表示のため）
                    resultMenu.removeAttribute('animation');
                    resultMenu.removeAttribute('animation__scale');
                }
                
                if (timerDisplay) {
                    timerDisplay.setAttribute('visible', 'false');
                    window.debugLog('Timer display set to hidden');
                }
                
                // raycasterを.clickableと.collidableの両方を対象に戻す
                const mouseCursor = document.getElementById('mouseCursor');
                const leftController = document.getElementById('leftController');
                const rightController = document.getElementById('rightController');
                
                if (mouseCursor) {
                    mouseCursor.setAttribute('raycaster', 'objects: .clickable, .collidable');
                    window.debugLog('Restored .clickable to mouse cursor');
                }
                if (leftController) {
                    leftController.setAttribute('raycaster', 'objects: .collidable, .clickable; far: 5; showLine: false');
                    window.debugLog('Restored .clickable to left controller');
                }
                if (rightController) {
                    rightController.setAttribute('raycaster', 'objects: .collidable, .clickable; far: 5; showLine: true');
                    window.debugLog('Restored .clickable to right controller');
                }
                
                // スコア表示をリセット
                const currentScoreText = document.getElementById('currentScore');
                if (currentScoreText) {
                    currentScoreText.setAttribute('value', 'SCORE: 0.0');
                }
                
                // 🚀 追加: shootコンポーネントのキャッシュをクリア
                const camera = document.getElementById('my_camera');
                if (camera && camera.components && camera.components['shoot']) {
                    camera.components['shoot'].modelsList = null;
                    camera.components['shoot'].modelsCache = {};
                    camera.components['shoot'].hitBoxCache = {};
                    window.debugLog('Cleared shoot component all caches (modelsList, modelsCache, hitBoxCache)');
                }
                
                // 🚀 追加: 少し遅延を入れてからモデルを再作成（GCに時間を与える）
                window.registerTimeout(() => {
                    this.recreateInitialModels(sceneEl);
                    window.debugLog('Game reset complete - Ready to start new game');
                    window.updateDebug('Ready to start');
                }, 100);
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
                    window.debugLog('Restart button click and touch listeners added');
                }
            },
            
            restart: function(event) {
                window.debugLog('Restart button clicked');
                
                // 🚀 メモリリーク対策: プレイ回数チェック
                console.log('=== RESTART CHECK ===');
                console.log('Current playCount:', window.playCount);
                console.log('MAX_PLAY_COUNT:', window.MAX_PLAY_COUNT);
                console.log('Check result:', window.playCount >= window.MAX_PLAY_COUNT);
                
                if (window.playCount >= window.MAX_PLAY_COUNT) {
                    console.log('✅ 最大プレイ回数に達しました。ページをリフレッシュします。');
                    
                    // 🎯 VRゴーグル対応: VRモードを終了してからリフレッシュ
                    const sceneEl = document.querySelector('a-scene');
                    const isVRMode = sceneEl && sceneEl.is('vr-mode');
                    
                    console.log('🥽 VR Mode:', isVRMode);
                    
                    if (isVRMode) {
                        console.log('🚪 Exiting VR mode before reload...');
                        
                        // VRモード終了後にリロード
                        sceneEl.exitVR().then(() => {
                            console.log('✅ VR mode exited, reloading page...');
                            window.registerTimeout(() => {
                                this.performReload();
                            }, 500);
                        }).catch((err) => {
                            console.error('❌ VR exit error:', err);
                            // エラーでも強制リロード
                            this.performReload();
                        });
                    } else {
                        // 通常モードならすぐにリロード
                        console.log('💻 Normal mode, reloading immediately...');
                        this.performReload();
                    }
                    return;
                }
                
                console.log('➡️ 通常リスタート処理を実行');
                
                // スタートメニューコンポーネントのrestartGame関数を呼び出す
                const startMenu = document.getElementById('startMenu');
                if (startMenu && startMenu.components['start-menu']) {
                    startMenu.components['start-menu'].restartGame();
                }
            },
            
            performReload: function() {
                console.log('🔄 Attempting page reload...');
                
                // 複数の方法を試行（VRゴーグルのブラウザ互換性対応）
                try {
                    // 方法1: 標準的なリロード
                    if (window.location && window.location.reload) {
                        console.log('Method 1: location.reload()');
                        window.location.reload(true); // キャッシュ無視
                        return;
                    }
                } catch (e) {
                    console.error('Method 1 failed:', e);
                }
                
                try {
                    // 方法2: location.href再設定
                    console.log('Method 2: location.href reassignment');
                    window.location.href = window.location.href;
                } catch (e) {
                    console.error('Method 2 failed:', e);
                    
                    try {
                        // 方法3: location.replace（履歴残さない）
                        console.log('Method 3: location.replace()');
                        window.location.replace(window.location.href);
                    } catch (e2) {
                        console.error('All reload methods failed:', e2);
                        // 最終手段: 手動リロード指示
                        console.error('⚠️ 自動リロード失敗。手動でページをリロードしてください。');
                    }
                }
            },
            
            restartTouch: function(event) {
                window.debugLog('Restart button touched');
                event.preventDefault();
                event.stopPropagation();
                
                // 🚀 メモリリーク対策: プレイ回数チェック
                console.log('=== RESTART TOUCH CHECK ===');
                console.log('Current playCount:', window.playCount);
                console.log('MAX_PLAY_COUNT:', window.MAX_PLAY_COUNT);
                console.log('Check result:', window.playCount >= window.MAX_PLAY_COUNT);
                
                if (window.playCount >= window.MAX_PLAY_COUNT) {
                    console.log('✅ 最大プレイ回数に達しました。ページをリフレッシュします。');
                    
                    // 🎯 VRゴーグル対応: restart()と同じ処理を実行
                    const sceneEl = document.querySelector('a-scene');
                    const isVRMode = sceneEl && sceneEl.is('vr-mode');
                    
                    console.log('🥽 VR Mode:', isVRMode);
                    
                    if (isVRMode) {
                        console.log('🚪 Exiting VR mode before reload...');
                        
                        sceneEl.exitVR().then(() => {
                            console.log('✅ VR mode exited, reloading page...');
                            window.registerTimeout(() => {
                                this.performReload();
                            }, 500);
                        }).catch((err) => {
                            console.error('❌ VR exit error:', err);
                            this.performReload();
                        });
                    } else {
                        console.log('💻 Normal mode, reloading immediately...');
                        this.performReload();
                    }
                    return;
                }
                
                console.log('➡️ 通常リスタート処理を実行');
                
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
                    window.debugLog('Entered VR mode');
                    // VRモードに入ったらリスナーを再設定 - 🚀 registerTimeout使用
                    window.registerTimeout(() => {
                        this.setupCanvasListeners();
                        this.setupControllerListeners();
                    }, 100);
                });
                
                this.el.sceneEl.addEventListener('exit-vr', () => {
                    window.debugLog('Exited VR mode');
                    // VRモードを出たらリスナーを再設定 - 🚀 registerTimeout使用
                    window.registerTimeout(() => {
                        this.setupCanvasListeners();
                    }, 100);
                });
            },
            
            tick: function(time, timeDelta) {
                // 🚀 最適化: ボールがない場合は即座にリターン
                const ballCount = window.activeBalls.length;
                if (ballCount === 0) return;
                
                // アクティブなボールを更新（VRモード対応）
                const currentTime = Date.now();
                for (let i = ballCount - 1; i >= 0; i--) {
                    const ballData = window.activeBalls[i];
                    if (ballData && ballData.ball && ballData.ball.parentNode) {
                        this.updateBallPosition(ballData, currentTime);
                    } else {
                        // ボールが削除されている場合は配列から削除
                        window.activeBalls.splice(i, 1);
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
                
                // 🚀 最適化: Vector3再利用（ballDataに紐づけ）
                if (!ballData._currentPos) {
                    ballData._currentPos = new THREE.Vector3();
                }
                const currentPos = ballData._currentPos;
                
                // 放物線運動の計算
                currentPos.set(
                    startPos.x + velocity.x * elapsedTime,
                    startPos.y + velocity.y * elapsedTime + 0.5 * gravity * elapsedTime * elapsedTime,
                    startPos.z + velocity.z * elapsedTime
                );
                
                // ボールの位置を更新
                // 🚀 最適化: setAttributeではなくobject3Dを直接操作（大幅に高速化）
                ball.object3D.position.copy(currentPos);
                
                ballData.frameCount++;
                if (window.DEBUG_MODE && ballData.frameCount <= 3) {
                    window.debugLog(`Frame ${ballData.frameCount}: Ball at (${currentPos.x.toFixed(2)}, ${currentPos.y.toFixed(2)}, ${currentPos.z.toFixed(2)})`);
                }
                
                // 🚀 改善: モデル配列を初回のみ生成（キャッシュでメモリ効率向上）
                if (!this.modelsList) {
                    this.modelsList = [
                        { id: 'modelGroup_01', hitBoxId: 'hit-boxed_01', radius: 0.75, height: 1.5 },
                        { id: 'modelGroup_02', hitBoxId: 'hit-boxed_02', radius: 0.75, height: 1.5 },
                        { id: 'modelGroup_03', hitBoxId: 'hit-boxed_03', radius: 0.75, height: 1.5 },
                        { id: 'modelGroup_04', hitBoxId: 'hit-boxed_04', radius: 0.75, height: 1.5 },
                        { id: 'modelGroup_05', hitBoxId: 'hit-boxed_05', radius: 0.75, height: 1.5 },
                        { id: 'modelGroup_06', hitBoxId: 'hit-boxed_06', radius: 0.75, height: 1.5 },
                        { id: 'modelGroup_boss', hitBoxId: 'hit-boxed_boss', radius: 1.8, height: 3.2 } // ボスはすり抜け防止のため大きめに設定
                    ];
                    // 🚀 最適化: モデル参照もキャッシュ用配列を準備
                    this.modelsCache = {};
                }
                const models = this.modelsList;
                
                for (let i = 0; i < models.length; i++) {
                    const modelInfo = models[i];
                    
                    // 🚀 最適化: モデル参照をキャッシュ（document.getElementByIdを毎フレーム呼ばない）
                    let modelGroup = this.modelsCache[modelInfo.id];
                    if (!modelGroup || !modelGroup.parentNode) {
                        modelGroup = document.getElementById(modelInfo.id);
                        if (modelGroup) {
                            this.modelsCache[modelInfo.id] = modelGroup;
                        }
                    }
                    // 🚀 最適化: object3D.visibleを直接参照
                    if (!modelGroup || !modelGroup.parentNode || !modelGroup.object3D.visible) continue;
                    
                    // 🚀 最適化: hitbox参照をキャッシュ（querySelectorを毎フレーム呼ばない）
                    let hitBox = this.hitBoxCache ? this.hitBoxCache[modelInfo.hitBoxId] : null;
                    if (!hitBox || !hitBox.parentNode) {
                        hitBox = modelGroup.querySelector(`#${modelInfo.hitBoxId}`);
                        if (!this.hitBoxCache) this.hitBoxCache = {};
                        if (hitBox) this.hitBoxCache[modelInfo.hitBoxId] = hitBox;
                    }
                    if (!hitBox) {
                        // hitboxが削除されている場合はスキップ（anime02再生中）
                        if (window.DEBUG_MODE && ballData.frameCount <= 5) {
                            window.debugLog(`${modelInfo.id}: hitbox removed, skipping collision`);
                        }
                        continue;
                    }
                    
                    // 🚀 最適化: getAttributeではなくobject3D.positionを直接参照
                    const modelPos = modelGroup.object3D.position;
                    
                    // 🚀 最適化1: 大まかな範囲チェック（早期スキップ）
                    const dx = currentPos.x - modelPos.x;
                    const dz = currentPos.z - modelPos.z;
                    const maxRadius = modelInfo.radius + 0.5; // 余裕を持たせる
                    if (Math.abs(dx) > maxRadius || Math.abs(dz) > maxRadius) continue;
                    
                    // ヒットボックスの中心位置（モデルグループ + ヒットボックスのオフセット）
                    // ヒットボックスはposition='0 1.0 0'に配置されている
                    const hitBoxCenterY = modelPos.y + 1.0;
                    
                    // モデルごとの円柱形状での当たり判定
                    // Y軸方向の距離チェック（円柱の高さ範囲内か）
                    const halfHeight = modelInfo.height / 2;
                    const yMin = hitBoxCenterY - halfHeight; // 円柱の底面
                    const yMax = hitBoxCenterY + halfHeight; // 円柱の天面
                    const isInHeightRange = currentPos.y >= yMin && currentPos.y <= yMax;
                    
                    if (!isInHeightRange) continue; // Y範囲外なら早期スキップ
                    
                    // 🚀 最適化2: XZ平面での距離チェック（Math.sqrtを回避）
                    const xzDistanceSquared = dx * dx + dz * dz;
                    const cylinderRadiusSquared = modelInfo.radius * modelInfo.radius;
                        
                        // デバッグ出力（最初の数フレームのみ）
                        if (window.DEBUG_MODE && ballData.frameCount <= 5) {
                            const xzDistance = Math.sqrt(xzDistanceSquared); // デバッグ用のみ計算
                            window.debugLog(`${modelInfo.id}: xzDist=${xzDistance.toFixed(2)}, yInRange=${isInHeightRange}, y=${currentPos.y.toFixed(2)} (${yMin.toFixed(2)}-${yMax.toFixed(2)}), R=${modelInfo.radius}m, H=${modelInfo.height}m`);
                        }
                        
                        if (xzDistanceSquared < cylinderRadiusSquared) { // 🚀 2乗で比較
                            ballData.hasHit = true;
                            const xzDistance = Math.sqrt(xzDistanceSquared); // ログ用のみ計算
                            window.debugLog(`✓ Ball HIT ${modelInfo.id}! xzDist=${xzDistance.toFixed(2)}m, y=${currentPos.y.toFixed(2)}m`);
                            // 🚀 最適化: 既にキャッシュ済みのhitBoxを再利用
                            if (hitBox) {
                                hitBox.emit('ball-hit');
                            }
                            
                            // ボールの回転アニメーションを停止
                            ball.removeAttribute('animation__spin');
                            
                            // ボールのanime01アニメーションを再生（1回のみ）
                            ball.setAttribute('animation-mixer', 'clip: anime01; loop: once');
                            
                            // 🚀 最適化: ボールを即座に光らせる（model-loadedは既に発火済みなので直接処理）
                            const mesh = ball.getObject3D('mesh');
                            if (mesh) {
                                mesh.traverse((node) => {
                                    if (node.isMesh && node.material) {
                                        // ヒット時は白く光らせる
                                        if (window.cachedHitEmissiveColor) {
                                            node.material.emissive = window.cachedHitEmissiveColor;
                                        }
                                        node.material.emissiveIntensity = 1.5; // さらに明るく
                                    }
                                });
                            }
                            
                            // 🚀 最適化: clone()を避けてスカラー計算でバウンス位置を算出
                            const bounceX = currentPos.x - direction.x * 2;
                            const bounceY = currentPos.y - direction.y * 2;
                            const bounceZ = currentPos.z - direction.z * 2;
                            
                            ball.setAttribute('animation__bounce', {
                                property: 'position',
                                to: `${bounceX} ${bounceY} ${bounceZ}`,
                                dur: 300,
                                easing: 'easeOutQuad'
                            });
                            
                            // GLBモデルのフェードアウト（scaleを0に）
                            ball.setAttribute('animation__fade', {
                                property: 'scale',
                                to: '0 0 0',
                                dur: 300,
                                easing: 'easeInQuad'
                            });
                            
                            // 🚀 修正: setTimeout → registerTimeout（タイマー管理対象に）
                            window.registerTimeout(() => {
                                // 🚀 メモリ解放: THREE.jsオブジェクトを破棄
                                if (ball.object3D) {
                                    ball.object3D.traverse((node) => {
                                        if (node.geometry) node.geometry.dispose();
                                        if (node.material) {
                                            if (Array.isArray(node.material)) {
                                                node.material.forEach(mat => {
                                                    if (mat.map) mat.map.dispose();
                                                    mat.dispose();
                                                });
                                            } else {
                                                if (node.material.map) node.material.map.dispose();
                                                node.material.dispose();
                                            }
                                        }
                                    });
                                }
                                if (ball.parentNode) {
                                    ball.parentNode.removeChild(ball);
                                    window.debugLog('Ball removed after hit animation');
                                }
                            }, 300);
                            
                            return;
                    }
                }
                
                // 地面に落ちたら削除（y < -2）
                if (currentPos.y < -2 || elapsedTime > 3) {
                    if (elapsedTime > 3) {
                        window.debugLog('Ball timeout after 3 seconds');
                    } else {
                        window.debugLog('Ball fell to ground');
                    }
                    
                    // ヒットしなかった場合、コンボをリセット
                    if (!ballData.hasHit) {
                        window.debugLog('Ball missed - Resetting combo');
                        window.comboCount = 0;
                        window.lastBallHit = false;
                        // コンボ表示を非表示
                        const comboDisplay = document.getElementById('comboDisplay');
                        if (comboDisplay) {
                            comboDisplay.setAttribute('visible', false);
                        }
                    }
                    
                    // 🚀 メモリ解放: THREE.jsオブジェクトを破棄
                    if (ball.object3D) {
                        ball.object3D.traverse((node) => {
                            if (node.geometry) node.geometry.dispose();
                            if (node.material) {
                                if (Array.isArray(node.material)) {
                                    node.material.forEach(mat => {
                                        if (mat.map) mat.map.dispose();
                                        mat.dispose();
                                    });
                                } else {
                                    if (node.material.map) node.material.map.dispose();
                                    node.material.dispose();
                                }
                            }
                        });
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
                    window.debugLog('Canvas listeners set up (bubble phase)');
                }
            },
            
            setupControllerListeners: function() {
                const leftController = document.getElementById('leftController');
                const rightController = document.getElementById('rightController');
                
                if (leftController) {
                    leftController.removeEventListener('triggerdown', this.shoot);
                    leftController.addEventListener('triggerdown', this.shoot);
                    window.debugLog('Left controller listener set up');
                }
                if (rightController) {
                    rightController.removeEventListener('triggerdown', this.shoot);
                    rightController.addEventListener('triggerdown', this.shoot);
                    window.debugLog('Right controller listener set up');
                }
            },
            
            onKeyDown: function (event) {
                // スペースキーが押された場合
                if (event.code === 'Space') {
                    event.preventDefault(); // デフォルトのスペースキー動作を防ぐ
                    this.shoot(event);
                    window.debugLog('space key pressed');
                }
            },
            
            onClick: function (event) {
                // 重複発火を防ぐ（touchstartとclickの両方が発火する場合に対応）
                const currentTime = Date.now();
                if (currentTime - this.lastShootTime < this.shootCooldown) {
                    window.debugLog('Click ignored (too soon after last shoot)');
                    event.stopPropagation();
                    event.preventDefault();
                    return;
                }
                
                // マウスクリックの場合（A-Frameのcanvas上でのみ）
                this.shoot(event);
                window.debugLog('mouse clicked on canvas');
            },
            
            onTouchStart: function (event) {
                // 重複発火を防ぐ
                const currentTime = Date.now();
                if (currentTime - this.lastShootTime < this.shootCooldown) {
                    window.debugLog('Touch ignored (too soon after last shoot)');
                    event.stopPropagation();
                    event.preventDefault();
                    return;
                }
                
                // スマホタップの場合（A-Frameのcanvas上でのみ）
                event.preventDefault(); // デフォルトのタッチ動作を防ぐ
                event.stopPropagation(); // イベントの伝播を防ぐ
                this.shoot(event);
                window.debugLog('screen tapped on canvas');
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
                    window.debugLog('Game not started, ignoring shoot');
                    return;
                }
                
                // ゲーム終了後は撃てない
                if (window.gameEnded) {
                    window.debugLog('Game ended, ignoring shoot');
                    return;
                }
                
                // ボールの同時描画数を制限（2個まで）
                if (window.activeBalls.length >= 2) {
                    window.debugLog('Ball limit reached (2), ignoring shoot');
                    return;
                }
                
                // 最後のshoot時刻を更新
                this.lastShootTime = Date.now();
                
                window.debugLog('=== Shoot function called ===');
                window.debugLog('Event type:', event.type);
                
                const sceneEl = this.el.sceneEl;
                const camera = this.el;
                
                // ボールエンティティを作成（GLBモデルを使用）
                const ball = document.createElement('a-entity');
                ball.setAttribute('gltf-model', 'cg/poke_ball_seiei.glb');
                ball.setAttribute('scale', '0.1 0.1 0.1'); // サイズ調整
                ball.setAttribute('rotation', '0 0 0');
                
                // 🚀 パフォーマンス改善: キャッシュしたカラーを使用し、リスナーを{once: true}で1回だけ実行
                ball.addEventListener('model-loaded', function onBallLoaded() {
                    const mesh = ball.getObject3D('mesh');
                    if (mesh) {
                        mesh.traverse((node) => {
                            if (node.isMesh && node.material) {
                                // マテリアルを明るくする（キャッシュしたカラーを使用）
                                if (window.cachedBallEmissiveColor) {
                                    node.material.emissive = window.cachedBallEmissiveColor;
                                }
                                node.material.emissiveIntensity = 0.1; // 発光強度
                                node.material.needsUpdate = true;
                            }
                        });
                    }
                }, { once: true }); // 🚀 1回だけ実行してリスナーを自動削除
                
                // 回転アニメーションを追加（飛んでいる間に回転）
                ball.setAttribute('animation__spin', {
                    property: 'rotation',
                    to: '-1080 0 0',
                    dur: 1000,
                    loop: true,
                    easing: 'linear'
                });
                
                // 位置と方向を計算
                let position = new THREE.Vector3();
                let direction = new THREE.Vector3();
                
                if (event.type === 'triggerdown') {
                    // VRコントローラーからの発射
                    window.debugLog('Shooting from VR controller');
                    const controller = event.target;
                    
                    // コントローラーの位置を取得
                    controller.object3D.getWorldPosition(position);
                    window.debugLog('Controller position:', position);
                    
                    // raycasterコンポーネントから方向を取得
                    const raycasterComponent = controller.components.raycaster;
                    if (raycasterComponent && raycasterComponent.raycaster) {
                        // raycasterの方向をコピー
                        direction.copy(raycasterComponent.raycaster.ray.direction).normalize();
                        window.debugLog('Using raycaster direction:', direction);
                    } else {
                        // raycasterがない場合はコントローラーのローカル前方向を使用
                        direction.set(0, 0, -1);
                        direction.applyQuaternion(controller.object3D.quaternion);
                        direction.normalize();
                        window.debugLog('Using controller quaternion direction:', direction);
                    }
                } else {
                    // スペースキー、マウスクリック、スマホタップからの発射
                    window.debugLog('Shooting from camera/input');
                    
                    // VRモードかどうかを確認
                    const isVRMode = sceneEl.is('vr-mode');
                    window.debugLog('Is VR Mode:', isVRMode);
                    
                    if (isVRMode) {
                        // VRモード時
                        if (sceneEl.camera) {
                            // シーンのアクティブカメラから取得
                            sceneEl.camera.getWorldPosition(position);
                            direction.set(0, 0, -1);
                            direction.applyQuaternion(sceneEl.camera.quaternion);
                            direction.normalize();
                            window.debugLog('VR Mode - Using scene.camera');
                        } else {
                            // フォールバック
                            camera.object3D.getWorldPosition(position);
                            direction.set(0, 0, -1);
                            direction.applyQuaternion(camera.object3D.quaternion);
                            direction.normalize();
                            window.debugLog('VR Mode - Using camera.object3D');
                        }
                    } else {
                        // 通常モード時
                        camera.object3D.getWorldPosition(position);
                        direction.set(0, 0, -1);
                        direction.applyQuaternion(camera.object3D.quaternion);
                        direction.normalize();
                        window.debugLog('Normal Mode - Using camera.object3D');
                    }
                    
                    window.debugLog('Camera position:', position);
                    window.debugLog('Camera direction:', direction);
                }
                
                // コントローラー/カメラの少し前にボールを配置
                const startPos = position.clone().add(direction.clone().multiplyScalar(0.3));
                ball.setAttribute('position', `${startPos.x} ${startPos.y} ${startPos.z}`);
                sceneEl.appendChild(ball);
                
                window.debugLog('Ball created at:', startPos);

                // 物理演算で放物線を描く
                const gravity = -4.9; // 重力加速度 (m/s^2)
                const initialSpeed = 30; // 初速度 (m/s)  10→30へ
                const velocity = direction.clone().multiplyScalar(initialSpeed); // 初速度ベクトル
                
                window.debugLog('Initial velocity:', velocity);
                window.debugLog('=== Ball added to activeBalls array ===');
                
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
        
        // モデルを個別の経路で移動させるコンポーネント
        AFRAME.registerComponent('approach-camera', {
            schema: {
                speed: { type: 'number', default: 0.25 },        // 移動速度（m/s）
                startPos: { type: 'vec3', default: {x: 0, y: 0, z: -5} }, // 始点
                endPos: { type: 'vec3', default: {x: 0, y: 0, z: -1} },   // 終点
                useCamera: { type: 'boolean', default: true },   // カメラを終点にするか
                autoRespawn: { type: 'boolean', default: true }, // 自動再描画
                waitTime: { type: 'number', default: 3000 }      // 終点到着後の待機時間（ms）
            },
            
            init: function() {
                this.isMoving = true;
                this.hasReachedEnd = false;
                this.reachedTime = 0;
                this.startPosition = null;
                this.targetPosition = null;
                this.isRespawning = false; // 再描画中フラグ
                this.isPlayingAlert = false; // アラート音再生中フラグ
                this.alertSound = null; // アラート音の参照
                
                // 🚀 最適化: Vector3を事前生成（tick内でのnew回避）
                this._direction = new THREE.Vector3();
                this._cameraPos = new THREE.Vector3();
                this._lastAlertCheck = 0; // アラート音チェックのスロットリング用
                
                // 始点を設定（コンポーネント指定がなければ現在位置）
                if (this.data.startPos.x === 0 && this.data.startPos.y === 0 && this.data.startPos.z === -5) {
                    // デフォルト値の場合は現在位置を使用
                    const currentPos = this.el.getAttribute('position');
                    this.startPosition = new THREE.Vector3(currentPos.x, currentPos.y, currentPos.z);
                } else {
                    this.startPosition = new THREE.Vector3(this.data.startPos.x, this.data.startPos.y, this.data.startPos.z);
                }
                
                // アラート音の参照を取得
                this.alertSound = document.getElementById('sound_alert');
                
                window.debugLog('approach-camera initialized:', {
                    speed: this.data.speed,
                    useCamera: this.data.useCamera,
                    endPos: this.data.endPos,
                    autoRespawn: this.data.autoRespawn
                });
            },
            
            tick: function(time, timeDelta) {
                // ゲームが終了している場合は、移動・再描画・アラート音をすべて停止
                if (window.gameEnded) {
                    // アラート音を停止
                    if (this.isPlayingAlert && this.alertSound) {
                        this.alertSound.pause();
                        this.alertSound.currentTime = 0;
                        this.isPlayingAlert = false;
                        
                        // このモデルがアラート音を鳴らしていた場合、グローバル状態もリセット
                        if (window.currentAlertModel === this.el.id) {
                            window.alertSoundPlaying = false;
                            window.currentAlertModel = null;
                        }
                    }
                    // 移動を停止
                    this.isMoving = false;
                    return;
                }
                
                // ゲームが開始されていない場合は移動しない
                if (!window.gameStarted) return;
                
                if (!this.isMoving) {
                    // 終点に到着している場合、待機時間をチェック（ゲーム中のみ）
                    if (this.hasReachedEnd && this.data.autoRespawn && !this.isRespawning && !window.gameEnded) {
                        const elapsed = Date.now() - this.reachedTime;
                        if (elapsed >= this.data.waitTime) {
                            window.debugLog('Auto-respawn triggered after', this.data.waitTime, 'ms');
                            this.isRespawning = true; // 再描画中フラグを立てる
                            this.despawnAndRespawn();
                        }
                    }
                    return;
                }
                
                // 終点を取得（初回のみ設定）
                if (!this.targetPosition) {
                    if (this.data.useCamera) {
                        const sceneEl = this.el.sceneEl;
                        const camera = sceneEl.camera ? sceneEl.camera.el : document.querySelector('[camera]');
                        if (!camera) return;
                        
                        this.targetPosition = new THREE.Vector3();
                        camera.object3D.getWorldPosition(this.targetPosition);
                        this.targetPosition.y = 0; // 地面レベルに調整
                        window.debugLog('Target set to camera position:', this.targetPosition);
                    } else {
                        // useCameraがfalseの場合はendPosを使用
                        this.targetPosition = new THREE.Vector3(this.data.endPos.x, this.data.endPos.y, this.data.endPos.z);
                        window.debugLog('Target set to fixed endPos:', this.targetPosition);
                    }
                }
                
                // モデルの現在位置を取得
                const modelPos = this.el.object3D.position;
                
                // 🚀 アラート音機能を無効化（パフォーマンス改善のため）
                // カメラとの距離チェック処理をスキップ
                
                // 🚀 最適化: 事前生成したVector3を再利用（毎フレームのnew回避）
                const direction = this._direction;
                direction.subVectors(this.targetPosition, modelPos);
                direction.y = 0; // Y軸方向は移動しない（地面を滑るように）
                
                const distance = direction.length();
                
                // 終点に到着したか判定（Boss: 1.6m以内、それ以外: 0.99m以内）
                const isBoss = this.el.id === 'modelGroup_boss';
                const stopDistance = isBoss ? 1.6 : 0.99;
                
                if (distance < stopDistance) {
                    this.isMoving = false;
                    this.hasReachedEnd = true;
                    this.reachedTime = Date.now();
                    window.debugLog(`${isBoss ? 'BOSS' : 'Model'} reached end position (${stopDistance}m). Waiting for`, this.data.waitTime, 'ms before respawn');
                    return;
                }
                
                // 方向を正規化して速度を適用
                direction.normalize();
                const moveDistance = this.data.speed * (timeDelta / 1000); // timeDeltaはミリ秒
                direction.multiplyScalar(moveDistance);
                
                // 新しい位置を設定
                modelPos.add(direction);
                
                // 進行方向を向くように回転（Y軸のみ）
                const angle = Math.atan2(direction.x, direction.z);
                this.el.object3D.rotation.y = angle;
            },
            
            // 外部から移動を停止できるメソッド
            stop: function() {
                this.isMoving = false;
            },
            
            // モデルを消去して再描画
            despawnAndRespawn: function() {
                // ゲーム終了時は再描画しない
                if (window.gameEnded) {
                    window.debugLog('Game ended, skipping despawn and respawn');
                    return;
                }
                
                const modelGroup = this.el;
                const modelId = modelGroup.id;
                const modelEntity = modelGroup.querySelector('[gltf-model]');
                const gltfModelSrc = modelEntity ? modelEntity.getAttribute('gltf-model') : null;
                
                if (!gltfModelSrc) {
                    console.error('Could not find gltf-model for respawn');
                    return;
                }
                
                window.debugLog('Despawning model:', modelId);
                
                // アラート音を停止（グローバル変数もリセット）
                if (this.isPlayingAlert && this.alertSound) {
                    this.alertSound.pause();
                    this.alertSound.currentTime = 0;
                    this.isPlayingAlert = false;
                    
                    // このモデルがアラート音を鳴らしていた場合、グローバル状態もリセット
                    if (window.currentAlertModel === modelId) {
                        window.alertSoundPlaying = false;
                        window.currentAlertModel = null;
                    }
                    window.debugLog('Alert sound stopped - model despawning');
                }
                
                // フェードアウト
                modelGroup.setAttribute('animation__fadeout', {
                    property: 'scale',
                    to: '0 0 0',
                    dur: 500,
                    easing: 'easeInQuad'
                });
                
                // フェードアウト後に削除して再生成 - 🚀 registerTimeout使用
                window.registerTimeout(() => {
                    // 🚀 改善: 削除前にメモリを解放
                    if (modelGroup.object3D) {
                        modelGroup.object3D.traverse((node) => {
                            if (node.geometry) {
                                node.geometry.dispose();
                            }
                            if (node.material) {
                                if (Array.isArray(node.material)) {
                                    node.material.forEach(mat => {
                                        if (mat.map) mat.map.dispose();
                                        mat.dispose();
                                    });
                                } else {
                                    if (node.material.map) node.material.map.dispose();
                                    node.material.dispose();
                                }
                            }
                        });
                    }
                    
                    // パターン使用状況をクリア
                    if (window.usedPatterns && window.usedPatterns[modelId] !== undefined) {
                        delete window.usedPatterns[modelId];
                    }
                    
                    if (modelGroup.parentNode) {
                        modelGroup.parentNode.removeChild(modelGroup);
                    }
                    
                    // hit-boxコンポーネントのrespawnModel関数を呼び出し
                    // グローバルに再描画関数を登録
                    if (window.respawnModelGlobal) {
                        window.respawnModelGlobal(modelId, gltfModelSrc);
                    }
                }, 500);
            },
            
            // リセットメソッド（リスタート時に使用）
            reset: function() {
                const modelId = this.el.id;
                
                // アラート音を停止（グローバル変数もリセット）
                if (this.isPlayingAlert && this.alertSound) {
                    this.alertSound.pause();
                    this.alertSound.currentTime = 0;
                    this.isPlayingAlert = false;
                    
                    // このモデルがアラート音を鳴らしていた場合、グローバル状態もリセット
                    if (window.currentAlertModel === modelId) {
                        window.alertSoundPlaying = false;
                        window.currentAlertModel = null;
                    }
                    window.debugLog('Alert sound stopped - component reset');
                }
                
                this.isMoving = true;
                this.hasReachedEnd = false;
                this.reachedTime = 0;
                this.targetPosition = null;
                this.isRespawning = false; // 再描画中フラグもリセット
                window.debugLog('Approach-camera component reset');
            },
            
            // 🚀 追加: コンポーネント削除時のクリーンアップ
            remove: function() {
                // アラートサウンドを停止
                if (this.isPlayingAlert) {
                    const alertSound = document.getElementById('sound_alert');
                    if (alertSound) {
                        alertSound.pause();
                        alertSound.currentTime = 0;
                    }
                }
                // Vector3オブジェクトの参照を解放
                this._direction = null;
                this.startPosition = null;
                this.targetPosition = null;
                window.debugLog('Approach-camera component removed and cleaned up');
            }
        });
        
        AFRAME.registerComponent('hit-box', {
            schema: {
                isBoss: { type: 'boolean', default: false } // ボスかどうかのフラグ
            },
            
            init: function () {
                const modelGroup = this.el.parentEl; // 親エンティティ（modelGroup）を取得
                const modelEntity = modelGroup.querySelector('[gltf-model]'); // gltf-modelを持つエンティティを取得
                let hitFlag = false;
                let hitCount = 0; // ヒット回数をカウント

                // モデルの情報を保存
                const modelId = modelGroup.id;
                const gltfModelSrc = modelEntity ? modelEntity.getAttribute('gltf-model') : null;
                const isBoss = this.data.isBoss; // ボスかどうか
                
                // グローバルなrespawn関数を登録（初回のみ）
                if (!window.respawnModelGlobal) {
                    window.respawnModelGlobal = this.respawnModel.bind(this);
                }

                // ボールがヒットしたときのみ発火する独自イベント 'ball-hit' を監視
                this.el.addEventListener('ball-hit', () => {
                    if(!hitFlag) {
                        // ヒット回数を増やす
                        hitCount++;
                        window.debugLog(`Model hit! (${hitCount} hits) - ${isBoss ? 'BOSS' : 'Normal'} - Level ${window.currentLevel}`, modelEntity);
                        
                        // 必要なヒット数を判定（BOSS: 15回、Level 1: 1回、Level 2: 2回）
                        const requiredHits = isBoss ? 15 : (window.currentLevel === 2 ? 2 : 1);
                        
                        // 必要なヒット数に達していない場合
                        if (hitCount < requiredHits) {
                            window.debugLog(`Need ${requiredHits - hitCount} more hit(s) to destroy (${isBoss ? 'BOSS' : 'Level ' + window.currentLevel})`);
                            
                            // ヒット音を再生
                            const hitSound = document.getElementById('sound_hit');
                            if (hitSound) {
                                hitSound.currentTime = 0;
                                hitSound.play().then(() => {
                                    window.debugLog('Hit sound played (not destroyed yet)');
                                }).catch(err => {
                                    window.debugLog('Hit sound play failed:', err);
                                });
                            }
                            
                            // ダメージエフェクトを表示（段階的なフラッシュ）
                            if (modelEntity) {
                                let flashColor;
                                
                                // 🚀 パフォーマンス改善: キャッシュしたカラーを使用
                                const colors = window.cachedFlashColors || {
                                    blue: new THREE.Color(0x0000FF),
                                    green: new THREE.Color(0x00FF00),
                                    yellow: new THREE.Color(0xFFFF00),
                                    red: new THREE.Color(0xFF0000),
                                    black: new THREE.Color(0x000000)
                                };
                                
                                // Bossの場合は段階的に色を変更
                                if (isBoss) {
                                    if (hitCount >= 1 && hitCount <= 3) {
                                        flashColor = colors.blue; // 青フラッシュ（1-3回目）
                                    } else if (hitCount >= 4 && hitCount <= 6) {
                                        flashColor = colors.green; // 緑フラッシュ（4-6回目）
                                    } else if (hitCount >= 7 && hitCount <= 10) {
                                        flashColor = colors.yellow; // 黄フラッシュ（7-10回目）
                                    } else if (hitCount >= 11 && hitCount <= 14) {
                                        flashColor = colors.red; // 赤フラッシュ（11-14回目）
                                    }
                                } else {
                                    flashColor = colors.red; // 通常モデルは赤
                                }
                                
                                // フラッシュエフェクトを適用
                                const mesh = modelEntity.getObject3D('mesh');
                                if (mesh) {
                                    mesh.traverse((node) => {
                                        if (node.isMesh && node.material) {
                                            const originalEmissive = node.material.emissive ? node.material.emissive.clone() : colors.black;
                                            node.material.emissive = flashColor;
                                            node.material.emissiveIntensity = 0.5;
                                            
                                            // 0.2秒後に元に戻す - 🚀 registerTimeout使用
                                            window.registerTimeout(() => {
                                                node.material.emissive = originalEmissive;
                                                node.material.emissiveIntensity = 0;
                                            }, 200);
                                        }
                                    });
                                }
                            }
                            
                            // まだ倒れないので処理を終了
                            return;
                        }
                        
                        // 必要なヒット数に達した場合、以下の処理を実行
                        hitFlag = true;
                        window.debugLog(`${isBoss ? 'BOSS' : 'Model'} destroyed after ${hitCount} hits!`);
                        
                        // 倒したゾンビの数をインクリメント
                        window.enemiesDefeated++;
                        window.debugLog('Enemies Defeated:', window.enemiesDefeated);
                        
                        // 【重要】当たり判定オブジェクトを即座に消去（anime02再生中に再ヒットを防ぐ）
                        const hitBox = this.el;
                        if (hitBox && hitBox.parentNode) {
                            hitBox.parentNode.removeChild(hitBox);
                            window.debugLog('Hit box removed immediately');
                        }
                        
                        // アラート音を停止（ヒット時・グローバル変数もリセット）
                        const approachComponent = modelGroup.components['approach-camera'];
                        if (approachComponent && approachComponent.isPlayingAlert && approachComponent.alertSound) {
                            approachComponent.alertSound.pause();
                            approachComponent.alertSound.currentTime = 0;
                            approachComponent.isPlayingAlert = false;
                            
                            // このモデルがアラート音を鳴らしていた場合、グローバル状態もリセット
                            if (window.currentAlertModel === modelId) {
                                window.alertSoundPlaying = false;
                                window.currentAlertModel = null;
                            }
                            window.debugLog('Alert sound stopped - model hit');
                        }
                        
                        // ヒット音を再生
                        const hitSound = document.getElementById('sound_hit');
                        if (hitSound) {
                            hitSound.currentTime = 0; // 最初から再生
                            hitSound.play().then(() => {
                                window.debugLog('Hit sound played');
                            }).catch(err => {
                                window.debugLog('Hit sound play failed:', err);
                            });
                        }
                        
                        // カメラとモデルの距離を計算してスコア化
                        const sceneEl = document.querySelector('a-scene');
                        const camera = sceneEl.camera ? sceneEl.camera.el : document.querySelector('[camera]');
                        
                        let distance = 0;
                        if (camera) {
                            // 🚀 最適化: キャッシュされたVector3を再利用
                            const modelPos = window._cachedModelPos;
                            const cameraPos = window._cachedCameraPos;
                            
                            modelGroup.object3D.getWorldPosition(modelPos);
                            camera.object3D.getWorldPosition(cameraPos);
                            
                            // 距離を計算（メートル単位）
                            distance = modelPos.distanceTo(cameraPos);
                            window.debugLog('Hit distance from camera:', distance.toFixed(2), 'm');
                        }
                        
                        // 基本スコアを計算（距離を10倍して小数第一位まで）
                        let baseScore = Math.round(distance * 100) / 10; // 小数第一位まで
                        
                        // ボスボーナス：10倍スコア
                        if (isBoss) {
                            baseScore *= 10;
                            window.debugLog('🎯 BOSS BONUS: Score multiplied by 10x =', baseScore);
                        }
                        
                        // コンボカウントを増やす（スコア計算前に）
                        window.comboCount++;
                        window.lastBallHit = true;
                        window.debugLog('Combo Count:', window.comboCount);
                        
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
                        window.debugLog('Base Score:', baseScore, 'Multiplier:', comboMultiplier, 'Final Score:', finalScore);
                        
                        // 合計スコアに加算
                        window.totalScore += finalScore;
                        window.debugLog('Total Score:', window.totalScore.toFixed(1));
                        
                        // 最大コンボ数を更新
                        if (window.comboCount > window.maxComboCount) {
                            window.maxComboCount = window.comboCount;
                            window.debugLog('New Max Combo:', window.maxComboCount);
                        }
                        
                        // コンボ表示を更新（2連続以上の場合）
                        // ※ showCombo関数は使用しない（スコアテキストに統合済み）
                        // if (window.comboCount >= 2) {
                        //     this.showCombo(window.comboCount, modelGroup);
                        // }
                        
                        // リアルタイムスコア表示を更新
                        const currentScoreText = document.getElementById('currentScore');
                        if (currentScoreText) {
                            currentScoreText.setAttribute('value', `SCORE: ${window.totalScore.toFixed(1)}`);
                        }
                        
                        // 距離に応じたスコアテキストのサイズを決定（3段階）
                        let scoreWidth = 6; // 基準サイズ（近距離）
                        if (distance > 8) {
                            scoreWidth = 16; // 遠距離（8m以上）：約2.7倍
                        } else if (distance > 4) {
                            scoreWidth = 10; // 中距離（4-8m）：約1.7倍
                        }
                        // 4m以内は基準サイズ（6 = 1倍）
                        
                        // ボーナス時はさらに1.1倍
                        if (comboBonus) {
                            scoreWidth = scoreWidth * 1.1;
                        }
                        
                        window.debugLog('Distance:', distance.toFixed(2), 'm, Score width:', scoreWidth);

                        // スコアテキストをモデルの上に表示
                        const scoreText = document.createElement('a-text');
                        const scoreDisplay = comboBonus ? `Combo ${window.comboCount} ${comboBonus}\n${finalScore.toFixed(1)}pt` : `${finalScore.toFixed(1)}pt`;
                        scoreText.setAttribute('value', scoreDisplay);
                        scoreText.setAttribute('align', 'center');
                        scoreText.setAttribute('color', comboBonus ? '#FF6600' : '#FFD700'); // ボーナス時はオレンジ、通常は金色
                        scoreText.setAttribute('width', scoreWidth); // 距離に応じたサイズ
                        scoreText.setAttribute('font', 'mozillavr'); // コンボ時も通常時もmozillavr
                        scoreText.setAttribute('shader', 'msdf');
                        scoreText.setAttribute('anchor', 'center');
                        
                        // ワールド座標を取得（キャッシュ使用）
                        modelGroup.object3D.getWorldPosition(window._cachedModelPos);
                        const scoreModelPos = window._cachedModelPos;
                        
                        // ワールド座標に配置
                        scoreText.setAttribute('position', `${scoreModelPos.x} ${scoreModelPos.y + 0.5} ${scoreModelPos.z}`);
                        
                        // カメラの方を向く（独自face-cameraコンポーネント）
                        scoreText.setAttribute('face-camera', '');
                        
                        // シーンに直接追加
                        sceneEl.appendChild(scoreText);
                        window.debugLog('Score text added to scene at world position');
                        
                        // 🚀🚀 パフォーマンス改善: パーティクルエフェクト無効化（GPUメモリ節約）
                        // 通常ヒット時（コンボなし）のパーティクル表示 - 無効化
                        /*
                        if (!comboBonus) {
                            const normalParticle = document.getElementById('particle-normal');
                            if (normalParticle) {
                                modelGroup.object3D.getWorldPosition(window._cachedParticlePos);
                                const normalParticlePos = window._cachedParticlePos;
                                normalParticle.setAttribute('position', `${normalParticlePos.x} ${normalParticlePos.y + 0.5} ${normalParticlePos.z}`);
                                normalParticle.setAttribute('visible', true);
                                window.registerTimeout(() => {
                                    normalParticle.setAttribute('visible', false);
                                }, 1000);
                            }
                        }
                        */
                        
                        // ボーナス時のエフェクト - パーティクル無効化、スケールアニメーションのみ維持
                        if (comboBonus) {
                            // 🚀🚀 パーティクルエフェクト無効化（GPUメモリ節約）
                            /*
                            let particleId = 'particle-tier1';
                            if (bonusTier === 1) {
                                particleId = 'particle-tier1';
                            } else if (bonusTier === 2) {
                                particleId = 'particle-tier2';
                            } else if (bonusTier === 3) {
                                particleId = 'particle-tier3';
                            }
                            const particle = document.getElementById(particleId);
                            if (particle) {
                                const modelPos = window._cachedParticlePos;
                                modelGroup.object3D.getWorldPosition(modelPos);
                                particle.setAttribute('position', `${modelPos.x} ${modelPos.y + 0.5} ${modelPos.z}`);
                                particle.setAttribute('visible', true);
                                window.registerTimeout(() => {
                                    particle.setAttribute('visible', false);
                                }, 1500);
                            }
                            */
                            
                            // スコアテキストを拡大縮小アニメーション（ボーナスレベルに応じて拡大率を変更）
                            const scaleMultiplier = 1.1 + (bonusTier * 0.2); // 1.3, 1.5, 1.7
                            scoreText.setAttribute('scale', `${scaleMultiplier} ${scaleMultiplier} ${scaleMultiplier}`);
                            scoreText.setAttribute('animation__scale', {
                                property: 'scale',
                                to: '1 1 1',
                                dur: 400,
                                easing: 'easeOutElastic'
                            });
                            
                            // 注意: animation__rotateは削除（face-cameraと競合するため）
                        }
                        
                        // スコアテキストをフェードアウトさせる（ワールド座標で上に移動）
                        window.registerTimeout(() => {
                            const currentPos = scoreText.getAttribute('position');
                            scoreText.setAttribute('animation__scoreup', {
                                property: 'position',
                                to: `${currentPos.x} ${currentPos.y + 0.5} ${currentPos.z}`, // 現在位置から0.5m上に移動
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
                            
                            // アニメーション完了後に削除
                            window.registerTimeout(() => {
                                if (scoreText.parentNode) {
                                    scoreText.parentNode.removeChild(scoreText);
                                }
                            }, 1500);
                        }, 100);
                        
                        // カメラへの移動を停止（既に宣言済みのapproachComponentを使用）
                        if (approachComponent) {
                            approachComponent.stop();
                            window.debugLog('Stopped approaching camera');
                        }

                        // anime02に切り替え（1.5秒間再生）
                        if (modelEntity) {
                            modelEntity.removeAttribute('animation-mixer'); // 一旦削除
                            window.registerTimeout(() => {
                                modelEntity.setAttribute('animation-mixer', 'clip: anime02; loop: repeat; timeScale: 1');
                                window.debugLog('Playing anime02 for 1.5 seconds');
                                
                                // BOSSの場合は死亡音を再生
                                if (isBoss) {
                                    const dieSound = document.getElementById('sound_zombie_die');
                                    if (dieSound) {
                                        dieSound.currentTime = 0;
                                        dieSound.play().then(() => {
                                            window.debugLog('BOSS die sound played');
                                        }).catch(err => {
                                            window.debugLog('BOSS die sound play failed:', err);
                                        });
                                    }
                                }
                            }, 50);
                        }
                        
                        // 1.5秒後にフェードアウト開始（anime03はスキップ）
                        window.registerTimeout(() => {
                            if (modelGroup && modelGroup.parentNode) {
                                window.debugLog('Starting fadeout for modelGroup');
                                // フェードアウトアニメーション（0.5秒かけて縮小）
                                modelGroup.setAttribute('animation__modelfadeout', {
                                    property: 'scale',
                                    to: '0 0 0',
                                    dur: 500,
                                    easing: 'easeInQuad'
                                });
                                
                                // フェードアウト完了後に削除して、4秒後に再描画
                                window.registerTimeout(() => {
                                    if (modelGroup.parentNode) {
                                        modelGroup.parentNode.removeChild(modelGroup);
                                        window.debugLog('Model removed');
                                        
                                        // パターン使用状況をクリア（他のモデルがこのパターンを使用可能に）
                                        if (window.usedPatterns && window.usedPatterns[modelId] !== undefined) {
                                            delete window.usedPatterns[modelId];
                                            window.debugLog(`📍 Pattern freed for model ${modelId}`);
                                        }
                                        
                                        // リスポーン中フラグを設定（重複防止）
                                        if (!window.respawningModels) {
                                            window.respawningModels = {};
                                        }
                                        
                                        // 既にこのモデルがリスポーン待機中なら無視
                                        if (window.respawningModels[modelId]) {
                                            window.debugLog('⚠️ Model', modelId, 'already scheduled for respawn, skipping duplicate');
                                            return;
                                        }
                                        
                                        // リスポーン待機中としてマーク
                                        window.respawningModels[modelId] = true;
                                        
                                        // 4秒後に別の場所に再描画
                                        window.registerTimeout(() => {
                                            this.respawnModel(modelId, gltfModelSrc);
                                            // リスポーン完了後、フラグをクリア
                                            delete window.respawningModels[modelId];
                                        }, 4000);
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
                    
                    // カメラとモデルの距離を取得
                    const sceneEl = document.querySelector('a-scene');
                    const camera = sceneEl.camera ? sceneEl.camera.el : document.querySelector('[camera]');
                    let distance = 0;
                    
                    if (camera) {
                        // 🚀 最適化: キャッシュされたVector3を再利用
                        const modelPos = window._cachedModelPos;
                        const cameraPos = window._cachedCameraPos;
                        modelGroup.object3D.getWorldPosition(modelPos);
                        camera.object3D.getWorldPosition(cameraPos);
                        distance = modelPos.distanceTo(cameraPos);
                    }
                    
                    // 距離に応じたコンボテキストのサイズを決定（3段階）
                    let comboWidth = 8; // 基準サイズ
                    if (distance > 8) {
                        comboWidth = 16; // 遠距離（8m以上）：2倍
                    } else if (distance > 4) {
                        comboWidth = 12; // 中距離（4-8m）：1.5倍
                    }
                    // 4m以内は基準サイズ（8 = 1倍）
                    
                    window.debugLog('Combo - Distance:', distance.toFixed(2), 'm, Combo width:', comboWidth);

                    // コンボテキストを作成
                    const comboText = document.createElement('a-text');
                    comboText.setAttribute('value', `Combo ${comboCount}!`);
                    comboText.setAttribute('align', 'center');
                    comboText.setAttribute('color', '#FF6600'); // オレンジ色
                    comboText.setAttribute('width', comboWidth); // 距離に応じたサイズ
                    comboText.setAttribute('font', 'roboto');
                    comboText.setAttribute('shader', 'msdf');
                    comboText.setAttribute('anchor', 'center');
                    comboText.classList.add('combo-text');
                    
                    // ワールド座標を取得（キャッシュ使用）
                    modelGroup.object3D.getWorldPosition(window._cachedModelPos);
                    const comboModelPos = window._cachedModelPos;
                    
                    // スコアの上に配置（スコアは+0.5なので+1.0）
                    comboText.setAttribute('position', `${comboModelPos.x} ${comboModelPos.y + 1.0} ${comboModelPos.z}`);
                    
                    // カメラの方を向く（独自face-cameraコンポーネント）
                    comboText.setAttribute('face-camera', '');
                    
                    // シーンに直接追加
                    sceneEl.appendChild(comboText);
                    window.debugLog('Combo text added to scene at world position');
                    
                    // コンボテキストをフェードアウトさせる（ワールド座標で上に移動）
                    window.registerTimeout(() => {
                        const currentPos = comboText.getAttribute('position');
                        comboText.setAttribute('animation__fadeup', {
                            property: 'position',
                            to: `${currentPos.x} ${currentPos.y + 0.5} ${currentPos.z}`, // 現在位置から0.5m上に移動
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
                        window.registerTimeout(() => {
                            if (comboText.parentNode) {
                                comboText.parentNode.removeChild(comboText);
                            }
                        }, 1500);
                    }, 100);
                    
                    window.debugLog('Combo displayed on model:', comboCount);
                }
            },

            // モデルを再描画する関数
            respawnModel: function(modelId, gltfModelSrc) {
                // ゲームが終了している場合はリスポーンしない
                if (window.gameEnded || !window.gameStarted) {
                    window.debugLog('Game ended, no respawn');
                    return;
                }
                
                // Level 1の場合、modelGroup_04, 05, 06はリスポーンしない
                if (window.currentLevel === 1 && (modelId === 'modelGroup_04' || modelId === 'modelGroup_05' || modelId === 'modelGroup_06')) {
                    window.debugLog('Level 1: Skipping', modelId, 'respawn');
                    return;
                }
                
                window.debugLog('Respawning model:', modelId);
                const sceneEl = document.querySelector('a-scene');
                
                // 【重要】既存の同IDモデルを完全に削除（重複防止）
                const existingModel = document.getElementById(modelId);
                if (existingModel) {
                    window.debugLog('⚠️ WARNING: Model', modelId, 'already exists! Removing duplicate...');
                    
                    // 🚀 改善: THREE.jsメモリ解放（respawnModel時）
                    if (existingModel.object3D) {
                        existingModel.object3D.traverse((node) => {
                            if (node.geometry) {
                                node.geometry.dispose();
                            }
                            if (node.material) {
                                if (Array.isArray(node.material)) {
                                    node.material.forEach(mat => mat.dispose());
                                } else {
                                    node.material.dispose();
                                }
                            }
                        });
                    }
                    
                    if (existingModel.parentNode) {
                        existingModel.parentNode.removeChild(existingModel);
                    }
                    // 削除後、少し待機してからリスポーン
                    window.registerTimeout(() => {
                        this.createNewModel(modelId, gltfModelSrc, sceneEl);
                    }, 100);
                    return;
                }
                
                // 新規作成
                this.createNewModel(modelId, gltfModelSrc, sceneEl);
            },
            
            // モデル作成処理を分離（重複防止のため）
            createNewModel: function(modelId, gltfModelSrc, sceneEl) {
                window.debugLog('Creating new model:', modelId);
                
                // ランダムパターン設定（10パターン）
                const allMovementPatterns = [
                    // パターン1: 左後方(-3, 0, -3)からカメラ(0, 0, 0)へ - 距離: 4.2m - Level 1対象
                    {
                        startPos: { x: -3, y: 0, z: -3 },
                        speed: 0.25,
                        useCamera: true,
                        waitTime: 4000
                    },
                    // パターン2: 正面奥(0, 0, -4)からカメラ(0, 0, 0)へ - 距離: 4.0m - Level 1対象
                    {
                        startPos: { x: 0, y: 0, z: -4 },
                        speed: 0.25,
                        useCamera: true,
                        waitTime: 4000
                    },
                    // パターン3: 右後方(3, 0, -3)からカメラ(0, 0, 0)へ - 距離: 4.2m - Level 1対象
                    {
                        startPos: { x: 3, y: 0, z: -3 },
                        speed: 0.25,
                        useCamera: true,
                        waitTime: 4000
                    },
                    // パターン4: 右側(4, 0, 0)からカメラ(0, 0, 0)へ - 距離: 4.0m - Level 1対象
                    {
                        startPos: { x: 4, y: 0, z: 0 },
                        speed: 0.25,
                        useCamera: true,
                        waitTime: 4000
                    },
                    // パターン5: 右前方(5, 0, 3)からカメラ(0, 0, 0)へ - 距離: 5.8m - Level 2対象
                    {
                        startPos: { x: 5, y: 0, z: 3 },
                        speed: 0.25,
                        useCamera: true,
                        waitTime: 4000
                    },
                    // パターン6: 左側(-6, 0, 0)からカメラ(0, 0, 0)へ - 距離: 6.0m - Level 2対象
                    {
                        startPos: { x: -6, y: 0, z: 0 },
                        speed: 0.25,
                        useCamera: true,
                        waitTime: 4000
                    },
                    // パターン7: 正面前方(0, 0, 3)からカメラ(0, 0, 0)へ - 距離: 3.0m - Level 2対象
                    {
                        startPos: { x: 0, y: 0, z: 4 },
                        speed: 0.15,
                        useCamera: true,
                        waitTime: 4000
                    },
                    // パターン8: 右斜め前(3, 0, 6)からカメラ(0, 0, 0)へ - 距離: 6.7m - Level 2対象
                    {
                        startPos: { x: 3, y: 0, z: 6 },
                        speed: 0.25,
                        useCamera: true,
                        waitTime: 4000
                    },
                    // パターン9: 左斜め前(-4, 0, 6)からカメラ(0, 0, 0)へ - 距離: 7.2m - Level 2対象
                    {
                        startPos: { x: -4, y: 0, z: 6 },
                        speed: 0.25,
                        useCamera: true,
                        waitTime: 4000
                    },
                    // パターン10: 左後方(-7, 0, 2)からカメラ(0, 0, 0)へ - 距離: 7.3m - Level 2対象
                    {
                        startPos: { x: -7, y: 0, z: 2 },
                        speed: 0.25,
                        useCamera: true,
                        waitTime: 4000
                    }
                ];
                
                // レベルに応じてパターンをフィルタリング
                const movementPatterns = window.currentLevel === 1 
                    ? allMovementPatterns.slice(0, 4)  // Level 1: パターン1-4のみ
                    : allMovementPatterns;              // Level 2: 全パターン1-10
                
                // スピード倍率（Level 2は1.2倍速）
                const speedMultiplier = window.currentLevel === 2 ? 1.2 : 1.0;
                
                window.debugLog('Level:', window.currentLevel, 'Available patterns:', movementPatterns.length, 'Speed multiplier:', speedMultiplier);
                
                // 使用可能なパターンを取得（他のモデルと重複しない）
                const { pattern: randomPattern, index: patternIndex } = window.getAvailablePattern(movementPatterns, modelId);
                
                let startPos = randomPattern.startPos;
                let endPos = randomPattern.endPos || null;
                let speed = randomPattern.speed * speedMultiplier; // スピード倍率を適用
                let useCamera = randomPattern.useCamera;
                let waitTime = randomPattern.waitTime;
                
                // 始点から終点への角度を計算
                let rotation;
                if (useCamera) {
                    // カメラ方向への角度（始点から原点方向）
                    rotation = Math.atan2(startPos.x, -startPos.z) * (180 / Math.PI);
                } else {
                    // 固定終点への角度
                    const dx = endPos.x - startPos.x;
                    const dz = endPos.z - startPos.z;
                    rotation = Math.atan2(dx, -dz) * (180 / Math.PI);
                }
                
                window.debugLog('Selected random pattern:', {
                    patternIndex: patternIndex,
                    startPos: startPos,
                    endPos: endPos,
                    speed: speed,
                    useCamera: useCamera
                });
                
                // 新しいモデルグループを作成
                const newModelGroup = document.createElement('a-entity');
                newModelGroup.setAttribute('id', modelId);
                newModelGroup.setAttribute('position', `${startPos.x} ${startPos.y} ${startPos.z}`);
                newModelGroup.setAttribute('rotation', `0 ${rotation} 0`);
                newModelGroup.setAttribute('scale', '0 0 0'); // 最初は見えない状態
                
                // 個別設定でapproach-cameraコンポーネントを追加
                const cameraConfig = {
                    speed: speed,
                    startPos: startPos,
                    useCamera: useCamera,
                    autoRespawn: true,
                    waitTime: waitTime
                };
                
                // endPosが設定されている場合は追加
                if (endPos) {
                    cameraConfig.endPos = endPos;
                }
                
                newModelGroup.setAttribute('approach-camera', cameraConfig);
                
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
                window.debugLog('Model added to scene with random pattern:', randomPattern);
                
                // フェードインアニメーション - 🚀 registerTimeout使用
                window.registerTimeout(() => {
                    newModelGroup.setAttribute('animation__fadein', {
                        property: 'scale',
                        to: '1 1 1',
                        dur: 1000,
                        easing: 'easeOutQuad'
                    });
                    window.debugLog('Model fading in');
                }, 100);
            }
        });




        // 自動VRモード切り替えコンポーネント
        AFRAME.registerComponent('auto-enter-vr', {
            init: function () {
                const sceneEl = this.el;
                
                // シーンが読み込まれたら実行
                sceneEl.addEventListener('loaded', () => {
                    window.debugLog('Scene loaded, checking for VR device...');
                    window.updateDebug('Checking VR device...');
                    
                    // 🚀 パフォーマンス改善: THREE.Color を初期化（一度だけ）
                    if (typeof THREE !== 'undefined') {
                        if (!window.cachedBallEmissiveColor) {
                            window.cachedBallEmissiveColor = new THREE.Color(0x444444);
                            window.debugLog('Cached ball emissive color initialized');
                        }
                        if (!window.cachedHitEmissiveColor) {
                            window.cachedHitEmissiveColor = new THREE.Color(0xFFFFFF);
                            window.debugLog('Cached hit emissive color initialized');
                        }
                        if (!window.cachedFlashColors) {
                            window.cachedFlashColors = {
                                blue: new THREE.Color(0x0000FF),
                                green: new THREE.Color(0x00FF00),
                                yellow: new THREE.Color(0xFFFF00),
                                red: new THREE.Color(0xFF0000),
                                black: new THREE.Color(0x000000)
                            };
                            window.debugLog('Cached flash colors initialized');
                        }
                        if (!window.cachedEnhanceMaterialColor) {
                            window.cachedEnhanceMaterialColor = new THREE.Color(0x444444);
                            window.debugLog('Cached enhance material color initialized');
                        }
                    }
                    
                    // VRデバイスが利用可能かチェック
                    if (navigator.xr) {
                        navigator.xr.isSessionSupported('immersive-vr').then((supported) => {
                            if (supported) {
                                window.debugLog('VR device detected! Auto-entering VR mode...');
                                window.updateDebug('VR device found! Entering VR...');
                                
                                // 少し待ってからVRモードに入る（アセット読み込み完了を待つ） - 🚀 registerTimeout使用
                                window.registerTimeout(() => {
                                    sceneEl.enterVR();
                                    window.debugLog('Entered VR mode automatically');
                                    window.updateDebug('VR mode activated');
                                }, 1000);
                            } else {
                                window.debugLog('VR not supported on this device');
                                window.updateDebug('VR not supported');
                            }
                        }).catch((err) => {
                            window.debugLog('Error checking VR support:', err);
                            window.updateDebug('VR check failed');
                        });
                    } else {
                        window.debugLog('WebXR not available');
                        window.updateDebug('WebXR not available');
                    }
                });
            }
        });

        // Controller
        AFRAME.registerComponent("vr-controller", {
            dependencies: ["raycaster"],// Important
            init: function () {
                // window.debugLog("vr-controller");
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
            <a-asset-item id="model_01" src="{{ asset('cg/zombie_matsubara.glb') }}"></a-asset-item>
            <a-asset-item id="model_02" src="{{ asset('cg/zombie_fujii.glb') }}"></a-asset-item>
            <a-asset-item id="model_03" src={{ asset('cg/zombie_isobe.glb') }}></a-asset-item>
            <a-asset-item id="model_04" src={{ asset('cg/zombie_ootani.glb') }}></a-asset-item>
            <a-asset-item id="model_05" src={{ asset('cg/zombie_oda3.glb') }}></a-asset-item>
            <a-asset-item id="model_06" src={{ asset('cg/zombie_ishimaru.glb') }}></a-asset-item>
            <a-asset-item id="model_boss" src={{ asset('cg/zombie_morishige4.glb') }}></a-asset-item>
            <a-asset-item id="gun_model" src="{{ asset('cg/gun_02.glb') }}"></a-asset-item>


            <!-- サウンド -->
            <audio id="sound_hit" src={{ asset('cg/sound_hit02.mp3') }} preload="auto" crossorigin="anonymous"></audio>
            <audio id="sound_bgm" src={{ asset('cg/sound_bgm06.mp3') }} preload="auto" crossorigin="anonymous"></audio>
            <audio id="sound_alert" src={{ asset('cg/sound_alert.mp3') }} preload="auto" loop crossorigin="anonymous"></audio>
            <audio id="sound_zombie_appear" src={{ asset('cg/sound_zombie_appear.mp3') }} preload="auto" crossorigin="anonymous"></audio>
            <audio id="sound_zombie_die" src={{ asset('cg/sound_zombie_die.mp3') }} preload="auto" crossorigin="anonymous"></audio>
            
            <!-- 背景画像 -->
            <img id="sky02" src={{ asset('cg/R0010143a.JPG') }} crossorigin="anonymous" >
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
        <a-entity id="leftController" laser-controls="hand: left" raycaster="objects: .collidable, .clickable; far: 5; showLine: false" vr-controller></a-entity>
        <a-entity id="rightController" laser-controls="hand: right; model: false" raycaster="objects: .collidable, .clickable; far: 5" vr-controller>
            <a-entity gltf-model="#gun_model" position="0 -0.05 -0.1" rotation="0 -90 0" scale="0.2 0.2 0.2"></a-entity>
        </a-entity>

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
                value="seiei VR SHOOTING GAME" 
                position="0 0.5 0.01" 
                align="center" 
                color="#FFFFFF" 
                width="3"
                font="roboto"
                shader="msdf">
            </a-text>
            
            <!-- Level 1 ボタンの背景 -->
            <a-plane 
                id="level1Button"
                position="-1.2 -0.5 0.01" 
                width="1.8" 
                height="0.6" 
                color="#00FF00" 
                opacity="0.9"
                material="transparent: true"
                class="clickable">
            </a-plane>
            
            <!-- Level 1 ボタンテキスト -->
            <a-text 
                value="Level 1&#10;EASY" 
                position="-1.2 -0.5 0.02" 
                align="center" 
                color="#000000" 
                width="3"
                font="roboto"
                shader="msdf"
                baseline="center">
            </a-text>
            
            <!-- Level 2 ボタンの背景 -->
            <a-plane 
                id="level2Button"
                position="1.2 -0.5 0.01" 
                width="1.8" 
                height="0.6" 
                color="#FF6600" 
                opacity="0.9"
                material="transparent: true"
                class="clickable">
            </a-plane>
            
            <!-- Level 2 ボタンテキスト -->
            <a-text 
                value="Level 2&#10;HARD" 
                position="1.2 -0.5 0.02" 
                align="center" 
                color="#000000" 
                width="3"
                font="roboto"
                shader="msdf"
                baseline="center">
            </a-text>
            
            <!-- 難易度説明 -->
            <a-text 
                value="Level 1: toward camera only&#10;Level 2: toward & backward" 
                position="0 -1.3 0.02" 
                align="center" 
                color="#CCCCCC" 
                width="3"
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
        <a-entity id="debugDisplay" position="0 2.75 -3" visible="false">
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
            
            <!-- レベル表示（スコアの上） -->
            <a-text 
                id="resultLevel"
                value="Level 1" 
                position="0 1.7 0.01" 
                align="center" 
                color="#00FF00" 
                width="3"
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
            
            <!-- ランキング表示エリア（0.2上げる：-0.5→-0.3） -->
            <a-entity id="rankingDisplay" position="0 -0.3 0.01">
                <!-- JavaScriptで動的に生成 -->
            </a-entity>
            
            <!-- RESTARTボタンの背景 -->
            <a-plane 
                id="restartButton"
                position="0 -2.0 0.01" 
                width="3.5" 
                height="0.6" 
                color="#00FF00" 
                opacity="0.9"
                material="transparent: true"
                class="clickable">
            </a-plane>
            
            <!-- RESTARTボタンテキスト -->
            <a-text 
                value="Return to Start Screen" 
                position="0 -2.0 0.02" 
                align="center" 
                color="#000000" 
                width="4"
                font="roboto"
                shader="msdf"
                baseline="center">
            </a-text>
        </a-entity>

        <!-- モデル01グループ（初期非表示） -->
        <a-entity id="modelGroup_01" position="-4 0 -8" rotation="0 45 0" scale="1 1 1" 
                  approach-camera="speed: 0.3; useCamera: true; autoRespawn: true; waitTime: 4000" 
                  visible="false">
            <a-entity gltf-model="#model_01" animation-mixer="clip: anime01; loop: repeat" enhance-materials></a-entity>
            <a-entity id="hit-boxed_01" hit-box position="0 1.0 0">
                <a-entity geometry="primitive: cylinder" material="color: blue; opacity: 0.0; transparent: true" 
                          scale="1.0 2.0 1.0" class="collidable"></a-entity>
            </a-entity>
        </a-entity>

        <!-- モデル02グループ（初期非表示） -->
        <a-entity id="modelGroup_02" position="0 0 -10" rotation="0 0 0" scale="1 1 1" 
                  approach-camera="speed: 0.25; useCamera: true; autoRespawn: true; waitTime: 4000" 
                  visible="false">
            <a-entity gltf-model="#model_02" animation-mixer="clip: anime01; loop: repeat" enhance-materials></a-entity>
            <a-entity id="hit-boxed_02" hit-box position="0 1.0 0">
                <a-entity geometry="primitive: cylinder" material="color: green; opacity: 0.0; transparent: true" 
                          scale="1.0 2.0 1.0" class="collidable"></a-entity>
            </a-entity>
        </a-entity>

        <!-- モデル03グループ（初期非表示） -->
        <!-- 例: 固定終点を使う場合は useCamera: false; endPos: x y z を指定 -->
        <a-entity id="modelGroup_03" position="4 0 -8" rotation="0 -45 0" scale="1 1 1" 
                  approach-camera="speed: 0.35; useCamera: false; endPos: 2 0 -2; autoRespawn: true; waitTime: 4000" 
                  visible="false">
            <a-entity gltf-model="#model_03" animation-mixer="clip: anime01; loop: repeat" enhance-materials></a-entity>
            <a-entity id="hit-boxed_03" hit-box position="0 1.0 0">
                <a-entity geometry="primitive: cylinder" material="color: red; opacity: 0.0; transparent: true" 
                          scale="1.0 2.0 1.0" class="collidable"></a-entity>
            </a-entity>
        </a-entity>

        <!-- モデル04グループ（初期非表示・Level 2専用） -->
        <a-entity id="modelGroup_04" position="-4 0 -8" rotation="0 45 0" scale="1 1 1" 
                  approach-camera="speed: 0.3; useCamera: true; autoRespawn: true; waitTime: 4000" 
                  visible="false">
            <a-entity gltf-model="#model_04" animation-mixer="clip: anime01; loop: repeat" enhance-materials></a-entity>
            <a-entity id="hit-boxed_04" hit-box position="0 1.0 0">
                <a-entity geometry="primitive: cylinder" material="color: yellow; opacity: 0.0; transparent: true" 
                          scale="1.0 2.0 1.0" class="collidable"></a-entity>
            </a-entity>
        </a-entity>

        <!-- モデル05グループ（初期非表示・Level 2専用） -->
        <a-entity id="modelGroup_05" position="0 0 -10" rotation="0 0 0" scale="1 1 1" 
                  approach-camera="speed: 0.3; useCamera: true; autoRespawn: true; waitTime: 4000" 
                  visible="false">
            <a-entity gltf-model="#model_05" animation-mixer="clip: anime01; loop: repeat" enhance-materials></a-entity>
            <a-entity id="hit-boxed_05" hit-box position="0 1.0 0">
                <a-entity geometry="primitive: cylinder" material="color: purple; opacity: 0.0; transparent: true" 
                          scale="1.0 2.0 1.0" class="collidable"></a-entity>
            </a-entity>
        </a-entity>

        <!-- モデル06グループ（初期非表示・Level 2専用） -->
        <a-entity id="modelGroup_06" position="4 0 -8" rotation="0 -45 0" scale="1 1 1" 
                  approach-camera="speed: 0.3; useCamera: true; autoRespawn: true; waitTime: 4000" 
                  visible="false">
            <a-entity gltf-model="#model_06" animation-mixer="clip: anime01; loop: repeat" enhance-materials></a-entity>
            <a-entity id="hit-boxed_06" hit-box position="0 1.0 0">
                <a-entity geometry="primitive: cylinder" material="color: cyan; opacity: 0.0; transparent: true" 
                          scale="1.0 2.0 1.0" class="collidable"></a-entity>
            </a-entity>
        </a-entity>

        <!-- 360度画像を表示 -->
        <a-sky id="aSky" src="#sky02"></a-sky>

        <!-- Particle Effects - 3 Tiers -->
        <!-- 🚀🚀 パフォーマンス改善: パーティクル数を更に半分に削減 -->
        <!-- 通常ヒット用: コンボなし時 - White, size 0.1, 3 particles -->
        <a-entity id="particle-normal" visible="false" position="0 3 0" 
                  particle-system="preset: default; color: #FFFFFF; particleCount: 3; size: 0.1; maxAge: 1.0; velocityValue: 1 1 1; velocitySpread: 2 2 2; accelerationValue: 0 -2 0; accelerationSpread: 0.5 0.5 0.5"></a-entity>
        
        <!-- Tier 1: 1.1x (2-3 combo) - Cyan, size 0.1, 5 particles -->
        <a-entity id="particle-tier1" visible="false" position="0 3 0" 
                  particle-system="preset: default; color: #00FFFF; particleCount: 5; size: 0.1; maxAge: 1.5; velocityValue: 2 2 2; velocitySpread: 3 3 3; accelerationValue: 0 -2 0; accelerationSpread: 1 1 1"></a-entity>
        
        <!-- Tier 2: 1.2x (4-5 combo) - Orange, size 0.15, 8 particles -->
        <a-entity id="particle-tier2" visible="false" position="0 3 0" 
                  particle-system="preset: default; color: #FF6600; particleCount: 8; size: 0.15; maxAge: 1.5; velocityValue: 2 2 2; velocitySpread: 3 3 3; accelerationValue: 0 -2 0; accelerationSpread: 1 1 1"></a-entity>
        
        <!-- Tier 3: 1.3x (6+ combo) - Magenta, size 0.2, 10 particles -->
        <a-entity id="particle-tier3" visible="false" position="0 3 0" 
                  particle-system="preset: default; color: #FF00FF; particleCount: 10; size: 0.2; maxAge: 1.5; velocityValue: 2 2 2; velocitySpread: 3 3 3; accelerationValue: 0 -2 0; accelerationSpread: 1 1 1"></a-entity>
        
        <!-- Top 5 Celebration Particle - 🚀🚀 パフォーマンス改善: パーティクル数を更に半分に削減 -->
        <a-entity id="particle-celebration" visible="false" position="0 2 -3">
            <!-- メインゴールドパーティクル：金色パーティクル -->
            <a-entity particle-system="preset: default; color: #FFD700,#FFA500,#FFFF00; particleCount: 15; size: 0.3; maxAge: 3; velocityValue: 0 5 0; velocitySpread: 5 2 5; accelerationValue: 0 -1 0; accelerationSpread: 2 0 2; blending: 1"></a-entity>
            
            <!-- 輝く星パーティクル：キラキラ効果 -->
            <a-entity particle-system="preset: default; color: #FFFFFF,#FFD700; particleCount: 10; size: 0.15; maxAge: 2.5; velocityValue: 0 3 0; velocitySpread: 4 3 4; accelerationValue: 0 -0.5 0; accelerationSpread: 1 0 1; blending: 1" position="0 0.5 0"></a-entity>
            
            <!-- 紙吹雪効果：カラフルな紙吹雪 -->
            <a-entity particle-system="preset: default; color: #FF1493,#00FFFF,#FF6600,#00FF00,#9400D3; particleCount: 12; size: 0.2; maxAge: 3.5; velocityValue: 0 4 0; velocitySpread: 6 1 6; accelerationValue: 0 -2 0; accelerationSpread: 3 0 3; blending: 1; rotation: 0 0 45" position="0 1 0"></a-entity>
            
            <!-- 輪っか状に広がるパーティクル -->
            <a-entity particle-system="preset: default; color: #FFD700,#FFFFFF; particleCount: 8; size: 0.25; maxAge: 2; velocityValue: 8 0 0; velocitySpread: 2 3 8; accelerationValue: -3 -1 0; accelerationSpread: 1 2 3; blending: 1" position="0 -0.5 0"></a-entity>
        </a-entity>
        

        <a-camera id="my_camera" shoot>
        </a-camera>
    </a-scene>
</body>

</html>