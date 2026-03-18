<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>seieiVR</title>
    <script src="{{ asset('js/aframe.min.js') }}"></script>
    <!-- Set DRACO decoder path to CDN to ensure Draco-compressed GLBs can be decoded -->
    <script>
        (function() {
            // 🚀 パフォーマンス最適化: 初期化ログは本番では非表示
            const DEBUG_INIT = false; // 本番では false に設定
            const initLog = DEBUG_INIT ? console.log.bind(console) : function(){};
            
            // Wait for A-Frame/THREE to become available
            function setDracoPath() {
                try {
                    if (typeof THREE !== 'undefined' && THREE.DRACOLoader && typeof THREE.DRACOLoader.setDecoderPath === 'function') {
                        THREE.DRACOLoader.setDecoderPath('https://www.gstatic.com/draco/versioned/decoders/1.5.6/');
                        initLog('DRACOLoader decoder path set to CDN');
                    } else {
                        // Retry if THREE.DRACOLoader is not yet available
                        setTimeout(setDracoPath, 200);
                    }
                } catch (e) {
                    console.warn('Failed to set DRACOLoader decoder path', e);
                }
            }
            setDracoPath();

            // Also ensure the gltf-model system's dracoLoader instance gets the CDN path
            (function setSceneDracoPath() {
                try {
                    if (typeof AFRAME !== 'undefined' && AFRAME.scenes && AFRAME.scenes.length > 0) {
                        const scene = AFRAME.scenes[0];
                        if (scene && scene.systems && scene.systems['gltf-model']) {
                            const sys = scene.systems['gltf-model'];
                            if (sys && sys.dracoLoader && typeof sys.dracoLoader.setDecoderPath === 'function') {
                                sys.dracoLoader.setDecoderPath('https://www.gstatic.com/draco/versioned/decoders/1.5.6/');
                                initLog('gltf-model system dracoLoader decoder path set to CDN');
                                return;
                            }
                        }
                        // Fallback: set scene attribute if system not initialized yet
                        try {
                            scene.setAttribute('gltf-model', 'dracoDecoderPath: https://www.gstatic.com/draco/versioned/decoders/1.5.6/');
                            initLog('Set scene attribute gltf-model.dracoDecoderPath to CDN');
                        } catch (e) {}
                    }
                } catch (e) {
                    // ignore and retry
                }
                setTimeout(setSceneDracoPath, 200);
            })();

            // Debug: watch a-assets events (only in DEBUG_INIT mode)
            if (DEBUG_INIT) {
                (function watchAssets() {
                    try {
                        const assets = document.querySelector('a-assets');
                        if (assets) {
                            assets.addEventListener('timeout', (e) => console.warn('a-assets timeout event:', e));
                            assets.addEventListener('loaded', (e) => initLog('a-assets loaded event:', e));
                            assets.addEventListener('error', (e) => console.warn('a-assets error event:', e));
                            initLog('a-assets debug listeners attached');
                            return;
                        }
                    } catch (e) {}
                    setTimeout(watchAssets, 200);
                })();

                // Attach asset item listeners to show which assets succeeded/failed
                (function watchAssetItems() {
                    try {
                        const items = document.querySelectorAll('a-asset-item');
                        if (items && items.length > 0) {
                            items.forEach(item => {
                                const src = item.getAttribute('src') || item.src || '(unknown)';
                                item.addEventListener('loaded', () => initLog('Asset loaded:', src));
                                item.addEventListener('error', (e) => console.warn('Asset ERROR:', src, e));
                            });
                            initLog('a-asset-item debug listeners attached');
                            return;
                        }
                    } catch (e) {}
                    setTimeout(watchAssetItems, 200);
                })();
            }
        })();
    </script>
    <script src="{{ asset('js/aframe-particle-system-component.min.js') }}"></script>
    <script src="{{ asset('js/aframe-extras.min.js') }}"></script>
    <script src="{{ asset('js/axios.min.js') }}"></script>

    <script>
        // ソースコード保護: 右クリック・キーボードショートカット無効化
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });
        
        document.addEventListener('keydown', function(e) {
            // F12（開発者ツール）
            if (e.key === 'F12' || e.keyCode === 123) {
                e.preventDefault();
                return false;
            }
            
            // Ctrl+Shift+I（検証ツール）
            if (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.keyCode === 73)) {
                e.preventDefault();
                return false;
            }
            
            // Ctrl+Shift+J（コンソール）
            if (e.ctrlKey && e.shiftKey && (e.key === 'J' || e.keyCode === 74)) {
                e.preventDefault();
                return false;
            }
            
            // Ctrl+Shift+C（要素選択）
            if (e.ctrlKey && e.shiftKey && (e.key === 'C' || e.keyCode === 67)) {
                e.preventDefault();
                return false;
            }
            
            // Ctrl+U（ソース表示）
            if (e.ctrlKey && (e.key === 'U' || e.keyCode === 85)) {
                e.preventDefault();
                return false;
            }
            
            // Ctrl+S（保存）
            if (e.ctrlKey && (e.key === 'S' || e.keyCode === 83)) {
                e.preventDefault();
                return false;
            }
            
            // Cmd+Option+I（Mac版検証ツール）
            if (e.metaKey && e.altKey && (e.key === 'I' || e.keyCode === 73)) {
                e.preventDefault();
                return false;
            }
            
            // Cmd+Option+J（Mac版コンソール）
            if (e.metaKey && e.altKey && (e.key === 'J' || e.keyCode === 74)) {
                e.preventDefault();
                return false;
            }
            
            // Cmd+Option+C（Mac版要素選択）
            if (e.metaKey && e.altKey && (e.key === 'C' || e.keyCode === 67)) {
                e.preventDefault();
                return false;
            }
            
            // Cmd+U（Mac版ソース表示）
            if (e.metaKey && (e.key === 'U' || e.keyCode === 85)) {
                e.preventDefault();
                return false;
            }
        });
        
        // テキスト選択の無効化
        document.addEventListener('selectstart', function(e) {
            e.preventDefault();
            return false;
        });
        
        // ドラッグ操作の無効化
        document.addEventListener('dragstart', function(e) {
            e.preventDefault();
            return false;
        });
        
        // コピー操作の無効化
        document.addEventListener('copy', function(e) {
            e.preventDefault();
            return false;
        });
        
        // 開発者ツール検知（簡易版）- デバッグ中は無効化
        /*
        (function() {
            const devtools = /./;
            devtools.toString = function() {
                this.opened = true;
            }
            console.log('%c', devtools);
            
            setInterval(function() {
                if (devtools.opened) {
                    console.clear();
                    devtools.opened = false;
                }
            }, 1000);
        })();
        */
    </script>

    <script>  
        // 🚀 パフォーマンス改善: デバッグモードの制御
        window.DEBUG_MODE = false; // 本番環境では false に設定
        
        // デバッグログ関数（DEBUG_MODE が true の時のみ出力）
        window.debugLog = function(...args) {
            if (window.DEBUG_MODE) {
                console.log(...args);
            }
        };
        
        // ゲーム状態管理
        window.gameStarted = false;
        window.gameEnded = false;
        window.totalScore = 0;
        window.gameTimer = null;
        window.gameTimeLeft = 75; // 75秒
        window.comboCount = 0; // 連続ヒット数
        window.maxComboCount = 0; // 最大連続ヒット数
        window.enemiesDefeated = 0; // 捕獲した動物の数
        window.lastBallHit = false; // 最後のボールがヒットしたかどうか
        window.gameLevel = 1; // ゲームレベル選択用（1 or 2）
        window.currentLevel = 1; // 現在プレイ中のレベル（1 or 2）
        
        // ボール選択管理（2種類のポケボール）
        window.ballTypes = [
            'cg/poke_ball_07apple.glb',
            'cg/poke_ball_09cabbage.glb'
        ];
        window.currentBallIndex = 0; // 現在選択されているボールのインデックス（0 or 1）
        
        // 🚀 最適化: THREE.Vector3のグローバルキャッシュ
        window._cachedVector3 = new THREE.Vector3();
        window._cachedVector3_2 = new THREE.Vector3();
        window._cachedVector3_3 = new THREE.Vector3();
        
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
                                    node.material.map.anisotropy = 2; // テクスチャのアニソトロピックフィルタリング
                                }
                                
                                // fukuda.glbの場合は明るさを増加
                                if (isMattsun) {
                                    // エミッシブカラーを追加して明るくする
                                    if (!node.material.emissive) {
                                        node.material.emissive = new THREE.Color(0x444444); // グレーのエミッシブ
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
                                    node.material.metalnessMap.anisotropy = 2;
                                }
                                if (node.material.roughnessMap) {
                                    node.material.roughnessMap.anisotropy = 2;
                                }
                                if (node.material.normalMap) {
                                    node.material.normalMap.anisotropy = 2;
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

                // 🚀 修正: Vector3が未初期化の場合は再初期化
                if (!this._cameraPos || !this._cameraPos.isVector3) {
                    this._cameraPos = new THREE.Vector3();
                    this._textPos = new THREE.Vector3();
                }
                
                this.cameraEl.object3D.getWorldPosition(this._cameraPos);
                this.el.object3D.getWorldPosition(this._textPos);

                // カメラ方向を向く（lookAt使用）
                this.el.object3D.lookAt(this._cameraPos);
                
                // X軸とZ軸の回転をリセット（Y軸のみ保持）
                const currentRotation = this.el.object3D.rotation;
                this.el.object3D.rotation.set(0, currentRotation.y, 0);
            }
        });

        // fit-to-hitbox コンポーネント
        // 目的: モデル(gltf-model)の形状を親の hit-box に合わせて自動縮尺・配置する
        // 修正: 固定スケールを使用（GLBモデルは既に適切なサイズで作成されている前提）
        AFRAME.registerComponent('fit-to-hitbox', {
            schema: {
                // 固定スケール値（デフォルト0.5）
                scale: { type: 'number', default: 0.5 }
            },
            init: function() {
                this.fitted = false;
                this.onModelLoaded = this.onModelLoaded.bind(this);
                this.el.addEventListener('model-loaded', this.onModelLoaded);
                setTimeout(() => {
                    if (this.el.getObject3D('mesh') && !this.fitted) {
                        this.onModelLoaded();
                    }
                }, 100);
            },
            remove: function() {
                this.el.removeEventListener('model-loaded', this.onModelLoaded);
            },
            onModelLoaded: function() {
                if (this.fitted) {
                    window.debugLog('fit-to-hitbox: already applied, skipping');
                    return;
                }
                
                try {
                    const modelObj = this.el.getObject3D('mesh') || this.el.object3D;
                    if (!modelObj) return;

                    // 固定スケールを適用（デフォルト1.5）
                    const targetScale = this.data.scale;
                    this.el.setAttribute('scale', `${targetScale} ${targetScale} ${targetScale}`);

                    this.fitted = true;
                    window.debugLog('fit-to-hitbox: applied fixed scale', targetScale);
                } catch (err) {
                    console.warn('fit-to-hitbox error:', err);
                }
            }
        });
        
        // ボール管理用のグローバル配列
        window.activeBalls = [];
        
        // 🚀 メモリリーク対策: プレイ回数管理（2回で自動リフレッシュ）
        window.playCount = 0;
        window.MAX_PLAY_COUNT = 2; // 最大プレイ回数
        
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
                console.warn('⚠️ All patterns in use, selecting random pattern');
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
        AFRAME.registerComponent('start-menu', {
            init: function() {
                this.startGame = this.startGame.bind(this);
                this.selectLevel = this.selectLevel.bind(this);
                this.handleClick = this.handleClick.bind(this);
                this.handleTouch = this.handleTouch.bind(this);
                this.setupListeners = this.setupListeners.bind(this);
                this.clickBlocked = false; // クリックブロックフラグ
                this.controllersUpdated = false; // コントローラー更新フラグ
                
                // コンポーネント初期化時に要素が見つからない場合があるため、
                // 複数のタイミングでリスナー設定を試みる
                const self = this;
                
                // 方法1: loadedイベント
                if (this.el.hasLoaded) {
                    setTimeout(() => self.setupListeners(), 100);
                } else {
                    this.el.addEventListener('loaded', () => {
                        setTimeout(() => self.setupListeners(), 100);
                    });
                }
                
                // 方法2: シーンのloadedイベント（フォールバック）
                this.el.sceneEl.addEventListener('loaded', () => {
                    setTimeout(() => self.setupListeners(), 200);
                });
                
                // 方法3: DOMContentLoaded後のフォールバック
                if (document.readyState === 'complete') {
                    setTimeout(() => self.setupListeners(), 500);
                } else {
                    window.addEventListener('load', () => {
                        setTimeout(() => self.setupListeners(), 500);
                    });
                }
            },

            setupListeners: function() {
                // 重複防止
                if (this.listenersSetup) {
                    window.debugLog('Listeners already setup, skipping...');
                    return;
                }
                
                window.debugLog('Setting up start menu listeners...');
                
                // レベル選択ボタンのイベントリスナーを追加
                const level1Button = document.getElementById('level1Button');
                const level2Button = document.getElementById('level2Button');
                
                window.debugLog('- level1Button:', level1Button ? 'FOUND' : 'NOT FOUND');
                window.debugLog('- level2Button:', level2Button ? 'FOUND' : 'NOT FOUND');
                
                // ボタンが見つからない場合は再試行
                if (!level1Button || !level2Button) {
                    window.debugLog('Buttons not found, will retry...');
                    return;
                }
                
                const self = this;
                
                // A-Frameでは「click」イベントはraycaster経由で発火する
                // 直接ボタンにイベントリスナーを設定する
                if (level1Button) {
                    level1Button.addEventListener('click', function(e) {
                        window.debugLog('=== Level 1 button CLICKED! ===');
                        if (window.gameStarted && !window.gameEnded) {
                            window.debugLog('Blocked - game in progress');
                            return;
                        }
                        self.selectLevel(1);
                    });
                    window.debugLog('Level 1 button click listener attached');
                }
                
                if (level2Button) {
                    level2Button.addEventListener('click', function(e) {
                        window.debugLog('=== Level 2 button CLICKED! ===');
                        if (window.gameStarted && !window.gameEnded) {
                            window.debugLog('Blocked - game in progress');
                            return;
                        }
                        self.selectLevel(2);
                    });
                    window.debugLog('Level 2 button click listener attached');
                }
                
                this.listenersSetup = true;
                window.debugLog('Start menu listeners setup complete');
            },
            
            selectLevel: function(level) {
                window.debugLog('Level selected:', level);
                window.gameLevel = level;
                
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
                            leftController.setAttribute('raycaster', 'objects: .collidable; far: 5');
                            window.debugLog('Removed .clickable from left controller raycaster');
                        }
                        if (rightController) {
                            rightController.setAttribute('raycaster', 'objects: .collidable; far: 5');
                            window.debugLog('Removed .clickable from right controller raycaster');
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
                            window.debugLog('Restored .clickable to mouse cursor raycaster');
                        }
                        if (leftController) {
                            leftController.setAttribute('raycaster', 'objects: .collidable, .clickable; far: 5');
                            window.debugLog('Restored .clickable to left controller raycaster');
                        }
                        if (rightController) {
                            rightController.setAttribute('raycaster', 'objects: .collidable, .clickable; far: 5');
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
                window.debugLog('Menu visible:', this.el.getAttribute('visible'));
                window.debugLog('Game started:', window.gameStarted);
                window.debugLog('Game ended:', window.gameEnded);
                window.debugLog('Click blocked:', this.clickBlocked);
                
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
                window.debugLog('Menu visible:', this.el.getAttribute('visible'));
                window.debugLog('Game started:', window.gameStarted);
                window.debugLog('Game ended:', window.gameEnded);
                window.debugLog('Click blocked:', this.clickBlocked);
                
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
                window.debugLog('=== GAME START ===');
                window.debugLog('🎮 Play count:', window.playCount, '/', window.MAX_PLAY_COUNT);
                
                // === デバッグ: startGame開始時のモデル状態確認 ===
                if (window.DEBUG_MODE) {
                    window.debugLog('=== Model State at startGame() ===');
                    for (let i = 1; i <= 6; i++) {
                        const model = document.getElementById(`modelGroup_0${i}`);
                        if (model) {
                            const pos = model.getAttribute('position');
                            const visible = model.getAttribute('visible');
                            const scale = model.getAttribute('scale');
                            window.debugLog(`modelGroup_0${i}:`, {
                                position: pos,
                                visible: visible,
                                scale: scale
                            });
                        }
                    }
                    window.debugLog('===================================');
                }
                
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
                    leftController.setAttribute('raycaster', 'objects: .collidable; far: 5');
                    window.debugLog('Removed .clickable from left controller');
                }
                if (rightController) {
                    rightController.setAttribute('raycaster', 'objects: .collidable; far: 5');
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
                window.gameTimeLeft = 75; // タイマーを70秒に設定
                window.comboCount = 0; // コンボカウントをリセット
                window.maxComboCount = 0; // 最大コンボカウントをリセット
                window.enemiesDefeated = 0; // 捕獲した動物の数をリセット
                window.lastBallHit = false; // ヒット状態をリセット
                window.usedPatterns = {}; // パターン使用状況をリセット
                
                // ランダムパターン設定（初期スポーン用：近距離パターン）🚀 30%遅く調整
                const movementPatterns = [
                    // パターン1: 左前方からカメラへ - 距離: 約4m
                    { startPos: { x: -3, y: 0, z: -4 }, speed: 0.168, useCamera: true, waitTime: 3000 },
                    // パターン2: 右前方からカメラへ - 距離: 約4m
                    { startPos: { x: 3, y: 0, z: -4 }, speed: 0.14, useCamera: true, waitTime: 3000 },
                    // パターン3: 正面からカメラへ - 距離: 約5m
                    { startPos: { x: 0, y: 0, z: -5 }, speed: 0.112, useCamera: true, waitTime: 3000 },
                    // パターン4: 左から右へ横移動（固定終点）- 距離: 6m
                    { startPos: { x: -3, y: 0, z: -4 }, endPos: { x: 3, y: 0, z: -4 }, speed: 0.168, useCamera: false, waitTime: 3000 },
                    // パターン5: 右から左へ横移動（固定終点）- 距離: 6m
                    { startPos: { x: 3, y: 0, z: -4 }, endPos: { x: -3, y: 0, z: -4 }, speed: 0.168, useCamera: false, waitTime: 3000 },
                    // パターン6: 左斜めからカメラへ - 距離: 約4.2m
                    { startPos: { x: -3, y: 0, z: -3 }, speed: 0.2, useCamera: true, waitTime: 3000 }
                ];
                
                // 初期モデル数をレベルに応じて設定（Level 1: 3体、Level 2: 6体）
                const initialModelIds = window.currentLevel === 1 
                    ? ['modelGroup_01', 'modelGroup_02', 'modelGroup_03']
                    : ['modelGroup_01', 'modelGroup_02', 'modelGroup_03', 'modelGroup_04', 'modelGroup_05', 'modelGroup_06'];
                window.debugLog('Showing initial', initialModelIds.length, 'models with random patterns (Level', window.currentLevel, ')');
                
                // 🚀 最適化: モデルを1秒ずつずらして出現させる（registerTimeoutで管理）
                initialModelIds.forEach((modelId, index) => {
                    window.registerTimeout(() => {
                        window.debugLog(`=== Spawning model ${modelId} (delay: ${index}s) ===`);
                        const model = document.getElementById(modelId);
                        if (model) {
                            // 使用可能なパターンを取得（他のモデルと重複しない）
                            const { pattern: randomPattern, index: patternIndex } = window.getAvailablePattern(movementPatterns, modelId);
                            
                            window.debugLog(`Pattern for ${modelId}:`, randomPattern);
                            
                            // 始点位置を設定
                            const startX = randomPattern.startPos.x;
                            const startY = randomPattern.startPos.y;
                            const startZ = randomPattern.startPos.z;
                            window.debugLog(`Setting ${modelId} position to: ${startX}, ${startY}, ${startZ}`);
                            model.setAttribute('position', `${startX} ${startY} ${startZ}`);
                            
                            // 角度を計算
                            let rotation;
                            if (randomPattern.useCamera) {
                                rotation = Math.atan2(startX, -startZ) * (180 / Math.PI);
                            } else {
                                const dx = randomPattern.endPos.x - startX;
                                const dz = randomPattern.endPos.z - startZ;
                                rotation = Math.atan2(dx, -dz) * (180 / Math.PI);
                            }
                            window.debugLog(`Setting ${modelId} rotation to: 0, ${rotation}, 0`);
                            model.setAttribute('rotation', `0 ${rotation} 0`);
                            
                            // 🎯 動物の頭上にランダムなボールインジケーターを追加（初期スポーン時）
                            const requiredBallIndex = Math.floor(Math.random() * 2); // 0 or 1
                            model.setAttribute('data-required-ball', requiredBallIndex.toString());
                            
                            // 既存のボールインジケーターを削除
                            const existingIndicator = model.querySelector(`#ball-indicator-${modelId}`);
                            if (existingIndicator) {
                                existingIndicator.parentNode.removeChild(existingIndicator);
                            }
                            
                            // 🚀 最適化: GLBの代わりにシンプルな球体を使用（メモリ節約）
                            const ballIndicator = document.createElement('a-sphere');
                            ballIndicator.setAttribute('id', `ball-indicator-${modelId}`);
                            ballIndicator.setAttribute('radius', '0.12');
                            ballIndicator.setAttribute('position', '0 1.2 0'); // 動物の頭上に配置
                            // リンゴ=赤、キャベツ=緑
                            const indicatorColor = requiredBallIndex === 0 ? '#ff3e3e' : '#5ce35c';
                            ballIndicator.setAttribute('color', indicatorColor);
                            ballIndicator.setAttribute('material', `color: ${indicatorColor}; emissive: ${indicatorColor}; emissiveIntensity: 0.3; shader: flat`);
                            // 🚀 最適化: 回転アニメーションを削除（負荷軽減）
                            model.appendChild(ballIndicator);
                            window.debugLog('Ball indicator added for', modelId, ':', requiredBallIndex === 0 ? 'Apple (Red)' : 'Cabbage (Green)');
                            
                            // まずvisibleをtrueにする
                            model.setAttribute('visible', true);
                            window.debugLog(`${modelId} visible set to true`);
                            
                            // approach-cameraコンポーネントの設定
                            // A-Frameのvec3は文字列 "x y z" または オブジェクト {x, y, z} で設定
                            const startPosStr = `${randomPattern.startPos.x} ${randomPattern.startPos.y} ${randomPattern.startPos.z}`;
                            let configStr = `speed: ${randomPattern.speed}; startPos: ${startPosStr}; useCamera: ${randomPattern.useCamera}; autoRespawn: true; waitTime: 2000; active: true`;
                            if (randomPattern.endPos) {
                                const endPosStr = `${randomPattern.endPos.x} ${randomPattern.endPos.y} ${randomPattern.endPos.z}`;
                                configStr += `; endPos: ${endPosStr}`;
                            }
                            window.debugLog(`Setting ${modelId} approach-camera string:`, configStr);
                            model.setAttribute('approach-camera', configStr);
                            
                            // 位置を確認
                            const finalPos = model.getAttribute('position');
                            const finalVisible = model.getAttribute('visible');
                            window.debugLog(`${modelId} FINAL state - position:`, finalPos, 'visible:', finalVisible);
                        } else {
                            console.error('Model not found:', modelId);
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
                        
                        // 🚀 最適化: IDで直接取得（querySelectorAllを避ける）
                        const modelIds = ['modelGroup_01', 'modelGroup_02', 'modelGroup_03', 'modelGroup_04', 'modelGroup_05', 'modelGroup_06'];
                        modelIds.forEach(modelId => {
                            const model = document.getElementById(modelId);
                            if (model) {
                                model.setAttribute('visible', false);
                            }
                        });
                        window.debugLog('All models hidden');
                        
                        // タイマー非表示
                        const timerDisplay = document.getElementById('timerDisplay');
                        if (timerDisplay) {
                            timerDisplay.setAttribute('visible', false);
                        }
                        
                        // リザルト画面を表示（再度取得して確実に存在することを確認）
                        const currentResultMenu = document.getElementById('resultMenu');
                        window.debugLog('=== Looking for result menu ===');
                        window.debugLog('Result menu element:', currentResultMenu);
                        window.debugLog('Result menu exists:', currentResultMenu ? 'YES' : 'NO');
                        
                        if (currentResultMenu) {
                            window.debugLog('Calling showResult...');
                            window.updateDebug('Showing result...');
                            self.showResult(currentResultMenu);
                        } else {
                            console.error('ERROR: Result menu element NOT FOUND!');
                            window.updateDebug('ERROR: Result menu NOT FOUND!');
                            // デバッグ: DOM内のすべての要素を確認
                            const allEntities = document.querySelectorAll('a-entity');
                            window.debugLog('Total a-entity count:', allEntities.length);
                            const menuEntities = document.querySelectorAll('[result-menu]');
                            window.debugLog('Entities with result-menu attribute:', menuEntities.length);
                        }
                    }
                }, 1000);
                
                window.debugLog('Timer started, interval ID:', window.gameTimer);
            },
            
            showResult: function(resultMenu) {
                window.debugLog('=== showResult function called ===');
                window.updateDebug(`Result: Score ${window.totalScore.toFixed(1)}`);
                
                // 🚀 メモリ解放: 残っているアクティブなボールを全て削除
                if (window.activeBalls && window.activeBalls.length > 0) {
                    window.debugLog('Cleaning up', window.activeBalls.length, 'remaining balls at game end');
                    window.activeBalls.forEach(ballData => {
                        if (ballData && ballData.ball) {
                            const ball = ballData.ball;
                            // DOMから削除
                            if (ball.parentNode) {
                                ball.parentNode.removeChild(ball);
                            }
                        }
                    });
                    window.activeBalls = [];
                }
                
                window.debugLog('resultMenu parameter:', resultMenu);
                window.debugLog('resultMenu is null?', resultMenu === null);
                window.debugLog('resultMenu is undefined?', resultMenu === undefined);
                
                if (!resultMenu) {
                    console.error('ERROR: resultMenu is null or undefined!');
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
                
                // 少し待ってからアニメーション開始（確実に反映させる）
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
                window.debugLog('Saving score to database:', score, 'Level:', window.currentLevel);
                
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
                        game_mode: 'model',
                        max_combo: window.maxComboCount || 0, // 最大コンボ数
                        enemies_defeated: window.enemiesDefeated || 0, // 捕獲した動物の数
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
                window.debugLog('Fetching top 5 rankings for Level:', window.currentLevel);
                
                fetch(`api/shooting-scores/top5?level=${window.currentLevel || 1}&game_mode=model`)
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
                    
                    // 🎯 パーティクルクリーンアップ: 5秒後にパーティクルを停止＆リセット
                    window.registerTimeout(() => {
                        // 子要素のパーティクルシステムもすべてリセット
                        const childParticles = celebrationParticle.querySelectorAll('[particle-system]');
                        childParticles.forEach(child => {
                            const childComponent = child.components['particle-system'];
                            if (childComponent) {
                                childComponent.stopParticles();
                                // パーティクルグループをリセット（ゴミ残り防止）
                                if (childComponent.particleGroup) {
                                    childComponent.particleGroup.emitters.forEach(emitter => {
                                        emitter.reset(true);
                                    });
                                }
                            }
                        });
                        
                        if (particleSystem) {
                            particleSystem.stopParticles();
                        }
                        celebrationParticle.setAttribute('visible', 'false');
                        window.debugLog('Celebration particles cleaned up');
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
                const levelName = window.currentLevel === 2 ? 'Model Lv2 Ranking' : 'Model Lv1 Ranking';
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
                    
                    // ランキング行のテキスト（名前の代わりにコンボ数と捕獲した動物数を表示）
                    const rankingText = `${rank}. ${item.score.toFixed(1)}pt  Combo:${item.max_combo || 0}  Animals:${item.enemies_defeated || 0}`;
                    
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
                window.debugLog('Recreating initial models...');
                
                // モデル01を再作成
                const model01 = document.createElement('a-entity');
                model01.setAttribute('id', 'modelGroup_01');
                model01.setAttribute('position', '-4 0 -8');
                model01.setAttribute('rotation', '0 45 0');
                model01.setAttribute('scale', '1 1 1');
                model01.setAttribute('approach-camera', 'speed: 0.3; useCamera: true; autoRespawn: true; waitTime: 3000');
                model01.setAttribute('visible', 'false');
                
                const model01Entity = document.createElement('a-entity');
                model01Entity.setAttribute('gltf-model', '#model_01');
                model01Entity.setAttribute('fit-to-hitbox', '');
                model01Entity.setAttribute('animation-mixer', 'clip: anime01; loop: repeat');
                model01Entity.setAttribute('enhance-materials', '');
                model01.appendChild(model01Entity);
                
                const hitBox01 = document.createElement('a-entity');
                hitBox01.setAttribute('id', 'hit-boxed_01');
                hitBox01.setAttribute('hit-box', '');
                hitBox01.setAttribute('position', '0 0.3 0');
                const cylinder01 = document.createElement('a-entity');
                cylinder01.setAttribute('geometry', 'primitive: cylinder');
                cylinder01.setAttribute('material', 'color: blue; opacity: 0.0; transparent: true');
                cylinder01.setAttribute('scale', '0.3 0.4 0.3');
                cylinder01.setAttribute('class', 'collidable');
                hitBox01.appendChild(cylinder01);
                model01.appendChild(hitBox01);
                sceneEl.appendChild(model01);
                
                // モデル02を再作成
                const model02 = document.createElement('a-entity');
                model02.setAttribute('id', 'modelGroup_02');
                model02.setAttribute('position', '0 0 -10');
                model02.setAttribute('rotation', '0 0 0');
                model02.setAttribute('scale', '1 1 1');
                model02.setAttribute('approach-camera', 'speed: 0.25; useCamera: true; autoRespawn: true; waitTime: 3000');
                model02.setAttribute('visible', 'false');
                
                const model02Entity = document.createElement('a-entity');
                model02Entity.setAttribute('gltf-model', '#model_02');
                model02Entity.setAttribute('fit-to-hitbox', '');
                model02Entity.setAttribute('animation-mixer', 'clip: anime01; loop: repeat');
                model02Entity.setAttribute('enhance-materials', '');
                model02.appendChild(model02Entity);
                
                const hitBox02 = document.createElement('a-entity');
                hitBox02.setAttribute('id', 'hit-boxed_02');
                hitBox02.setAttribute('hit-box', '');
                hitBox02.setAttribute('position', '0 0.3 0');
                const cylinder02 = document.createElement('a-entity');
                cylinder02.setAttribute('geometry', 'primitive: cylinder');
                cylinder02.setAttribute('material', 'color: blue; opacity: 0.0; transparent: true');
                cylinder02.setAttribute('scale', '0.3 0.4 0.3');
                cylinder02.setAttribute('class', 'collidable');
                hitBox02.appendChild(cylinder02);
                model02.appendChild(hitBox02);
                sceneEl.appendChild(model02);
                
                // モデル03を再作成
                const model03 = document.createElement('a-entity');
                model03.setAttribute('id', 'modelGroup_03');
                model03.setAttribute('position', '4 0 -8');
                model03.setAttribute('rotation', '0 -45 0');
                model03.setAttribute('scale', '1 1 1');
                model03.setAttribute('approach-camera', 'speed: 0.35; useCamera: false; endPos: 2 0 -2; autoRespawn: true; waitTime: 3000');
                model03.setAttribute('visible', 'false');
                
                const model03Entity = document.createElement('a-entity');
                model03Entity.setAttribute('gltf-model', '#model_03');
                model03Entity.setAttribute('fit-to-hitbox', '');
                model03Entity.setAttribute('animation-mixer', 'clip: anime01; loop: repeat');
                model03Entity.setAttribute('enhance-materials', '');
                model03.appendChild(model03Entity);
                
                const hitBox03 = document.createElement('a-entity');
                hitBox03.setAttribute('id', 'hit-boxed_03');
                hitBox03.setAttribute('hit-box', '');
                hitBox03.setAttribute('position', '0 0.3 0');
                const cylinder03 = document.createElement('a-entity');
                cylinder03.setAttribute('geometry', 'primitive: cylinder');
                cylinder03.setAttribute('material', 'color: blue; opacity: 0.0; transparent: true');
                cylinder03.setAttribute('scale', '0.3 0.4 0.3');
                cylinder03.setAttribute('class', 'collidable');
                hitBox03.appendChild(cylinder03);
                model03.appendChild(hitBox03);
                sceneEl.appendChild(model03);
                
                // モデル04を再作成
                const model04 = document.createElement('a-entity');
                model04.setAttribute('id', 'modelGroup_04');
                model04.setAttribute('position', '-4 0 -8');
                model04.setAttribute('rotation', '0 45 0');
                model04.setAttribute('scale', '1 1 1');
                model04.setAttribute('approach-camera', 'speed: 0.3; useCamera: true; autoRespawn: true; waitTime: 3000');
                model04.setAttribute('visible', 'false');
                
                const model04Entity = document.createElement('a-entity');
                model04Entity.setAttribute('gltf-model', '#model_04');
                model04Entity.setAttribute('fit-to-hitbox', '');
                model04Entity.setAttribute('animation-mixer', 'clip: anime01; loop: repeat');
                model04Entity.setAttribute('enhance-materials', '');
                model04.appendChild(model04Entity);
                
                const hitBox04 = document.createElement('a-entity');
                hitBox04.setAttribute('id', 'hit-boxed_04');
                hitBox04.setAttribute('hit-box', '');
                hitBox04.setAttribute('position', '0 0.3 0');
                const cylinder04 = document.createElement('a-entity');
                cylinder04.setAttribute('geometry', 'primitive: cylinder');
                cylinder04.setAttribute('material', 'color: blue; opacity: 0.0; transparent: true');
                cylinder04.setAttribute('scale', '0.3 0.4 0.3');
                cylinder04.setAttribute('class', 'collidable');
                hitBox04.appendChild(cylinder04);
                model04.appendChild(hitBox04);
                sceneEl.appendChild(model04);
                
                // モデル05を再作成（Level 2専用）
                const model05 = document.createElement('a-entity');
                model05.setAttribute('id', 'modelGroup_05');
                model05.setAttribute('position', '3 0 -9');
                model05.setAttribute('rotation', '0 -30 0');
                model05.setAttribute('scale', '1 1 1');
                model05.setAttribute('approach-camera', 'speed: 0.3; useCamera: true; autoRespawn: true; waitTime: 2000');
                model05.setAttribute('visible', 'false');
                
                const model05Entity = document.createElement('a-entity');
                model05Entity.setAttribute('gltf-model', '#model_05');
                model05Entity.setAttribute('fit-to-hitbox', '');
                model05Entity.setAttribute('animation-mixer', 'clip: anime01; loop: repeat');
                model05Entity.setAttribute('enhance-materials', '');
                model05.appendChild(model05Entity);
                
                const hitBox05 = document.createElement('a-entity');
                hitBox05.setAttribute('id', 'hit-boxed_05');
                hitBox05.setAttribute('hit-box', '');
                hitBox05.setAttribute('position', '0 0.3 0');
                const cylinder05 = document.createElement('a-entity');
                cylinder05.setAttribute('geometry', 'primitive: cylinder');
                cylinder05.setAttribute('material', 'color: blue; opacity: 0.0; transparent: true');
                cylinder05.setAttribute('scale', '0.3 0.4 0.3');
                cylinder05.setAttribute('class', 'collidable');
                hitBox05.appendChild(cylinder05);
                model05.appendChild(hitBox05);
                sceneEl.appendChild(model05);
                
                // モデル06を再作成（Level 2専用）
                const model06 = document.createElement('a-entity');
                model06.setAttribute('id', 'modelGroup_06');
                model06.setAttribute('position', '-3 0 -9');
                model06.setAttribute('rotation', '0 30 0');
                model06.setAttribute('scale', '1 1 1');
                model06.setAttribute('approach-camera', 'speed: 0.3; useCamera: true; autoRespawn: true; waitTime: 2000');
                model06.setAttribute('visible', 'false');
                
                const model06Entity = document.createElement('a-entity');
                model06Entity.setAttribute('gltf-model', '#model_06');
                model06Entity.setAttribute('fit-to-hitbox', '');
                model06Entity.setAttribute('animation-mixer', 'clip: anime01; loop: repeat');
                model06Entity.setAttribute('enhance-materials', '');
                model06.appendChild(model06Entity);
                
                const hitBox06 = document.createElement('a-entity');
                hitBox06.setAttribute('id', 'hit-boxed_06');
                hitBox06.setAttribute('hit-box', '');
                hitBox06.setAttribute('position', '0 0.3 0');
                const cylinder06 = document.createElement('a-entity');
                cylinder06.setAttribute('geometry', 'primitive: cylinder');
                cylinder06.setAttribute('material', 'color: blue; opacity: 0.0; transparent: true');
                cylinder06.setAttribute('scale', '0.3 0.4 0.3');
                cylinder06.setAttribute('class', 'collidable');
                hitBox06.appendChild(cylinder06);
                model06.appendChild(hitBox06);
                sceneEl.appendChild(model06);
                
                window.debugLog('Initial models recreated');
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
                
                // 🚀 メモリ解放: 全てのアクティブなボールを削除
                if (window.activeBalls && window.activeBalls.length > 0) {
                    window.debugLog('Cleaning up', window.activeBalls.length, 'active balls');
                    window.activeBalls.forEach(ballData => {
                        if (ballData && ballData.ball) {
                            const ball = ballData.ball;
                            // DOMから削除
                            if (ball.parentNode) {
                                ball.parentNode.removeChild(ball);
                            }
                        }
                    });
                }
                window.activeBalls = [];
                window.usedPatterns = {}; // パターン使用状況をリセット
                
                // クリックブロックフラグをリセット
                this.clickBlocked = false;
                this.controllersUpdated = false;
                window.debugLog('Click block flags reset');
                
                // 【重要】全ての未実行setTimeoutをクリア（リスタート時のカクカク対策）
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
                    
                    window.debugLog('🚀 THREE.js GPU resource cleanup done');
                }
                
                // BGMを停止
                const bgm = document.getElementById('sound_bgm');
                if (bgm) {
                    bgm.pause();
                    bgm.currentTime = 0;
                }
                
                // 全パーティクルシステムを強制停止（パフォーマンス向上）＆ゴミクリア
                const particleIds = ['particle-normal', 'particle-tier1', 'particle-tier2', 'particle-tier3', 'particle-celebration'];
                particleIds.forEach(particleId => {
                    const particle = document.getElementById(particleId);
                    if (particle) {
                        // 🎯 パーティクルシステムの完全リセット
                        const particleSystem = particle.components['particle-system'];
                        if (particleSystem) {
                            particleSystem.stopParticles();
                            // パーティクルグループをリセット（ゴミ残り防止）
                            if (particleSystem.particleGroup) {
                                particleSystem.particleGroup.emitters.forEach(emitter => {
                                    emitter.reset(true);
                                });
                            }
                        }
                        
                        // 子要素のパーティクルシステムも処理（particle-celebration用）
                        const childParticles = particle.querySelectorAll('[particle-system]');
                        childParticles.forEach(child => {
                            const childComponent = child.components['particle-system'];
                            if (childComponent) {
                                childComponent.stopParticles();
                                if (childComponent.particleGroup) {
                                    childComponent.particleGroup.emitters.forEach(emitter => {
                                        emitter.reset(true);
                                    });
                                }
                            }
                        });
                        
                        particle.setAttribute('visible', 'false');
                        window.debugLog('Stopped and cleaned particle system:', particleId);
                    }
                });
                
                // すべてのモデルを完全に削除（アニメーションとコンポーネントもクリア）
                const allModels = ['modelGroup_01', 'modelGroup_02', 'modelGroup_03', 'modelGroup_04', 'modelGroup_05', 'modelGroup_06'];
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
                    leftController.setAttribute('raycaster', 'objects: .collidable, .clickable; far: 5');
                    window.debugLog('Restored .clickable to left controller');
                }
                if (rightController) {
                    rightController.setAttribute('raycaster', 'objects: .collidable, .clickable; far: 5');
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
                    window.debugLog('Cleared shoot component all caches');
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
                window.debugLog('=== RESTART CHECK ===');
                window.debugLog('Current playCount:', window.playCount);
                window.debugLog('MAX_PLAY_COUNT:', window.MAX_PLAY_COUNT);
                window.debugLog('Check result:', window.playCount >= window.MAX_PLAY_COUNT);
                
                if (window.playCount >= window.MAX_PLAY_COUNT) {
                    window.debugLog('✅ 最大プレイ回数に達しました。ページをリフレッシュします。');
                    
                    // 🎯 VRゴーグル対応: VRモードを終了してからリフレッシュ
                    const sceneEl = document.querySelector('a-scene');
                    const isVRMode = sceneEl && sceneEl.is('vr-mode');
                    
                    window.debugLog('🥽 VR Mode:', isVRMode);
                    
                    if (isVRMode) {
                        window.debugLog('🚪 Exiting VR mode before reload...');
                        
                        // VRモード終了後にリロード
                        sceneEl.exitVR().then(() => {
                            window.debugLog('✅ VR mode exited, reloading page...');
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
                        window.debugLog('💻 Normal mode, reloading immediately...');
                        this.performReload();
                    }
                    return;
                }
                
                window.debugLog('➡️ 通常リスタート処理を実行');
                
                // スタートメニューコンポーネントのrestartGame関数を呼び出す
                const startMenu = document.getElementById('startMenu');
                if (startMenu && startMenu.components['start-menu']) {
                    startMenu.components['start-menu'].restartGame();
                }
            },
            
            performReload: function() {
                window.debugLog('🔄 Attempting page reload...');
                
                // 複数の方法を試行（VRゴーグルのブラウザ互換性対応）
                try {
                    // 方法1: 標準的なリロード
                    if (window.location && window.location.reload) {
                        window.debugLog('Method 1: location.reload()');
                        window.location.reload(true); // キャッシュ無視
                        return;
                    }
                } catch (e) {
                    console.error('Method 1 failed:', e);
                }
                
                try {
                    // 方法2: location.href再設定
                    window.debugLog('Method 2: location.href reassignment');
                    window.location.href = window.location.href;
                } catch (e) {
                    console.error('Method 2 failed:', e);
                    
                    try {
                        // 方法3: location.replace（履歴残さない）
                        window.debugLog('Method 3: location.replace()');
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
                window.debugLog('=== RESTART TOUCH CHECK ===');
                window.debugLog('Current playCount:', window.playCount);
                window.debugLog('MAX_PLAY_COUNT:', window.MAX_PLAY_COUNT);
                window.debugLog('Check result:', window.playCount >= window.MAX_PLAY_COUNT);
                
                if (window.playCount >= window.MAX_PLAY_COUNT) {
                    window.debugLog('✅ 最大プレイ回数に達しました。ページをリフレッシュします。');
                    alert('ゲーム終了！お疲れ様でした。\nページをリフレッシュします。');
                    location.reload();
                    return;
                }
                
                window.debugLog('➡️ 通常リスタート処理を実行');
                
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
                    // VRモードに入ったらリスナーを再設定
                    window.registerTimeout(() => {
                        this.setupCanvasListeners();
                        this.setupControllerListeners();
                    }, 100);
                });
                
                this.el.sceneEl.addEventListener('exit-vr', () => {
                    window.debugLog('Exited VR mode');
                    // VRモードを出たらリスナーを再設定
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
                // 🚀 最適化: setAttributeではなくobject3Dを直接操作（高速化）
                ball.object3D.position.copy(currentPos);
                
                // 🚀 最適化: 最初の3フレームのみログ出力（DEBUG_MODE時のみ）
                ballData.frameCount++;
                if (ballData.frameCount <= 3 && window.DEBUG_MODE) {
                    window.debugLog(`Frame ${ballData.frameCount}: Ball at (${currentPos.x.toFixed(2)}, ${currentPos.y.toFixed(2)}, ${currentPos.z.toFixed(2)})`);
                }
                
                // 🚀 改善: 衝突判定の最適化（毎フレーム実行されるため重要）
                // モデル配列を初回のみ生成（キャッシュ）
                // 🚀 修正: modelsCache/hitBoxCacheを初期化（undefinedエラー防止）
                if (!this.modelsCache) this.modelsCache = {};
                if (!this.hitBoxCache) this.hitBoxCache = {};
                if (!this.modelsList) {
                    this.modelsList = [
                        { id: 'modelGroup_01', hitBoxId: 'hit-boxed_01' },
                        { id: 'modelGroup_02', hitBoxId: 'hit-boxed_02' },
                        { id: 'modelGroup_03', hitBoxId: 'hit-boxed_03' },
                        { id: 'modelGroup_04', hitBoxId: 'hit-boxed_04' },
                        { id: 'modelGroup_05', hitBoxId: 'hit-boxed_05' },
                        { id: 'modelGroup_06', hitBoxId: 'hit-boxed_06' }
                    ];
                }
                const models = this.modelsList;
                
                const hitThresholdSquared = 0.25; // 0.5 * 0.5 = 0.25（2乗で比較）
                
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
                    if (!modelGroup || !modelGroup.parentNode) continue;
                    
                    // 🚀 最適化1: visible=falseのモデルをスキップ（object3Dを直接参照）
                    if (!modelGroup.object3D.visible) continue;
                    
                    // 🚀 最適化: hitbox参照をキャッシュ（querySelectorを毎フレーム呼ばない）
                    let hitBox = this.hitBoxCache ? this.hitBoxCache[modelInfo.hitBoxId] : null;
                    if (!hitBox || !hitBox.parentNode) {
                        hitBox = modelGroup.querySelector(`#${modelInfo.hitBoxId}`);
                        if (!this.hitBoxCache) this.hitBoxCache = {};
                        if (hitBox) this.hitBoxCache[modelInfo.hitBoxId] = hitBox;
                    }
                    if (!hitBox) continue;
                    
                    // 🚀 最適化: getAttributeではなくobject3D.positionを直接参照
                    const modelPos = modelGroup.object3D.position;
                    
                    // 🚀 最適化2: 大まかな範囲チェック（XZ平面のみ、高速）
                    const dx = currentPos.x - modelPos.x;
                    const dz = currentPos.z - modelPos.z;
                    if (Math.abs(dx) > 1.0 || Math.abs(dz) > 1.0) continue; // 1m以上離れていたらスキップ
                    
                    // 🚀 最適化3: 距離の2乗で比較（Math.sqrtを回避）
                    const dy = currentPos.y - modelPos.y;
                    const distanceSquared = dx * dx + dy * dy + dz * dz;
                    
                    if (distanceSquared < hitThresholdSquared) {
                        ballData.hasHit = true;
                        window.debugLog(`Ball hit ${modelInfo.id}!`);
                        // 🚀 最適化: 既にキャッシュ済みのhitBoxを再利用
                        // 🎯 投げたボールの種類を渡す
                        if (hitBox) {
                            hitBox.emit('ball-hit', { ballIndex: ballData.ballIndex });
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
                        
                        // ヒット時の回転を速くする
                        ball.setAttribute('animation__spin', {
                            property: 'rotation',
                            to: '0 720 0',
                            dur: 300,
                            loop: false,
                            easing: 'linear'
                        });
                        
                        window.registerTimeout(() => {
                            if (ball.parentNode) {
                                ball.parentNode.removeChild(ball);
                                window.debugLog('Ball removed after bounce');
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
                // スペースキーが押された場合（トリガーボタン代替）
                if (event.code === 'Space') {
                    event.preventDefault(); // デフォルトのスペースキー動作を防ぐ
                    this.shoot(event);
                    window.debugLog('space key pressed');
                }
                // Gキーが押された場合（グリップボタン代替）
                if (event.code === 'KeyG') {
                    event.preventDefault();
                    // 【変更】ゲーム前でもボール切り替えを許可（ゲーム終了後のみ無視）
                    if (window.gameEnded) {
                        window.debugLog('Game ended, ignoring G key');
                        return;
                    }
                    // ボールタイプを切り替え
                    window.currentBallIndex = (window.currentBallIndex + 1) % window.ballTypes.length;
                    const newBall = window.ballTypes[window.currentBallIndex];
                    window.debugLog('G key pressed - Switched to ball:', newBall);
                    
                    // 両方のコントローラーのプレビューを更新
                    const leftController = document.getElementById('leftController');
                    const rightController = document.getElementById('rightController');
                    if (leftController && leftController.components['vr-controller']) {
                        leftController.components['vr-controller'].updatePreview();
                    }
                    if (rightController && rightController.components['vr-controller']) {
                        rightController.components['vr-controller'].updatePreview();
                    }
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
                
                // 【変更】ゲーム前でも発射を許可（ゲーム終了後のみ無視）
                if (window.gameEnded) {
                    window.debugLog('Game ended, ignoring shoot');
                    return;
                }
                
                // 🚀 パフォーマンス改善: ボールの同時描画数を制限（3個まで）
                if (window.activeBalls.length >= 3) {
                    window.debugLog('Ball limit reached (3), ignoring shoot');
                    return;
                }
                
                // 最後のshoot時刻を更新
                this.lastShootTime = Date.now();
                
                window.debugLog('=== Shoot function called ===');
                window.debugLog('Event type:', event.type);
                
                const sceneEl = this.el.sceneEl;
                const camera = this.el;
                
                // ボールエンティティを作成（選択されているGLBモデルを使用）
                const ball = document.createElement('a-entity');
                const currentBallModel = window.ballTypes[window.currentBallIndex];
                ball.setAttribute('gltf-model', currentBallModel);
                ball.setAttribute('scale', '0.14 0.14 0.14'); // サイズ調整Ｈ30%縮小: 0.2→0.14Ｉ
                ball.setAttribute('rotation', '0 0 0');
                
                window.debugLog('Creating ball with model:', currentBallModel);
                
                // 🚀 パフォーマンス改善: モデルが読み込まれたら適切なライティングを設定
                // {once: true} でリスナーを自動削除（メモリリーク防止）
                ball.addEventListener('model-loaded', function onBallModelLoaded() {
                    const mesh = ball.getObject3D('mesh');
                    if (mesh) {
                        mesh.traverse((node) => {
                            if (node.isMesh && node.material) {
                                // 発光は使わず、元の色のみを使用
                                node.material.emissive = new THREE.Color(0x000000); // 発光なし
                                node.material.emissiveIntensity = 0;
                                // 元のマテリアル色はそのまま保持
                                node.material.needsUpdate = true;
                            }
                        });
                    }
                }, { once: true }); // 🚀 1回だけ実行してリスナーを自動削除
                
                // 回転アニメーションを追加（飛んでいる間に回転）
                ball.setAttribute('animation__spin', {
                    property: 'rotation',
                    to: '720 90 0',
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
                
                // 物理演算で放物線を描く（餌やり体験なのでゆっくり）
                const gravity = -4.9; // 重力加速度 (m/s^2)
                const initialSpeed = 5; // 初速度 (m/s) - 餌やり体験用にゆっくり（10→5）
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
                    frameCount: 0,
                    ballIndex: window.currentBallIndex // 🎯 投げたボールの種類を記録
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
                waitTime: { type: 'number', default: 3000 },     // 終点到着後の待機時間（ms）
                active: { type: 'boolean', default: false }      // 移動を有効にするかどうか
            },
            
            init: function() {
                // 初期状態ではactiveプロパティに従う
                this.isMoving = this.data.active;
                this.hasReachedEnd = false;
                this.reachedTime = 0;
                this.startPosition = null;
                this.targetPosition = null;
                this.isRespawning = false; // 再描画中フラグ
                
                // 始点を設定（コンポーネント指定がなければ現在位置）
                if (this.data.startPos.x === 0 && this.data.startPos.y === 0 && this.data.startPos.z === -5) {
                    // デフォルト値の場合は現在位置を使用
                    const currentPos = this.el.getAttribute('position');
                    this.startPosition = new THREE.Vector3(currentPos.x, currentPos.y, currentPos.z);
                } else {
                    this.startPosition = new THREE.Vector3(this.data.startPos.x, this.data.startPos.y, this.data.startPos.z);
                }
                
                // 🚀 最適化: Vector3を事前生成（tick内でのnew回避）
                this._direction = new THREE.Vector3();
                
                window.debugLog('approach-camera initialized:', {
                    speed: this.data.speed,
                    useCamera: this.data.useCamera,
                    endPos: this.data.endPos,
                    autoRespawn: this.data.autoRespawn,
                    active: this.data.active
                });
            },
            
            // setAttributeで属性が変更された時に呼ばれる
            update: function(oldData) {
                // 初回呼び出し（init直後）はスキップ
                if (!oldData || Object.keys(oldData).length === 0) return;
                
                window.debugLog('approach-camera update called:', {
                    newStartPos: this.data.startPos,
                    oldStartPos: oldData.startPos,
                    newActive: this.data.active,
                    oldActive: oldData.active
                });
                
                // startPosが変更された場合、内部状態をリセット
                this.startPosition = new THREE.Vector3(
                    this.data.startPos.x,
                    this.data.startPos.y,
                    this.data.startPos.z
                );
                
                this.targetPosition = null;  // 終点を再計算させる
                this.hasReachedEnd = false;
                this.isRespawning = false;
                // activeの値に従って移動状態を設定
                this.isMoving = this.data.active;
                
                window.debugLog('approach-camera reset:', {
                    startPosition: this.startPosition,
                    isMoving: this.isMoving
                });
            },
            
            tick: function(time, timeDelta) {
                // ゲームが開始されていない、またはactiveでない場合は移動しない
                if (!window.gameStarted || !this.isMoving) {
                    // 終点に到着している場合、待機時間をチェック
                    if (this.hasReachedEnd && this.data.autoRespawn && !this.isRespawning) {
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
                        // カメラの初期位置（原点）をターゲットとして使用
                        // カメラが移動している可能性があるため、固定の原点を使用
                        this.targetPosition = new THREE.Vector3(0, 0, 0);
                        window.debugLog('Target set to origin (0, 0, 0)');
                    } else {
                        // useCameraがfalseの場合はendPosを使用
                        this.targetPosition = new THREE.Vector3(this.data.endPos.x, this.data.endPos.y, this.data.endPos.z);
                        window.debugLog('Target set to fixed endPos:', this.targetPosition);
                    }
                }
                
                // モデルの現在位置を取得
                const modelPos = this.el.object3D.position;
                
                // 🚀 最適化: 事前生成したVector3を再利用
                const direction = this._direction;
                direction.subVectors(this.targetPosition, modelPos);
                direction.y = 0; // Y軸方向は移動しない（地面を滑るように）
                
                const distance = direction.length();
                
                // 終点に到着したか判定（0.9m以内）
                if (distance < 0.9) {
                    this.isMoving = false;
                    this.hasReachedEnd = true;
                    this.reachedTime = Date.now();
                    window.debugLog('Model reached end position. Waiting for', this.data.waitTime, 'ms before respawn');
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
                const modelGroup = this.el;
                const modelId = modelGroup.id;
                const modelEntity = modelGroup.querySelector('[gltf-model]');
                const gltfModelSrc = modelEntity ? modelEntity.getAttribute('gltf-model') : null;
                
                if (!gltfModelSrc) {
                    console.error('Could not find gltf-model for respawn');
                    return;
                }
                
                window.debugLog('Despawning model:', modelId);
                
                // フェードアウト
                modelGroup.setAttribute('animation__fadeout', {
                    property: 'scale',
                    to: '0 0 0',
                    dur: 500,
                    easing: 'easeInQuad'
                });
                
                // フェードアウト後に削除して再生成
                window.registerTimeout(() => {
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
                this.isMoving = true;
                this.hasReachedEnd = false;
                this.reachedTime = 0;
                this.targetPosition = null;
                this.isRespawning = false; // 再描画中フラグもリセット
                window.debugLog('Approach-camera component reset');
            },
            
            // 🚀 最適化: リソースクリーンアップ
            remove: function() {
                this._direction = null;
                this.startPosition = null;
                this.targetPosition = null;
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
                
                // グローバルなrespawn関数を登録（初回のみ）
                if (!window.respawnModelGlobal) {
                    window.respawnModelGlobal = this.respawnModel.bind(this);
                }

                // ボールがヒットしたときのみ発火する独自イベント 'ball-hit' を監視
                this.el.addEventListener('ball-hit', (event) => {
                    if(!hitFlag) {
                        hitFlag = true;
                        window.debugLog('Model hit!', modelEntity);
                        
                        // 【変更】ゲーム中のみ、捕獲した動物の数をインクリメント
                        if (window.gameStarted && !window.gameEnded) {
                            window.enemiesDefeated++;
                            window.debugLog('Animals Captured:', window.enemiesDefeated);
                        }
                        
                        // 【重要】当たり判定オブジェクトを即座に消去（anime02再生中に再ヒットを防ぐ）
                        const hitBox = this.el;
                        if (hitBox && hitBox.parentNode) {
                            hitBox.parentNode.removeChild(hitBox);
                            window.debugLog('Hit box removed immediately');
                        }
                        
                        // 🎯 ボールマッチング判定（+10/+3スコア計算 + 連続正解ボーナス）
                        const thrownBallIndex = event.detail && event.detail.ballIndex !== undefined 
                            ? event.detail.ballIndex 
                            : window.currentBallIndex;
                        const requiredBallIndex = parseInt(modelGroup.getAttribute('data-required-ball') || '0');
                        const isCorrectBall = thrownBallIndex === requiredBallIndex;
                        
                        // 連続正解のカウント管理（不正解でもコンボは続く）
                        if (isCorrectBall) {
                            window.comboCount++; // 連続正解カウントを増やす
                            window.lastBallHit = true;
                        } else {
                            // 不正解でもコンボはリセットしない
                            window.lastBallHit = false;
                        }
                        
                        // 連続正解ボーナス計算（5連続以上で控えめな追加ボーナス）
                        let comboBonus = 0;
                        let comboBonusText = '';
                        if (isCorrectBall && window.comboCount >= 5) {
                            if (window.comboCount >= 15) {
                                comboBonus = 10; // 15連続以上: +10ボーナス
                                comboBonusText = '+10';
                            } else if (window.comboCount >= 10) {
                                comboBonus = 7; // 10-14連続: +7ボーナス
                                comboBonusText = '+7';
                            } else if (window.comboCount >= 5) {
                                comboBonus = 5; // 5-9連続: +5ボーナス
                                comboBonusText = '+5';
                            }
                        }
                        
                        // 【変更】ゲーム中のみ、スコア加算とコンボ管理
                        let scoreChange = 0;
                        if (window.gameStarted && !window.gameEnded) {
                            const baseScore = isCorrectBall ? 10 : 3;
                            scoreChange = baseScore + comboBonus;
                            window.totalScore += scoreChange;
                            
                            // スコアが0未満にならないように制限
                            if (window.totalScore < 0) window.totalScore = 0;
                            
                            // 最大コンボ数を更新
                            if (window.comboCount > window.maxComboCount) {
                                window.maxComboCount = window.comboCount;
                                window.debugLog('New Max Combo:', window.maxComboCount);
                            }
                        }
                        
                        window.debugLog('Ball Match:', isCorrectBall ? `CORRECT (+${scoreChange})` : 'WRONG (+3)', 
                                       '| Thrown:', thrownBallIndex, '| Required:', requiredBallIndex,
                                       '| Combo:', window.comboCount, '| Total Score:', window.totalScore);
                        
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
                        
                        // カメラとモデルの距離を計算（スコアテキストサイズ用）
                        const sceneEl = document.querySelector('a-scene');
                        const camera = sceneEl.camera ? sceneEl.camera.el : document.querySelector('[camera]');
                        
                        let distance = 0;
                        if (camera) {
                            // 🚀 最適化: グローバルキャッシュを使用
                            const modelPos = window._cachedModelPos;
                            const cameraPos = window._cachedCameraPos;
                            
                            modelGroup.object3D.getWorldPosition(modelPos);
                            camera.object3D.getWorldPosition(cameraPos);
                            
                            // 距離を計算（メートル単位）
                            distance = modelPos.distanceTo(cameraPos);
                            window.debugLog('Hit distance from camera:', distance.toFixed(2), 'm');
                        }
                        
                        // 【変更】ゲーム中のみ、リアルタイムスコア表示を更新
                        if (window.gameStarted && !window.gameEnded) {
                            const currentScoreText = document.getElementById('currentScore');
                            if (currentScoreText) {
                                currentScoreText.setAttribute('value', `SCORE: ${window.totalScore.toFixed(1)}`);
                            }
                        }
                        
                        // 距離に応じたスコアテキストのサイズを決定（3段階）
                        let scoreWidth = 6; // 基準サイズ（近距離）
                        if (distance > 8) {
                            scoreWidth = 16; // 遠距離（8m以上）：約2.7倍
                        } else if (distance > 4) {
                            scoreWidth = 10; // 中距離（4-8m）：約1.7倍
                        }
                        // 4m以内は基準サイズ（6 = 1倍）
                        
                        window.debugLog('Distance:', distance.toFixed(2), 'm, Score width:', scoreWidth);
                        
                        // 🎯 スコアテキストをモデルの上に表示（正解は緑、不正解も青、コンボは金色）
                        const scoreText = document.createElement('a-text');
                        let scoreDisplay;
                        let scoreColor;
                        if (isCorrectBall) {
                            if (comboBonus > 0) {
                                scoreDisplay = `+${scoreChange}pt ✓ (${window.comboCount})`;
                                scoreColor = '#FFD700'; // コンボ時は金色
                            } else {
                                scoreDisplay = `+${scoreChange}pt ✓`;
                                scoreColor = '#00FF00'; // 正解は緑
                            }
                        } else {
                            scoreDisplay = `+${scoreChange}pt`;
                            scoreColor = '#87CEEB'; // 不正解は青
                        }
                        scoreText.setAttribute('value', scoreDisplay);
                        scoreText.setAttribute('align', 'center');
                        scoreText.setAttribute('color', scoreColor);
                        scoreText.setAttribute('width', scoreWidth); // 距離に応じたサイズ
                        scoreText.setAttribute('font', 'mozillavr');
                        scoreText.setAttribute('shader', 'msdf');
                        scoreText.setAttribute('anchor', 'center');
                        
                        // 🚀 最適化: グローバルキャッシュを使用
                        const scoreModelPos = window._cachedScorePos;
                        modelGroup.object3D.getWorldPosition(scoreModelPos);
                        
                        // ワールド座標に配置
                        scoreText.setAttribute('position', `${scoreModelPos.x} ${scoreModelPos.y + 0.5} ${scoreModelPos.z}`);
                        
                        // カメラの方を向く（独自face-cameraコンポーネント）
                        scoreText.setAttribute('face-camera', '');
                        
                        // シーンに直接追加
                        sceneEl.appendChild(scoreText);
                        window.debugLog('Score text added to scene at world position');
                        
                        // 🎯 パーティクル表示（正解時のみシンプルに表示）
                        if (isCorrectBall) {
                            const normalParticle = document.getElementById('particle-normal');
                            if (normalParticle) {
                                // 🚀 最適化: グローバルキャッシュを使用
                                const normalParticlePos = window._cachedParticlePos;
                                modelGroup.object3D.getWorldPosition(normalParticlePos);
                                normalParticle.setAttribute('position', `${normalParticlePos.x} ${normalParticlePos.y + 0.5} ${normalParticlePos.z}`);
                                normalParticle.setAttribute('visible', true);
                                
                                // 🎯 パーティクルクリーンアップ: 1秒後に確実に非表示＆リセット
                                window.registerTimeout(() => {
                                    normalParticle.setAttribute('visible', false);
                                    // パーティクルシステムをリスタート（ゴミ残り防止）
                                    const particleComponent = normalParticle.components['particle-system'];
                                    if (particleComponent && particleComponent.particleGroup) {
                                        particleComponent.particleGroup.emitters.forEach(emitter => {
                                            emitter.reset(true);
                                        });
                                    }
                                }, 1000);
                            }
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
                        
                        // カメラへの移動を停止
                        const approachComponent = modelGroup.components['approach-camera'];
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
                                        
                                        // 4秒後に別の場所に再描画
                                        window.registerTimeout(() => {
                                            this.respawnModel(modelId, gltfModelSrc);
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
                        // 🚀 最適化: グローバルキャッシュを使用
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
                    
                    // 🚀 最適化: グローバルキャッシュを使用
                    const comboModelPos = window._cachedScorePos;
                    modelGroup.object3D.getWorldPosition(comboModelPos);
                    
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
                
                // ランダムパターン設定（8パターン）- ヒット後2秒で再描画 🚀 30%遅く調整
                const allMovementPatterns = [
                    // パターン1: 左後方からカメラへ（速い）- Level 1対象 - 距離: 3.6m
                    {
                        startPos: { x: -3, y: 0, z: -3 },
                        speed: 0.196,
                        useCamera: true,
                        waitTime: 2000
                    },
                    // パターン2: 右後方からカメラへ（普通）- Level 1対象 - 距離: 4.0m
                    {
                        startPos: { x: 0, y: 0, z: -4 },
                        speed: 0.168,
                        useCamera: true,
                        waitTime: 2000
                    },
                    // パターン3: 正面奥からカメラへ（遅い）- Level 1対象 - 距離: 3.6m
                    {
                        startPos: { x: 3, y: 0, z: -3 },
                        speed: 0.14,
                        useCamera: true,
                        waitTime: 2000
                    },
                    // パターン4: 正面奥からカメラへ（普通）- Level 1対象 - 距離: 5.0m
                    {
                        startPos: { x: 5, y: 0, z: 0 },
                        speed: 0.168,
                        useCamera: true,
                        waitTime: 2000
                    },
                    // パターン5: 右奥からカメラへ（速い）- Level 2対象 - 距離: 12.2m
                    {
                        startPos: { x: 6, y: 0, z: 6 },
                        speed: 0.112,
                        useCamera: true,
                        waitTime: 2000
                    },
                    // パターン6: 後ろからカメラへ（速い）- Level 2対象 - 距離: 6.3m
                    {
                        startPos: { x: -6, y: 0, z: 2 },
                        speed: 0.112,
                        useCamera: true,
                        waitTime: 2000
                    },
                    // パターン7: 左から右へ横移動（固定終点）- Level 2のみ - 距離: 12.0m
                    {
                        startPos: { x: 1, y: 0, z: 7 },
                        speed: 0.112,
                        useCamera: true,
                        waitTime: 2000
                    },
                    // パターン8: 右から左へ横移動（固定終点）- Level 2のみ - 距離: 12.0m
                    {
                        startPos: { x: -3, y: 0, z: 5 },
                        speed: 0.112,
                        useCamera: true,
                        waitTime: 2000
                    }
                ];
                
                // レベルに応じてパターンをフィルタリング
                const movementPatterns = window.currentLevel === 1 
                    ? allMovementPatterns.slice(0, 4)  // Level 1: パターン1-4のみ
                    : allMovementPatterns;              // Level 2: 全パターン1-8
                
                // スピード倍率（Level 2は2倍速）
                const speedMultiplier = window.currentLevel === 2 ? 2.0 : 1.0;
                
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
                    waitTime: waitTime,
                    active: true // 常にアクティブ
                };
                
                // endPosが設定されている場合は追加
                if (endPos) {
                    cameraConfig.endPos = endPos;
                }
                
                newModelGroup.setAttribute('approach-camera', cameraConfig);
                
                // 3Dモデルエンティティを作成
                const newModelEntity = document.createElement('a-entity');
                newModelEntity.setAttribute('gltf-model', gltfModelSrc);
                newModelEntity.setAttribute('fit-to-hitbox', '');
                newModelEntity.setAttribute('animation-mixer', 'clip: anime01; loop: repeat');
                newModelEntity.setAttribute('enhance-materials', ''); // マテリアル品質向上
                newModelGroup.appendChild(newModelEntity);
                
                // 🎯 動物の頭上にランダムなボールインジケーターを追加
                const requiredBallIndex = Math.floor(Math.random() * 2); // 0 or 1
                newModelGroup.setAttribute('data-required-ball', requiredBallIndex.toString());
                
                // 🚀 最適化: GLBの代わりにシンプルな球体を使用（メモリ節約）
                const ballIndicator = document.createElement('a-sphere');
                ballIndicator.setAttribute('id', `ball-indicator-${modelId}`);
                ballIndicator.setAttribute('radius', '0.12');
                ballIndicator.setAttribute('position', '0 1.2 0'); // 動物の頭上に配置
                // リンゴ=赤、キャベツ=緑
                const indicatorColor = requiredBallIndex === 0 ? '#FF0000' : '#00FF00';
                ballIndicator.setAttribute('color', indicatorColor);
                ballIndicator.setAttribute('material', `color: ${indicatorColor}; emissive: ${indicatorColor}; emissiveIntensity: 0.3; shader: flat`);
                // 🚀 最適化: 回転アニメーションを削除（負荷軽減）
                newModelGroup.appendChild(ballIndicator);
                window.debugLog('Ball indicator added:', requiredBallIndex === 0 ? 'Apple (Red)' : 'Cabbage (Green)');
                
                // 当たり判定オブジェクトを作成
                const hitBoxId = modelId.replace('modelGroup', 'hit-boxed');
                const newHitBox = document.createElement('a-entity');
                newHitBox.setAttribute('id', hitBoxId);
                newHitBox.setAttribute('hit-box', '');
                newHitBox.setAttribute('position', '0 0.3 0'); // HTMLと同じ位置に修正
                
                const hitBoxCylinder = document.createElement('a-entity');
                // ジオメトリに直接サイズを指定（scaleではなくgeometryで）
                hitBoxCylinder.setAttribute('geometry', 'primitive: cylinder; radius: 0.3; height: 0.8');
                hitBoxCylinder.setAttribute('material', 'color: blue; opacity: 0.0; transparent: true');
                hitBoxCylinder.setAttribute('class', 'collidable');
                
                newHitBox.appendChild(hitBoxCylinder);
                newModelGroup.appendChild(newHitBox);
                
                // シーンに追加
                sceneEl.appendChild(newModelGroup);
                window.debugLog('Model added to scene with pattern', patternIndex, ':', randomPattern);
                
                // フェードインアニメーション
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
                    
                    // VRデバイスが利用可能かチェック
                    if (navigator.xr) {
                        navigator.xr.isSessionSupported('immersive-vr').then((supported) => {
                            if (supported) {
                                window.debugLog('VR device detected! Auto-entering VR mode...');
                                window.updateDebug('VR device found! Entering VR...');
                                
                                // 少し待ってからVRモードに入る（アセット読み込み完了を待つ）
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
                window.debugLog("vr-controller initialized for", this.el.id);
                
                // Aボタン、Bボタン、グリップボタンでボール切り替え（Pico4対応）
                this.onButtonDown = this.onButtonDown.bind(this);
                this.el.addEventListener("abuttondown", this.onButtonDown); // Aボタン
                this.el.addEventListener("bbuttondown", this.onButtonDown); // Bボタン
                this.el.addEventListener("gripdown", this.onButtonDown); // グリップボタン
                
                // コントローラー先端にボールプレビューを追加
                this.createBallPreview();
                
                // ボール切り替え時にプレビューを更新
                this.updatePreview = this.updatePreview.bind(this);
            },
            
            onButtonDown: function(e) {
                window.debugLog("A/B/Grip button pressed on", this.el.id);
                
                // 【変更】ゲーム前でもボール切り替えを許可（ゲーム終了後のみ無視）
                if (window.gameEnded) {
                    window.debugLog('Game ended, ignoring button');
                    return;
                }
                
                // ボールタイプを切り替え（0 <-> 1）
                window.currentBallIndex = (window.currentBallIndex + 1) % window.ballTypes.length;
                const newBall = window.ballTypes[window.currentBallIndex];
                window.debugLog("Switched to ball:", newBall);
                
                // プレビューを更新
                this.updatePreview();
            },
            
            createBallPreview: function() {
                // コントローラー先端にボールのプレビューを作成
                this.ballPreview = document.createElement('a-entity');
                this.ballPreview.setAttribute('id', this.el.id + '-ball-preview');
                this.ballPreview.setAttribute('gltf-model', window.ballTypes[window.currentBallIndex]);
                this.ballPreview.setAttribute('scale', '0.05 0.05 0.05'); // 小さめに表示
                this.ballPreview.setAttribute('position', '0 0 -0.15'); // コントローラーの少し前
                this.ballPreview.setAttribute('rotation', '0 0 0');
                
                // モデルが読み込まれたら明るくする
                this.ballPreview.addEventListener('model-loaded', () => {
                    const mesh = this.ballPreview.getObject3D('mesh');
                    if (mesh) {
                        mesh.traverse((node) => {
                            if (node.isMesh && node.material) {
                                node.material.emissive = new THREE.Color(0x444444);
                                node.material.emissiveIntensity = 0.3;
                                node.material.needsUpdate = true;
                            }
                        });
                        window.debugLog('Ball preview material enhanced');
                    }
                });
                
                // 回転アニメーション追加
                this.ballPreview.setAttribute('animation', {
                    property: 'rotation',
                    to: '0 360 0',
                    dur: 3000,
                    loop: true,
                    easing: 'linear'
                });
                
                this.el.appendChild(this.ballPreview);
                window.debugLog('Ball preview created on', this.el.id);
            },
            
            updatePreview: function() {
                // プレビューのモデルを更新
                if (this.ballPreview) {
                    const newModel = window.ballTypes[window.currentBallIndex];
                    this.ballPreview.setAttribute('gltf-model', newModel);
                    window.debugLog('Ball preview updated to:', newModel);
                }
            },
            
            remove: function() {
                this.el.removeEventListener("abuttondown", this.onButtonDown);
                this.el.removeEventListener("bbuttondown", this.onButtonDown);
                this.el.removeEventListener("gripdown", this.onButtonDown);
                if (this.ballPreview && this.ballPreview.parentNode) {
                    this.ballPreview.parentNode.removeChild(this.ballPreview);
                }
            }
        });


    </script>
</head>

<body>
    <a-scene 
        renderer="antialias: true; 
                  colorManagement: true; 
                  sortObjects: true; 
                  physicallyCorrectLights: true; 
                  exposure: 1;
                  toneMapping: ACESFilmic"
        vr-mode-ui="enabled: true"
        auto-enter-vr
        gltf-model="dracoDecoderPath: https://www.gstatic.com/draco/versioned/decoders/1.5.6/">
        <a-assets timeout="4000">
            <!-- 3Dモデル -->
            <a-asset-item id="model_01" src={{ asset('cg/3d_pro_whiteTiger_isobe.glb') }}></a-asset-item>
            <a-asset-item id="model_02" src={{ asset('cg/3d_pro_pengin_morita.glb') }}></a-asset-item>
            <a-asset-item id="model_03" src={{ asset('cg/3d_pro_namakemono_oda.glb') }}></a-asset-item>
            <a-asset-item id="model_04" src={{ asset('cg/3d_pro_burger_fujii.glb') }}></a-asset-item>
            <a-asset-item id="model_05" src={{ asset('cg/3d_pro_cat_fukuda.glb') }}></a-asset-item>
            <a-asset-item id="model_06" src={{ asset('cg/3d_pro_bear_tagashira.glb') }}></a-asset-item>

            <!-- サウンド -->
            <audio id="sound_hit" src={{ asset('cg/sound_hit01.mp3') }} preload="auto"></audio>
            <audio id="sound_bgm" src={{ asset('cg/sound_bgm05.mp3') }} preload="auto"></audio>
            
            <!-- 背景画像 -->
            <img id="sky02" src={{ asset('cg/R0010191.JPG') }} crossorigin="anonymous" >
            <!-- <img id="sky02" src={{ asset('cg/IMG_20251012_155122_00_048.jpg') }} crossorigin="anonymous" > -->
        </a-assets>

        <!-- ライティング設定（3Dモデルをきれいに表示） -->
        <a-entity light="type: ambient; color: #DDD; intensity: 1.2"></a-entity>
        <a-entity light="type: directional; color: #FFF; intensity: 1.5" position="1 2 1"></a-entity>
        <a-entity light="type: directional; color: #FFF; intensity: 0.8" position="-1 1 -1"></a-entity>
        <a-entity light="type: directional; color: #FFF; intensity: 0.6" position="0 1 2"></a-entity>

        <!-- マウスカーソル（スタートメニューとゲームオブジェクトをターゲット） -->
        <!-- fuseを無効化し、クリックイベントを発火させるために必要な設定 -->
        <a-entity id="mouseCursor" cursor="rayOrigin: mouse; fuse: false" raycaster="objects: .clickable, .collidable; far: 100"></a-entity>

        <!-- Controller -->
        <a-entity id="leftController" laser-controls="hand: left" raycaster="objects: .collidable, .clickable; far: 5" vr-controller></a-entity>
        <a-entity id="rightController" laser-controls="hand: right" raycaster="objects: .collidable, .clickable; far: 5" vr-controller></a-entity>

        <!-- スタートメニュー（半透明） -->
        <a-entity id="startMenu" position="0 1.6 -3" start-menu>
            <!-- 背景パネル（半透明）- クリック不可 -->
            <a-plane 
                id="menuBackground"
                position="0 0 -0.01" 
                width="3" 
                height="2" 
                color="#000000" 
                opacity="0.7" 
                material="transparent: true">
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
                position="-0.8 -0.5 0.1" 
                width="1.4" 
                height="0.6" 
                color="#00FF00" 
                opacity="0.9"
                material="transparent: true; side: double"
                class="clickable">
            </a-plane>
            
            <!-- Level 1 ボタンテキスト -->
            <a-text 
                value="Level 1&#10;EASY" 
                position="-0.8 -0.5 0.12" 
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
                position="0.8 -0.5 0.1" 
                width="1.4" 
                height="0.6" 
                color="#FF6600" 
                opacity="0.9"
                material="transparent: true; side: double"
                class="clickable">
            </a-plane>
            
            <!-- Level 2 ボタンテキスト -->
            <a-text 
                value="Level 2&#10;HARD" 
                position="0.8 -0.5 0.12" 
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
            
            <!-- PC操作説明 -->
            <a-text 
                value="PC: Space=Shoot | G=Switch Ball | Click=Shoot" 
                position="0 -1.7 0.02" 
                align="center" 
                color="#88FF88" 
                width="4"
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

        <!-- モデル01グループ（初期非表示・70%縮小） -->
        <a-entity id="modelGroup_01" position="0 0 -50" rotation="0 0 0" scale="1 1 1" 
                  approach-camera="speed: 0.3; useCamera: true; autoRespawn: true; waitTime: 3000; active: false" 
                  visible="false">
            <a-entity gltf-model="#model_01" animation-mixer="clip: anime01; loop: repeat" enhance-materials fit-to-hitbox></a-entity>
            <a-entity id="hit-boxed_01" hit-box position="0 0.3 0">
                <a-entity geometry="primitive: cylinder; radius: 0.3; height: 0.8" material="color: blue; opacity: 0.0; transparent: true" 
                          class="collidable"></a-entity>
            </a-entity>
        </a-entity>

        <!-- モデル02グループ（初期非表示） -->
        <a-entity id="modelGroup_02" position="0 0 -50" rotation="0 0 0" scale="1 1 1" 
                  approach-camera="speed: 0.25; useCamera: true; autoRespawn: true; waitTime: 3000; active: false" 
                  visible="false">
            <a-entity gltf-model="#model_02" animation-mixer="clip: anime01; loop: repeat" enhance-materials fit-to-hitbox></a-entity>
            <a-entity id="hit-boxed_02" hit-box position="0 0.3 0">
                <a-entity geometry="primitive: cylinder; radius: 0.3; height: 0.8" material="color: blue; opacity: 0.0; transparent: true" 
                          class="collidable"></a-entity>
            </a-entity>
        </a-entity>

        <!-- モデル03グループ（初期非表示） -->
        <!-- 例: 固定終点を使う場合は useCamera: false; endPos: x y z を指定 -->
        <a-entity id="modelGroup_03" position="0 0 -50" rotation="0 0 0" scale="1 1 1" 
                  approach-camera="speed: 0.35; useCamera: true; autoRespawn: true; waitTime: 3000; active: false" 
                  visible="false">
            <a-entity gltf-model="#model_03" animation-mixer="clip: anime01; loop: repeat" enhance-materials fit-to-hitbox></a-entity>
            <a-entity id="hit-boxed_03" hit-box position="0 0.3 0">
                <a-entity geometry="primitive: cylinder; radius: 0.3; height: 0.8" material="color: blue; opacity: 0.0; transparent: true" 
                          class="collidable"></a-entity>
            </a-entity>
        </a-entity>

        <!-- モデル04グループ（初期非表示・Level 2専用） -->
        <a-entity id="modelGroup_04" position="0 0 -50" rotation="0 0 0" scale="1 1 1" 
                  approach-camera="speed: 0.3; useCamera: true; autoRespawn: true; waitTime: 2000; active: false" 
                  visible="false">
            <a-entity gltf-model="#model_04" animation-mixer="clip: anime01; loop: repeat" enhance-materials fit-to-hitbox></a-entity>
            <a-entity id="hit-boxed_04" hit-box position="0 0.3 0">
                <a-entity geometry="primitive: cylinder; radius: 0.3; height: 0.8" material="color: blue; opacity: 0.0; transparent: true" 
                          class="collidable"></a-entity>
            </a-entity>
        </a-entity>

        <!-- モデル05グループ（初期非表示・Level 2専用） -->
        <a-entity id="modelGroup_05" position="0 0 -50" rotation="0 0 0" scale="1 1 1" 
                  approach-camera="speed: 0.3; useCamera: true; autoRespawn: true; waitTime: 2000; active: false" 
                  visible="false">
            <a-entity gltf-model="#model_05" animation-mixer="clip: anime01; loop: repeat" enhance-materials fit-to-hitbox></a-entity>
            <a-entity id="hit-boxed_05" hit-box position="0 0.3 0">
                <a-entity geometry="primitive: cylinder; radius: 0.3; height: 0.8" material="color: blue; opacity: 0.0; transparent: true" 
                          class="collidable"></a-entity>
            </a-entity>
        </a-entity>

        <!-- モデル06グループ（初期非表示・Level 2専用） -->
        <a-entity id="modelGroup_06" position="0 0 -50" rotation="0 0 0" scale="1 1 1" 
                  approach-camera="speed: 0.3; useCamera: true; autoRespawn: true; waitTime: 2000; active: false" 
                  visible="false">
            <a-entity gltf-model="#model_06" animation-mixer="clip: anime01; loop: repeat" enhance-materials fit-to-hitbox></a-entity>
            <a-entity id="hit-boxed_06" hit-box position="0 0.3 0">
                <a-entity geometry="primitive: cylinder; radius: 0.3; height: 0.8" material="color: blue; opacity: 0.0; transparent: true" 
                          class="collidable"></a-entity>
            </a-entity>
        </a-entity>

        <!-- 360度画像を表示 -->
        <a-sky id="aSky" src="#sky02"></a-sky>

        <!-- Particle Effects - 🚀 軽量化版 -->
        <!-- 通常ヒット用（正解時）: 緑色、5 particles -->
        <a-entity id="particle-normal" visible="false" position="0 3 0" 
                  particle-system="preset: default; color: #00FF00; particleCount: 5; size: 0.08; maxAge: 0.8; velocityValue: 1 1 1; velocitySpread: 1.5 1.5 1.5; accelerationValue: 0 -2 0; accelerationSpread: 0.3 0.3 0.3"></a-entity>
        
        <!-- Tier 1: 1.1x (2-3 combo) - Cyan, 8 particles（使用されなくなったがバックアップ用に残す） -->
        <a-entity id="particle-tier1" visible="false" position="0 3 0" 
                  particle-system="preset: default; color: #00FFFF; particleCount: 8; size: 0.08; maxAge: 1.0; velocityValue: 1.5 1.5 1.5; velocitySpread: 2 2 2; accelerationValue: 0 -2 0; accelerationSpread: 0.5 0.5 0.5"></a-entity>
        
        <!-- Tier 2: 1.2x (4-5 combo) - Orange, 10 particles（使用されなくなったがバックアップ用に残す） -->
        <a-entity id="particle-tier2" visible="false" position="0 3 0" 
                  particle-system="preset: default; color: #FF6600; particleCount: 10; size: 0.1; maxAge: 1.0; velocityValue: 1.5 1.5 1.5; velocitySpread: 2 2 2; accelerationValue: 0 -2 0; accelerationSpread: 0.5 0.5 0.5"></a-entity>
        
        <!-- Tier 3: 1.3x (6+ combo) - Magenta, 12 particles（使用されなくなったがバックアップ用に残す） -->
        <a-entity id="particle-tier3" visible="false" position="0 3 0" 
                  particle-system="preset: default; color: #FF00FF; particleCount: 12; size: 0.12; maxAge: 1.0; velocityValue: 1.5 1.5 1.5; velocitySpread: 2 2 2; accelerationValue: 0 -2 0; accelerationSpread: 0.5 0.5 0.5"></a-entity>
        
        <!-- Top 5 Celebration Particle - 🚀 軽量化版ゴールドパーティクル -->
        <a-entity id="particle-celebration" visible="false" position="0 2 -3">
            <!-- メインゴールドパーティクル：30個に削減 -->
            <a-entity particle-system="preset: default; color: #FFD700,#FFA500,#FFFF00; particleCount: 30; size: 0.25; maxAge: 2; velocityValue: 0 4 0; velocitySpread: 3 2 3; accelerationValue: 0 -1 0; accelerationSpread: 1 0 1; blending: 1"></a-entity>
            
            <!-- 輝く星パーティクル：15個に削減 -->
            <a-entity particle-system="preset: default; color: #FFFFFF,#FFD700; particleCount: 15; size: 0.12; maxAge: 1.8; velocityValue: 0 2 0; velocitySpread: 3 2 3; accelerationValue: 0 -0.5 0; accelerationSpread: 0.5 0 0.5; blending: 1" position="0 0.5 0"></a-entity>
            
            <!-- 紙吹雪効果：20個に削減 -->
            <a-entity particle-system="preset: default; color: #FF1493,#00FFFF,#FF6600,#00FF00,#9400D3; particleCount: 20; size: 0.15; maxAge: 2.5; velocityValue: 0 3 0; velocitySpread: 4 1 4; accelerationValue: 0 -2 0; accelerationSpread: 2 0 2; blending: 1; rotation: 0 0 45" position="0 1 0"></a-entity>
        </a-entity>
        

        <a-camera id="my_camera" shoot wasd-controls="enabled: false" look-controls="pointerLockEnabled: false">
        </a-camera>
    </a-scene>
</body>

</html>