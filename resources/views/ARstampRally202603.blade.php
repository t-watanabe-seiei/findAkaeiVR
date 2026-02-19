<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AR Stamp Rally</title>
    <script>
        // グローバル変数：飛んでいるボールの数（パフォーマンス最適化用）
        window.activeBalls = 0;
        window.allHitboxes = []; // グローバルでヒットボックスを管理

        // グローバルエラーハンドラ（解析用／実行継続の助け）
        window.addEventListener('error', function(e) {
            try { console.error('Global error:', e && e.message ? e.message : e); } catch (err) { /* ignore */ }
        });
        window.addEventListener('unhandledrejection', function(e) {
            try { console.error('UnhandledPromiseRejection:', e && e.reason ? e.reason : e); } catch (err) { /* ignore */ }
        });

        // 古いAndroidを自動判定（Android 7以下なら true）
        function detectOldAndroid() {
            try {
                const ua = navigator.userAgent || '';
                const m = ua.match(/Android\s([0-9]+)(?:[\.\_][0-9]*)?/i);
                if (m && m[1]) {
                    const major = parseInt(m[1], 10);
                    return major <= 7;
                }
            } catch (e) { /* ignore */ }
            return false;
        }

        // lowres フラグ（URLパラメータ or 自動判定）
        window.AR_FORCE_LOWRES = (function() {
            const url = new URL(window.location.href);
            if (url.searchParams.get('lowres') === '1') return true;
            return detectOldAndroid();
        })();

        // デバッグ用: ARカメラの起動を監視し、失敗時に再試行UIを表示
        function monitorCameraStartup(timeoutMs = 6000) {
            const start = Date.now();
            const interval = setInterval(() => {
                // 動画要素が見つかり、ある程度読み込みが進んでいればOK
                const v = document.querySelector('video');
                if (v && (v.readyState >= 2 || v.currentTime > 0 || !v.paused)) {
                    clearInterval(interval);
                    console.log('Camera started OK');
                    const el = document.getElementById('camera-error'); if (el) el.style.display = 'none';
                    return;
                }
                if (Date.now() - start > timeoutMs) {
                    clearInterval(interval);
                    console.warn('Camera did not start within', timeoutMs, 'ms');
                    const el = document.getElementById('camera-error'); if (el) el.style.display = 'flex';

                    // もし古い端末であれば低解像度再試行ボタンを自動で表示（UIにボタンがあるのでこちらは任意）
                    const lowBtn = document.getElementById('retry-camera-lowres');
                    if (lowBtn) lowBtn.style.display = 'inline-block';
                }
            }, 500);
        }

        // ボール（entity）を即時非表示にしてメモリを開放し、DOMから削除するユーティリティ
        function destroyAndFreeEntity(el) {
            if (!el) return;
            try {
                try { el.setAttribute('visible', 'false'); } catch (e) {}

                // Three.js の mesh を取得して traverse で dispose
                const mesh = el.getObject3D && el.getObject3D('mesh');
                if (mesh) {
                    mesh.traverse((node) => {
                        try {
                            if (node.isMesh) {
                                if (node.geometry) {
                                    try { node.geometry.dispose(); } catch (e) {}
                                    node.geometry = undefined;
                                }
                                if (node.material) {
                                    const materials = Array.isArray(node.material) ? node.material.slice() : [node.material];
                                    materials.forEach((mat) => {
                                        try {
                                            // dispose common texture maps
                                            ['map','metalnessMap','roughnessMap','normalMap','emissiveMap','aoMap','alphaMap'].forEach(k => {
                                                if (mat[k] && typeof mat[k].dispose === 'function') {
                                                    try { mat[k].dispose(); mat[k] = null; } catch (e) {}
                                                }
                                            });
                                            if (typeof mat.dispose === 'function') mat.dispose();
                                        } catch (e) {}
                                    });
                                    node.material = undefined;
                                }
                            }
                        } catch (e) { /* ignore per-node errors */ }
                    });
                }

                // emit for existing listeners and remove element from DOM
                try { el.emit('pokeball-gone'); } catch (e) {}
                if (el.parentNode) {
                    try { el.parentNode.removeChild(el); } catch (e) {}
                }
            } catch (err) {
                console.warn('destroyAndFreeEntity failed', err);
                try { if (el.parentNode) el.parentNode.removeChild(el); } catch (e) {}
            }
        }

        // 最優先でキーボードイベントをブロック（キャプチャフェーズで捕捉）
        document.addEventListener('keydown', function(e) {
            // Ctrl+U, Cmd+U (ソースコード表示)
            if ((e.ctrlKey || e.metaKey) && (e.key === 'u' || e.key === 'U' || e.keyCode === 85)) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        }, true);

        // スタンプを登録（グローバル関数として定義）
        function collectStamp(stampId, screenshot = null) {
            if (!stampId) {
                console.warn('collectStamp called with empty stampId');
                return false;
            }
                    try {
                        const collectedStamps = getCollectedStamps();

                        if (!collectedStamps[stampId]) {
                            const name = (STAMPS[stampId] && STAMPS[stampId].name) ? STAMPS[stampId].name : (stampId || 'unknown');
                            collectedStamps[stampId] = {
                                collectedAt: new Date().toISOString(),
                                name: name,
                                screenshot: screenshot // スクリーンショットのBase64データ
                            };
                            saveCollectedStamps(collectedStamps);
                            updateStampBadge();

                            // 動物をゲットした時だけマーカースキャンを記録
                            try { recordMarkerScan(stampId, name, 'ball_hit'); } catch (e) { console.warn('recordMarkerScan failed', e); }

                            // 新規取得の処理
                            const totalCollected = Object.keys(collectedStamps).length;
                            const isComplete = totalCollected === Object.keys(STAMPS).length;

                            // 音声再生
                            if (isComplete) {
                                // 全種類コンプリート！
                                playSound(soundStamp02);
                                showCompleteParticles();
                            } else {
                                // 通常の取得
                                playSound(soundStamp01);
                                showNormalParticles();
                            }

                            // 通知表示
                            showStampNotification(stampId, isComplete);

                            console.log('✓ Stamp collected:', stampId, 'Total:', totalCollected);
                            return true;
                        } else {
                            // 既にある場合はスクショが提供されれば上書き
                            if (screenshot && (!collectedStamps[stampId].screenshot || collectedStamps[stampId].screenshot.length < 100)) {
                                collectedStamps[stampId].screenshot = screenshot;
                                saveCollectedStamps(collectedStamps);
                                console.log('Updated screenshot for already-collected stamp:', stampId);
                                return true;
                            }
                            console.log('Already collected:', stampId);
                            return false;
                        }
                    } catch (err) {
                        console.error('collectStamp failed for', stampId, err);
                        try {
                            // 可能であればローカルストレージを初期化して再試行
                            localStorage.removeItem('ar-stamp-rally-202603');
                        } catch (e) { /* ignore */ }
                        return false;
                    }
                }
        
        // ブラウザのページズームを完全に防止（モデルのズームは許可）
        document.addEventListener('gesturestart', function(e) {
            e.preventDefault();
        }, { passive: false });

        document.addEventListener('gesturechange', function(e) {
            e.preventDefault();
        }, { passive: false });

        document.addEventListener('gestureend', function(e) {
            e.preventDefault();
        }, { passive: false });

        // ダブルタップズームを防止
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(e) {
            const now = Date.now();
            if (now - lastTouchEnd <= 300) {
                e.preventDefault();
            }
            lastTouchEnd = now;
        }, { passive: false });

        // ピンチズームをdocumentレベルでブロック
        document.addEventListener('touchmove', function(e) {
            if (e.touches && e.touches.length > 1) {
                // 2本指以上のタッチはピンチズームの可能性
                // ブラウザのデフォルト動作をキャンセル
                e.preventDefault();
            }
        }, { passive: false });
        
        // 右クリック無効化
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }, true);
    </script>
    <script src="{{ asset('js/ar-engine.min.js') }}"></script>
    <script src="{{ asset('js/ar-tracking.min.js') }}"></script>
    <script>
        // ポケボール投擲コンポーネント
        AFRAME.registerComponent('pokeball-throwable', {
            init: function() {
                this.velocity = new THREE.Vector3();
                this.gravity = -4.5; // 重力を弱めて遠くまで飛ぶように
                this.isThrown = false;
                this.lifetime = 0;
                this.maxLifetime = 8; // 8秒後に消滅（より長く）
                this.prevPosition = new THREE.Vector3(); // 前フレームの位置（すり抜け防止用）
                
                // アクティブなボール数をカウントアップ
                window.activeBalls = (window.activeBalls || 0) + 1;
                // console.log('Active balls:', window.activeBalls);
            },
            
            remove: function() {
                // アクティブなボール数をカウントダウン
                window.activeBalls = Math.max(0, (window.activeBalls || 1) - 1);
                // console.log('Active balls:', window.activeBalls);
            },
            
            throw: function(direction, speed) {
                this.velocity.copy(direction).multiplyScalar(speed);
                // 上向きの初速を追加（放物線を描く）
                this.velocity.y += 2.5; // 上方向への追加速度
                this.isThrown = true;
                this.lifetime = 0;
                this.prevPosition.copy(this.el.object3D.position); // 初期位置を記録
                console.log('Pokeball thrown with velocity:', this.velocity);
            },
            
            tick: function(time, deltaTime) {
                if (!this.isThrown) return;
                
                // deltaTimeの安全性チェック
                const dt = (deltaTime || 16) / 1000;
                this.lifetime += dt;
                
                // 現在位置を保存（移動前）
                this.prevPosition.copy(this.el.object3D.position);

                // 重力を適用
                this.velocity.y += this.gravity * dt;
                
                // 位置を更新
                const pos = this.el.object3D.position;
                pos.x += this.velocity.x * dt;
                pos.y += this.velocity.y * dt;
                pos.z += this.velocity.z * dt;
                
                // 回転させる（投げた感じを出す）- Android向けに速度調整
                this.el.object3D.rotation.x += dt * 4; // 8 → 4に減速（ちらつき軽減）
                this.el.object3D.rotation.z += dt * 2.5; // 5 → 2.5に減速
                
                // 寿命チェック
                if (this.lifetime > this.maxLifetime || pos.y < -5) {
                    this.el.emit('pokeball-gone');
                    if (this.el.parentNode) {
                        this.el.parentNode.removeChild(this.el);
                    }
                    return;
                }

                // 当たり判定チェック（ここで行うことでsetIntervalを廃止し同期させる）
                // すり抜け防止のため毎フレームチェックする
                // if (this.el.sceneEl.frame && this.el.sceneEl.frame % 3 !== 0) return;

                const currentPos = this.el.object3D.position; // 現在位置（移動後）
                
                // レイキャストによるすり抜け防止判定
                // 前回の位置から現在の位置へのベクトル
                const direction = new THREE.Vector3().subVectors(currentPos, this.prevPosition);
                const distance = direction.length();
                
                // 移動距離が極端に短い場合はスキップ
                if (distance < 0.001) return;

                direction.normalize();
                const ray = new THREE.Ray(this.prevPosition, direction);

                // グローバルのallHitboxesを参照
                if (typeof window.allHitboxes !== 'undefined') {
                    for (let i = 0; i < window.allHitboxes.length; i++) {
                        const hitbox = window.allHitboxes[i];
                        // hitboxの要素が見えている場合のみ判定（親要素の可視性もチェック）
                        if (!hitbox.el.object3D.visible) continue;
                        if (hitbox.el.parentElement && hitbox.el.parentElement.object3D && !hitbox.el.parentElement.object3D.visible) continue;

                        let isHit = false;

                        // 1. 従来の包含チェック（ボールが内部にあるか）
                        const ballWorldPos = this.el.object3D.getWorldPosition(new THREE.Vector3());
                        if (hitbox.checkCollision(ballWorldPos)) {
                            isHit = true;
                        } 
                        // 2. レイキャストチェック（通り抜けたか）
                        else if (hitbox.checkIntersection) {
                            if (hitbox.checkIntersection(ray, distance)) {
                                isHit = true;
                                console.log('Tunneling hit detected!'); // すり抜け防止ログ
                            }
                        }

                        if (isHit) {
                            // ヒット処理
                            this.handleHit(hitbox);
                            break;
                        }
                    }
                }
            },

            handleHit: function(hitbox) {
                const stampId = hitbox.data.stampId;
                console.log('✓ Hit!', stampId);
                
                const hitModel = hitbox.el;

                // 再生可能なモデルなら anime02 を再生して、完了後にモデルを非表示にする
                let animationPromise = Promise.resolve();
                if (hitModel && hitModel.playHitAnimation) {
                    try {
                        animationPromise = hitModel.playHitAnimation(); // この関数は Promise を返すように改善済み
                    } catch (e) {
                        console.warn('playHitAnimation threw', e);
                        animationPromise = Promise.resolve();
                    }
                } else {
                    // playHitAnimation が無い場合は即時に登録（フォールバック）
                    try {
                        if (typeof collectAndMarkWithRetry === 'function') {
                            collectAndMarkWithRetry(stampId, null, 3, 2000);
                        }
                        if (hitModel && typeof hitModel.setCapturedState === 'function') {
                            hitModel.setCapturedState(true);
                        }
                    } catch (e) { console.warn('Fallback registration failed', e); }
                }

                // 衝突エフェクト（視覚エフェクトは即時）
                if (typeof showHitEffect === 'function') {
                    showHitEffect(this.el, hitbox);
                }

                // 跳ね返り（物理表現） - すぐに跳ね返す
                this.velocity.multiplyScalar(-0.6);
                this.velocity.y += 3;

                // ボールは即時で非表示にし、メモリを解放してDOMから削除する（即時処理）
                try {
                    destroyAndFreeEntity(this.el);
                } catch (e) { console.warn('Immediate destroy failed', e); }

                // アニメーション終了後：スクリーンショットは取得せず、既知のアイコン（STAMPS）を用いて
                // スタンプを登録・マークしてモデルを非表示にする（軽量な処理）
                animationPromise.then(() => {
                    try {
                        try {
                            // まずは記録（スクショ無し）を試みる
                            const saved = (typeof collectAndMarkWithRetry === 'function') ? collectAndMarkWithRetry(stampId, null, 3, 2000) : false;
                            console.log('collectAndMarkWithRetry (no-screenshot) returned', saved, 'for', stampId);
                            // markAnimalCaptured は内部で呼ばれているはずだが念のため補助的に呼ぶ
                            try { if (typeof markAnimalCaptured === 'function') markAnimalCaptured(stampId); } catch (e) { /* ignore */ }

                            // モデルを非表示・状態更新・アイコン表示
                            try { hitModel.setAttribute('visible', 'false'); } catch (e) { /* ignore */ }
                            try { if (hitModel && typeof hitModel.setCapturedState === 'function') hitModel.setCapturedState(true); } catch (e) { /* ignore */ }
                            try { if (typeof showCapturedMessage === 'function') showCapturedMessage(stampId); } catch (e) { console.warn('showCapturedMessage failed in handleHit (no-screenshot) callback', e); }
                        } catch (err) {
                            console.warn('Non-screenshot capture failed for', stampId, err);
                            try { hitModel.setAttribute('visible', 'false'); } catch (e) { /* ignore */ }
                            try { if (hitModel && typeof hitModel.setCapturedState === 'function') hitModel.setCapturedState(true); } catch (e) { /* ignore */ }
                            try { if (typeof showCapturedMessage === 'function') showCapturedMessage(stampId); } catch (e) { /* ignore */ }
                        }
                    } catch (e) { console.warn('Post-animation handling failed', e); }
                });
            },
        });

        // 当たり判定ボックスコンポーネント
        AFRAME.registerComponent('hitbox', {
            schema: {
                stampId: {type: 'string', default: ''},
                width: {type: 'number', default: 1},
                height: {type: 'number', default: 1},
                depth: {type: 'number', default: 1},
                offset: {type: 'vec3', default: {x: 0, y: 0, z: 0}},
                autoCenter: {type: 'boolean', default: true} // 高さの半分だけ自動で上にずらす（足元基準のモデル用）
            },
            
            init: function() {
                const data = this.data;
                
                // グローバルリストに登録
                if (typeof window.allHitboxes !== 'undefined' && !window.allHitboxes.includes(this)) {
                    window.allHitboxes.push(this);
                }

                // Three.jsのバウンディングボックスを作成
                this.box = new THREE.Box3();
                this.updateBox();
                
                // デバッグ用のボックス表示（開発時のみ）
                if (false) { // trueにするとボックスが見える
                    const geometry = new THREE.BoxGeometry(data.width, data.height, data.depth);
                    const material = new THREE.MeshBasicMaterial({ 
                        color: 0x00ff00, 
                        wireframe: true,
                        opacity: 0.3,
                        transparent: true
                    });
                    const mesh = new THREE.Mesh(geometry, material);
                    
                    // オフセットの適用（デバッグ表示用）
                    const debugOffset = new THREE.Vector3(data.offset.x, data.offset.y, data.offset.z);
                    if (data.autoCenter) {
                        debugOffset.y += data.height / 2;
                    }
                    mesh.position.copy(debugOffset);
                    
                    this.el.object3D.add(mesh);
                }
            },

            remove: function() {
                // グローバルリストから削除
                if (typeof window.allHitboxes !== 'undefined') {
                    const index = window.allHitboxes.indexOf(this);
                    if (index > -1) {
                        window.allHitboxes.splice(index, 1);
                    }
                }
            },
            
            updateBox: function() {
                const data = this.data;
                const pos = this.el.object3D.getWorldPosition(new THREE.Vector3());

                // ワールドスケールを反映して hitbox をスケールする
                const worldScale = new THREE.Vector3();
                this.el.object3D.getWorldScale(worldScale);

                const halfWidth = (data.width / 2) * (worldScale.x || 1);
                const halfHeight = (data.height / 2) * (worldScale.y || 1);
                const halfDepth = (data.depth / 2) * (worldScale.z || 1);

                // 中心位置の計算（オフセット適用）
                const center = pos.clone();
                
                // ローカルオフセットをワールドスケールに合わせて適用
                const scaledOffset = new THREE.Vector3(data.offset.x, data.offset.y, data.offset.z);
                if (data.autoCenter) {
                    scaledOffset.y += data.height / 2;
                }
                scaledOffset.multiply(worldScale);
                
                center.add(scaledOffset);

                this.box.min.set(
                    center.x - halfWidth,
                    center.y - halfHeight,
                    center.z - halfDepth
                );
                this.box.max.set(
                    center.x + halfWidth,
                    center.y + halfHeight,
                    center.z + halfDepth
                );
            },
            
            tick: function() {
                // 可視状態でない場合は更新しない（パフォーマンス最適化）
                // 親（マーカー）が見えていない場合もスキップ
                if (!this.el.object3D.visible || (this.el.parentElement && this.el.parentElement.object3D && !this.el.parentElement.object3D.visible)) return;
                
                // ボールが飛んでいない時は当たり判定ボックスの更新をスキップ（超重要：CPU負荷軽減）
                if (!window.activeBalls || window.activeBalls <= 0) return;

                this.updateBox();
            },
            
            checkCollision: function(point) {
                return this.box.containsPoint(point);
            },

            // レイキャストによる交差判定（すり抜け防止用）
            checkIntersection: function(ray, maxDistance) {
                if (!this.box) return false;
                const intersection = ray.intersectBox(this.box, new THREE.Vector3());
                if (intersection) {
                    const dist = ray.origin.distanceTo(intersection);
                    return dist <= maxDistance;
                }
                return false;
            }
        });
        
        // 遅延読み込みコンポーネント（メモリ対策）
        AFRAME.registerComponent('lazy-model', {
            schema: {
                src: {type: 'string'},
                timeout: {type: 'number', default: 5000} // 5秒後にメモリ解放（Android 7向けに短縮）
            },
            init: function() {
                this.timer = null;
                this.isLoaded = false;
                
                // マーカー検出イベント
                this.el.sceneEl.addEventListener('markerFound', (e) => {
                    if (e.target === this.el.parentElement) {
                        this.onMarkerFound();
                    }
                });
                
                // マーカーロストイベント
                this.el.sceneEl.addEventListener('markerLost', (e) => {
                    if (e.target === this.el.parentElement) {
                        this.onMarkerLost();
                    }
                });
            },
            onMarkerFound: function() {
                if (this.timer) {
                    clearTimeout(this.timer);
                    this.timer = null;
                }
                
                // まだモデルが設定されていなければ設定
                if (!this.isLoaded) {
                    console.log('Lazy loading model:', this.data.src);
                    this.el.setAttribute('gltf-model', this.data.src);
                    this.isLoaded = true;
                }
            },
            onMarkerLost: function() {
                // 即座には消さない（ちらつき防止）
                if (this.timer) clearTimeout(this.timer);
                
                this.timer = setTimeout(() => {
                    console.log('Unloading model to free memory:', this.data.src);
                    this.el.removeAttribute('gltf-model');
                    this.isLoaded = false;
                    this.el.emit('model-unloaded'); // カスタムイベント発火
                    
                    // メモリ解放（Three.jsのキャッシュクリア）
                    const mesh = this.el.getObject3D('mesh');
                    if (mesh) {
                        mesh.traverse((node) => {
                            if (node.isMesh) {
                                if (node.geometry) node.geometry.dispose();
                                if (node.material) {
                                    if (Array.isArray(node.material)) {
                                        node.material.forEach(m => m.dispose());
                                    } else {
                                        node.material.dispose();
                                    }
                                }
                            }
                        });
                    }
                }, this.data.timeout);
            }
        });
        
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
                let modelCaptured = false; // モデルが捕獲されたか
                
                // stampIdを最初に取得
                const hitboxAttr = el.getAttribute('hitbox');
                const stampId = hitboxAttr ? hitboxAttr.split(':')[1].split(';')[0].trim() : '';
                console.log('Initializing model for stampId:', stampId);
                
                el.addEventListener('model-loaded', () => {
                    console.log('Model loaded for:', stampId);
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
                    action02.setLoop(THREE.LoopOnce, 1); // 1回のみ再生
                    action02.clampWhenFinished = true; // 終了時に最後のフレームで停止
                    action02.stop();
                    
                    this.action01 = action01;
                    this.action02 = action02;
                    
                    console.log('Animations ready:');
                    console.log('  anime01:', clip01.name);
                    console.log('  anime02:', clip02.name);
                    
                    // anime02の終了イベントを監視
                    mixer.addEventListener('finished', (e) => {
                        if (e.action === action02) {
                            console.log('anime02 finished for', stampId);
                            // モデル非表示は捕獲済み（一貫したデータ）でのみ実施する
                            const persistedCaptured = (typeof isAnimalCaptured === 'function') ? isAnimalCaptured(stampId) : false;
                            if (modelCaptured || persistedCaptured) {
                                console.log('anime02 finished for', stampId, '- hiding model (captured)', { modelCaptured, persistedCaptured });
                                el.setAttribute('visible', 'false');
                            } else {
                                // まだ捕獲データがない場合はアニメーションを戻す（表示を維持）
                                console.log('anime02 finished for', stampId, '- not captured; resuming anime01');
                                try {
                                    if (action02) action02.stop();
                                    if (action01) {
                                        action01.reset();
                                        action01.play();
                                        currentAnimation = 1;
                                    }
                                    el.setAttribute('visible', 'true');
                                } catch (err) {
                                    console.warn('Failed to resume idle animation after anime02 for', stampId, err);
                                }
                            }
                        }
                    });

                    // マーカーが既に見えている状態でモデルがロード完了した場合は anime01 を自動再生する
                    try {
                        if (markerVisible && !modelCaptured && action01) {
                            action01.reset();
                            action01.play();
                            currentAnimation = 1;
                            console.log('Auto-started anime01 on model load for', stampId);
                        }
                    } catch (e) {
                        console.warn('Failed to auto-start anime01 on model load', e);
                    }
                });
                
                // マーカー検出時の処理
                const marker = el.parentElement;
                
                // 外部からリセットできる関数
                el.resetCaptureState = () => {
                    console.log('=== RESET CAPTURE STATE for:', stampId, '===');
                    modelCaptured = false;
                    // visible状態は変更しない（markerFoundで制御）
                    if (action01) {
                        action01.reset();
                        action01.play();
                        currentAnimation = 1;
                    }
                    console.log('Capture state reset completed for:', stampId);
                };

                // 外部から捕獲状態をセットできるようにする
                el.setCapturedState = (flag) => {
                    console.log('setCapturedState for', stampId, '->', flag);
                    modelCaptured = !!flag;
                    if (modelCaptured) {
                        // hide model as captured
                        try { el.setAttribute('visible', 'false'); } catch (e) {}
                        hideCapturedMessage();
                    } else {
                        // show model if not captured
                        try { el.setAttribute('visible', 'true'); } catch (e) {}
                    }
                };
                
                marker.addEventListener('markerFound', () => {
                    console.log('==================================================');
                    console.log('✓ Marker found for:', stampId);
                    console.log('  Checking capture state...');
                    console.log('  modelCaptured flag:', modelCaptured);
                    
                    // ローカルフラグをチェック（ボールヒット直後）
                    if (modelCaptured) {
                        console.log('  → Model captured (local flag) - hiding model and showing message');
                        el.setAttribute('visible', 'false');
                        
                        // 捕獲済みメッセージを表示
                        if (typeof showCapturedMessage === 'function') {
                            showCapturedMessage(stampId);
                        }
                        console.log('==================================================');
                        return; // ここで処理終了
                    }
                    
                    // 外部関数を使って捕獲済みかチェック（LocalStorageを確認）
                    const isCaptured = typeof isAnimalCaptured === 'function' && isAnimalCaptured(stampId);
                    console.log('  isAnimalCaptured(' + stampId + '):', isCaptured);
                    
                    if (isCaptured) {
                        console.log('  → Already captured (LocalStorage) - showing message, hiding model');
                        el.setAttribute('visible', 'false');
                        modelCaptured = true; // ローカル状態も更新
                        
                        // 捕獲済みメッセージを表示
                        if (typeof showCapturedMessage === 'function') {
                            showCapturedMessage(stampId);
                        }
                        console.log('==================================================');
                        return; // ここで処理終了
                    }
                    
                    // 捕獲されていない場合のみ、モデルを表示
                    console.log('  → Not captured - showing model with anime01');
                    el.setAttribute('visible', 'true');
                    markerVisible = true;
                    
                    // anime01を自動再生
                    if (action01) {
                        action01.reset();
                        action01.play();
                        currentAnimation = 1;
                        console.log('  anime01 started');
                    }
                    
                    // マーカー検出を記録（未捕獲の場合のみ、1日1回）
                    try {
                        if (typeof recordMarkerDetection === 'function') {
                            const name = (STAMPS[stampId] && STAMPS[stampId].name) ? STAMPS[stampId].name : stampId;
                            recordMarkerDetection(stampId, name);
                        }
                    } catch (e) {
                        console.warn('recordMarkerDetection failed', e);
                    }
                    
                    console.log('==================================================');
                });
                
                marker.addEventListener('markerLost', () => {
                    console.log('✗ Marker lost for:', stampId);
                    markerVisible = false;
                    
                    // 捕獲済みメッセージを非表示
                    hideCapturedMessage();
                    
                    // モデルを必ず非表示（マーカーがないのに表示される問題を防止）
                    el.setAttribute('visible', 'false');
                    
                    // アニメーションを停止
                    if (!modelCaptured) {
                        if (action01) action01.stop();
                        if (action02) action02.stop();
                    } else {
                        // 捕獲済みの場合もアニメーションを停止
                        if (action01) action01.stop();
                        if (action02) action02.stop();
                    }
                });
                
                // ボールヒット時にanime02を再生する関数（外部から呼び出し可能）
                el.playHitAnimation = () => {
                    console.log('=== BALL HIT! for', stampId, '===');

                    // 戻り値は、anime02が終了したときに解決されるPromiseにする
                    return new Promise((resolve) => {
                        // anime02を再生（視覚効果のみ）
                        if (action01) action01.stop();
                        if (action02) {
                            try {
                                // 1回だけ再生して終了時に停止する
                                action02.reset();
                                action02.setLoop(THREE.LoopOnce, 0);
                                action02.clampWhenFinished = true;
                                action02.play();
                                currentAnimation = 2;

                                let resolved = false;
                                const onFinished = (ev) => {
                                    try {
                                        if (ev && ev.action === action02) {
                                            if (mixer && typeof mixer.removeEventListener === 'function') {
                                                mixer.removeEventListener('finished', onFinished);
                                            }
                                            if (!resolved) {
                                                resolved = true;
                                                resolve();
                                            }
                                        }
                                    } catch (err) { /* ignore */ }
                                };

                                if (mixer && typeof mixer.addEventListener === 'function') {
                                    mixer.addEventListener('finished', onFinished);
                                }

                                // フォールバック: クリップの長さを用いたタイムアウト
                                let clipDuration = 1.0;
                                try {
                                    if (action02._clip && action02._clip.duration) clipDuration = action02._clip.duration;
                                    else if (typeof action02.getClip === 'function' && action02.getClip() && action02.getClip().duration) clipDuration = action02.getClip().duration;
                                } catch (err) { /* ignore */ }
                                setTimeout(() => {
                                    if (!resolved) {
                                        resolved = true;
                                        try { if (mixer && typeof mixer.removeEventListener === 'function') mixer.removeEventListener('finished', onFinished); } catch (e) {}
                                        resolve();
                                    }
                                }, (clipDuration * 1000) + 120);
                            } catch (e) {
                                console.warn('Failed to play action02', e);
                                resolve();
                            }
                        } else {
                            // action02が無ければ直ちに解決
                            resolve();
                        }

                        // 即時にスタンプを登録し、成功時のみローカルの捕獲フラグを設定する（非同期処理）
                        try {
                            const saved = collectAndMarkWithRetry(stampId, null, 3, 2000);
                            console.log('collectAndMarkWithRetry from playHitAnimation returned', saved, 'for', stampId);
                            const stamps = getCollectedStamps();
                            if (stamps && stamps[stampId]) {
                                modelCaptured = true;
                                try { if (saved && typeof showCapturedMessage === 'function') showCapturedMessage(stampId); } catch (e) { console.warn('showCapturedMessage failed in playHitAnimation', e); }
                            } else {
                                modelCaptured = false;
                            }
                        } catch (e) {
                            console.warn('Immediate collectStamp failed in playHitAnimation for', stampId, e);
                            modelCaptured = false;
                            // リトライを試みる（短時間間隔、最大3回）
                            let retryCount = 0;
                            const retryInterval = setInterval(() => {
                                retryCount++;
                                try {
                                    const r = collectStamp(stampId, null);
                                    if (r) {
                                        if (typeof markAnimalCaptured === 'function' && stampId) markAnimalCaptured(stampId);
                                        modelCaptured = true;
                                        console.log('Retry collectStamp succeeded for', stampId);
                                        clearInterval(retryInterval);
                                    }
                                } catch (er) {
                                    console.warn('Retry collectStamp error', er);
                                }
                                if (retryCount >= 3) {
                                    console.warn('collectStamp retry failed for', stampId, 'after', retryCount, 'attempts');
                                    clearInterval(retryInterval);
                                }
                            }, 2000);
                        }

                        console.log('Model captured! Will be hidden on next marker detection if capture persisted');
                    });
                };
                
                // タップでのアニメーション切替機能は廃止（コメントアウト）
                /*
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
                */
            },
            tick: function(time, deltaTime) {
                // 可視状態でない場合は更新しない（パフォーマンス最適化）
                // 親（マーカー）が見えていない場合もスキップ
                if (!this.el.object3D.visible || (this.el.parentElement && this.el.parentElement.object3D && !this.el.parentElement.object3D.visible)) return;
                
                // mixerが存在する場合のみ更新
                if (this.mixer) {
                    // deltaTimeを秒に変換（ミリ秒 → 秒）
                    // deltaTimeが異常に大きい場合は制限（フレームドロップ対策）
                    const dt = Math.min(deltaTime / 1000, 0.1);
                    this.mixer.update(dt);
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
            touch-action: pan-x pan-y; /* ピンチズームを無効化、パンは許可 */
            -webkit-user-select: none;
            user-select: none;
        }
        a-scene {
            touch-action: none; /* ARシーン内では全てのデフォルトタッチ動作を無効化 */
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
        
        /* カメラボタンのスタイル */
        #camera-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 70px;
            height: 70px;
            background-color: rgba(255, 255, 255, 0.9);
            border: 3px solid #333;
            border-radius: 50%;
            cursor: pointer;
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 35px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            transition: transform 0.1s, background-color 0.2s;
        }
        
        #camera-button:active {
            transform: scale(0.9);
            background-color: rgba(200, 200, 200, 0.9);
        }
        
        #camera-button:hover {
            background-color: rgba(240, 240, 240, 0.9);
        }
        
        /* カメラ切り替えボタン */
        #switch-camera-button {
            position: fixed;
            top: 30px;
            left: 30px;
            width: 60px;
            height: 60px;
            background-color: rgba(255, 255, 255, 0.9);
            border: 3px solid #333;
            border-radius: 50%;
            cursor: pointer;
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 28px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            transition: transform 0.1s, background-color 0.2s;
        }
        
        #switch-camera-button:active {
            transform: scale(0.9) rotate(180deg);
            background-color: rgba(200, 200, 200, 0.9);
        }
        
        #switch-camera-button:hover {
            background-color: rgba(240, 240, 240, 0.9);
        }
        
        /* 動画撮影ボタン */
        #video-button {
            position: fixed;
            bottom: 110px;
            right: 30px;
            width: 60px;
            height: 60px;
            background-color: rgba(255, 255, 255, 0.9);
            border: 3px solid #333;
            border-radius: 50%;
            cursor: pointer;
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 28px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            transition: transform 0.1s, background-color 0.2s;
        }
        
        #video-button:active {
            transform: scale(0.9);
        }
        
        #video-button:hover {
            background-color: rgba(240, 240, 240, 0.9);
        }

        /* 投げるボタン（下中央） - 非表示にして新しい操作方法へ移行 */
        #throw-button {
            display: none !important;
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 90px;
            height: 90px;
            background-color: rgba(255, 255, 255, 0.95);
            border: 3px solid #333;
            border-radius: 50%;
            cursor: pointer;
            /* Ensure the stamp-book modal appears above the throw button (throw button lowered) */
            /* Lower z-index so modal (10001) will sit above this button when open */
            z-index: 10000;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 36px;
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.35);
            transition: transform 0.08s, background-color 0.12s;
            touch-action: none; /* prevent default pinch-to-zoom on some browsers when touching the button */
            /* Prevent blue selection highlight / long-press selection on mobile */
            -webkit-user-select: none; /* Safari */
            -moz-user-select: none; /* Firefox */
            -ms-user-select: none; /* IE10+ */
            user-select: none; /* Standard */
            -webkit-touch-callout: none; /* iOS Safari long-press menu */
            -webkit-tap-highlight-color: transparent; /* remove tap highlight on some Android browsers */
            -webkit-user-drag: none; /* prevent dragging */
        }

        /* Guide modal: header and language switch */
        .guide-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .lang-switch { display:flex; gap:6px; }
        .lang-btn {
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(0,0,0,0.08);
            padding: 6px 8px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
        }
        .lang-btn.active {
            background: linear-gradient(135deg,#7fc7ff 0%, #4aa0ff 100%);
            color: white;
            border-color: rgba(0,0,0,0.14);
            box-shadow: 0 4px 10px rgba(0,0,0,0.12);
        }

        /* Camera help modal (shown when camera permissions appear disabled) */
        #camera-help-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            z-index: 10010;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        #camera-help-modal .camera-help-content {
            background: white;
            border-radius: 12px;
            max-width: 640px;
            width: 100%;
            padding: 18px 22px;
            box-shadow: 0 12px 36px rgba(0,0,0,0.3);
        }

        #camera-help-modal .camera-help-content h3 { margin-top:0; }
        #camera-help-modal .camera-help-actions { text-align:right; margin-top:12px; }
        #camera-help-modal .camera-help-actions button { margin-left:8px; }

        #throw-button:active { transform: translateX(-50%) scale(0.92); }
        #throw-button:hover { background-color: rgba(245,245,245,0.98); }

        /* 長押しで強さを示すクラス */
        #throw-button.power-low { box-shadow: 0 8px 18px rgba(0,0,0,0.35), 0 0 0 6px rgba(60,150,255,0.12) inset; }
        #throw-button.power-mid { box-shadow: 0 10px 22px rgba(0,0,0,0.38), 0 0 0 8px rgba(255,180,40,0.14) inset; transform: translateX(-50%) scale(1.02); }
        #throw-button.power-high { box-shadow: 0 12px 26px rgba(0,0,0,0.42), 0 0 0 10px rgba(255,80,80,0.16) inset; transform: translateX(-50%) scale(1.06); }
        
        #video-button.recording {
            background-color: rgba(255, 100, 100, 0.9);
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        /* 撮影フラッシュエフェクト */
        #flash {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: white;
            opacity: 0;
            pointer-events: none;
            z-index: 9998;
            transition: opacity 0.2s;
        }
        
        #flash.active {
            opacity: 0.8;
        }
        
        /* スタンプ帳ボタン */
        #stamp-book-button {
            position: fixed;
            top: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background-color: rgba(255, 255, 255, 0.9);
            border: 3px solid #333;
            border-radius: 50%;
            cursor: pointer;
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 28px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            transition: transform 0.1s, background-color 0.2s;
        }
        
        #stamp-book-button:active {
            transform: scale(0.9);
            background-color: rgba(200, 200, 200, 0.9);
        }
        
        #stamp-book-button:hover {
            background-color: rgba(240, 240, 240, 0.9);
        }
        
        #stamp-book-button .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ff4444;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 12px;
            font-weight: bold;
            display: flex;
            justify-content: center;
            align-items: center;
            border: 2px solid white;
        }

        /* 操作説明ボタン */
        #guide-button {
            position: fixed;
            top: 30px;
            right: 100px; /* stamp-book-button の少し左 */
            width: 56px;
            height: 56px;
            background-color: rgba(255, 255, 255, 0.92);
            border: 2px solid #333;
            border-radius: 50%;
            cursor: pointer;
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.25);
            transition: transform 0.1s, background-color 0.2s;
        }

        #guide-button:active { transform: scale(0.95); }
        #guide-button:hover { background-color: rgba(245,245,245,0.95); }
        
        /* 捕まえるボタン（廃止） */
        #catch-button {
            display: none;
        }
        
        /* 回転ボタン */
        .rotation-buttons {
            position: fixed;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            flex-direction: column;
            gap: 15px;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }
        
        .rotation-buttons.visible {
            opacity: 1;
            visibility: visible;
        }
        
        .rotation-button {
            width: 60px;
            height: 60px;
            background-color: rgba(255, 255, 255, 0.9);
            border: 3px solid #333;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 32px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            transition: transform 0.1s, background-color 0.2s;
            user-select: none;
            -webkit-user-select: none;
            -webkit-tap-highlight-color: transparent;
        }
        
        .rotation-button:active {
            transform: scale(0.9);
            background-color: rgba(200, 200, 200, 0.9);
        }
        
        .rotation-button:hover {
            background-color: rgba(240, 240, 240, 0.9);
        }
        
        /* 捕獲済みメッセージ */
        .captured-message {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.85);
            color: white;
            padding: 30px 40px;
            border-radius: 15px;
            text-align: center;
            z-index: 2000;
            display: none;
            pointer-events: none;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
            opacity: 0;
            transition: opacity 0.3s;
            -webkit-transform: translate(-50%, -50%);
            -webkit-transition: opacity 0.3s;
            max-width: 80vw;
        }
        
        .captured-message.show {
            display: block;
            pointer-events: auto;
            opacity: 1;
            touch-action: auto;
            -webkit-touch-callout: default;
            -webkit-user-select: auto;
            user-select: auto;
        }
        
        .captured-message h2 {
            font-size: 24px;
            margin: 0 0 20px 0;
            color: #ffeb3b;
        }
        
        .captured-message .animal-name {
            font-size: 28px;
            margin: 10px 0;
            font-weight: bold;
            color: #4CAF50;
        }
        
        .captured-message p {
            margin: 15px 0 0 0;
            font-size: 14px;
            color: #ccc;
            line-height: 1.6;
        }
        
        /* スタンプ帳モーダル */
        #stamp-book-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            display: none;
            z-index: 10001;
            overflow-y: scroll !important;
            -webkit-overflow-scrolling: touch !important;
        }

        /* 操作説明モーダル */
        #guide-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.85);
            display: none;
            z-index: 10002;
            -webkit-overflow-scrolling: touch;
            overflow-y: auto !important;
        }

        #guide-content {
            background: #fff;
            border-radius: 12px;
            /* より広く、かつモバイルでは横幅に合わせる */
            width: 92vw;
            max-width: 1000px;
            margin: 24px auto;
            padding: 16px 18px;
            box-sizing: border-box;
        }

        #guide-content h2 {
            margin: 6px 0 10px 0;
            text-align: center;
            font-size: 18px;
            color: #222;
        }

        /* tighten spacing by ~30% */
        .guide-steps { display: flex; flex-direction: column; gap: 8px; }
        .guide-steps .step { display:flex; gap: 8px; align-items:flex-start; }
        /* 大きなメイン画像 (1枚だけ表示) */
        .guide-steps .step.main { justify-content: center; }
        /* サムネイル画像は小さめ（第3ステップなど） */
        .guide-steps .step img { width: 120px; height: 84px; object-fit:cover; border-radius:8px; border:1px solid #eee; }

        /* 大きなメイン画像 (1枚だけ表示) — より具体的なセレクタで優先適用 */
        .guide-steps .step.main img.howto-main {
            width: 80%;
            max-width: 480px;
            height: auto;
            max-height: 360px;
            object-fit: cover;
            border-radius: 12px;
            border:1px solid #eee;
            display:block;
            margin: 0 auto 10px auto;
        }
        /* キャプションスタイル */
        .howto-caption { text-align:center; font-size:13px; color:#666; margin-bottom:12px; }
        /* サムネイル画像は小さめ（第3ステップなど） */
        .guide-steps .step img { width: 120px; height: 84px; object-fit:cover; border-radius:8px; border:1px solid #eee; }
        .guide-steps .step .step-text { font-size: 14px; color:#333; line-height: 1.12; }
        .guide-steps .step .step-text strong { display:block; margin-bottom:6px; font-size:15px; }

        .guide-close-row { text-align: right; margin-top: 12px; }
        #close-guide { padding: 8px 12px; border-radius: 8px; background:#333; color:#fff; border:none; cursor:pointer; margin-bottom: 5px; margin-right: 5px; }
        #close-guide:active { transform: scale(0.98); }
        
        /* 右上の×ボタン */
        .close-guide-x {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 32px;
            height: 32px;
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            border: none;
            border-radius: 50%;
            font-size: 24px;
            line-height: 28px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            transition: background 0.2s ease;
        }
        .close-guide-x:hover {
            background: rgba(0, 0, 0, 0.8);
        }
        .close-guide-x:active {
            transform: scale(0.95);
        }
        
        #guide-content {
            position: relative;
        }
        /* guide notes (privacy / cookies / photo) */
        .guide-notes { margin-top: 10px; border-top: 1px dashed #eee; padding-top: 10px; color: #333; font-size: 13px; }
        .guide-note { display:flex; gap: 12px; align-items:flex-start; margin-bottom: 14px; padding: 12px 14px; background: linear-gradient(135deg, #fffdf0 0%, #fff3d6 100%); border: 1px solid #ffd66b; border-radius: 8px; box-shadow: 0 6px 18px rgba(0,0,0,0.06); color: #2b2b2b; font-weight: 500; }
        .guide-note strong { display:block; margin-bottom:6px; font-weight:700; }
        .guide-note p { margin:0; line-height:1.25; }

        /* Hint PDF styling */
        .hint-pdf { background: #fff; border: 1px solid #eee; border-radius: 8px; padding: 12px; box-shadow: 0 6px 14px rgba(0,0,0,0.06); margin-top: 12px; }
        .hint-pdf .step-text { margin-bottom: 8px; }
        .pdf-embed object { width: 100%; height: 280px; border: 1px solid #ddd; border-radius: 6px; }
        .pdf-actions { margin-top: 8px; display:flex; gap:8px; }
        .pdf-actions .btn { padding:8px 12px; border-radius:6px; text-decoration:none; display:inline-block; }
        .pdf-actions .btn-primary { background:#0078D4; color:white; }
        .pdf-actions .btn-open { background: #ff8c00; color: #fff; }
        .pdf-actions .btn-light { background:#f4f4f4; color:#333; }
        /* removed note-icon: notes now use full-width text */
        .note-text { line-height: 1.12; }
        
        #stamp-book-content {
            background-color: white;
            border-radius: 15px;
            padding: 18px 12px;
            max-width: 500px;
            margin: 20px auto;
            box-sizing: border-box;
        }
        
        #stamp-book-content h2 {
            text-align: center;
            color: #333;
            margin: 0 0 6px 0;
            font-size: 20px;
        }
        
        #stamp-book-content .progress {
            text-align: center;
            color: #666;
            margin-bottom: 12px;
            font-size: 14px;
        }
        
        #stamp-book-content .complete-message {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 12px;
            font-weight: bold;
            font-size: 13px;
            animation: celebrate 1s ease-in-out;
        }
        
        @keyframes celebrate {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        #stamp-book-content .stamps-grid {
            display: grid;
            /* 5列 x 4行 = 20 スロット */
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            margin-bottom: 15px;
        }
        
        #stamp-book-content .stamp-item {
            background-color: #f5f5f5;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 8px 4px;
            text-align: center;
            transition: all 0.3s;
        }
        
        #stamp-book-content .stamp-item.collected {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
            border-color: #4CAF50;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        #stamp-book-content .stamp-item .stamp-icon {
            font-size: 28px;
            margin-bottom: 3px;
            width: 100%;
            height: 60px;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            border-radius: 4px;
            background-color: white;
        }
        
        #stamp-book-content .stamp-item .stamp-icon img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        #stamp-book-content .stamp-item .stamp-name {
            font-size: 11px;
            font-weight: bold;
            color: #333;
        }
        
        #stamp-book-content .stamp-item .stamp-date {
            font-size: 8px;
            color: #666;
            margin-top: 2px;
        }
        
        #stamp-book-content .stamp-item.not-collected {
            opacity: 0.4;
        }

        /* シークレット表示用: 未入手時は影（シルエット）だけ表示 */
        #stamp-book-content .stamp-item.secret .stamp-icon {
            /* 絵文字を透明にして text-shadow で影だけ見せる（シルエット風）*/
            color: transparent;
            text-shadow: 0 6px 8px rgba(0,0,0,0.55);
            background: linear-gradient(180deg, rgba(0,0,0,0.03), rgba(0,0,0,0.0));
        }

        #stamp-book-content .stamp-item.secret .stamp-name {
            color: #999;
            font-size: 11px;
            letter-spacing: 1px;
            opacity: 0.8;
        }

        #stamp-book-content .stamp-item.not-collected .stamp-icon {
            filter: grayscale(100%);
        }
        
        #close-stamp-book {
            flex: 1;
            padding: 12px;
            background-color: #999;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            margin: 0;
            box-sizing: border-box;
        }
        
        #close-stamp-book:hover {
            background-color: #777;
        }
        
        #clear-stamps {
            flex: 1;
            padding: 12px;
            background-color: #f44336;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            margin: 0;
            box-sizing: border-box;
        }
        
        #clear-stamps:hover {
            background-color: #d32f2f;
        }
        
        .button-row {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        #exchange-prize-button {
            flex: 1;
            padding: 12px;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s, opacity 0.3s;
            background-color: #4CAF50;
        }
        
        #exchange-prize-button:disabled {
            cursor: not-allowed;
            opacity: 0.7;
            background-color: #999 !important;
        }
        
        #exchange-prize-button:not(:disabled):hover {
            background-color: #45a049;
        }

        #hint-button {
            flex: 1;
            padding: 12px;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s, opacity 0.3s;
            background-color: #FF9800;
        }

        #hint-button:hover {
            background-color: #F57C00;
        }
        
        /* 確認ダイアログ */
        #confirm-dialog {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            z-index: 10003;
            display: none;
            min-width: 280px;
            text-align: center;
        }
        
        #confirm-dialog .confirm-message {
            font-size: 16px;
            color: #333;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        #confirm-dialog .confirm-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        #confirm-dialog .confirm-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
        }
        
        #confirm-dialog .confirm-yes {
            background-color: #f44336;
            color: white;
        }
        
        #confirm-dialog .confirm-no {
            background-color: #999;
            color: white;
        }
        
        #confirm-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 10002;
            display: none;
        }
        
        /* パーティクルエフェクト */
        .particle {
            position: fixed;
            pointer-events: none;
            z-index: 9998;
            font-size: 30px;
            animation: particle-float 2s ease-out forwards;
        }
        
        @keyframes particle-float {
            0% {
                opacity: 1;
                transform: translateY(0) rotate(0deg);
            }
            100% {
                opacity: 0;
                transform: translateY(-200px) rotate(360deg);
            }
        }
        
        .particle.large {
            font-size: 50px;
            animation: particle-float-large 3s ease-out forwards;
        }
        
        @keyframes particle-float-large {
            0% {
                opacity: 1;
                transform: translateY(0) rotate(0deg) scale(0.5);
            }
            50% {
                transform: translateY(-100px) rotate(180deg) scale(1.2);
            }
            100% {
                opacity: 0;
                transform: translateY(-300px) rotate(360deg) scale(0.5);
            }
        }
        
        /* ヒットパーティクルアニメーション */
        @keyframes hitParticle {
            0% {
                opacity: 1;
                transform: translate(0, 0) scale(1);
            }
            100% {
                opacity: 0;
                transform: translate(
                    calc(var(--random-x, 0) * 100px),
                    calc(var(--random-y, -150) * 1px)
                ) scale(0.3) rotate(360deg);
            }
        }
        
        .hit-particle {
            --random-x: calc(Math.random() * 2 - 1);
            --random-y: calc(Math.random() * -150);
        }
        
        /* 撮影した画像のプレビュー */
        #photo-preview {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-width: 90%;
            max-height: 90%;
            background-color: rgba(0, 0, 0, 0.9);
            padding: 10px;
            border-radius: 10px;
            display: none;
            z-index: 10000;
            flex-direction: column;
            align-items: center;
        }
        
        #photo-preview img,
        #photo-preview video {
            max-width: 100%;
            max-height: 70vh;
            border-radius: 5px;
        }
        
        #photo-preview .buttons {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        
        #photo-preview button {
            padding: 12px 24px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            color: white;
            font-weight: bold;
        }
        
        #download-button {
            background-color: #4CAF50;
        }
        
        #close-button {
            background-color: #f44336;
        }
    </style>
</head>
<body>
    <div class="arjs-loader">
        <div>カメラを起動中...</div>
    </div>
    
    <!-- 確認ダイアログ -->
    <div id="confirm-overlay"></div>
    <div id="confirm-dialog">
        <div class="confirm-message">本当に動物たちを逃がしますか？</div>
        <div class="confirm-buttons">
            <button class="confirm-yes" type="button">逃がす</button>
            <button class="confirm-no" type="button">キャンセル</button>
        </div>
    </div>
    
    <!-- フラッシュエフェクト -->
    <div id="flash"></div>

    <!-- カメラ使用に関するヘルプモーダル (表示はJSで制御) -->
    <div id="camera-help-modal" role="dialog" aria-hidden="true">
        <div class="camera-help-content">
            <h3 id="camera-help-title">Camera access blocked?</h3>
            <div id="camera-help-body">
                <!-- content is replaced by JS depending on language -->
            </div>
            <div class="camera-help-actions">
                <button id="camera-help-close">Close</button>
            </div>
        </div>
    </div>
    
    <!-- スタンプ帳ボタン -->
    <button id="stamp-book-button" type="button" title="コレクションを見る">
        <div class="icon">🎁</div>
        <span class="badge">0</span>
    </button>
    
    <!-- 操作説明ボタン (ガイド) -->
    <button id="guide-button" type="button" title="操作説明" aria-label="操作説明">
        <div class="icon">❓</div>
    </button>
    
    <!-- 捕まえるボタン -->
    <button id="catch-button" type="button" title="タップで捕獲モード">
        <div class="icon">⚾</div>
        <div class="text">捕まえる</div>
    </button>
    
    <!-- 回転ボタン (一時的に非表示: コメントアウトしました。復帰するには下のコメントを外してください) -->
    <!--
    <div class="rotation-buttons" id="rotation-buttons">
        <button class="rotation-button" id="rotate-up" type="button" title="上に回転">⬆️</button>
        <button class="rotation-button" id="rotate-down" type="button" title="下に回転">⬇️</button>
    </div>
    -->
    
    <!-- スタンプ帳モーダル -->
    <div id="stamp-book-modal">
        <div id="stamp-book-content">
            <h2>🎯 コレクション 🎯</h2>
            <div class="progress">
                <span id="collected-count">0</span> / <span id="total-slots">20</span> 種類
            </div>
            <div id="complete-message-container"></div>
            <div class="stamps-grid" id="stamps-grid">
                <!-- スタンプアイテムはJavaScriptで動的生成 -->
            </div>
            <div class="button-row">
                <button id="close-stamp-book" type="button">閉じる</button>
                <!-- <button id="hint-button" type="button">ヒントを見る</button> -->
                <button id="exchange-prize-button" type="button">景品と交換する</button>
                <button id="clear-stamps" type="button">動物たちを逃がす</button>
            </div>
        </div>
    </div>

    <!-- 操作説明モーダル -->
    <div id="guide-modal" aria-hidden="true">
        <div id="guide-content">
            <button id="close-guide-top" class="close-guide-x" type="button" aria-label="Close">×</button>
            <div id="stamp-rally-note" class="guide-note" aria-hidden="false" style="margin-bottom:10px;">
                <!-- Localized notice about the stamp rally will be injected here by JS -->
            </div>
            <div class="guide-header">
                <h2 id="guide-title">How to play</h2>
                <div class="lang-switch" id="guide-lang-switch" role="tablist" aria-label="言語切替">
                    <button id="lang-jp" class="lang-btn" aria-pressed="false">日本語</button>
                    <button id="lang-en" class="lang-btn active" aria-pressed="true">English</button>
                </div>
            </div>
            <div class="guide-steps">
                <!-- 大きな操作イメージを1枚だけ表示 -->
                <div class="step main">
                    <img class="howto-main" src="{{ asset('img/howToOperate.png') }}" alt="操作ガイド" />
                    <!-- <div class="howto-caption">Point your camera at a marker, then tap the screen to throw a ball. (This picture shows how to use it.)</div> -->
                </div>

                <div class="step" id="guide-step-find">
                    <!-- content set dynamically for EN/JP -->
                </div>
                <div class="step" id="guide-step-zoom">
                    <!-- Zoom content set dynamically for EN/JP -->
                </div>

                <div class="step" id="guide-step-photo">
                    <!-- Photo & video content set dynamically for EN/JP -->
                </div>

                <div class="step" id="guide-step-throw">
                    <!-- Throw button content set dynamically for EN/JP -->
                </div>

                <div class="step" id="guide-step-prize">
                    <!-- Prize exchange content set dynamically for EN/JP -->
                </div>

                <div class="step" id="guide-step-others">
                    <!-- privacy / cookies / made by students / learning content set dynamically for EN/JP -->
                </div>

                <div class="step" id="guide-step-hints">
                    <!-- Marker hint PDF (localized) will be injected here -->
                </div>

                    </div>
                </div>

                            <div class="guide-close-row">
                <button id="close-guide" type="button">close</button>
            </div>

            </div>
        </div>
    </div>
    
    <!-- 捕獲済みメッセージ -->
    <div id="captured-message" class="captured-message">
        <h2>🎉 捕まえました！ 🎉</h2>
        <div class="animal-name" id="captured-animal-name"></div>
        <p style="margin-top: 15px; font-size: 14px; color: #ccc;">
            コレクションの「動物たちを逃がす」ボタンで<br>全てリセットできます
        </p>
    </div>
    
    <!-- カメラ切り替えボタン -->
    <button id="switch-camera-button" type="button" title="カメラを切り替え">🔄</button>
    
    <!-- 動画撮影ボタン -->
    <button id="video-button" type="button" title="動画を撮る">📹</button>
    
    <!-- カメラボタン -->
    <button id="camera-button" type="button" title="写真を撮る">📷</button>

    <!-- 投げるボタン（画面下中央、スマホ向け） -->
    <button id="throw-button" type="button" title="投げる" aria-label="投げるボタン">
        <!-- ビーチボール SVG (赤/白) -->
        <svg width="56" height="56" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <defs>
                <radialGradient id="g1" cx="30%" cy="30%" r="70%">
                    <stop offset="0%" stop-color="#ffffff"/>
                    <stop offset="100%" stop-color="#fffbf2"/>
                </radialGradient>
            </defs>
            <circle cx="32" cy="32" r="30" fill="#ffffff" stroke="#d33" stroke-width="2"/>
            <path d="M32 2 A30 30 0 0 1 56.8 18.9 L42 34 L32 2 Z" fill="#ff4d4d" opacity="0.95"/>
            <path d="M8.8 18.9 A30 30 0 0 1 32 2 L22 34 L8.8 18.9 Z" fill="#ffdede" opacity="0.95"/>
            <path d="M56 34 A30 30 0 0 1 32 62 L42 34 L56 34 Z" fill="#fff3f3" opacity="0.9"/>
            <circle cx="32" cy="32" r="8" fill="#fff" stroke="#f4c0c0" stroke-width="1"/>
        </svg>
    </button>
    
    <!-- 撮影した写真のプレビュー -->
    <div id="photo-preview">
        <img id="preview-image" src="" alt="撮影した写真">
        <div class="buttons">
            <button id="download-button">ダウンロード</button>
            <button id="close-button">閉じる</button>
        </div>
    </div>
    
    <a-scene
        embedded
        arjs="sourceType: webcam; debugUIEnabled: false; sourceWidth: 640; sourceHeight: 480; detectionMode: mono; maxDetectionRate: 15;"
        vr-mode-ui="enabled: false"
        renderer="logarithmicDepthBuffer: false; antialias: false; alpha: true; precision: mediump;">
        
        <a-entity camera="near: 0.2; far: 800;">
            <!-- 手持ちのポケボール (HUD) -->
            <a-entity 
                id="holding-pokeball"
                gltf-model="{{ asset('cg/poke_ball_05.glb') }}"
                position="0 -0.24 -0.5"
                scale="0.075 0.075 0.075"
                rotation="0 0 0"
                visible="true">
            </a-entity>
        </a-entity>
        
        <!-- iPhone対応：シーン全体で1つのライトのみ使用（パフォーマンス向上） -->
        <a-light type="ambient" intensity="1.5"></a-light>
        <a-light type="directional" intensity="0.8" position="1 1 1"></a-light>
        
        <a-marker type="pattern" url="{{ asset('cg/pattern-sheep.patt') }}" id="pattern-sheep-marker">
            <a-entity
                id="sheep-model"
                lazy-model="src: {{ asset('cg/3d_pro_sheep_matsubara.glb') }}"
                position="0 0 0.5"
                scale="1.1 1.1 1.1"
                rotation="-90 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: sheep; width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>
        
        <!-- Namakemono (なまけもの) - 新しいマーカー -->
        <a-marker type="pattern" url="{{ asset('cg/pattern-namakemono.patt') }}" id="pattern-namakemono-marker">
            <a-entity
                id="namakemono-model"
                lazy-model="src: {{ asset('cg/3d_pro_namakemono_oda.glb') }}"
                position="0 0 0.5"
                scale="1.1 1.1 1.1"
                rotation="-90 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: namakemono; width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>
        
        <!-- Hamstar (ハムスター) - 新しいマーカー -->
        <a-marker type="pattern" url="{{ asset('cg/pattern-hamstar.patt') }}" id="pattern-hamstar-marker">
            <a-entity
                id="hamstar-model"
                lazy-model="src: {{ asset('cg/3d_pro_humstar_harada.glb') }}"
                position="0 0 0.5"
                scale="1.1 1.1 1.1"
                rotation="-90 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: hamstar; width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>
        
        <!-- Burger (バーガー) - 新しいマーカー -->
        <a-marker type="pattern" url="{{ asset('cg/pattern-burger.patt') }}" id="pattern-burger-marker">
            <a-entity
                id="burger-model"
                lazy-model="src: {{ asset('cg/3d_pro_burger_fujii.glb') }}"
                position="0 0 0.5"
                scale="1.1 1.1 1.1"
                rotation="-90 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: burger; width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>
        
        <!-- Duck (アヒル) - 新しいマーカー -->
        <a-marker type="pattern" url="{{ asset('cg/pattern-duck.patt') }}" id="pattern-duck-marker">
            <a-entity
                id="duck-model"
                lazy-model="src: {{ asset('cg/3d_pro_duck_oonomi.glb') }}"
                position="0 0 0.5"
                scale="1.1 1.1 1.1"
                rotation="-90 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: duck; width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>
        
        <!-- Cat (ねこ) - 新しいマーカー -->
        <a-marker type="pattern" url="{{ asset('cg/pattern-cat.patt') }}" id="pattern-cat-marker">
            <a-entity
                id="cat-model"
                lazy-model="src: {{ asset('cg/3d_pro_cat_fukuda.glb') }}"
                position="0 0 0.5"
                scale="1.1 1.1 1.1"
                rotation="-90 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: cat; width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>
        
        <!-- Bear (くま) - 新しいマーカー -->
        <a-marker type="pattern" url="{{ asset('cg/pattern-bear.patt') }}" id="pattern-bear-marker">
            <a-entity
                id="bear-model"
                lazy-model="src: {{ asset('cg/3d_pro_bear_tagashira.glb') }}"
                position="0 0 0.5"
                scale="1.1 1.1 1.1"
                rotation="-90 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: bear; width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>
        
        <!-- Harinezumi (はりねずみ) - 新しいマーカー -->
        <a-marker type="pattern" url="{{ asset('cg/pattern-harinezumi.patt') }}" id="pattern-harinezumi-marker">
            <a-entity
                id="harinezumi-model"
                lazy-model="src: {{ asset('cg/3d_pro_harinezumi_harada.glb') }}"
                position="0 0 0.5"
                scale="1.1 1.1 1.1"
                rotation="-90 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: harinezumi; width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>
        
        <!-- WhiteTiger (白いトラ) - 新しいマーカー -->
        <a-marker type="pattern" url="{{ asset('cg/pattern-Maker_202603_panda.patt') }}" id="pattern-whiteTiger-marker">
            <a-entity
                id="whiteTiger-model"
                lazy-model="src: {{ asset('cg/3d_202603_panda.glb') }}"
                position="0 0 0.5"
                scale="1.1 1.1 1.1"
                rotation="0 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: panda; width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>
        
        <!-- Santa (サンタクロース) - 新しいマーカー -->
        <a-marker type="pattern" url="{{ asset('cg/pattern-Maker_202603_kirin.patt') }}" id="pattern-santa-marker">
            <a-entity
                id="santa-model"
                lazy-model="src: {{ asset('cg/3d_202603_kirin.glb') }}"
                position="0 0 0.5"
                scale="1.1 1.1 1.1"
                rotation="-90 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: kirin; width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>
        
        <a-marker type="pattern" url="{{ asset('cg/pattern-fox.patt') }}" id="pattern-fox-marker">
            <a-entity
                id="fox-model"
                lazy-model="src: {{ asset('cg/3d_pro_fox_isobe.glb') }}"
                position="0 0 0.5"
                scale="1.1 1.1 1.1"
                rotation="-90 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: fox; width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>
        
        <a-marker type="pattern" url="{{ asset('cg/pattern-pengin.patt') }}" id="pattern-pengin-marker">
            <a-entity
                id="pengin-model"
                lazy-model="src: {{ asset('cg/3d_pro_pengin_morita.glb') }}"
                position="0 0 0.5"
                scale="1.1 1.1 1.1"
                rotation="-90 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: pengin; width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>
        
        <a-marker type="pattern" url="{{ asset('cg/pattern-tonakai.patt') }}" id="pattern-tonakai-marker">
            <a-entity
                id="tonakai-model"
                lazy-model="src: {{ asset('cg/3d_pro_tonakai_matsumura2.glb') }}"
                position="0 0 0.5"
                scale="1.1 1.1 1.1"
                rotation="-90 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: tonakai; width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>
        
        <a-marker type="pattern" url="{{ asset('cg/pattern-pig.patt') }}" id="pattern-pig-marker">
            <a-entity
                id="pig-model"
                lazy-model="src: {{ asset('cg/3d_pro_pig_matsubara.glb') }}"
                position="0 0 0.5"
                scale="1.1 1.1 1.1"
                rotation="-90 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: pig; width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>

        <!-- Tora (とら) - 新しいマーカー -->
        <a-marker type="pattern" url="{{ asset('cg/pattern-tora.patt') }}" id="pattern-tora-marker">
            <a-entity
                id="tora-model"
                lazy-model="src: {{ asset('cg/3d_pro_tora_iwamoto.glb') }}"
                position="0 0 0.5"
                scale="1.1 1.1 1.1"
                rotation="-90 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: tora; width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>

        <!-- Gollira (ごりら) - 新しいマーカー -->
        <a-marker type="pattern" url="{{ asset('cg/pattern-gollira.patt') }}" id="pattern-gollira-marker">
            <a-entity
                id="gollira-model"
                lazy-model="src: {{ asset('cg/3d_pro_gollira_ishimaru.glb') }}"
                position="0 0 0.5"
                scale="1.1 1.1 1.1"
                rotation="-90 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: gollira; width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>

        <!-- White Duck (白アヒル) - 新しいマーカー -->
        <a-marker type="pattern" url="{{ asset('cg/pattern-whiteDuck.patt') }}" id="pattern-whiteDuck-marker">
            <a-entity
                id="whiteDuck-model"
                lazy-model="src: {{ asset('cg/3d_pro_whiteDuck_tagashira.glb') }}"
                position="0 0 0.5"
                scale="1.1 1.1 1.1"
                rotation="-90 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: whiteDuck; width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>
        
        <!-- Araiguma (あらいぐま) - 新しいマーカー -->
        <a-marker type="pattern" url="{{ asset('cg/pattern-araiguma.patt') }}" id="pattern-araiguma-marker">
            <a-entity
                id="araiguma-model"
                lazy-model="src: {{ asset('cg/3d_pro_araiguma_oonomi.glb') }}"
                position="0 0 0.5"
                scale="1.1 1.1 1.1"
                rotation="-90 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: araiguma; width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>
        
        <!-- Wolf (おおかみ) - 新しいマーカー -->
        <a-marker type="pattern" url="{{ asset('cg/pattern-wolf.patt') }}" id="pattern-wolf-marker">
            <a-entity
                id="wolf-model"
                lazy-model="src: {{ asset('cg/3d_pro_wolf_morita.glb') }}"
                position="0 0 0.5"
                scale="1.1 1.1 1.1"
                rotation="-90 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: wolf; width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>
        
        <!-- T-Rex (ティラノサウルス) - 新しいマーカー -->
        <a-marker type="pattern" url="{{ asset('cg/pattern-t-rex.patt') }}" id="pattern-t-rex-marker">
            <a-entity
                id="t-rex-model"
                lazy-model="src: {{ asset('cg/3d_pro_t-rex_ootani.glb') }}"
                position="0 0 0.5"
                scale="1.1 1.1 1.1"
                rotation="-90 0 0"
                click-animation="clip: anime01"
                hitbox="stampId: t-rex; width: 1.6; height: 3.2; depth: 1.6">
            </a-entity>
        </a-marker>
        
    </a-scene>

    <!-- カメラ起動失敗の案内（古い端末や権限エラー向けの再試行UI） -->
    <div id="camera-error" style="display:none; position:fixed; left:0; right:0; top:0; bottom:0; background: rgba(0,0,0,0.75); color:#fff; z-index:9999; align-items:center; justify-content:center; display:flex; flex-direction:column;">
        <div style="max-width:420px; text-align:center; padding:20px;">
            <h2 style="margin-top:0;">カメラが起動できません</h2>
            <p>カメラの許可が拒否されているか、端末がカメラを初期化できませんでした。カメラの許可を確認し、もう一度お試しください。<br>それでもダメなら別のブラウザや端末でお試しください。</p>
            <div style="margin-top:12px;">
                <button id="retry-camera" style="padding:10px 16px;font-size:16px;border-radius:6px;background:#0078D4;color:#fff;border:none;margin-right:8px;">再試行</button>
                <button id="retry-camera-lowres" style="padding:10px 16px;font-size:16px;border-radius:6px;background:#ff8c00;color:#fff;border:none;display:none;">低解像度で再試行</button>
            </div>
        </div>
    </div>

    <script>
        // ソースコード保護: 右クリック・キーボードショートカット無効化（バブリングフェーズでも処理）
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            return false;
        }, false);

        // カメラ監視: ページロード時にカメラが起動するか確認して、失敗時は再試行UIを表示
        window.addEventListener('load', function() {
            try {
                // 少し遅らせて監視を開始（AR.jsの初期化に時間がかかる場合があるため）
                setTimeout(function() {
                    monitorCameraStartup(7000); // 7秒待ってもカメラが起動しなければUI表示
                }, 600);
            } catch (e) { console.warn('monitorCameraStartup failed to schedule', e); }

            // 再試行ボタン
            const retryBtn = document.getElementById('retry-camera');
            if (retryBtn) {
                retryBtn.addEventListener('click', function() {
                    // ページリロードで最も確実に再試行
                    try { location.reload(); } catch (e) { window.location.href = window.location.href; }
                });
            }

            // 低解像度で再試行ボタン
            const retryLowBtn = document.getElementById('retry-camera-lowres');
            if (retryLowBtn) {
                retryLowBtn.addEventListener('click', function() {
                    try {
                        const url = new URL(window.location.href);
                        url.searchParams.set('lowres', '1');
                        window.location.href = url.toString();
                    } catch (e) {
                        // フォールバック
                        if (window.location.href.indexOf('?') === -1) {
                            window.location.href = window.location.href + '?lowres=1';
                        } else {
                            window.location.href = window.location.href + '&lowres=1';
                        }
                    }
                });
            }
        });
        
        document.addEventListener('keydown', function(e) {
            // Ctrl+U, Cmd+U（ソース表示）
            if ((e.ctrlKey || e.metaKey) && (e.key === 'u' || e.key === 'U' || e.keyCode === 85)) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
            
            // F12（開発者ツール）
            if (e.key === 'F12' || e.keyCode === 123) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
            
            // Ctrl+Shift+I, Cmd+Option+I（検証ツール）
            if ((e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.keyCode === 73)) ||
                (e.metaKey && e.altKey && (e.key === 'I' || e.key === 'i' || e.keyCode === 73))) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
            
            // Ctrl+Shift+J, Cmd+Option+J（コンソール）
            if ((e.ctrlKey && e.shiftKey && (e.key === 'J' || e.key === 'j' || e.keyCode === 74)) ||
                (e.metaKey && e.altKey && (e.key === 'J' || e.key === 'j' || e.keyCode === 74))) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
            
            // Ctrl+Shift+C, Cmd+Option+C（要素選択）
            if ((e.ctrlKey && e.shiftKey && (e.key === 'C' || e.key === 'c' || e.keyCode === 67)) ||
                (e.metaKey && e.altKey && (e.key === 'C' || e.key === 'c' || e.keyCode === 67))) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
            
            // Ctrl+S, Cmd+S（保存）
            if ((e.ctrlKey || e.metaKey) && (e.key === 'S' || e.key === 's' || e.keyCode === 83)) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        }, false);
        
        // キャプチャフェーズでも追加処理
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'u' || e.key === 'U' || e.keyCode === 85)) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        }, true);
        
        document.addEventListener('keyup', function(e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'u' || e.key === 'U' || e.keyCode === 85)) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        }, true);
        
        // テキスト選択の無効化
        document.addEventListener('selectstart', function(e) {
            e.preventDefault();
            e.stopPropagation();
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
        
        // 開発者ツール検知（簡易版）
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
        
        // A-Frameの痕跡を削除
        window.addEventListener('load', function() {
            // コンソールログを無効化（A-Frameのデバッグ情報を隠す）
            const originalLog = console.log;
            const originalWarn = console.warn;
            const originalInfo = console.info;
            
            console.log = function() {
                const args = Array.from(arguments);
                const text = args.join(' ');
                // A-Frame関連のログをフィルタリング
                if (text.includes('A-Frame') || text.includes('AFRAME') || text.includes('three.js')) {
                    return;
                }
                originalLog.apply(console, arguments);
            };
            
            console.warn = function() {
                const args = Array.from(arguments);
                const text = args.join(' ');
                if (text.includes('A-Frame') || text.includes('AFRAME')) {
                    return;
                }
                originalWarn.apply(console, arguments);
            };
            
            console.info = function() {
                const args = Array.from(arguments);
                const text = args.join(' ');
                if (text.includes('A-Frame') || text.includes('AFRAME')) {
                    return;
                }
                originalInfo.apply(console, arguments);
            };
            
            // A-Frame固有の属性を削除
            setTimeout(function() {
                document.querySelectorAll('[data-aframe-inspector]').forEach(el => {
                    el.removeAttribute('data-aframe-inspector');
                });
                
                document.querySelectorAll('[data-aframe-default-camera]').forEach(el => {
                    el.removeAttribute('data-aframe-default-camera');
                });
                
                // シーン要素から不要な属性を削除
                const scene = document.querySelector('a-scene');
                if (scene) {
                    scene.removeAttribute('inspector');
                    scene.removeAttribute('keyboard-shortcuts');
                }
            }, 2000);
            
            // AFRAMEオブジェクトのバージョン情報を削除
            if (window.AFRAME) {
                delete window.AFRAME.version;
            }
        });
        
        // track whether AR.js successfully started the camera/video
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
        
        // スタンプラリー機能
        const STAMPS = {
            'sheep': { name: 'ひつじ', icon: '🐑', model: '3d_pro_sheep_matsubara.glb' },
            'fox': { name: 'きつね', icon: '🦊', model: '3d_pro_fox_isobe.glb' },
            'pengin': { name: 'ペンギン', icon: '🐧', model: '3d_pro_pengin_morita.glb' },
            'tonakai': { name: 'トナカイ', icon: '🦌', model: '3d_pro_tonakai_matsumura2.glb' },
            'pig': { name: 'ぶた', icon: '🐷', model: '3d_pro_pig_matsubara.glb' },
            // tora (とら) - 新しいマーカー/モデル
            'tora': { name: 'とら', icon: '🐯', model: '3d_pro_tora_iwamoto.glb' },
            // gollira (ごりら) - 新しいマーカー/モデル
            'gollira': { name: 'ごりら', icon: '🦍', model: '3d_pro_gollira_ishimaru.glb' },
            // white duck - 新しいマーカー/モデル
            'whiteDuck': { name: '白アヒル', icon: '🦆', model: '3d_pro_whiteDuck_tagashira.glb' },
            // araiguma - 新しいマーカー/モデル（あらいぐま）
            'araiguma': { name: 'あらいぐま', icon: '🦝', model: '3d_pro_araiguma_oonomi.glb' },
            // wolf / オオカミ
            'wolf': { name: 'おおかみ', icon: '🐺', model: '3d_pro_wolf_morita.glb' },
            // duck / アヒル
            'duck': { name: 'あひる', icon: '🦆', model: '3d_pro_duck_oonomi.glb' },
            // cat / ねこ
            'cat': { name: 'ねこ', icon: '🐱', model: '3d_pro_cat_fukuda.glb' },
            // bear / くま
            'bear': { name: 'くま', icon: '🐻', model: '3d_pro_bear_tagashira.glb' },
            // harinezumi / はりねずみ
            'harinezumi': { name: 'はりねずみ', icon: '🦔', model: '3d_pro_harinezumi_harada.glb' },
            // hamstar / ハムスター
            'hamstar': { name: 'ハムスター', icon: '🐹', model: '3d_pro_humstar_harada.glb' },
            // === シークレット動物（一番下の列に表示） ===
            // burger / バーガー (シークレット)
            'burger': { name: 'バーガー', icon: '🍔', model: '3d_pro_burger_fujii.glb', secret: true },
            // kirin / きりん (シークレット)
            'kirin': { name: 'きりん', icon: '🦒', model: '3d_202603_kirin.glb', secret: true },
            // namakemono / なまけもの (シークレット)
            'namakemono': { name: 'なまけもの', icon: '🦥', model: '3d_pro_namakemono_oda.glb', secret: true },
            // t-rex (ティラノサウルス) (シークレット)
            't-rex': { name: 'ティラノサウルス', icon: '🦖', model: '3d_pro_t-rex_ootani.glb', secret: true },
            // panda / パンダ (シークレット)
            'panda': { name: 'パンダ', icon: '🐼', model: '3d_202603_panda.glb', secret: true }
        };

        // シークレット動物のID配列
        const SECRET_STAMPS = ['burger', 'kirin', 'namakemono', 't-rex', 'panda'];

        // スタンプ帳に表示する総スロット数（最終的には20）
        const TOTAL_STAMP_SLOTS = 20;

        // Path to hint PDF asset
        const HINT_PDF_PATH = '{{ asset("cg/stampRallyHints202603.pdf") }}';
        
        // 音声ファイルをプリロード
        const soundStamp01 = new Audio("{{ asset('cg/sound_stamp01.mp3') }}");
        const soundStamp02 = new Audio("{{ asset('cg/sound_stamp02.mp3') }}");
        soundStamp01.preload = 'auto';
        soundStamp02.preload = 'auto';
        
        // ========== 景品交換機能のヘルパー関数 ==========
        
        // IndexedDB操作のヘルパー関数
        const UserIdDB = {
            dbName: 'ARStampRallyDB202603',
            storeName: 'userIdStore',
            version: 1,
            
            // DBを開く
            openDB() {
                return new Promise((resolve, reject) => {
                    const request = indexedDB.open(this.dbName, this.version);
                    
                    request.onerror = () => reject(request.error);
                    request.onsuccess = () => resolve(request.result);
                    
                    request.onupgradeneeded = (event) => {
                        const db = event.target.result;
                        if (!db.objectStoreNames.contains(this.storeName)) {
                            db.createObjectStore(this.storeName);
                        }
                    };
                });
            },
            
            // ユーザーIDを保存
            async saveUserId(userId) {
                try {
                    const db = await this.openDB();
                    const transaction = db.transaction([this.storeName], 'readwrite');
                    const store = transaction.objectStore(this.storeName);
                    store.put(userId, 'userId');
                    return new Promise((resolve, reject) => {
                        transaction.oncomplete = () => resolve();
                        transaction.onerror = () => reject(transaction.error);
                    });
                } catch (error) {
                    console.error('IndexedDB save error:', error);
                }
            },
            
            // ユーザーIDを取得
            async getUserId() {
                try {
                    const db = await this.openDB();
                    const transaction = db.transaction([this.storeName], 'readonly');
                    const store = transaction.objectStore(this.storeName);
                    const request = store.get('userId');
                    
                    return new Promise((resolve, reject) => {
                        request.onsuccess = () => resolve(request.result);
                        request.onerror = () => reject(request.error);
                    });
                } catch (error) {
                    console.error('IndexedDB get error:', error);
                    return null;
                }
            }
        };
        
        // Cookie操作のヘルパー関数
        const CookieHelper = {
            // Cookieを設定（1年間有効）
            setCookie(name, value, days = 365) {
                const expires = new Date();
                expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
                document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/;SameSite=Strict`;
            },
            
            // Cookieを取得
            getCookie(name) {
                const nameEQ = name + '=';
                const ca = document.cookie.split(';');
                for (let i = 0; i < ca.length; i++) {
                    let c = ca[i];
                    while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                    if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
                }
                return null;
            }
        };
        
        // ユニークなユーザーIDを生成
        function generateUUID() {
            return 'uid_' + 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }
        
        // ユニークなユーザーIDを取得または生成（localStorage + IndexedDB + Cookie）
        async function getUserId() {
            const storageKey = 'ar-user-id-202603';
            const cookieName = 'ar_user_id_202603';
            
            // 1. localStorageから取得を試みる
            let userId = localStorage.getItem(storageKey);
            
            // 2. なければCookieから取得を試みる
            if (!userId) {
                userId = CookieHelper.getCookie(cookieName);
                if (userId) {
                    console.log('✓ User ID restored from Cookie:', userId);
                    localStorage.setItem(storageKey, userId);
                }
            }
            
            // 3. なければIndexedDBから取得を試みる
            if (!userId) {
                userId = await UserIdDB.getUserId();
                if (userId) {
                    console.log('✓ User ID restored from IndexedDB:', userId);
                    localStorage.setItem(storageKey, userId);
                    CookieHelper.setCookie(cookieName, userId);
                }
            }
            
            // 4. どこにもなければ新規生成
            if (!userId) {
                userId = generateUUID();
                console.log('✓ New user ID generated:', userId);
            }
            
            // 5. 3箇所すべてに保存
            localStorage.setItem(storageKey, userId);
            CookieHelper.setCookie(cookieName, userId);
            await UserIdDB.saveUserId(userId);
            
            return userId;
        }
        
        // デバイスフィンガープリント生成（補助的な識別情報として使用）
        async function generateFingerprint() {
            // ユーザーIDをベースに、デバイス情報を組み合わせる
            const userId = await getUserId();
            
            const data = [
                userId, // ユニークなユーザーID（最重要）
                navigator.userAgent,
                navigator.language,
                screen.width + 'x' + screen.height,
                screen.colorDepth,
                new Date().getTimezoneOffset(),
                navigator.hardwareConcurrency || 'unknown',
                navigator.deviceMemory || 'unknown'
            ].join('|');
            
            let hash = 0;
            for (let i = 0; i < data.length; i++) {
                const char = data.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash;
            }
            return 'fp_' + Math.abs(hash).toString(36);
        }
        
        // デバイス情報収集
        function collectDeviceInfo() {
            return {
                userAgent: navigator.userAgent,
                platform: navigator.platform,
                language: navigator.language,
                screenWidth: screen.width,
                screenHeight: screen.height,
                colorDepth: screen.colorDepth,
                pixelRatio: window.devicePixelRatio,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                isIOS: /iPad|iPhone|iPod/.test(navigator.userAgent),
                isAndroid: /Android/.test(navigator.userAgent)
            };
        }
        
        // マーカー検出を記録（1日1回のみ、未捕獲のみ）
        async function recordMarkerDetection(markerId, markerName) {
            // 当日の記録があるかLocalStorageでチェック
            const today = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
            const cacheKey = `marker-scan-cache-202603-${markerId}-${today}`;
            
            // キャッシュ確認
            const cached = localStorage.getItem(cacheKey);
            if (cached) {
                console.log('✓ Marker detection already recorded today:', markerId);
                return; // 当日既に記録済み
            }
            
            // マーカースキャンを記録（capture_type: 'marker_scan'）
            try {
                await recordMarkerScan(markerId, markerName, 'marker_scan');
                
                // LocalStorageに記録（当日のキャッシュ）
                localStorage.setItem(cacheKey, JSON.stringify({
                    scanned: true,
                    timestamp: new Date().toISOString()
                }));
                
                console.log('✓ Marker detection recorded:', markerId);
            } catch (error) {
                console.error('Error recording marker detection:', error);
            }
        }
        
        // マーカー読み取りを記録
        async function recordMarkerScan(markerId, markerName, captureType = 'ball_hit') {
            const fingerprint = await generateFingerprint();
            const deviceInfo = collectDeviceInfo();
            
            // CSRFトークンを取得
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (!csrfToken) {
                console.error('CSRF token not found');
                return;
            }
            
            try {
                const response = await fetch('{{ url("/api/record-marker-scan") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken.content
                    },
                    body: JSON.stringify({
                        markerId: markerId,
                        markerName: markerName,
                        fingerprint: fingerprint,
                        deviceInfo: deviceInfo,
                        captureType: captureType,
                        scannedAt: new Date().toISOString()
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    console.log('✓ Marker scan recorded:', markerId, 'Type:', captureType, 'Total scans:', data.totalScans);
                }
            } catch (error) {
                console.error('Error recording marker scan:', error);
            }
        }
        
        // ========== LocalStorage管理 ==========
        
        // LocalStorageからスタンプデータを取得（パースエラーを保護）
        function getCollectedStamps() {
            const stored = localStorage.getItem('ar-stamp-rally-202603');
            if (!stored) return {};
            try {
                return JSON.parse(stored);
            } catch (err) {
                console.warn('getCollectedStamps: JSON parse error, resetting storage', err);
                localStorage.removeItem('ar-stamp-rally-202603');
                return {};
            }
        }
        
        // LocalStorageにスタンプデータを保存
        function saveCollectedStamps(stamps) {
            localStorage.setItem('ar-stamp-rally-202603', JSON.stringify(stamps));
        }
        
        // 捕獲済み動物の管理（モデル非表示用）
        function getCapturedAnimals() {
            const stored = localStorage.getItem('ar-captured-animals-202603');
            if (!stored) return {};
            try {
                return JSON.parse(stored);
            } catch (err) {
                console.warn('getCapturedAnimals: JSON parse error, resetting storage', err);
                localStorage.removeItem('ar-captured-animals-202603');
                return {};
            }
        }
        
        function saveCapturedAnimals(captured) {
            localStorage.setItem('ar-captured-animals-202603', JSON.stringify(captured));
        }
        
        function markAnimalCaptured(stampId) {
            const captured = getCapturedAnimals();
            captured[stampId] = true;
            saveCapturedAnimals(captured);
            console.log('Animal marked as captured:', stampId);
        }
        
        function isAnimalCaptured(stampId) {
            const captured = getCapturedAnimals();
            return captured[stampId] === true;
        }
        
        // collectStamp removed (duplicate)

        // 既存のスタンプにスクリーンショットを追加/上書きする（スクリーンショット取得後呼び出す）
        function updateStampScreenshot(stampId, screenshotDataUrl) {
            if (!stampId || !screenshotDataUrl) return false;
            try {
                const stamps = getCollectedStamps();
                if (!stamps[stampId]) {
                    // まだ記録がない場合はまず登録（保険）
                    stamps[stampId] = {
                        collectedAt: new Date().toISOString(),
                        name: (STAMPS[stampId] && STAMPS[stampId].name) ? STAMPS[stampId].name : stampId,
                        screenshot: screenshotDataUrl
                    };
                } else {
                    stamps[stampId].screenshot = screenshotDataUrl;
                }
                saveCollectedStamps(stamps);
                updateStampBadge();
                console.log('✓ Updated screenshot for stamp:', stampId);
                try {
                    if (typeof markAnimalCaptured === 'function') markAnimalCaptured(stampId);
                    // 表示用のアイコンを即時に出す
                    try { if (typeof showCapturedMessage === 'function') showCapturedMessage(stampId); } catch (e) { console.warn('showCapturedMessage failed in updateStampScreenshot', e); }
                } catch (e) { console.warn('markAnimalCaptured failed in updateStampScreenshot', e); }
                return true;
            } catch (err) {
                console.error('updateStampScreenshot failed for', stampId, err);
                return false;
            }
        }

        // collectStamp を呼んで保存されたことが確認できたら markAnimalCaptured も行う（リトライあり）
        function collectAndMarkWithRetry(stampId, screenshot = null, maxRetries = 3, intervalMs = 2000) {
            if (!stampId) return false;
            try {
                const saved = collectStamp(stampId, screenshot);
                const stamps = getCollectedStamps();
                if (stamps && stamps[stampId]) {
                    // persisted
                    try { 
                        markAnimalCaptured(stampId);
                        try { if (typeof showCapturedMessage === 'function') showCapturedMessage(stampId); } catch (e) { console.warn('showCapturedMessage failed in collectAndMarkWithRetry', e); }
                    } catch (e) { console.warn('markAnimalCaptured failed in collectAndMarkWithRetry', e); }
                    return true;
                }
                // Not persisted yet, retry a few times
                let retries = 0;
                const handle = setInterval(() => {
                    retries++;
                    try {
                        const s = collectStamp(stampId, screenshot);
                        const ss = getCollectedStamps();
                        if (ss && ss[stampId]) {
                            try { 
                                markAnimalCaptured(stampId); 
                                try { if (typeof showCapturedMessage === 'function') showCapturedMessage(stampId); } catch (e) { console.warn('showCapturedMessage failed in collectAndMarkWithRetry (retry)', e); }
                            } catch (e) { console.warn('markAnimalCaptured failed in collectAndMarkWithRetry', e); }
                            clearInterval(handle);
                            return true;
                        }
                    } catch (err) {
                        console.warn('collectAndMarkWithRetry retry error', err);
                    }
                    if (retries >= maxRetries) {
                        console.warn('collectAndMarkWithRetry failed after', retries, 'retries for', stampId);
                        clearInterval(handle);
                    }
                }, intervalMs);
                return false;
            } catch (err) {
                console.warn('collectAndMarkWithRetry initial collect failed for', stampId, err);
                return false;
            }
        }

        // 音声を再生
        function playSound(audioElement) {
            try {
                audioElement.currentTime = 0; // 最初から再生
                audioElement.play().catch(err => {
                    console.log('Audio play prevented:', err);
                });
            } catch (error) {
                console.error('Error playing sound:', error);
            }
        }
        
        // 捕獲済みメッセージを表示
        let currentCapturedAnimal = null;
        
        function showCapturedMessage(stampId) {
            const message = document.getElementById('captured-message');
            const animalName = document.getElementById('captured-animal-name');
            
            if (STAMPS[stampId]) {
                animalName.textContent = `${STAMPS[stampId].icon} ${STAMPS[stampId].name}`;
                currentCapturedAnimal = stampId;
                // インラインスタイルをリセット
                message.style.display = '';
                message.style.pointerEvents = '';
                message.style.visibility = '';
                // showクラスを追加
                message.classList.add('show');
            }
        }
        
        function hideCapturedMessage() {
            const message = document.getElementById('captured-message');
            if (!message) return;
            
            console.log('Hiding captured message');
            
            // showクラスを削除
            message.classList.remove('show');
            
            // 即座に非表示
            message.style.display = 'none';
            message.style.opacity = '0';
            message.style.pointerEvents = 'none';
            message.style.visibility = 'hidden';
            
            // currentCapturedAnimalをクリア
            currentCapturedAnimal = null;
        }
        
        // 通常パーティクル（スタンプ取得時）
        function showNormalParticles() {
            const particleIcons = ['✨', '⭐', '💫', '🌟', '💥'];
            const particleCount = 15;
            
            for (let i = 0; i < particleCount; i++) {
                setTimeout(() => {
                    createParticle(
                        particleIcons[Math.floor(Math.random() * particleIcons.length)],
                        false
                    );
                }, i * 50);
            }
        }
        
        // 豪華パーティクル（コンプリート時）
        function showCompleteParticles() {
            const particleIcons = ['🎉', '🎊', '🎈', '✨', '⭐', '💫', '🌟', '💥', '🎆', '🎇'];
            const particleCount = 40;
            
            for (let i = 0; i < particleCount; i++) {
                setTimeout(() => {
                    createParticle(
                        particleIcons[Math.floor(Math.random() * particleIcons.length)],
                        true // 大きいサイズ
                    );
                }, i * 30);
            }
            
            // 追加の連続パーティクル
            setTimeout(() => {
                for (let i = 0; i < 20; i++) {
                    setTimeout(() => {
                        createParticle(
                            particleIcons[Math.floor(Math.random() * particleIcons.length)],
                            true
                        );
                    }, i * 40);
                }
            }, 500);
        }
        
        // パーティクルを生成
        function createParticle(icon, isLarge = false) {
            const particle = document.createElement('div');
            particle.className = isLarge ? 'particle large' : 'particle';
            particle.textContent = icon;
            
            // ランダムな位置に配置
            const startX = Math.random() * window.innerWidth;
            const startY = Math.random() * window.innerHeight * 0.7 + window.innerHeight * 0.15;
            
            particle.style.left = startX + 'px';
            particle.style.top = startY + 'px';
            
            document.body.appendChild(particle);
            
            // アニメーション終了後に削除
            setTimeout(() => {
                if (particle.parentNode) {
                    document.body.removeChild(particle);
                }
            }, isLarge ? 3000 : 2000);
        }
        
        // スタンプ取得通知を表示
        function showStampNotification(stampId, isComplete = false) {
            const notification = document.createElement('div');
            
            if (isComplete) {
                // コンプリート通知
                notification.style.cssText = `
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                    color: white;
                    padding: 30px 40px;
                    border-radius: 20px;
                    font-size: 24px;
                    font-weight: bold;
                    z-index: 10002;
                    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
                    text-align: center;
                    animation: celebratePop 0.6s ease-out;
                `;
                notification.innerHTML = `
                    🎊 全種類コンプリート！ 🎊<br>
                    <div style="font-size: 18px; margin-top: 10px;">おめでとうございます！</div>
                `;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.style.animation = 'fadeOut 0.5s ease-in';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            document.body.removeChild(notification);
                        }
                    }, 500);
                }, 3000);
            } else {
                // 通常の取得通知
                notification.style.cssText = `
                    position: fixed;
                    top: 100px;
                    left: 50%;
                    transform: translateX(-50%);
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 15px 30px;
                    border-radius: 10px;
                    font-size: 18px;
                    font-weight: bold;
                    z-index: 9999;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                    animation: slideDown 0.5s ease-out;
                `;
                notification.innerHTML = `🎉 ${STAMPS[stampId].icon} ${STAMPS[stampId].name} をゲット！`;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.style.animation = 'slideUp 0.5s ease-in';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            document.body.removeChild(notification);
                        }
                    }, 500);
                }, 2000);
            }
        }
        
        // バッジの数字を更新
        function updateStampBadge() {
            const collectedStamps = getCollectedStamps();
            const count = Object.keys(collectedStamps).length;
            const badge = document.querySelector('#stamp-book-button .badge');
            if (badge) {
                badge.textContent = count;
                badge.style.display = count > 0 ? 'flex' : 'none';
            }
        }
        
        // モデルのスクリーンショットを撮影（背景を除く）
        function captureModelScreenshot(callback) {
            try {
                const scene = document.querySelector('a-scene');
                if (!scene || !scene.canvas) {
                    console.error('Scene canvas not found');
                    callback(null);
                    return;
                }
                
                // シーンのcanvasから画像を取得
                const sceneCanvas = scene.canvas;
                
                console.log('Canvas info:', {
                    width: sceneCanvas.width,
                    height: sceneCanvas.height,
                    exists: !!sceneCanvas
                });
                
                // ボール等の一時エンティティが映り込まないよう一時的に非表示にしてから1フレーム待つ
                const _tmpHiddenEls = [];
                try {
                    document.querySelectorAll('[pokeball-throwable]').forEach(el => {
                        try {
                            if (el && el.getAttribute && el.getAttribute('visible') !== 'false') {
                                el._prevVisible = el.getAttribute('visible');
                                el.setAttribute('visible', 'false');
                                _tmpHiddenEls.push(el);
                            }
                        } catch (e) { /* ignore per-element errors */ }
                    });
                    const holding = document.getElementById('holding-pokeball');
                    if (holding) {
                        holding._prevDisplay = holding.style.display || '';
                        holding.style.display = 'none';
                        _tmpHiddenEls.push(holding);
                    }
                } catch (e) { console.warn('Failed to hide transient elements for screenshot', e); }

                // レンダリングが完了するのを待つ（1フレームのみ）
                requestAnimationFrame(() => {
                    try {
                        // 一時的なcanvasを作成（元の画像用）
                        const tempCanvas = document.createElement('canvas');
                        tempCanvas.width = sceneCanvas.width;
                        tempCanvas.height = sceneCanvas.height;
                        const ctx = tempCanvas.getContext('2d');
                        
                        // シーン全体を描画
                        ctx.drawImage(sceneCanvas, 0, 0);
                        console.log('✓ Canvas drawn successfully');
                        
                        // 画像データを取得してモデル部分を検出
                        const imageData = ctx.getImageData(0, 0, tempCanvas.width, tempCanvas.height);
                        const bounds = detectModelBounds(imageData);
                        
                        console.log('Model bounds:', bounds);
                        
                        if (!bounds) {
                            console.warn('No model detected in image');
                            try { _tmpHiddenEls.forEach(el => { if (el.id === 'holding-pokeball') { el.style.display = el._prevDisplay || ''; delete el._prevDisplay; } else { try { el.setAttribute('visible', el._prevVisible || 'true'); } catch (e) {} delete el._prevVisible; } }); } catch (e) { console.warn('Failed to restore transient elements after screenshot', e); }
                            callback(null);
                            return;
                        }
                        
                        // モデル部分をクロップして拡大
                        const croppedCanvas = cropAndResize(tempCanvas, bounds, 400, 400);
                        
                        // Base64に変換（非同期で処理）
                        setTimeout(() => {
                            const screenshot = croppedCanvas.toDataURL('image/png');
                            console.log('Screenshot created:', {
                                length: screenshot.length,
                                bounds: bounds
                            });
                            try { _tmpHiddenEls.forEach(el => { if (el.id === 'holding-pokeball') { el.style.display = el._prevDisplay || ''; delete el._prevDisplay; } else { try { el.setAttribute('visible', el._prevVisible || 'true'); } catch (e) {} delete el._prevVisible; } }); } catch (e) { console.warn('Failed to restore transient elements after screenshot', e); }
                            callback(screenshot);
                        }, 0);
                        
                    } catch (drawError) {
                        console.error('Draw error:', drawError);
                        try { _tmpHiddenEls.forEach(el => { if (el.id === 'holding-pokeball') { el.style.display = el._prevDisplay || ''; delete el._prevDisplay; } else { try { el.setAttribute('visible', el._prevVisible || 'true'); } catch (e) {} delete el._prevVisible; } }); } catch (e) { console.warn('Failed to restore transient elements after screenshot', e); }
                        callback(null);
                    }
                });
                
            } catch (error) {
                console.error('Screenshot capture error:', error);
                callback(null);
            }
        }
        
        // モデルの境界を検出（背景以外の部分を見つける）
        function detectModelBounds(imageData) {
            const data = imageData.data;
            const width = imageData.width;
            const height = imageData.height;
            
            let minX = width, minY = height, maxX = 0, maxY = 0;
            let foundPixel = false;
            
            // 背景色（ほぼ白または透明）を除外してモデルのピクセルを探す
            for (let y = 0; y < height; y++) {
                for (let x = 0; x < width; x++) {
                    const i = (y * width + x) * 4;
                    const r = data[i];
                    const g = data[i + 1];
                    const b = data[i + 2];
                    const a = data[i + 3];
                    
                    // 背景でないピクセルを判定
                    // 白(255,255,255)や透明(a=0)でない、または色がついているピクセル
                    const isNotBackground = a > 10 && (
                        r < 240 || g < 240 || b < 240 || // 真っ白でない
                        Math.abs(r - g) > 10 || Math.abs(g - b) > 10 // 色がついている
                    );
                    
                    if (isNotBackground) {
                        foundPixel = true;
                        minX = Math.min(minX, x);
                        minY = Math.min(minY, y);
                        maxX = Math.max(maxX, x);
                        maxY = Math.max(maxY, y);
                    }
                }
            }
            
            if (!foundPixel) {
                return null;
            }
            
            // パディングを追加（モデルの周りに余白を持たせる）
            const padding = 20;
            minX = Math.max(0, minX - padding);
            minY = Math.max(0, minY - padding);
            maxX = Math.min(width - 1, maxX + padding);
            maxY = Math.min(height - 1, maxY + padding);
            
            return {
                x: minX,
                y: minY,
                width: maxX - minX + 1,
                height: maxY - minY + 1
            };
        }
        
        // 画像をクロップして指定サイズにリサイズ
        function cropAndResize(sourceCanvas, bounds, targetWidth, targetHeight) {
            const resultCanvas = document.createElement('canvas');
            resultCanvas.width = targetWidth;
            resultCanvas.height = targetHeight;
            const ctx = resultCanvas.getContext('2d');
            
            // 透明背景
            ctx.clearRect(0, 0, targetWidth, targetHeight);
            
            // アスペクト比を維持して最大限に拡大
            const sourceAspect = bounds.width / bounds.height;
            const targetAspect = targetWidth / targetHeight;
            
            let drawWidth, drawHeight, offsetX, offsetY;
            
            if (sourceAspect > targetAspect) {
                // 横長の画像
                drawWidth = targetWidth;
                drawHeight = targetWidth / sourceAspect;
                offsetX = 0;
                offsetY = (targetHeight - drawHeight) / 2;
            } else {
                // 縦長の画像
                drawHeight = targetHeight;
                drawWidth = targetHeight * sourceAspect;
                offsetX = (targetWidth - drawWidth) / 2;
                offsetY = 0;
            }
            
            // クロップした部分を描画
            ctx.drawImage(
                sourceCanvas,
                bounds.x, bounds.y, bounds.width, bounds.height,
                offsetX, offsetY, drawWidth, drawHeight
            );
            
            return resultCanvas;
        }
        
        // スタンプ帳を表示
        function showStampBook() {
            const collectedStamps = getCollectedStamps();
            const stampsGrid = document.getElementById('stamps-grid');
            const collectedCount = document.getElementById('collected-count');
            const completeMessageContainer = document.getElementById('complete-message-container');
            
            // アクティブモデルをリセット（マーカーなしで表示される問題を防止）
            if (typeof activeModel !== 'undefined') {
                activeModel = null;
            }
            
            // グリッドをクリア
            stampsGrid.innerHTML = '';
            
            // 各スタンプスロットを表示（総スロット数 TOTAL_STAMP_SLOTS）
            const stampKeys = Object.keys(STAMPS);
            const totalSlots = typeof TOTAL_STAMP_SLOTS === 'number' ? TOTAL_STAMP_SLOTS : stampKeys.length;

            for (let i = 0; i < totalSlots; i++) {
                if (i < stampKeys.length) {
                    // 実際に用意されているスタンプ
                    const stampId = stampKeys[i];
                    const stamp = STAMPS[stampId];
                    const isCollected = collectedStamps[stampId] !== undefined;
                    const isSecret = stamp.secret === true;

                    const stampItem = document.createElement('div');
                    stampItem.className = `stamp-item ${isCollected ? 'collected' : 'not-collected'}`;

                    let dateText = '';
                    let iconContent = stamp.icon; // デフォルトは絵文字
                    let nameText = stamp.name; // デフォルトは動物名

                    if (isCollected) {
                        const date = new Date(collectedStamps[stampId].collectedAt);
                        dateText = `<div class="stamp-date">${date.getMonth() + 1}/${date.getDate()} ${date.getHours()}:${String(date.getMinutes()).padStart(2, '0')}</div>`;

                        // スクリーンショットがあれば画像を表示
                        if (collectedStamps[stampId].screenshot) {
                            const screenshotData = collectedStamps[stampId].screenshot;
                            iconContent = `<img src="${screenshotData}" alt="${stamp.name}" style="width:100%; height:100%; object-fit:contain;">`;
                        }
                        // シークレット動物でも収集後は実際の名前を表示
                        nameText = stamp.name;
                    } else if (isSecret) {
                        // シークレット動物は未収集時にアイコンと名前を処理
                        if (stampId === 'panda') {
                            // パンダは特別扱い: 未収集でも「パンダ」と表示
                            iconContent = '🐾'; // 足跡アイコン
                            nameText = 'パンダ';
                        } else {
                            // パンダ以外のシークレット: 'シークレット'
                            iconContent = '🐾'; // 足跡アイコン
                            nameText = 'シークレット'; // 名前も隠す
                        }
                    } else {
                        // 通常動物の未収集時: '？？？' を表示、アイコンは足跡
                        iconContent = '🐾'; // 足跡アイコンに変更
                        nameText = '？？？';
                    }

                    stampItem.innerHTML = `
                        <div class="stamp-icon">${iconContent}</div>
                        <div class="stamp-name">${nameText}</div>
                        ${dateText}
                    `;

                    stampsGrid.appendChild(stampItem);
                } else {
                    // シークレットスロット（未実装の残り）
                    const secretIndex = i - stampKeys.length + 1;
                    const stampItem = document.createElement('div');
                    stampItem.className = 'stamp-item not-collected secret';

                    // 影のみで見せるアイコン（プレースホルダー）
                    const iconContent = '🐾';
                    const nameText = 'シークレット';

                    stampItem.innerHTML = `
                        <div class="stamp-icon">${iconContent}</div>
                        <div class="stamp-name">${nameText}</div>
                    `;

                    stampsGrid.appendChild(stampItem);
                }
            }
            
            // 進捗を更新（現在の収集数 / 総スロット数）
            const count = Object.keys(collectedStamps).length;
            collectedCount.textContent = count;
            const totalSlotsNode = document.getElementById('total-slots');
            const totalSlotsVal = typeof TOTAL_STAMP_SLOTS === 'number' ? TOTAL_STAMP_SLOTS : Object.keys(STAMPS).length;
            if (totalSlotsNode) totalSlotsNode.textContent = totalSlotsVal;

            // コンプリートメッセージ（全スロットを集めた場合）
            if (count === totalSlotsVal) {
                completeMessageContainer.innerHTML = `
                    <div class="complete-message">
                        🎊 おめでとうございます！ 🎊<br>
                        全${totalSlotsVal}種類コンプリート！
                    </div>
                `;
            } else {
                completeMessageContainer.innerHTML = '';
            }
            
            // モーダルを表示
            const modal = document.getElementById('stamp-book-modal');
            modal.style.display = 'block';
            
            // 明示的にスクロール位置をリセット
            requestAnimationFrame(() => {
                modal.scrollTop = 0;
                console.log('Modal scrollTop set to 0');
            });
            
            // 景品交換ボタンの状態を更新（関数が定義されている場合のみ）
            if (typeof updatePrizeButton === 'function') {
                updatePrizeButton();
            }
        }
        
        // アニメーション追加
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateX(-50%) translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateX(-50%) translateY(0);
                }
            }
            @keyframes slideUp {
                from {
                    opacity: 1;
                    transform: translateX(-50%) translateY(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(-50%) translateY(-20px);
                }
            }
            @keyframes celebratePop {
                0% {
                    opacity: 0;
                    transform: translate(-50%, -50%) scale(0.5);
                }
                50% {
                    transform: translate(-50%, -50%) scale(1.1);
                }
                100% {
                    opacity: 1;
                    transform: translate(-50%, -50%) scale(1);
                }
            }
            @keyframes fadeOut {
                from {
                    opacity: 1;
                }
                to {
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
        
        // 画面タップでアニメーションを再生
        let sceneReady = false;
        let currentFacingMode = 'environment'; // 'environment' = アウトカメラ, 'user' = インカメラ
        
        // 拡大縮小用の変数
        let currentScale = 1; // 1〜3 のスケール倍率（UIはモデルの baseScale 1.1 に乗算 → 1.1〜3.3）
        let isPinching = false;
        let pinchStartDistance = 0;
        let pinchInitialScale = 1;
        let throwButtonPressStart = 0; // 投げボタンの押下時間
        
        // 回転用の変数
        let currentRotationX = 0;
        let currentRotationY = 0; // Y軸回転（回転ボタン用）
        
        // (double-tap behavior removed) // previously used to detect double-tap animation toggles
        
        document.addEventListener('DOMContentLoaded', function() {
            const scene = document.querySelector('a-scene');
            const sheepModel = document.querySelector('#sheep-model');
            const foxModel = document.querySelector('#fox-model');
            const penginModel = document.querySelector('#pengin-model');
            const tonakaiModel = document.querySelector('#tonakai-model');
            const pigModel = document.querySelector('#pig-model');
            const toraModel = document.querySelector('#tora-model');
            const golliraModel = document.querySelector('#gollira-model');
            let activeModel = null; // 現在アクティブなモデル
            const cameraButton = document.getElementById('camera-button');
            const videoButton = document.getElementById('video-button');
            const switchCameraButton = document.getElementById('switch-camera-button');
            const flash = document.getElementById('flash');
            const photoPreview = document.getElementById('photo-preview');
            const previewImage = document.getElementById('preview-image');
            const downloadButton = document.getElementById('download-button');
            const closeButton = document.getElementById('close-button');
            let capturedImageData = null;
            let mediaRecorder = null;
            let recordedChunks = [];
            let isRecording = false;
            let recordingStartTime = 0;
            
            // マーカー検出時にアクティブモデルを設定
            const patternSheepMarker = document.querySelector('#pattern-sheep-marker');
            const patternFoxMarker = document.querySelector('#pattern-fox-marker');
            const patternPenginMarker = document.querySelector('#pattern-pengin-marker');
            const patternTonakaiMarker = document.querySelector('#pattern-tonakai-marker');
            const patternPigMarker = document.querySelector('#pattern-pig-marker');
            const patternToraMarker = document.querySelector('#pattern-tora-marker');
            const patternGolliraMarker = document.querySelector('#pattern-gollira-marker');
            const tRexModel = document.querySelector('#t-rex-model');
            const patternTRexMarker = document.querySelector('#pattern-t-rex-marker');
            const burgerModel = document.querySelector('#burger-model');
            const patternBurgerMarker = document.querySelector('#pattern-burger-marker');
            const hamstarModel = document.querySelector('#hamstar-model');
            const patternHamstarMarker = document.querySelector('#pattern-hamstar-marker');
            const whiteDuckModel = document.querySelector('#whiteDuck-model');
            const patternWhiteDuckMarker = document.querySelector('#pattern-whiteDuck-marker');
            const wolfModel = document.querySelector('#wolf-model');
            const patternWolfMarker = document.querySelector('#pattern-wolf-marker');
            const namakemonoModel = document.querySelector('#namakemono-model');
            const patternNamakemonoMarker = document.querySelector('#pattern-namakemono-marker');
            const araigumaModel = document.querySelector('#araiguma-model');
            const patternAraigumaMarker = document.querySelector('#pattern-araiguma-marker');
            const duckModel = document.querySelector('#duck-model');
            const patternDuckMarker = document.querySelector('#pattern-duck-marker');
            const catModel = document.querySelector('#cat-model');
            const patternCatMarker = document.querySelector('#pattern-cat-marker');
            const bearModel = document.querySelector('#bear-model');
            const patternBearMarker = document.querySelector('#pattern-bear-marker');
            const harinezumiModel = document.querySelector('#harinezumi-model');
            const patternHarinezumiMarker = document.querySelector('#pattern-harinezumi-marker');
            const whiteTigerModel = document.querySelector('#whiteTiger-model');
            const patternWhiteTigerMarker = document.querySelector('#pattern-whiteTiger-marker');
            const santaModel = document.querySelector('#santa-model');
            const patternSantaMarker = document.querySelector('#pattern-santa-marker');
            
            let currentMarkerStampId = null; // 現在検出中のマーカーのスタンプID
                        // --- Guide modal language handling ---
                        const guideLangJPBtn = document.getElementById('lang-jp');
                        const guideLangENBtn = document.getElementById('lang-en');
                        // initial language: always start with Japanese (can switch to English via button)
                        let guideLang = 'jp';

                        function setGuideLanguage(lang) {
                            guideLang = lang === 'jp' ? 'jp' : 'en';
                            const title = document.getElementById('guide-title');
                            const stepThrow = document.getElementById('guide-step-throw');
                            const stepZoom = document.getElementById('guide-step-zoom');
                            const stepPhoto = document.getElementById('guide-step-photo');
                            const stepOthers = document.getElementById('guide-step-others');

                            const stepFind = document.getElementById('guide-step-find');
                            const stepPrize = document.getElementById('guide-step-prize');
                            const closeGuideBtn = document.getElementById('close-guide');

                            if (guideLang === 'jp') {
                                if (title) title.textContent = '操作方法';
                                // Stamp rally notice (Japanese) - show above the guide title
                                try {
                                    const noteEl = document.getElementById('stamp-rally-note');
                                    if (noteEl) noteEl.innerHTML = `<p style="margin:0;"><strong>このスタンプラリーについて</strong></p>`;
                               } catch (e) { console.warn('Failed to set JP stamp-rally-note', e); }

                                // populate sections in the requested order: Find -> Zoom -> Photo -> Throw -> Prize -> Others
                                if (stepFind) stepFind.innerHTML = `
                                    <div class="step-text">
                                        <strong>マーカーを探す</strong>
                                        <p>会場内のマーカーにカメラを向けると3Dの動物が出現します。</p>
                                    </div>`;

                                if (stepZoom) stepZoom.innerHTML = `
                                    <div class="step-text">
                                        <strong>拡大 / 縮小</strong>
                                        <p>スマホ: ピンチで拡大・縮小できます。PC: マウスのホイールで拡大・縮小できます。</p>
                                    </div>`;

                                if (stepPhoto) stepPhoto.innerHTML = `
                                    <div class="step-text">
                                        <strong>写真・動画</strong>
                                        <p>3D動物と一緒に写真や動画を撮影できます。撮影したデータは端末に保存されます。</p>
                                    </div>`;

                                if (stepThrow) stepThrow.innerHTML = `
                                    <div class="step-text">
                                        <strong>ボールを投げる</strong>
                                        <p>画面下部にあるモンスターボールをスワイプ（フリック）して投げます。</p>
                                        <p>スワイプの速さと長さで、ボールの飛距離や速度が変わります。</p>
                                    </div>`;

                                if (stepPrize) stepPrize.innerHTML = `
                                    <div class="step-text">
                                        <strong>景品交換</strong>
                                        <p>会場で10種類以上のマーカーを集めると景品と交換できます。</p>
                                        <p>場所：2階 ヴィレッジヴァンガード横の特設エリア。</p>
                                        <p>日時：2026年3月20日 — 14:00〜16:00</p>
                                    </div>`;

                                if (stepOthers) stepOthers.innerHTML = `
                                    <div class="step-text">
                                        <hr class="guide-sep" style="border:none;border-top:1px solid #eee;margin:12px 0;">
                                        <p><strong>プライバシー</strong> — 写真・動画のデータは当方で収集しません。データは端末にのみ保存されます。</p>
                                        <p><strong>クッキー</strong> — 利用状況の集計や改善のためにクッキーを使用する場合があります。クッキーからはブラウザ情報が分かることがありますが、個人情報は含みません。</p>
                                        <p><strong>生徒制作</strong> — この作品は誠英高校（Seiei High School）の福祉クラスの生徒が授業の一環として制作したものです。誠英高校は2024年にDXハイスクールプログラムに採択され、多くのVR/ARプロジェクトを制作しています。
                                        生徒たちはプロトタイピングやPDCAサイクルを通じて作品を改善し、論理的思考力や問題解決能力を身に着けていきます。</p>
                                    </div>`;

                                // Populate JP hint information in the hints section
                                try {
                                    const stepHints = document.getElementById('guide-step-hints');
                                    if (stepHints) {
                                        stepHints.innerHTML = `
                                            <div class="step-text">
                                                <strong>マーカー設置場所のヒント</strong>
                                                <p>景品交換所にて、ヒントマップを配布予定です。</p>
                                            </div>
                                        `;
                                    }
                                } catch (e) { console.warn('Failed to populate JP hint info', e); }

                                if (closeGuideBtn) closeGuideBtn.textContent = '閉じる';

                                guideLangJPBtn.classList.add('active');
                                guideLangENBtn.classList.remove('active');
                                guideLangJPBtn.setAttribute('aria-pressed','true');
                                guideLangENBtn.setAttribute('aria-pressed','false');
                            } else {
                                if (title) title.textContent = 'How to play';
                                // Stamp rally notice (English) - show above the guide title
                                try {
                                    const noteEl = document.getElementById('stamp-rally-note');
                                    if (noteEl) noteEl.innerHTML = `<p style="margin:0;"><strong>About this stamp rally</strong></p>`;
                                } catch (e) { console.warn('Failed to set EN stamp-rally-note', e); }
                                // populate sections in the requested order: Find -> Zoom -> Photo -> Throw -> Prize -> Others
                                if (stepFind) stepFind.innerHTML = `
                                    <div class="step-text">
                                        <strong>Find a marker</strong>
                                        <p>Point your camera at markers placed in the venue to make a 3D animal appear.</p>
                                    </div>`;

                                if (stepZoom) stepZoom.innerHTML = `
                                    <div class="step-text">
                                        <strong>Zoom</strong>
                                        <p>Mobile: pinch to zoom in and out. Desktop: use the mouse wheel to zoom.</p>
                                    </div>`;

                                if (stepPhoto) stepPhoto.innerHTML = `
                                    <div class="step-text">
                                        <strong>Photo & video</strong>
                                        <p>You can take photos and videos with the 3D animals. Captured files are saved to your device only.</p>
                                    </div>`;

                                if (stepThrow) stepThrow.innerHTML = `
                                    <div class="step-text">
                                        <strong>Throw the ball</strong>
                                        <p>Swipe (flick) the Poké Ball at the bottom of the screen to throw it.</p>
                                        <p>The speed and distance of the throw depend on how fast and far you swipe.</p>
                                    </div>`;

                                if (stepPrize) stepPrize.innerHTML = `
                                    <div class="step-text">
                                        <strong>Prize exchange</strong>
                                        <p>Collect 10 or more markers in the venue to exchange for a prize.</p>
                                        <p>Where: Special area next to Village Vanguard on the 2nd floor.</p>
                                        <p>When: Mar 20, 2026 — 14:00 to 16:00</p>
                                    </div>`;

                                if (stepOthers) stepOthers.innerHTML = `
                                    <div class="step-text">
                                        <hr class="guide-sep" style="border:none;border-top:1px solid #eee;margin:12px 0;">
                                        <p><strong>Privacy</strong> — We do NOT collect data from your photos or videos. Captured files are saved to your device only.</p>
                                        <p><strong>Cookies</strong> — We may use cookies to aggregate usage statistics and improve the app. Cookies can tell us your browser details but do not include personal information.</p>
                                        <p><strong>Made by students</strong> — This project was created by students in the Welfare class at Seiei High School as part of their coursework. Seiei High School was chosen for the DX High School program in 2024 and has produced many VR/AR projects.</p>
                                        <p><strong>Learning & prototyping</strong> — Students receive feedback and improve their works through prototyping and the PDCA cycle. This helps them develop logical thinking and problem-solving skills.</p>
                                    </div>`;

                                // Populate EN hint information in the hints section
                                try {
                                    const stepHints = document.getElementById('guide-step-hints');
                                    if (stepHints) {
                                        stepHints.innerHTML = `
                                            <div class="step-text">
                                                <strong>Marker location hints</strong>
                                                <p>Hint maps will be available at the prize exchange area.</p>
                                            </div>
                                        `;
                                    }
                                } catch (e) { console.warn('Failed to populate EN hint info', e); }

                                if (closeGuideBtn) closeGuideBtn.textContent = 'Close';

                                guideLangENBtn.classList.add('active');
                                guideLangJPBtn.classList.remove('active');
                                guideLangENBtn.setAttribute('aria-pressed','true');
                                guideLangJPBtn.setAttribute('aria-pressed','false');
                            }
                        }

                        if (guideLangJPBtn) guideLangJPBtn.addEventListener('click', function(){ setGuideLanguage('jp'); });
                        if (guideLangENBtn) guideLangENBtn.addEventListener('click', function(){ setGuideLanguage('en'); });

                        // initialize content
                        setGuideLanguage(guideLang);

                        // Show guide modal on startup
                        const startupGuideModal = document.getElementById('guide-modal');
                        if (startupGuideModal) {
                            startupGuideModal.style.display = 'block';
                            startupGuideModal.setAttribute('aria-hidden', 'false');
                        }

                        // --- Camera permission detection & help modal ---
                        const cameraHelpModal = document.getElementById('camera-help-modal');
                        const cameraHelpClose = document.getElementById('camera-help-close');

                        function showCameraHelp(langKey, reason) {
                            // pick content by language
                            const title = document.getElementById('camera-help-title');
                            const body = document.getElementById('camera-help-body');
                            const isIOS = /iPhone|iPad|iPod/.test(navigator.userAgent);

                            if (langKey === 'jp') {
                                if (title) title.textContent = 'カメラアクセスがブロックされている可能性があります';
                                if (body) {
                                    if (isIOS) {
                                        body.innerHTML = `
                                            <p>iPhoneの設定でカメラをブロックしている場合、ページ内でカメラが起動できません。</p>
                                            <p>設定を確認してください：</p>
                                            <ol>
                                                <li>設定 を開く → Safari</li>
                                                <li>Safari の Camera（カメラ）項目を選択</li>
                                                <li>「すべてのWebサイトでカメラへのアクセス」を <strong>許可</strong> にする</li>
                                            </ol>
                                            <p>もしくは、設定 → アプリ → Safari → カメラ の権限が「常に許可」になっていることを確認してください。</p>
                                            `;
                                    } else {
                                        body.innerHTML = `
                                            <p>カメラアクセスがブロックされているようです。ブラウザのサイトごとのカメラ許可を確認してください。</p>
                                            <p>例: ブラウザの設定 → サイトの設定 / プライバシー → カメラ → このサイトの許可</p>`;
                                    }
                                }
                            } else {
                                if (title) title.textContent = 'Camera access may be blocked';
                                if (body) {
                                    if (isIOS) {
                                        body.innerHTML = `
                                            <p>If your iPhone blocks camera access for all websites, this page cannot start the camera.</p>
                                            <p>Please check the setting:</p>
                                            <ol>
                                                <li>Open Settings → Safari</li>
                                                <li>Find Camera and set "Allow Access to All Websites" to <strong>Allow</strong></li>
                                            </ol>
                                            <p>Or check: Settings → Safari → Camera and ensure this site has permission.</p>`;
                                    } else {
                                        body.innerHTML = `
                                            <p>Your browser seems to block camera access. Please check site camera permissions in your browser settings.</p>`;
                                    }
                                }
                            }

                            if (cameraHelpModal) {
                                cameraHelpModal.style.display = 'flex';
                                cameraHelpModal.setAttribute('aria-hidden','false');
                            }
                        }

                        function hideCameraHelp() {
                            if (cameraHelpModal) {
                                cameraHelpModal.style.display = 'none';
                                cameraHelpModal.setAttribute('aria-hidden','true');
                            }
                        }

                        if (cameraHelpClose) cameraHelpClose.addEventListener('click', hideCameraHelp, false);

                        function checkCameraPermissions() {
                            const isIOS = /iPhone|iPad|iPod/.test(navigator.userAgent);

                            // Helper: only show for iOS scenarios where AR.js didn't start the camera
                            function showIfNoAR(reason, delay = 2000) {
                                setTimeout(() => {
                                    if (!window.arjsVideoReady) {
                                        showCameraHelp(guideLang, reason || 'no-start');
                                    }
                                }, delay);
                            }

                            // Use Permissions API when available
                            if (navigator.permissions && typeof navigator.permissions.query === 'function') {
                                try {
                                    navigator.permissions.query({ name: 'camera' }).then(result => {
                                        if (result && result.state === 'denied') {
                                            // explicit denial — show quickly
                                            setTimeout(() => showCameraHelp(guideLang, 'denied'), 150);
                                        } else if (result && result.state === 'prompt') {
                                            // permission not yet granted: if AR.js still hasn't started the video after a short wait, show hint
                                            showIfNoAR('no-start', 2200);
                                        } else {
                                            // granted — no action
                                        }

                                        // also listen for changes (user may change permissions in settings while page open)
                                        try {
                                            if (result && typeof result.addEventListener === 'function') {
                                                result.addEventListener('change', () => {
                                                    // if denied later, show help; if granted later, hide help
                                                    if (result.state === 'denied') showCameraHelp(guideLang, 'denied');
                                                    else if (result.state === 'granted') hideCameraHelp();
                                                });
                                            }
                                        } catch (e) { /* ignore */ }

                                    }).catch(e => {
                                        // permissions query failed — fallback heuristics for iOS
                                        showIfNoAR('no-start', 2500);
                                    });
                                } catch (e) {
                                    showIfNoAR('no-start', 2500);
                                }
                            } else {
                                // Permissions API not available — use heuristic for all browsers
                                showIfNoAR('no-start', 3000);
                            }

                            // Additional heuristic: if enumerateDevices reports no video inputs, that's a strong sign
                            if (navigator.mediaDevices && typeof navigator.mediaDevices.enumerateDevices === 'function') {
                                navigator.mediaDevices.enumerateDevices().then(devices => {
                                    const hasVideo = devices.some(d => d.kind && d.kind.toLowerCase() === 'videoinput');
                                    if (!hasVideo) {
                                        // No video inputs found — likely global block or no camera
                                        showIfNoAR('no-devices', 500);
                                    }
                                }).catch(e => {
                                    // ignore errors — we only use this as a heuristic
                                });
                            }
                        }

                        // run initial camera permission check
                        checkCameraPermissions();
            let allHitboxes = []; // すべてのヒットボックス

            // model スケールを扱うヘルパー
            function setBaseScaleIfMissing(el) {
                if (!el) return;
                try {
                    if (!el.dataset.baseScale) {
                        let s = el.getAttribute('scale');
                        let base = 1;
                        if (s && typeof s === 'string') {
                            const parts = s.trim().split(/\s+/);
                            const n = parseFloat(parts[0]);
                            if (!isNaN(n)) base = n;
                        } else if (s && s.x) {
                            base = parseFloat(s.x) || 1;
                        }
                        el.dataset.baseScale = base;
                    }
                } catch (e) {
                    console.warn('setBaseScaleIfMissing failed', el, e);
                }
            }

            function applyCurrentScaleTo(el) {
                if (!el) return;
                try {
                    const base = parseFloat(el.dataset.baseScale || 1);
                    const clamped = Math.max(1, Math.min(3, currentScale));
                    const v = base * clamped;
                    // set uniform scale on the A-Frame element
                    el.setAttribute('scale', `${v} ${v} ${v}`);

                    // also update underlying three.js object3D scale (some models have nested meshes)
                    try {
                        if (el.object3D && el.object3D.scale && typeof el.object3D.scale.set === 'function') {
                            el.object3D.scale.set(v, v, v);
                        }

                        // traverse children and apply to meshes as well (robustness for nested gltf nodes)
                        if (el.object3D && el.object3D.children) {
                            el.object3D.traverse((node) => {
                                if (node.isMesh) {
                                    if (node.scale && typeof node.scale.set === 'function') {
                                        node.scale.set(v, v, v);
                                    }
                                    // Ensure culling/ordering doesn't hide the mesh unexpectedly
                                    node.frustumCulled = false;
                                }
                            });
                        }

                        // also apply to any A-Frame child elements that declare scales explicitly
                        const aframeChildren = el.querySelectorAll && el.querySelectorAll('[scale]');
                        if (aframeChildren && aframeChildren.length) {
                            aframeChildren.forEach(child => {
                                try {
                                    const cb = parseFloat(child.dataset.baseScale || 1);
                                    const cv = cb * clamped;
                                    child.setAttribute('scale', `${cv} ${cv} ${cv}`);
                                    if (child.object3D && child.object3D.scale && typeof child.object3D.scale.set === 'function') {
                                        child.object3D.scale.set(cv, cv, cv);
                                    }
                                } catch (e) { /* ignore per-child errors */ }
                            });
                        }
                    } catch (err) {
                        // don't allow this to break the flow
                        console.debug('applyCurrentScaleTo: three.js traversal failed', err);
                    }
                } catch (e) {
                    console.warn('applyCurrentScaleTo failed', el, e);
                }
            }

            // 指定モデルの表示/アニメーション/ヒットボックスを安全にリセットする
            function resetActiveModel(el) {
                if (!el) return;
                try { el.setAttribute('visible', 'false'); } catch (err) { console.warn('resetActiveModel: set visible failed', err); }

                // click-animation コンポーネントがあれば action を停止し、mixer 停止も試みる
                try {
                    const clickComp = el.components && el.components['click-animation'];
                    if (clickComp) {
                        if (clickComp.action01) try { clickComp.action01.stop(); } catch (e) {}
                        if (clickComp.action02) try { clickComp.action02.stop(); } catch (e) {}
                        if (clickComp.mixer && typeof clickComp.mixer.stopAllAction === 'function') try { clickComp.mixer.stopAllAction(); } catch (e) {}
                    }
                } catch (e) { /* ignore */ }

                // gltf-model の mixer 側も念のため停止
                try {
                    const gltfMixer = el.components && el.components['gltf-model'] && el.components['gltf-model'].mixer;
                    if (gltfMixer && typeof gltfMixer.stopAllAction === 'function') gltfMixer.stopAllAction();
                } catch (e) { /* ignore */ }

                // ヒットボックスが登録されていたら削除
                try {
                    if (el.components && el.components.hitbox) {
                        const idx = allHitboxes.indexOf(el.components.hitbox);
//                         if (idx > -1) // allHitboxes.splice(idx, 1);
                    }
                } catch (e) { /* ignore */ }

                // 回転ボタンやメッセージを非表示に
                try {
                    const _rotationButtons = document.getElementById('rotation-buttons');
                    if (_rotationButtons) _rotationButtons.classList.remove('visible');
                    hideCapturedMessage();
                } catch (e) { /* ignore */ }

                // currentMarkerStampId をクリア（要素の hitbox 属性から stampId を推測）
                try {
                    const hb = el.getAttribute && el.getAttribute('hitbox');
                    if (hb && currentMarkerStampId) {
                        const stampId = hb.split(':')[1] ? hb.split(':')[1].split(';')[0].trim() : null;
                        if (stampId && currentMarkerStampId === stampId) {
                            currentMarkerStampId = null;
                        }
                    }
                } catch (e) { /* ignore */ }
            }

            function getTouchesDistance(t0, t1) {
                const dx = t0.clientX - t1.clientX;
                const dy = t0.clientY - t1.clientY;
                return Math.hypot(dx, dy);
            }
            
            // ヒットエフェクトを表示
            function showHitEffect(pokeball, hitbox) {
                // 1. パーティクルエフェクト（星）
                const ballPos = pokeball.object3D.getWorldPosition(new THREE.Vector3());
                const particleIcons = ['💥', '⭐', '✨', '💫', '🌟'];
                
                for (let i = 0; i < 10; i++) {
                    setTimeout(() => {
                        const particle = document.createElement('div');
                        particle.className = 'hit-particle';
                        particle.textContent = particleIcons[Math.floor(Math.random() * particleIcons.length)];
                        
                        // ランダムな方向を設定
                        const randomX = (Math.random() - 0.5) * 2; // -1 ~ 1
                        const randomY = -Math.random() * 150; // -150 ~ 0
                        
                        particle.style.cssText = `
                            position: fixed;
                            font-size: 30px;
                            pointer-events: none;
                            z-index: 10000;
                            --random-x: ${randomX};
                            --random-y: ${randomY};
                        `;
                        particle.style.animation = 'hitParticle 0.8s ease-out forwards';
                        
                        // 画面上の位置を計算（3D → 2D変換）
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
                
                // 2. ヒット音は collectStamp 関数内で再生されるため、ここでは再生しない
                
                // 3. 画面フラッシュ
                const flash = document.getElementById('flash');
                if (flash) {
                    flash.classList.add('active');
                    setTimeout(() => {
                        flash.classList.remove('active');
                    }, 100);
                }
            }
            
            // ポケボールを投げる関数
            function throwPokeball(event) {
                // タップ位置からカメラ方向へのレイを計算
                const camera = scene.camera;
                if (!camera) return;
                
                const touch = event.changedTouches ? event.changedTouches[0] : event;
                const x = (touch.clientX / window.innerWidth) * 2 - 1;
                const y = -(touch.clientY / window.innerHeight) * 2 + 1;
                
                // レイキャスター（タップ位置から3D空間への線）
                const raycaster = new THREE.Raycaster();
                raycaster.setFromCamera(new THREE.Vector2(x, y), camera);
                
                // ポケボールを生成
                const pokeball = document.createElement('a-entity');
                pokeball.setAttribute('gltf-model', '{{ asset("cg/poke_ball_05.glb") }}');
                pokeball.setAttribute('scale', '0.15 0.15 0.15'); // サイズを小さく（1.5x bigger than before）
                pokeball.setAttribute('pokeball-throwable', '');
                
                // カメラの位置から開始
                const cameraPos = camera.getWorldPosition(new THREE.Vector3());
                pokeball.setAttribute('position', `${cameraPos.x} ${cameraPos.y} ${cameraPos.z}`);
                
                scene.appendChild(pokeball);
                
                // レイの方向に投げる
                pokeball.addEventListener('loaded', function() {
                    // モデルのマテリアルを修正してちらつきを防ぐ
                    const model = pokeball.getObject3D('mesh');
                    if (model) {
                        model.traverse(function(node) {
                            if (node.isMesh) {
                                // ジオメトリのスムージングを有効化
                                if (node.geometry) {
                                    // 法線を再計算してスムーズに見せる
                                    node.geometry.computeVertexNormals();
                                }
                                
                                // マテリアルの設定
                                if (node.material) {
                                    const materials = Array.isArray(node.material) ? node.material : [node.material];
                                    materials.forEach(mat => {
                                        // 両面レンダリング
                                        mat.side = THREE.DoubleSide;
                                        mat.depthWrite = true;
                                        mat.depthTest = true;
                                        
                                        // Z-fightingを防ぐためのポリゴンオフセット
                                        mat.polygonOffset = true;
                                        mat.polygonOffsetFactor = 1;
                                        mat.polygonOffsetUnits = 1;
                                        
                                        // フラットシェーディングを無効化（スムーズに見せる）
                                        mat.flatShading = false;
                                        
                                        // アンチエイリアス効果を高める
                                        mat.precision = 'highp';
                                        
                                        // 金属質感を調整（ポケボールらしく）
                                        if (mat.metalness !== undefined) {
                                            mat.metalness = 0.3;
                                            mat.roughness = 0.4;
                                        }
                                        
                                        // アルファ値を完全不透明に
                                        mat.transparent = false;
                                        mat.opacity = 1.0;
                                        
                                        // デプスバイアスを設定
                                        mat.depthFunc = THREE.LessEqualDepth;
                                        
                                        // マテリアルの更新を強制
                                        mat.needsUpdate = true;
                                    });
                                }
                                
                                // メッシュのレンダリング順序を設定
                                node.renderOrder = 1000;
                                
                                // フラスタムカリングを無効化（遠くでも消えない）
                                node.frustumCulled = false;
                            }
                        });
                    }
                    
                    const direction = raycaster.ray.direction.clone();
                    const speed = 15; // 投げる速さを大幅に増加（8 → 15）
                    pokeball.components['pokeball-throwable'].throw(direction, speed);
                    
                    // 当たり判定チェック（フレームごと）
                    let hasHit = false; // 重複ヒット防止
                    const checkInterval = setInterval(() => {
                        if (hasHit) return;
                        
                        const ballPos = pokeball.object3D.getWorldPosition(new THREE.Vector3());
                        
                        // すべてのヒットボックスと衝突判定
                        for (let i = 0; i < allHitboxes.length; i++) {
                            const hitbox = allHitboxes[i];
                            if (hitbox.checkCollision(ballPos)) {
                                hasHit = true;
                                const stampId = hitbox.data.stampId;
                                console.log('✓ Hit!', stampId);
                                
                                // ヒットしたモデルのanime02を再生
                                const hitModel = hitbox.el;
                                if (hitModel && hitModel.playHitAnimation) {
                                    hitModel.playHitAnimation();
                                    console.log('Playing hit animation on model:', stampId);
                                }

                                // ★ 衝突時点で即時スタンプ登録（スクショが取れなかった時の保険）
                                // (registration fallback executed)
                                
                                // ★ 衝突時点で即時スタンプ登録（スクショが取れなかった時の保険）
                                try {
                                    const saved = collectAndMarkWithRetry(stampId, null, 3, 2000);
                                    console.log('collectAndMarkWithRetry from throw returned', saved, 'for', stampId);
                                    const stamps = getCollectedStamps();
                                    if (stamps && stamps[stampId]) {
                                        // Ensure component-level captured flag is set (so markerFound hides model properly)
                                        try {
                                            if (hitModel && typeof hitModel.setCapturedState === 'function') {
                                                hitModel.setCapturedState(true);
                                            }
                                        } catch (e) { console.warn('Failed to call setCapturedState on hitModel', e); }
                                    } else {
                                        console.warn('collectAndMarkWithRetry did not persist for', stampId, 'in throw');
                                    }
                                } catch (err) {
                                    console.warn('Immediate collectStamp failed in throw for', stampId, err);
                                }

                                // 衝突エフェクト
                                showHitEffect(pokeball, hitbox);

                                // 跳ね返りアニメーション（物理的な速度反転 + 見た目のスケール）
                                const throwableComponent = pokeball.components['pokeball-throwable'];
                                if (throwableComponent) {
                                    throwableComponent.velocity.multiplyScalar(-0.6);
                                    throwableComponent.velocity.y += 3;

                                    const model = pokeball.getObject3D('mesh');
                                    if (model) {
                                        model.traverse(function(node) {
                                            if (node.isMesh) {
                                                const originalScale = pokeball.object3D.scale.clone();
                                                pokeball.object3D.scale.multiplyScalar(1.25);
                                                setTimeout(() => {
                                                    pokeball.object3D.scale.copy(originalScale);
                                                }, 120);
                                            }
                                        });
                                    }
                                }

                                // ボールは即時で非表示にしてメモリを解放（DOMから削除）
                                try {
                                    destroyAndFreeEntity(pokeball);
                                } catch (e) { console.warn('Immediate destroy for thrown ball failed', e); }

                                // アニメーションが終わったらスクリーンショット→モデル非表示
                                if (hitModel && hitModel.playHitAnimation) {
                                    const animPromise = hitModel.playHitAnimation();
                                    animPromise.then(() => {
                                        try {
                                            try {
                                                const saved = (typeof collectAndMarkWithRetry === 'function') ? collectAndMarkWithRetry(stampId, null, 3, 2000) : false;
                                                console.log('collectAndMarkWithRetry (no-screenshot) returned', saved, 'for', stampId, 'in throw path');
                                                try { if (typeof markAnimalCaptured === 'function') markAnimalCaptured(stampId); } catch (e) { /* ignore */ }
                                                try { hitModel.setAttribute('visible', 'false'); } catch (e) {}
                                                try { if (hitModel && typeof hitModel.setCapturedState === 'function') hitModel.setCapturedState(true); } catch (e) {}
                                                try { if (typeof showCapturedMessage === 'function') showCapturedMessage(stampId); } catch (e) { console.warn('showCapturedMessage failed in throw (no-screenshot) callback', e); }
                                            } catch (err) {
                                                console.warn('Non-screenshot capture failed in throw for', stampId, err);
                                                try { hitModel.setAttribute('visible', 'false'); } catch (e) {}
                                                try { if (hitModel && typeof hitModel.setCapturedState === 'function') hitModel.setCapturedState(true); } catch (e) {}
                                                try { if (typeof showCapturedMessage === 'function') showCapturedMessage(stampId); } catch (e) {}
                                            }
                                        } catch (e) { console.warn('Post-animation handling failed in throw path', e); }
                                    });
                                } else {
                                    // フォールバック: 直ちにスクショを取り、モデルを非表示
                                    try {
                                        // フォールバック: スクリーンショットは取らず、即座に記録とUI更新を行う
                                        try {
                                            const saved = (typeof collectAndMarkWithRetry === 'function') ? collectAndMarkWithRetry(stampId, null, 3, 2000) : false;
                                            console.log('collectAndMarkWithRetry (no-screenshot, fallback) returned', saved, 'for', stampId);
                                            try { if (typeof markAnimalCaptured === 'function') markAnimalCaptured(stampId); } catch (e) { /* ignore */ }
                                            try { hitModel.setAttribute('visible', 'false'); } catch (e) {}
                                            try { if (hitModel && typeof hitModel.setCapturedState === 'function') hitModel.setCapturedState(true); } catch (e) {}
                                            try { if (typeof showCapturedMessage === 'function') showCapturedMessage(stampId); } catch (e) {}
                                        } catch (err) {
                                            console.warn('Non-screenshot fallback failed for', stampId, err);
                                            try { hitModel.setAttribute('visible', 'false'); } catch (e) {}
                                        }
                                    } catch (e) { console.warn('Fallback post-hit handling failed', e); }
                                }

                                break;
                            }
                        }
                    }, 16); // 約60FPS
                    
                    // 8秒後にチェック終了
                    setTimeout(() => clearInterval(checkInterval), 8000);
                });
            }
            
            // タップ時間によるボール投げシステム
            let tapStartTime = 0;
            let isTapping = false;
            let isThrowing = false; // ボール投げ中フラグ（重複防止）
            
            // 捕まえるボタンは廃止し、常に投げられる状態に
            const catchModeActive = true;
            
            // UIボタンかどうかをチェックする関数
            function isUIButton(element) {
                return element && (element.id === 'stamp-book-button' || 
                    element.id === 'camera-button' ||
                    element.id === 'throw-button' ||
                    element.id === 'video-button' ||
                    element.id === 'switch-camera-button' ||
                    // element.id === 'rotate-up' ||
                    // element.id === 'rotate-down' ||
                    element.closest('#stamp-book-button') ||
                    element.closest('#camera-button') ||
                    element.closest('#video-button') ||
                    element.closest('#switch-camera-button') ||
                    element.closest('#throw-button') ||
                    // element.closest('#rotate-up') ||
                    // element.closest('#rotate-down') ||
                    element.closest('.rotation-buttons'));
            }
            
            // タップ/クリック開始検出（タッチデバイス）
            scene.addEventListener('touchstart', function(event) {
                // ピンチ判定（2本指）
                if (event.touches && event.touches.length >= 2) {
                    isPinching = true;
                    pinchStartDistance = getTouchesDistance(event.touches[0], event.touches[1]);
                    pinchInitialScale = currentScale;
                    // ピンチ中はタップ判定を無効化
                    isTapping = false;
                    // preventDefault を呼んでブラウザのズーム動作を抑制
                    if (event.cancelable) event.preventDefault();
                    return;
                }

                const touch = event.touches[0];
                const element = document.elementFromPoint(touch.clientX, touch.clientY);

                // UIボタンのタップは無視
                if (isUIButton(element)) {
                    return;
                }

                // タップ開始時間を記録
                tapStartTime = Date.now();
                isTapping = true;

                console.log('タップ開始');
            }, { passive: false });

            // touchmove: ピンチ中は拡大縮小を実行
            scene.addEventListener('touchmove', function(event) {
                if (!isPinching) return;
                if (!(event.touches && event.touches.length >= 2)) return;

                const d = getTouchesDistance(event.touches[0], event.touches[1]);
                if (pinchStartDistance <= 0) return;
                const factor = d / pinchStartDistance;
                currentScale = Math.max(1, Math.min(3, pinchInitialScale * factor));
                applyCurrentScaleTo(activeModel);
                if (event.cancelable) event.preventDefault();
            }, { passive: false });
            
            // マウスダウン検出（PC）
            scene.addEventListener('mousedown', function(event) {
                const element = document.elementFromPoint(event.clientX, event.clientY);
                
                // UIボタンのクリックは無視
                if (isUIButton(element)) {
                    return;
                }
                
                // クリック開始時間を記録
                tapStartTime = Date.now();
                isTapping = true;
                
                console.log('マウスダウン開始');
            });
            
            // touchend: ピンチ終了の処理、画面タップで投げる動作は廃止
            scene.addEventListener('touchend', function(event) {
                if (isPinching) {
                    // タッチが減ったらピンチ終了
                    if (!event.touches || event.touches.length < 2) {
                        isPinching = false;
                    }
                    return;
                }

                // それ以外はタップ状態をクリア（投げはボタンから行う）
                isTapping = false;
            }, { passive: true });
            
            // マウスアップ時（PC）: スクリーンのクリックで投げる機能は無効化（代替は下部の投げボタン）
            scene.addEventListener('mouseup', function(event) {
                // clear tapping state only
                isTapping = false;
            }, { passive: true });

            // ホイールでズーム（PC のスクロール）
            scene.addEventListener('wheel', function(e) {
                // e.deltaY が正で下スクロール（縮小）
                const delta = -e.deltaY; // invert so wheel up increases
                // 歯切れよく変化させる
                const step = delta * 0.0018; // tuned factor
                currentScale = Math.max(1, Math.min(3, currentScale + step));
                applyCurrentScaleTo(activeModel);
                if (e.cancelable) e.preventDefault();
            }, { passive: false });
            
            // 画面中央方向にポケボールを投げる（タップ時間で速度調整）
            function throwPokeballToCenter(speed) {
                const camera = scene.camera;
                if (!camera) return;
                
                // カメラの位置から開始
                const cameraPos = camera.getWorldPosition(new THREE.Vector3());
                
                // ポケボールを生成（サイズを半分に: 0.2 → 0.1）
                const pokeball = document.createElement('a-entity');
                pokeball.setAttribute('gltf-model', '{{ asset("cg/poke_ball_05.glb") }}');
                pokeball.setAttribute('scale', '0.15 0.15 0.15');
                pokeball.setAttribute('pokeball-throwable', '');
                pokeball.setAttribute('position', `${cameraPos.x} ${cameraPos.y} ${cameraPos.z}`);
                
                scene.appendChild(pokeball);
                
                // 画面中央方向（カメラの前方向）を取得
                const cameraQuaternion = camera.quaternion.clone();
                const forward = new THREE.Vector3(0, 0, -1);
                forward.applyQuaternion(cameraQuaternion);
                forward.normalize();
                
                console.log('Throw to center - Speed:', speed, 'Direction:', forward);
                
                // ボールが読み込まれたら投げる
                pokeball.addEventListener('loaded', function() {
                    // モデルのマテリアルを修正してちらつきを防ぐ（Android対策強化）
                    const model = pokeball.getObject3D('mesh');
                    if (model) {
                        let meshIndex = 0;
                        model.traverse(function(node) {
                            if (node.isMesh) {
                                // ジオメトリのスムージングを有効化
                                if (node.geometry) {
                                    node.geometry.computeVertexNormals();
                                }
                                
                                // マテリアルの設定（Android向けちらつき対策強化）
                                if (node.material) {
                                    const materials = Array.isArray(node.material) ? node.material : [node.material];
                                    materials.forEach((mat, index) => {
                                        // 基本設定
                                        mat.side = THREE.FrontSide;
                                        mat.depthWrite = true;
                                        mat.depthTest = true;
                                        
                                        // Android向け：polygonOffsetを大幅に強化
                                        mat.polygonOffset = true;
                                        mat.polygonOffsetFactor = (meshIndex * 1.0 + index * 1.0); // 0.1 → 1.0に増加
                                        mat.polygonOffsetUnits = (meshIndex * 1.0 + index * 1.0); // 0.1 → 1.0に増加
                                        
                                        mat.flatShading = false;
                                        mat.precision = 'highp';
                                        
                                        // PBRマテリアルの設定
                                        if (mat.metalness !== undefined) {
                                            mat.metalness = 0.2;
                                            mat.roughness = 0.5;
                                        }
                                        
                                        // 透明度設定
                                        mat.transparent = false;
                                        mat.opacity = 1.0;
                                        mat.alphaTest = 0.5;
                                        
                                        // 深度関数（Android向け）
                                        mat.depthFunc = THREE.LessEqualDepth;
                                        
                                        // Android向け：dithering有効化（ちらつきを拡散）
                                        mat.dithering = true;
                                        
                                        mat.needsUpdate = true;
                                    });
                                    
                                    // renderOrderを大きく設定（Android向け）
                                    node.renderOrder = 1000 + meshIndex * 10;
                                }
                                meshIndex++;
                            }
                        });
                    }
                    
                    pokeball.components['pokeball-throwable'].throw(forward, speed);
                    
                    // 当たり判定は pokeball-throwable コンポーネント内で処理されるため、
                    // ここでの重複した判定ループは削除しました。
                });
            }
            
            if (patternSheepMarker) {
                patternSheepMarker.addEventListener('markerFound', function() {
                    console.log('Pattern-sheep marker found');
                    activeModel = sheepModel;
                    // baseScale を記録して現在スケールを適用
                    setBaseScaleIfMissing(activeModel);
                    applyCurrentScaleTo(activeModel);
                    currentMarkerStampId = 'sheep';
                    // 回転ボタンを表示 (要素が存在する場合のみ)
                    const _rotationButtons = document.getElementById('rotation-buttons');
                    if (_rotationButtons) _rotationButtons.classList.add('visible');
                    // ヒットボックスを登録
                    if (sheepModel.components.hitbox) {
                        if (!allHitboxes.includes(sheepModel.components.hitbox)) {
                            allHitboxes.push(sheepModel.components.hitbox);
                        }
                    }

                    // 低解像度モードを自動で適用するフラグ（デバッグログ）
                    if (window.AR_FORCE_LOWRES) {
                        console.log('Low-res mode active for older device or URL param');
                    }
                });

                // シーン設定: 低解像度モードの場合はAR.jsのパラメータを弱めて初期化（DOMContentLoadedより前に参照されることがあるため追加）
                try {
                    const scene = document.querySelector('a-scene');
                    if (scene && window.AR_FORCE_LOWRES) {
                        // 低解像度/低検出レートを適用
                        scene.setAttribute('arjs', 'sourceType: webcam; debugUIEnabled: false; sourceWidth: 320; sourceHeight: 240; detectionMode: mono; maxDetectionRate: 8;');
                        scene.setAttribute('renderer', 'antialias: false; alpha: true; precision: lowp;');
                        console.log('Applied low-res AR.js settings');
                    }
                } catch (e) { /* ignore */ }
            }
                patternSheepMarker.addEventListener('markerLost', function() {
                    console.log('Pattern-sheep marker lost');
                    if (activeModel === sheepModel) {
                        activeModel = null;
                        // 回転ボタンを非表示 (要素が存在する場合のみ)
                        const _rotationButtons = document.getElementById('rotation-buttons');
                        if (_rotationButtons) _rotationButtons.classList.remove('visible');
                    }
                    if (currentMarkerStampId === 'sheep') {
                        currentMarkerStampId = null;
                    }
                    // ヒットボックスを削除
                    if (sheepModel.components.hitbox) {
                        const index = allHitboxes.indexOf(sheepModel.components.hitbox);
                        if (index > -1) {
                            // allHitboxes.splice(index, 1);
                        }
                    }
                });
            
            if (patternFoxMarker) {
                patternFoxMarker.addEventListener('markerFound', function() {
                    console.log('Pattern-fox marker found');
                    activeModel = foxModel;
                    setBaseScaleIfMissing(activeModel);
                    applyCurrentScaleTo(activeModel);
                    currentMarkerStampId = 'fox';
                    // 回転ボタンを表示 (要素が存在する場合のみ)
                    const _rotationButtons = document.getElementById('rotation-buttons');
                    if (_rotationButtons) _rotationButtons.classList.add('visible');
                    if (foxModel.components.hitbox) {
                        if (!allHitboxes.includes(foxModel.components.hitbox)) {
                            allHitboxes.push(foxModel.components.hitbox);
                        }
                    }
                });
                patternFoxMarker.addEventListener('markerLost', function() {
                    console.log('Pattern-fox marker lost');
                    if (activeModel === foxModel) {
                        activeModel = null;
                        // 回転ボタンを非表示 (要素が存在する場合のみ)
                        const _rotationButtons = document.getElementById('rotation-buttons');
                        if (_rotationButtons) _rotationButtons.classList.remove('visible');
                    }
                    if (currentMarkerStampId === 'fox') {
                        currentMarkerStampId = null;
                    }
                    if (foxModel.components.hitbox) {
                        const index = allHitboxes.indexOf(foxModel.components.hitbox);
                        if (index > -1) {
                            // allHitboxes.splice(index, 1);
                        }
                    }
                });
            }
            
            if (patternPenginMarker) {
                patternPenginMarker.addEventListener('markerFound', function() {
                    console.log('Pattern-pengin marker found');
                    activeModel = penginModel;
                    setBaseScaleIfMissing(activeModel);
                    applyCurrentScaleTo(activeModel);
                    currentMarkerStampId = 'pengin';
                    // 回転ボタンを表示 (要素が存在する場合のみ)
                    const _rotationButtons = document.getElementById('rotation-buttons');
                    if (_rotationButtons) _rotationButtons.classList.add('visible');
                    if (penginModel.components.hitbox) {
                        if (!allHitboxes.includes(penginModel.components.hitbox)) {
                            allHitboxes.push(penginModel.components.hitbox);
                        }
                    }
                });
                patternPenginMarker.addEventListener('markerLost', function() {
                    console.log('Pattern-pengin marker lost');
                    if (activeModel === penginModel) {
                        activeModel = null;
                        // 回転ボタンを非表示 (要素が存在する場合のみ)
                        const _rotationButtons = document.getElementById('rotation-buttons');
                        if (_rotationButtons) _rotationButtons.classList.remove('visible');
                    }
                    if (currentMarkerStampId === 'pengin') {
                        currentMarkerStampId = null;
                    }
                    if (penginModel.components.hitbox) {
                        const index = allHitboxes.indexOf(penginModel.components.hitbox);
                        if (index > -1) {
                            // allHitboxes.splice(index, 1);
                        }
                    }
                });
            }
            
            if (patternTonakaiMarker) {
                patternTonakaiMarker.addEventListener('markerFound', function() {
                    console.log('Pattern-tonakai marker found');
                    activeModel = tonakaiModel;
                    setBaseScaleIfMissing(activeModel);
                    applyCurrentScaleTo(activeModel);
                    currentMarkerStampId = 'tonakai';
                    // 回転ボタンを表示 (要素が存在する場合のみ)
                    const _rotationButtons = document.getElementById('rotation-buttons');
                    if (_rotationButtons) _rotationButtons.classList.add('visible');
                    if (tonakaiModel.components.hitbox) {
                        if (!allHitboxes.includes(tonakaiModel.components.hitbox)) {
                            allHitboxes.push(tonakaiModel.components.hitbox);
                        }
                    }
                });
                patternTonakaiMarker.addEventListener('markerLost', function() {
                    console.log('Pattern-tonakai marker lost');
                    if (activeModel === tonakaiModel) {
                        activeModel = null;
                        // 回転ボタンを非表示 (要素が存在する場合のみ)
                        const _rotationButtons = document.getElementById('rotation-buttons');
                        if (_rotationButtons) _rotationButtons.classList.remove('visible');
                    }
                    if (currentMarkerStampId === 'tonakai') {
                        currentMarkerStampId = null;
                    }
                    if (tonakaiModel.components.hitbox) {
                        const index = allHitboxes.indexOf(tonakaiModel.components.hitbox);
                        if (index > -1) {
                            // allHitboxes.splice(index, 1);
                        }
                    }
                });
            }
            
            if (patternPigMarker) {
                patternPigMarker.addEventListener('markerFound', function() {
                    console.log('Pattern-pig marker found');
                    activeModel = pigModel;
                    setBaseScaleIfMissing(activeModel);
                    applyCurrentScaleTo(activeModel);
                    currentMarkerStampId = 'pig';
                    // 回転ボタンを表示 (要素が存在する場合のみ)
                    const _rotationButtons = document.getElementById('rotation-buttons');
                    if (_rotationButtons) _rotationButtons.classList.add('visible');
                    if (pigModel.components.hitbox) {
                        if (!allHitboxes.includes(pigModel.components.hitbox)) {
                            allHitboxes.push(pigModel.components.hitbox);
                        }
                    }
                });
                patternPigMarker.addEventListener('markerLost', function() {
                    console.log('Pattern-pig marker lost');
                    if (activeModel === pigModel) {
                        activeModel = null;
                        // 回転ボタンを非表示 (要素が存在する場合のみ)
                        const _rotationButtons = document.getElementById('rotation-buttons');
                        if (_rotationButtons) _rotationButtons.classList.remove('visible');
                    }
                    if (currentMarkerStampId === 'pig') {
                        currentMarkerStampId = null;
                    }
                    if (pigModel.components.hitbox) {
                        const index = allHitboxes.indexOf(pigModel.components.hitbox);
                        if (index > -1) {
                            // allHitboxes.splice(index, 1);
                        }
                    }
                });
            }

            if (patternToraMarker) {
                patternToraMarker.addEventListener('markerFound', function() {
                    console.log('Pattern-tora marker found');
                    activeModel = toraModel;
                    setBaseScaleIfMissing(activeModel);
                    applyCurrentScaleTo(activeModel);
                    currentMarkerStampId = 'tora';
                    // 回転ボタンを表示 (要素が存在する場合のみ)
                    const _rotationButtons = document.getElementById('rotation-buttons');
                    if (_rotationButtons) _rotationButtons.classList.add('visible');
                    if (toraModel && toraModel.components && toraModel.components.hitbox) {
                        if (!allHitboxes.includes(toraModel.components.hitbox)) {
                            allHitboxes.push(toraModel.components.hitbox);
                        }
                    }
                });
                patternToraMarker.addEventListener('markerLost', function() {
                    console.log('Pattern-tora marker lost');
                    if (activeModel === toraModel) {
                        activeModel = null;
                        const _rotationButtons = document.getElementById('rotation-buttons');
                        if (_rotationButtons) _rotationButtons.classList.remove('visible');
                    }
                    if (currentMarkerStampId === 'tora') {
                        currentMarkerStampId = null;
                    }
                    if (toraModel && toraModel.components && toraModel.components.hitbox) {
                        const index = allHitboxes.indexOf(toraModel.components.hitbox);
                        if (index > -1) {
                            // allHitboxes.splice(index, 1);
                        }
                    }
                });
            }

            if (patternGolliraMarker) {
                patternGolliraMarker.addEventListener('markerFound', function() {
                    console.log('Pattern-gollira marker found');
                    activeModel = golliraModel;
                    setBaseScaleIfMissing(activeModel);
                    applyCurrentScaleTo(activeModel);
                    currentMarkerStampId = 'gollira';
                    const _rotationButtons = document.getElementById('rotation-buttons');
                    if (_rotationButtons) _rotationButtons.classList.add('visible');
                    if (golliraModel && golliraModel.components && golliraModel.components.hitbox) {
                        if (!allHitboxes.includes(golliraModel.components.hitbox)) {
                            allHitboxes.push(golliraModel.components.hitbox);
                        }
                    }
                });

                // --- t-rex marker handlers ---
                if (patternTRexMarker) {
                    patternTRexMarker.addEventListener('markerFound', function() {
                        console.log('Pattern-t-rex marker found');
                        activeModel = tRexModel;
                        setBaseScaleIfMissing(activeModel);
                        applyCurrentScaleTo(activeModel);
                        currentMarkerStampId = 't-rex';
                        const _rotationButtons = document.getElementById('rotation-buttons');
                        if (_rotationButtons) _rotationButtons.classList.add('visible');
                        if (tRexModel && tRexModel.components && tRexModel.components.hitbox) {
                            if (!allHitboxes.includes(tRexModel.components.hitbox)) {
                                allHitboxes.push(tRexModel.components.hitbox);
                            }
                        }
                    });

                    patternTRexMarker.addEventListener('markerLost', function() {
                        console.log('Pattern-t-rex marker lost');
                        if (activeModel === tRexModel) {
                            activeModel = null;
                            const _rotationButtons = document.getElementById('rotation-buttons');
                            if (_rotationButtons) _rotationButtons.classList.remove('visible');
                        }
                        if (currentMarkerStampId === 't-rex') currentMarkerStampId = null;
                        if (tRexModel && tRexModel.components && tRexModel.components.hitbox) {
                            const index = allHitboxes.indexOf(tRexModel.components.hitbox);
//                             if (index > -1) // allHitboxes.splice(index, 1);
                        }
                    });
                    
                    // --- burger marker handlers ---
                    if (patternBurgerMarker) {
                        patternBurgerMarker.addEventListener('markerFound', function() {
                            console.log('Pattern-burger marker found');
                            activeModel = burgerModel;
                            setBaseScaleIfMissing(activeModel);
                            applyCurrentScaleTo(activeModel);
                            currentMarkerStampId = 'burger';
                            const _rotationButtons = document.getElementById('rotation-buttons');
                            if (_rotationButtons) _rotationButtons.classList.add('visible');
                            if (burgerModel && burgerModel.components && burgerModel.components.hitbox) {
                                if (!allHitboxes.includes(burgerModel.components.hitbox)) {
                                    allHitboxes.push(burgerModel.components.hitbox);
                                }
                            }
                        });

                        patternBurgerMarker.addEventListener('markerLost', function() {
                            console.log('Pattern-burger marker lost');
                            if (activeModel === burgerModel) {
                                activeModel = null;
                                const _rotationButtons = document.getElementById('rotation-buttons');
                                if (_rotationButtons) _rotationButtons.classList.remove('visible');
                            }
                            if (currentMarkerStampId === 'burger') currentMarkerStampId = null;
                            if (burgerModel && burgerModel.components && burgerModel.components.hitbox) {
                                const index = allHitboxes.indexOf(burgerModel.components.hitbox);
//                                 if (index > -1) // allHitboxes.splice(index, 1);
                            }
                        });

                        // --- hamstar marker handlers ---
                        if (patternHamstarMarker) {
                            patternHamstarMarker.addEventListener('markerFound', function() {
                                console.log('Pattern-hamstar marker found');
                                activeModel = hamstarModel;
                                setBaseScaleIfMissing(activeModel);
                                applyCurrentScaleTo(activeModel);
                                currentMarkerStampId = 'hamstar';
                                const _rotationButtons = document.getElementById('rotation-buttons');
                                if (_rotationButtons) _rotationButtons.classList.add('visible');

                                // try to register hitbox immediately; if missing, wait for model-loaded
                                if (hamstarModel && hamstarModel.components && hamstarModel.components.hitbox) {
                                    if (!allHitboxes.includes(hamstarModel.components.hitbox)) {
                                        allHitboxes.push(hamstarModel.components.hitbox);
                                    }
                                } else if (hamstarModel) {
                                    const registerIfReady = function hf() {
                                        try {
                                            // ensure baseScale is set and re-apply user scale when model assets finish loading
                                            try { setBaseScaleIfMissing(hamstarModel); applyCurrentScaleTo(hamstarModel); } catch (e) { /* ignore */ }
                                            if (hamstarModel.components && hamstarModel.components.hitbox) {
                                                if (!allHitboxes.includes(hamstarModel.components.hitbox)) {
                                                    allHitboxes.push(hamstarModel.components.hitbox);
                                                    console.log('Registered hamstar hitbox after model-loaded');
                                                }
                                            }
                                            // also check for nested DOM elements with hitbox attribute
                                            const nested = hamstarModel.querySelectorAll ? hamstarModel.querySelectorAll('[hitbox]') : [];
                                            if (nested && nested.length) {
                                                nested.forEach(n => {
                                                    if (n.components && n.components.hitbox && !allHitboxes.includes(n.components.hitbox)) {
                                                        allHitboxes.push(n.components.hitbox);
                                                        console.log('Registered nested hamstar hitbox element', n);
                                                    }
                                                });
                                            }
                                        } catch (e) {
                                            console.debug('hamstar registration check failed', e);
                                        } finally {
                                            hamstarModel.removeEventListener('model-loaded', hf);
                                        }
                                    };
                                    hamstarModel.addEventListener('model-loaded', registerIfReady, { once: true });
                                }
                            });

                            patternHamstarMarker.addEventListener('markerLost', function() {
                                console.log('Pattern-hamstar marker lost');
                                if (activeModel === hamstarModel) {
                                    activeModel = null;
                                    const _rotationButtons = document.getElementById('rotation-buttons');
                                    if (_rotationButtons) _rotationButtons.classList.remove('visible');
                                }
                                if (currentMarkerStampId === 'hamstar') currentMarkerStampId = null;
                                if (hamstarModel && hamstarModel.components && hamstarModel.components.hitbox) {
                                    const index = allHitboxes.indexOf(hamstarModel.components.hitbox);
//                                     if (index > -1) // allHitboxes.splice(index, 1);
                                } else if (hamstarModel) {
                                    // check for nested hitbox elements and remove
                                    const nested = hamstarModel.querySelectorAll ? hamstarModel.querySelectorAll('[hitbox]') : [];
                                    if (nested && nested.length) {
                                        nested.forEach(n => {
                                            if (n.components && n.components.hitbox) {
                                                const idx = allHitboxes.indexOf(n.components.hitbox);
//                                                 if (idx > -1) // allHitboxes.splice(idx, 1);
                                            }
                                        });
                                    }
                                }
                            });
                        }
                    }
                }

                patternGolliraMarker.addEventListener('markerLost', function() {
                    console.log('Pattern-gollira marker lost');
                    if (activeModel === golliraModel) {
                        activeModel = null;
                        const _rotationButtons = document.getElementById('rotation-buttons');
                        if (_rotationButtons) _rotationButtons.classList.remove('visible');
                    }
                    if (currentMarkerStampId === 'gollira') currentMarkerStampId = null;
                    if (golliraModel && golliraModel.components && golliraModel.components.hitbox) {
                        const index = allHitboxes.indexOf(golliraModel.components.hitbox);
//                         if (index > -1) // allHitboxes.splice(index, 1);
                    }
                });
            }

            // --- whiteDuck marker handlers ---
            if (patternWhiteDuckMarker) {
                patternWhiteDuckMarker.addEventListener('markerFound', function() {
                    console.log('Pattern-whiteDuck marker found');
                    activeModel = whiteDuckModel;
                    setBaseScaleIfMissing(activeModel);
                    applyCurrentScaleTo(activeModel);
                    currentMarkerStampId = 'whiteDuck';
                    const _rotationButtons = document.getElementById('rotation-buttons');
                    if (_rotationButtons) _rotationButtons.classList.add('visible');
                    if (whiteDuckModel && whiteDuckModel.components && whiteDuckModel.components.hitbox) {
                        if (!allHitboxes.includes(whiteDuckModel.components.hitbox)) {
                            allHitboxes.push(whiteDuckModel.components.hitbox);
                        }
                    }
                });

                patternWhiteDuckMarker.addEventListener('markerLost', function() {
                    console.log('Pattern-whiteDuck marker lost');
                    if (activeModel === whiteDuckModel) {
                        activeModel = null;
                        const _rotationButtons = document.getElementById('rotation-buttons');
                        if (_rotationButtons) _rotationButtons.classList.remove('visible');
                    }
                    if (currentMarkerStampId === 'whiteDuck') currentMarkerStampId = null;
                    if (whiteDuckModel && whiteDuckModel.components && whiteDuckModel.components.hitbox) {
                        const index = allHitboxes.indexOf(whiteDuckModel.components.hitbox);
//                         if (index > -1) // allHitboxes.splice(index, 1);
                    }
                });
                
                // --- araiguma marker handlers ---
                if (patternAraigumaMarker) {
                    patternAraigumaMarker.addEventListener('markerFound', function() {
                        console.log('Pattern-araiguma marker found');
                        activeModel = araigumaModel;
                        setBaseScaleIfMissing(activeModel);
                        applyCurrentScaleTo(activeModel);
                        currentMarkerStampId = 'araiguma';
                        const _rotationButtons = document.getElementById('rotation-buttons');
                        if (_rotationButtons) _rotationButtons.classList.add('visible');

                        // try to register hitbox immediately; if missing, wait for model-loaded
                        if (araigumaModel && araigumaModel.components && araigumaModel.components.hitbox) {
                            if (!allHitboxes.includes(araigumaModel.components.hitbox)) {
                                allHitboxes.push(araigumaModel.components.hitbox);
                            }
                        } else if (araigumaModel) {
                            const registerIfReady = function af() {
                                try {
                                    try { setBaseScaleIfMissing(araigumaModel); applyCurrentScaleTo(araigumaModel); } catch (e) { /* ignore */ }
                                    if (araigumaModel.components && araigumaModel.components.hitbox) {
                                        if (!allHitboxes.includes(araigumaModel.components.hitbox)) {
                                            allHitboxes.push(araigumaModel.components.hitbox);
                                            console.log('Registered araiguma hitbox after model-loaded');
                                        }
                                    }
                                    const nested = araigumaModel.querySelectorAll ? araigumaModel.querySelectorAll('[hitbox]') : [];
                                    if (nested && nested.length) {
                                        nested.forEach(n => {
                                            if (n.components && n.components.hitbox && !allHitboxes.includes(n.components.hitbox)) {
                                                allHitboxes.push(n.components.hitbox);
                                                console.log('Registered nested araiguma hitbox element', n);
                                            }
                                        });
                                    }
                                } catch (e) {
                                    console.debug('araiguma registration check failed', e);
                                } finally {
                                    araigumaModel.removeEventListener('model-loaded', af);
                                }
                            };
                            araigumaModel.addEventListener('model-loaded', registerIfReady, { once: true });
                        }
                    });

                    patternAraigumaMarker.addEventListener('markerLost', function() {
                        console.log('Pattern-araiguma marker lost');
                        if (activeModel === araigumaModel) {
                            activeModel = null;
                            const _rotationButtons = document.getElementById('rotation-buttons');
                            if (_rotationButtons) _rotationButtons.classList.remove('visible');
                        }
                        if (currentMarkerStampId === 'araiguma') currentMarkerStampId = null;
                        if (araigumaModel && araigumaModel.components && araigumaModel.components.hitbox) {
                            const index = allHitboxes.indexOf(araigumaModel.components.hitbox);
//                             if (index > -1) // allHitboxes.splice(index, 1);
                        } else if (araigumaModel) {
                            const nested = araigumaModel.querySelectorAll ? araigumaModel.querySelectorAll('[hitbox]') : [];
                            if (nested && nested.length) {
                                nested.forEach(n => {
                                    if (n.components && n.components.hitbox) {
                                        const idx = allHitboxes.indexOf(n.components.hitbox);
//                                         if (idx > -1) // allHitboxes.splice(idx, 1);
                                    }
                                });
                            }
                        }
                    });

                    // --- wolf marker handlers ---
                    if (patternWolfMarker) {
                        patternWolfMarker.addEventListener('markerFound', function() {
                            console.log('Pattern-wolf marker found');
                            activeModel = wolfModel;
                            setBaseScaleIfMissing(activeModel);
                            applyCurrentScaleTo(activeModel);
                            currentMarkerStampId = 'wolf';
                            const _rotationButtons = document.getElementById('rotation-buttons');
                            if (_rotationButtons) _rotationButtons.classList.add('visible');

                            if (wolfModel && wolfModel.components && wolfModel.components.hitbox) {
                                if (!allHitboxes.includes(wolfModel.components.hitbox)) {
                                    allHitboxes.push(wolfModel.components.hitbox);
                                }
                            } else if (wolfModel) {
                                const registerIfReady = function wf() {
                                    try {
                                        try { setBaseScaleIfMissing(wolfModel); applyCurrentScaleTo(wolfModel); } catch (e) { /* ignore */ }
                                        if (wolfModel.components && wolfModel.components.hitbox) {
                                            if (!allHitboxes.includes(wolfModel.components.hitbox)) {
                                                allHitboxes.push(wolfModel.components.hitbox);
                                                console.log('Registered wolf hitbox after model-loaded');
                                            }
                                        }
                                        const nested = wolfModel.querySelectorAll ? wolfModel.querySelectorAll('[hitbox]') : [];
                                        if (nested && nested.length) {
                                            nested.forEach(n => {
                                                if (n.components && n.components.hitbox && !allHitboxes.includes(n.components.hitbox)) {
                                                    allHitboxes.push(n.components.hitbox);
                                                    console.log('Registered nested wolf hitbox element', n);
                                                }
                                            });
                                        }
                                    } catch (e) {
                                        console.debug('wolf registration check failed', e);
                                    } finally {
                                        wolfModel.removeEventListener('model-loaded', wf);
                                    }
                                };
                                wolfModel.addEventListener('model-loaded', registerIfReady, { once: true });
                            }
                        });

                        patternWolfMarker.addEventListener('markerLost', function() {
                            console.log('Pattern-wolf marker lost');
                            if (activeModel === wolfModel) {
                                activeModel = null;
                                const _rotationButtons = document.getElementById('rotation-buttons');
                                if (_rotationButtons) _rotationButtons.classList.remove('visible');
                            }
                            if (currentMarkerStampId === 'wolf') currentMarkerStampId = null;
                            if (wolfModel && wolfModel.components && wolfModel.components.hitbox) {
                                const index = allHitboxes.indexOf(wolfModel.components.hitbox);
//                                 if (index > -1) // allHitboxes.splice(index, 1);
                            } else if (wolfModel) {
                                const nested = wolfModel.querySelectorAll ? wolfModel.querySelectorAll('[hitbox]') : [];
                                if (nested && nested.length) {
                                    nested.forEach(n => {
                                        if (n.components && n.components.hitbox) {
                                            const idx = allHitboxes.indexOf(n.components.hitbox);
//                                             if (idx > -1) // allHitboxes.splice(idx, 1);
                                        }
                                    });
                                }
                            }
                        });
                    }

                    // --- namakemono marker handlers ---
                    if (patternNamakemonoMarker) {
                        patternNamakemonoMarker.addEventListener('markerFound', function() {
                            console.log('Pattern-namakemono marker found');
                            activeModel = namakemonoModel;
                            setBaseScaleIfMissing(activeModel);
                            applyCurrentScaleTo(activeModel);
                            currentMarkerStampId = 'namakemono';
                            const _rotationButtons = document.getElementById('rotation-buttons');
                            if (_rotationButtons) _rotationButtons.classList.add('visible');

                            if (namakemonoModel && namakemonoModel.components && namakemonoModel.components.hitbox) {
                                if (!allHitboxes.includes(namakemonoModel.components.hitbox)) {
                                    allHitboxes.push(namakemonoModel.components.hitbox);
                                }
                            } else if (namakemonoModel) {
                                const registerIfReady = function nm() {
                                    try {
                                        try { setBaseScaleIfMissing(namakemonoModel); applyCurrentScaleTo(namakemonoModel); } catch (e) { /* ignore */ }
                                        if (namakemonoModel.components && namakemonoModel.components.hitbox) {
                                            if (!allHitboxes.includes(namakemonoModel.components.hitbox)) {
                                                allHitboxes.push(namakemonoModel.components.hitbox);
                                                console.log('Registered namakemono hitbox after model-loaded');
                                            }
                                        }
                                        const nested = namakemonoModel.querySelectorAll ? namakemonoModel.querySelectorAll('[hitbox]') : [];
                                        if (nested && nested.length) {
                                            nested.forEach(n => {
                                                if (n.components && n.components.hitbox && !allHitboxes.includes(n.components.hitbox)) {
                                                    allHitboxes.push(n.components.hitbox);
                                                    console.log('Registered nested namakemono hitbox element', n);
                                                }
                                            });
                                        }
                                    } catch (e) {
                                        console.debug('namakemono registration check failed', e);
                                    } finally {
                                        namakemonoModel.removeEventListener('model-loaded', nm);
                                    }
                                };
                                namakemonoModel.addEventListener('model-loaded', registerIfReady, { once: true });
                            }
                        });

                        patternNamakemonoMarker.addEventListener('markerLost', function() {
                            console.log('Pattern-namakemono marker lost');
                            if (activeModel === namakemonoModel) {
                                activeModel = null;
                                const _rotationButtons = document.getElementById('rotation-buttons');
                                if (_rotationButtons) _rotationButtons.classList.remove('visible');
                            }
                            if (currentMarkerStampId === 'namakemono') currentMarkerStampId = null;
                            if (namakemonoModel && namakemonoModel.components && namakemonoModel.components.hitbox) {
                                const index = allHitboxes.indexOf(namakemonoModel.components.hitbox);
//                                 if (index > -1) // allHitboxes.splice(index, 1);
                            } else if (namakemonoModel) {
                                const nested = namakemonoModel.querySelectorAll ? namakemonoModel.querySelectorAll('[hitbox]') : [];
                                if (nested && nested.length) {
                                    nested.forEach(n => {
                                        if (n.components && n.components.hitbox) {
                                            const idx = allHitboxes.indexOf(n.components.hitbox);
//                                             if (idx > -1) // allHitboxes.splice(idx, 1);
                                        }
                                    });
                                }
                            }
                        });
                    }

                    // --- duck marker handlers ---
                    if (patternDuckMarker) {
                        patternDuckMarker.addEventListener('markerFound', function() {
                            console.log('Pattern-duck marker found');
                            activeModel = duckModel;
                            setBaseScaleIfMissing(activeModel);
                            applyCurrentScaleTo(activeModel);
                            currentMarkerStampId = 'duck';
                            const _rotationButtons = document.getElementById('rotation-buttons');
                            if (_rotationButtons) _rotationButtons.classList.add('visible');

                            if (duckModel && duckModel.components && duckModel.components.hitbox) {
                                if (!allHitboxes.includes(duckModel.components.hitbox)) {
                                    allHitboxes.push(duckModel.components.hitbox);
                                }
                            } else if (duckModel) {
                                const registerIfReady = function df() {
                                    try {
                                        try { setBaseScaleIfMissing(duckModel); applyCurrentScaleTo(duckModel); } catch (e) { /* ignore */ }
                                        if (duckModel.components && duckModel.components.hitbox) {
                                            if (!allHitboxes.includes(duckModel.components.hitbox)) {
                                                allHitboxes.push(duckModel.components.hitbox);
                                                console.log('Registered duck hitbox after model-loaded');
                                            }
                                        }
                                        const nested = duckModel.querySelectorAll ? duckModel.querySelectorAll('[hitbox]') : [];
                                        if (nested && nested.length) {
                                            nested.forEach(n => {
                                                if (n.components && n.components.hitbox && !allHitboxes.includes(n.components.hitbox)) {
                                                    allHitboxes.push(n.components.hitbox);
                                                    console.log('Registered nested duck hitbox element', n);
                                                }
                                            });
                                        }
                                    } catch (e) {
                                        console.debug('duck registration check failed', e);
                                    } finally {
                                        duckModel.removeEventListener('model-loaded', df);
                                    }
                                };
                                duckModel.addEventListener('model-loaded', registerIfReady, { once: true });
                            }
                        });

                        patternDuckMarker.addEventListener('markerLost', function() {
                            console.log('Pattern-duck marker lost');
                            if (activeModel === duckModel) {
                                activeModel = null;
                                const _rotationButtons = document.getElementById('rotation-buttons');
                                if (_rotationButtons) _rotationButtons.classList.remove('visible');
                            }
                            if (currentMarkerStampId === 'duck') currentMarkerStampId = null;
                            if (duckModel && duckModel.components && duckModel.components.hitbox) {
                                const index = allHitboxes.indexOf(duckModel.components.hitbox);
//                                 if (index > -1) // allHitboxes.splice(index, 1);
                            } else if (duckModel) {
                                const nested = duckModel.querySelectorAll ? duckModel.querySelectorAll('[hitbox]') : [];
                                if (nested && nested.length) {
                                    nested.forEach(n => {
                                        if (n.components && n.components.hitbox) {
                                            const idx = allHitboxes.indexOf(n.components.hitbox);
//                                             if (idx > -1) // allHitboxes.splice(idx, 1);
                                        }
                                    });
                                }
                            }
                        });
                    }

                    // --- cat marker handlers ---
                    if (patternCatMarker) {
                        patternCatMarker.addEventListener('markerFound', function() {
                            console.log('Pattern-cat marker found');
                            activeModel = catModel;
                            setBaseScaleIfMissing(activeModel);
                            applyCurrentScaleTo(activeModel);
                            currentMarkerStampId = 'cat';
                            const _rotationButtons = document.getElementById('rotation-buttons');
                            if (_rotationButtons) _rotationButtons.classList.add('visible');

                            if (catModel && catModel.components && catModel.components.hitbox) {
                                if (!allHitboxes.includes(catModel.components.hitbox)) {
                                    allHitboxes.push(catModel.components.hitbox);
                                }
                            } else if (catModel) {
                                const registerIfReady = function cf() {
                                    try {
                                        try { setBaseScaleIfMissing(catModel); applyCurrentScaleTo(catModel); } catch (e) { /* ignore */ }
                                        if (catModel.components && catModel.components.hitbox) {
                                            if (!allHitboxes.includes(catModel.components.hitbox)) {
                                                allHitboxes.push(catModel.components.hitbox);
                                                console.log('Registered cat hitbox after model-loaded');
                                            }
                                        }
                                        const nested = catModel.querySelectorAll ? catModel.querySelectorAll('[hitbox]') : [];
                                        if (nested && nested.length) {
                                            nested.forEach(n => {
                                                if (n.components && n.components.hitbox && !allHitboxes.includes(n.components.hitbox)) {
                                                    allHitboxes.push(n.components.hitbox);
                                                    console.log('Registered nested cat hitbox element', n);
                                                }
                                            });
                                        }
                                    } catch (e) {
                                        console.debug('cat registration check failed', e);
                                    } finally {
                                        catModel.removeEventListener('model-loaded', cf);
                                    }
                                };
                                catModel.addEventListener('model-loaded', registerIfReady, { once: true });
                            }
                        });

                        patternCatMarker.addEventListener('markerLost', function() {
                            console.log('Pattern-cat marker lost');
                            if (activeModel === catModel) {
                                activeModel = null;
                                const _rotationButtons = document.getElementById('rotation-buttons');
                                if (_rotationButtons) _rotationButtons.classList.remove('visible');
                            }
                            if (currentMarkerStampId === 'cat') currentMarkerStampId = null;
                            if (catModel && catModel.components && catModel.components.hitbox) {
                                const index = allHitboxes.indexOf(catModel.components.hitbox);
//                                 if (index > -1) // allHitboxes.splice(index, 1);
                            } else if (catModel) {
                                const nested = catModel.querySelectorAll ? catModel.querySelectorAll('[hitbox]') : [];
                                if (nested && nested.length) {
                                    nested.forEach(n => {
                                        if (n.components && n.components.hitbox) {
                                            const idx = allHitboxes.indexOf(n.components.hitbox);
//                                             if (idx > -1) // allHitboxes.splice(idx, 1);
                                        }
                                    });
                                }
                            }
                        });
                    }

                    // --- bear marker handlers ---
                    if (patternBearMarker) {
                        patternBearMarker.addEventListener('markerFound', function() {
                            console.log('Pattern-bear marker found');
                            activeModel = bearModel;
                            setBaseScaleIfMissing(activeModel);
                            applyCurrentScaleTo(activeModel);
                            currentMarkerStampId = 'bear';
                            const _rotationButtons = document.getElementById('rotation-buttons');
                            if (_rotationButtons) _rotationButtons.classList.add('visible');

                            if (bearModel && bearModel.components && bearModel.components.hitbox) {
                                if (!allHitboxes.includes(bearModel.components.hitbox)) {
                                    allHitboxes.push(bearModel.components.hitbox);
                                }
                            } else if (bearModel) {
                                const registerIfReady = function bf() {
                                    try {
                                        try { setBaseScaleIfMissing(bearModel); applyCurrentScaleTo(bearModel); } catch (e) { /* ignore */ }
                                        if (bearModel.components && bearModel.components.hitbox) {
                                            if (!allHitboxes.includes(bearModel.components.hitbox)) {
                                                allHitboxes.push(bearModel.components.hitbox);
                                                console.log('Registered bear hitbox after model-loaded');
                                            }
                                        }
                                        const nested = bearModel.querySelectorAll ? bearModel.querySelectorAll('[hitbox]') : [];
                                        if (nested && nested.length) {
                                            nested.forEach(n => {
                                                if (n.components && n.components.hitbox && !allHitboxes.includes(n.components.hitbox)) {
                                                    allHitboxes.push(n.components.hitbox);
                                                    console.log('Registered nested bear hitbox element', n);
                                                }
                                            });
                                        }
                                    } catch (e) {
                                        console.debug('bear registration check failed', e);
                                    } finally {
                                        bearModel.removeEventListener('model-loaded', bf);
                                    }
                                };
                                bearModel.addEventListener('model-loaded', registerIfReady, { once: true });
                            }
                        });

                        patternBearMarker.addEventListener('markerLost', function() {
                            console.log('Pattern-bear marker lost');
                            if (activeModel === bearModel) {
                                activeModel = null;
                                const _rotationButtons = document.getElementById('rotation-buttons');
                                if (_rotationButtons) _rotationButtons.classList.remove('visible');
                            }
                            if (currentMarkerStampId === 'bear') currentMarkerStampId = null;
                            if (bearModel && bearModel.components && bearModel.components.hitbox) {
                                const index = allHitboxes.indexOf(bearModel.components.hitbox);
//                                 if (index > -1) // allHitboxes.splice(index, 1);
                            } else if (bearModel) {
                                const nested = bearModel.querySelectorAll ? bearModel.querySelectorAll('[hitbox]') : [];
                                if (nested && nested.length) {
                                    nested.forEach(n => {
                                        if (n.components && n.components.hitbox) {
                                            const idx = allHitboxes.indexOf(n.components.hitbox);
//                                             if (idx > -1) // allHitboxes.splice(idx, 1);
                                        }
                                    });
                                }
                            }
                        });
                    }

                    // --- harinezumi marker handlers ---
                    if (patternHarinezumiMarker) {
                        patternHarinezumiMarker.addEventListener('markerFound', function() {
                            console.log('Pattern-harinezumi marker found');
                            activeModel = harinezumiModel;
                            setBaseScaleIfMissing(activeModel);
                            applyCurrentScaleTo(activeModel);
                            currentMarkerStampId = 'harinezumi';
                            const _rotationButtons = document.getElementById('rotation-buttons');
                            if (_rotationButtons) _rotationButtons.classList.add('visible');

                            if (harinezumiModel && harinezumiModel.components && harinezumiModel.components.hitbox) {
                                if (!allHitboxes.includes(harinezumiModel.components.hitbox)) {
                                    allHitboxes.push(harinezumiModel.components.hitbox);
                                }
                            } else if (harinezumiModel) {
                                const registerIfReady = function hf() {
                                    try {
                                        try { setBaseScaleIfMissing(harinezumiModel); applyCurrentScaleTo(harinezumiModel); } catch (e) { /* ignore */ }
                                        if (harinezumiModel.components && harinezumiModel.components.hitbox) {
                                            if (!allHitboxes.includes(harinezumiModel.components.hitbox)) {
                                                allHitboxes.push(harinezumiModel.components.hitbox);
                                                console.log('Registered harinezumi hitbox after model-loaded');
                                            }
                                        }
                                        const nested = harinezumiModel.querySelectorAll ? harinezumiModel.querySelectorAll('[hitbox]') : [];
                                        if (nested && nested.length) {
                                            nested.forEach(n => {
                                                if (n.components && n.components.hitbox && !allHitboxes.includes(n.components.hitbox)) {
                                                    allHitboxes.push(n.components.hitbox);
                                                    console.log('Registered nested harinezumi hitbox element', n);
                                                }
                                            });
                                        }
                                    } catch (e) {
                                        console.debug('harinezumi registration check failed', e);
                                    } finally {
                                        harinezumiModel.removeEventListener('model-loaded', hf);
                                    }
                                };
                                harinezumiModel.addEventListener('model-loaded', registerIfReady, { once: true });
                            }
                        });

                        patternHarinezumiMarker.addEventListener('markerLost', function() {
                            console.log('Pattern-harinezumi marker lost');
                            if (activeModel === harinezumiModel) {
                                activeModel = null;
                                const _rotationButtons = document.getElementById('rotation-buttons');
                                if (_rotationButtons) _rotationButtons.classList.remove('visible');
                            }
                            if (currentMarkerStampId === 'harinezumi') currentMarkerStampId = null;
                            if (harinezumiModel && harinezumiModel.components && harinezumiModel.components.hitbox) {
                                const index = allHitboxes.indexOf(harinezumiModel.components.hitbox);
//                                 if (index > -1) // allHitboxes.splice(index, 1);
                            } else if (harinezumiModel) {
                                const nested = harinezumiModel.querySelectorAll ? harinezumiModel.querySelectorAll('[hitbox]') : [];
                                if (nested && nested.length) {
                                    nested.forEach(n => {
                                        if (n.components && n.components.hitbox) {
                                            const idx = allHitboxes.indexOf(n.components.hitbox);
//                                             if (idx > -1) // allHitboxes.splice(idx, 1);
                                        }
                                    });
                                }
                            }
                        });
                    }

                    // --- whiteTiger marker handlers ---
                    if (patternWhiteTigerMarker) {
                        patternWhiteTigerMarker.addEventListener('markerFound', function() {
                            console.log('Pattern-whiteTiger marker found');
                            activeModel = whiteTigerModel;
                            setBaseScaleIfMissing(activeModel);
                            applyCurrentScaleTo(activeModel);
                            currentMarkerStampId = 'panda';
                            const _rotationButtons = document.getElementById('rotation-buttons');
                            if (_rotationButtons) _rotationButtons.classList.add('visible');

                            if (whiteTigerModel && whiteTigerModel.components && whiteTigerModel.components.hitbox) {
                                if (!allHitboxes.includes(whiteTigerModel.components.hitbox)) {
                                    allHitboxes.push(whiteTigerModel.components.hitbox);
                                }
                            } else if (whiteTigerModel) {
                                const registerIfReady = function wtf() {
                                    try {
                                        try { setBaseScaleIfMissing(whiteTigerModel); applyCurrentScaleTo(whiteTigerModel); } catch (e) { /* ignore */ }
                                        if (whiteTigerModel.components && whiteTigerModel.components.hitbox) {
                                            if (!allHitboxes.includes(whiteTigerModel.components.hitbox)) {
                                                allHitboxes.push(whiteTigerModel.components.hitbox);
                                                console.log('Registered whiteTiger hitbox after model-loaded');
                                            }
                                        }
                                        const nested = whiteTigerModel.querySelectorAll ? whiteTigerModel.querySelectorAll('[hitbox]') : [];
                                        if (nested && nested.length) {
                                            nested.forEach(n => {
                                                if (n.components && n.components.hitbox && !allHitboxes.includes(n.components.hitbox)) {
                                                    allHitboxes.push(n.components.hitbox);
                                                    console.log('Registered nested whiteTiger hitbox element', n);
                                                }
                                            });
                                        }
                                    } catch (e) {
                                        console.debug('whiteTiger registration check failed', e);
                                    } finally {
                                        whiteTigerModel.removeEventListener('model-loaded', wtf);
                                    }
                                };
                                whiteTigerModel.addEventListener('model-loaded', registerIfReady, { once: true });
                            }
                        });

                        patternWhiteTigerMarker.addEventListener('markerLost', function() {
                            console.log('Pattern-whiteTiger marker lost');
                            if (activeModel === whiteTigerModel) {
                                activeModel = null;
                                const _rotationButtons = document.getElementById('rotation-buttons');
                                if (_rotationButtons) _rotationButtons.classList.remove('visible');
                            }
                            if (currentMarkerStampId === 'panda') currentMarkerStampId = null;
                            if (whiteTigerModel && whiteTigerModel.components && whiteTigerModel.components.hitbox) {
                                const index = allHitboxes.indexOf(whiteTigerModel.components.hitbox);
//                                 if (index > -1) // allHitboxes.splice(index, 1);
                            } else if (whiteTigerModel) {
                                const nested = whiteTigerModel.querySelectorAll ? whiteTigerModel.querySelectorAll('[hitbox]') : [];
                                if (nested && nested.length) {
                                    nested.forEach(n => {
                                        if (n.components && n.components.hitbox) {
                                            const idx = allHitboxes.indexOf(n.components.hitbox);
//                                             if (idx > -1) // allHitboxes.splice(idx, 1);
                                        }
                                    });
                                }
                            }
                        });
                    }

                    // --- santa marker handlers ---
                    if (patternSantaMarker) {
                        patternSantaMarker.addEventListener('markerFound', function() {
                            console.log('Pattern-santa marker found');
                            activeModel = santaModel;
                            setBaseScaleIfMissing(activeModel);
                            applyCurrentScaleTo(activeModel);
                            currentMarkerStampId = 'kirin';
                            const _rotationButtons = document.getElementById('rotation-buttons');
                            if (_rotationButtons) _rotationButtons.classList.add('visible');

                            if (santaModel && santaModel.components && santaModel.components.hitbox) {
                                if (!allHitboxes.includes(santaModel.components.hitbox)) {
                                    allHitboxes.push(santaModel.components.hitbox);
                                }
                            } else if (santaModel) {
                                const registerIfReady = function sf() {
                                    try {
                                        try { setBaseScaleIfMissing(santaModel); applyCurrentScaleTo(santaModel); } catch (e) { /* ignore */ }
                                        if (santaModel.components && santaModel.components.hitbox) {
                                            if (!allHitboxes.includes(santaModel.components.hitbox)) {
                                                allHitboxes.push(santaModel.components.hitbox);
                                                console.log('Registered santa hitbox after model-loaded');
                                            }
                                        }
                                        const nested = santaModel.querySelectorAll ? santaModel.querySelectorAll('[hitbox]') : [];
                                        if (nested && nested.length) {
                                            nested.forEach(n => {
                                                if (n.components && n.components.hitbox && !allHitboxes.includes(n.components.hitbox)) {
                                                    allHitboxes.push(n.components.hitbox);
                                                    console.log('Registered nested santa hitbox element', n);
                                                }
                                            });
                                        }
                                    } catch (e) {
                                        console.debug('santa registration check failed', e);
                                    } finally {
                                        santaModel.removeEventListener('model-loaded', sf);
                                    }
                                };
                                santaModel.addEventListener('model-loaded', registerIfReady, { once: true });
                            }
                        });

                        patternSantaMarker.addEventListener('markerLost', function() {
                            console.log('Pattern-santa marker lost');
                            if (activeModel === santaModel) {
                                activeModel = null;
                                const _rotationButtons = document.getElementById('rotation-buttons');
                                if (_rotationButtons) _rotationButtons.classList.remove('visible');
                            }
                            if (currentMarkerStampId === 'kirin') currentMarkerStampId = null;
                            if (santaModel && santaModel.components && santaModel.components.hitbox) {
                                const index = allHitboxes.indexOf(santaModel.components.hitbox);
//                                 if (index > -1) // allHitboxes.splice(index, 1);
                            } else if (santaModel) {
                                const nested = santaModel.querySelectorAll ? santaModel.querySelectorAll('[hitbox]') : [];
                                if (nested && nested.length) {
                                    nested.forEach(n => {
                                        if (n.components && n.components.hitbox) {
                                            const idx = allHitboxes.indexOf(n.components.hitbox);
//                                             if (idx > -1) // allHitboxes.splice(idx, 1);
                                        }
                                    });
                                }
                            }
                        });
                    }
                }
            }
            
            scene.addEventListener('loaded', function() {
                sceneReady = true;
                console.log('Scene loaded');
            });
            
            // スタンプ帳ボタン
            const stampBookButton = document.getElementById('stamp-book-button');
            stampBookButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                showStampBook();
            });

            // 投げるボタンのハンドラ (スマホ・PC 共通)
            const throwButton = document.getElementById('throw-button');
            // preview variables
            let previewEntity = null;
            let previewRotationSpeed = 0; // radians per second
            let previewAnimationId = null;
            let previewLastTime = null;
            if (throwButton) {
                // Prevent selection/long-press artifacts and context menu on mobile devices
                // - touchstart preventDefault ensures the browser doesn't select an overlay area while long-pressing
                // - contextmenu preventDefault blocks the native long-press menu on some browsers
                try {
                    throwButton.addEventListener('touchstart', function(e) {
                        if (e.cancelable) e.preventDefault();
                        e.stopPropagation();
                    }, { passive: false });

                    throwButton.addEventListener('contextmenu', function(e) {
                        e.preventDefault();
                        return false;
                    }, false);
                } catch (ex) {
                    // defensive: if any browser doesn't support these events, continue
                    console.warn('Throw button selection prevention not fully supported on this device', ex);
                }
                let throwPressInterval = null;
                throwButton.addEventListener('pointerdown', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    throwButtonPressStart = Date.now();

                    // start feedback timer
                    let lastLevel = 0;
                    // create preview entity attached to camera
                    try {
                        const cameraEl = scene.querySelector('[camera]') || scene.camera && scene.camera.el;
                        if (cameraEl && !previewEntity) {
                            previewEntity = document.createElement('a-entity');
                            previewEntity.setAttribute('id', 'throw-preview');
                            previewEntity.setAttribute('gltf-model', '{{ asset("cg/poke_ball_05.glb") }}');
                            // slightly larger for preview, set uniform 0.22
                            // preview ball should be half the previous size (smaller preview)
                            previewEntity.setAttribute('scale', '0.11 0.11 0.11');
                            // position: move preview half a ball higher than previous
                            const previewScale = 0.11; // used above for scale
                            const baseY = -0.45;
                            const yOffset = baseY + (previewScale / 2); // half a ball up
                            previewEntity.setAttribute('position', `0 ${yOffset} -0.9`);
                            previewEntity.setAttribute('visible', 'true');
                            // prevent frustum culling
                            previewEntity.addEventListener('loaded', function() {
                                const obj = previewEntity.getObject3D('mesh');
                                if (obj) {
                                    obj.traverse(function(n) { if (n.isMesh) n.frustumCulled = false; });
                                }
                            });
                            cameraEl.appendChild(previewEntity);
                        }
                    } catch (ex) {
                        console.warn('Failed to create preview entity', ex);
                    }

                    throwPressInterval = setInterval(() => {
                        const elapsed = Date.now() - throwButtonPressStart;
                        // levels: short <=300ms, mid <=800ms, long >800ms
                        let level = 0;
                        if (elapsed >= 800) level = 3; else if (elapsed >= 300) level = 2; else level = 1;
                        if (level !== lastLevel) {
                            throwButton.classList.remove('power-low','power-mid','power-high');
                            if (level === 1) throwButton.classList.add('power-low');
                            if (level === 2) throwButton.classList.add('power-mid');
                            if (level === 3) throwButton.classList.add('power-high');
                            lastLevel = level;
                            // update preview rotation speed for levels
                                if (previewEntity) {
                                    if (level === 1) previewRotationSpeed = 0.8; // slow (unchanged)
                                    else if (level === 2) previewRotationSpeed = 4.0; // mid (2x original)
                                    else previewRotationSpeed = 10.0; // fast (2x original)

                                // start animation loop if not running
                                if (!previewAnimationId) {
                                    previewLastTime = performance.now();
                                    const loop = (t) => {
                                        if (!previewEntity) { previewAnimationId = null; return; }
                                        const dt = (t - (previewLastTime || t)) / 1000;
                                        previewLastTime = t;
                                        try {
                                            const obj = previewEntity.getObject3D('mesh');
                                            if (obj) {
                                                // rotate vertically (around X-axis) instead of horizontally (Y-axis)
                                                obj.rotation.x += (previewRotationSpeed || 0) * dt;
                                            }
                                        } catch (e) { /* ignore */ }
                                        previewAnimationId = requestAnimationFrame(loop);
                                    };
                                    previewAnimationId = requestAnimationFrame(loop);
                                }
                            }
                        }
                    }, 50);
                });

                throwButton.addEventListener('pointerup', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    if (throwPressInterval) {
                        clearInterval(throwPressInterval);
                        throwPressInterval = null;
                    }

                    // compute discrete speed levels from press duration
                    const duration = Math.max(0, Date.now() - (throwButtonPressStart || Date.now()));
                    let speed = 20; // default medium
                    if (duration < 300) speed = 12; // low
                    else if (duration < 800) speed = 20; // medium
                    else speed = 30; // high

                    // visual reset
                    throwButton.classList.remove('power-low','power-mid','power-high');

                    // remove preview entity and stop animation
                    try {
                        if (previewAnimationId) { cancelAnimationFrame(previewAnimationId); previewAnimationId = null; }
                        if (previewEntity && previewEntity.parentNode) { previewEntity.parentNode.removeChild(previewEntity); }
                        previewEntity = null;
                        previewRotationSpeed = 0;
                    } catch (ex) { console.warn('Failed to remove preview', ex); }

                    if (isThrowing) return;
                    isThrowing = true;
                    throwPokeballToCenter(speed);
                    setTimeout(() => { isThrowing = false; }, 500);
                    throwButtonPressStart = 0;
                });

                throwButton.addEventListener('pointercancel', function(e) {
                    if (throwPressInterval) { clearInterval(throwPressInterval); throwPressInterval = null; }
                    throwButton.classList.remove('power-low','power-mid','power-high');
                    throwButtonPressStart = 0;
                    if (previewAnimationId) { cancelAnimationFrame(previewAnimationId); previewAnimationId = null; }
                    if (previewEntity && previewEntity.parentNode) previewEntity.parentNode.removeChild(previewEntity);
                    previewEntity = null;
                });
            }
            
            // カメラを再開するヘルパー関数（モーダルを閉じたときにフリーズを防止）
            function resumeCamera() {
                try {
                    // ビデオ要素を取得
                    const video = document.querySelector('video');
                    if (video) {
                        // ビデオが一時停止している場合は再生
                        if (video.paused) {
                            video.play().catch(e => console.log('Video play attempt:', e));
                        }
                    }
                    
                    // A-Frameシーンを再生（A-Frame内部のレンダリングループとシステムのtickを復帰）
                    const scene = document.querySelector('a-scene');
                    if (scene) {
                        try {
                            // A-Frame の play() を呼んでレンダリングループを取り戻す
                            if (typeof scene.play === 'function') {
                                scene.play();
                            }

                            // 一度だけ AR.js 系の tick をキックしておく（念のため）
                            if (scene.systems && scene.systems.arjs && typeof scene.systems.arjs.tick === 'function') {
                                requestAnimationFrame(() => scene.systems.arjs.tick());
                            }
                        } catch (err) {
                            console.warn('resumeCamera: failed to fully resume A-Frame scene', err);
                        }
                    }
                    
                    console.log('Camera resumed after modal close');
                } catch (error) {
                    console.log('Camera resume attempt:', error);
                }
            }
            
            // スタンプ帳を閉じる
            const closeStampBookButton = document.getElementById('close-stamp-book');
            closeStampBookButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const modal = document.getElementById('stamp-book-modal');
                modal.style.display = 'none';
                
                // アクティブモデルを安全にリセット
                if (typeof activeModel !== 'undefined' && activeModel) {
                    console.log('Resetting active model via helper:', activeModel.id);
                    resetActiveModel(activeModel);
                    activeModel = null;
                }
                
                // カメラを再開（フリーズ防止）
                setTimeout(() => {
                    resumeCamera();
                }, 100);
            });

            // ヒントボタン（非表示化のためコメントアウト）
            /*
            const hintButton = document.getElementById('hint-button');
            if (hintButton) {
                hintButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    window.open('{{ asset("/cg/stampRallyHints.pdf") }}', '_blank');
                });
            }
            */

            // 操作説明ボタン（ヘルプ）
            const guideButton = document.getElementById('guide-button');
            if (guideButton) {
                guideButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const modal = document.getElementById('guide-modal');
                    modal.style.display = 'block';
                    modal.setAttribute('aria-hidden', 'false');
                    // prevent scene taps while modal open
                });
            }

            const closeGuideButton = document.getElementById('close-guide');
            if (closeGuideButton) {
                closeGuideButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const modal = document.getElementById('guide-modal');
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                    
                    // カメラを再開（フリーズ防止）
                    setTimeout(() => {
                        resumeCamera();
                    }, 100);
                });
            }

            // 右上の×ボタン
            const closeGuideTopButton = document.getElementById('close-guide-top');
            if (closeGuideTopButton) {
                closeGuideTopButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const modal = document.getElementById('guide-modal');
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                    
                    // カメラを再開（フリーズ防止）
                    setTimeout(() => {
                        resumeCamera();
                    }, 100);
                });
            }

            // クリック（背景領域）でモーダルを閉じる
            const guideModal = document.getElementById('guide-modal');
            if (guideModal) {
                guideModal.addEventListener('click', function(e) {
                    if (e.target === guideModal) {
                        guideModal.style.display = 'none';
                        guideModal.setAttribute('aria-hidden', 'true');
                        
                        // カメラを再開（フリーズ防止）
                        setTimeout(() => {
                            resumeCamera();
                        }, 100);
                    }
                });
            }
            
            // ========== 景品交換機能 ==========
            
            // 景品交換状態をチェック
            async function checkPrizeExchangeStatus() {
                // CSRFトークンを取得
                const csrfToken = document.querySelector('meta[name="csrf-token"]');
                if (!csrfToken) {
                    console.error('CSRF token not found');
                    return { hasExchanged: false };
                }
                
                try {
                    const response = await fetch('{{ url("/api/check-prize-exchange") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken.content
                        },
                        body: JSON.stringify({
                            fingerprint: await generateFingerprint()
                        })
                    });
                    
                    const data = await response.json();
                    return data;
                } catch (error) {
                    console.error('Error checking prize exchange:', error);
                    return { hasExchanged: false };
                }
            }
            
            // 景品交換を実行
            async function exchangePrize() {
                const collectedStamps = getCollectedStamps();
                
                // 10匹以上集めているかチェック（10匹以上で景品交換可能）
                const collectedCount = Object.keys(collectedStamps).length;
                if (collectedCount < 10) {
                    alert('隠れている動物を10匹以上捕まえると、景品と交換できるよ！');
                    return;
                }
                
                const deviceInfo = collectDeviceInfo();
                const fingerprint = await generateFingerprint();
                
                // サーバーステータスをチェック
                const serverStatus = await checkPrizeExchangeStatus();
                
                // すでに使用済み（管理者が承認済み）の場合
                if (serverStatus.isRedeemed) {
                    // 使用済みの景品コードと日時を表示
                    showRedeemedPrizeInfo(serverStatus.prizeCode, serverStatus.exchangedAt);
                    return;
                }
                
                // 交換済みだがまだ未使用の場合、コードを再表示
                if (serverStatus.hasExchanged && serverStatus.prizeCode) {
                    showPrizeCode(serverStatus.prizeCode);
                    return;
                }
                
                // CSRFトークンを取得
                const csrfToken = document.querySelector('meta[name="csrf-token"]');
                if (!csrfToken) {
                    console.error('CSRF token not found');
                    alert('エラー：ページをリロードしてください');
                    return;
                }
                
                // 新規景品交換
                try {
                    const response = await fetch('{{ url("/api/exchange-prize") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken.content
                        },
                        body: JSON.stringify({
                            fingerprint: fingerprint,
                            deviceInfo: deviceInfo,
                            stamps: collectedStamps
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // LocalStorageに交換済みフラグを保存
                        localStorage.setItem('ar-prize-exchanged-202603', 'true');
                        localStorage.setItem('ar-prize-code-202603', data.prizeCode);
                        
                        // ボタンを更新
                        updatePrizeButton();
                        
                        // 景品コードを表示
                        showPrizeCode(data.prizeCode);
                    } else {
                        alert(data.message || '景品交換に失敗しました');
                    }
                } catch (error) {
                    console.error('Error exchanging prize:', error);
                    alert('通信エラーが発生しました');
                }
            }
            
            // 景品コードを表示
            function showPrizeCode(code) {
                const modal = document.createElement('div');
                modal.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.9);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 10005;
                `;
                
                modal.innerHTML = `
                    <div style="background: white; padding: 30px; border-radius: 15px; text-align: center; max-width: 90%;">
                        <h2 style="color: #4CAF50; margin: 0 0 20px 0;">🎉 景品交換完了！ 🎉</h2>
                        <p style="font-size: 16px; margin-bottom: 20px;">以下のコードを受付でお見せください</p>
                        <div style="background: #f5f5f5; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                            <div style="font-size: 32px; font-weight: bold; color: #333; letter-spacing: 3px;">${code}</div>
                        </div>
                        <button onclick="this.parentElement.parentElement.remove()" style="padding: 12px 30px; background: #4CAF50; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer;">閉じる</button>
                    </div>
                `;
                
                document.body.appendChild(modal);
            }
            
            // 使用済み景品情報を表示
            function showRedeemedPrizeInfo(code, exchangedAt) {
                const modal = document.createElement('div');
                modal.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.9);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 10005;
                `;
                
                // 日時をフォーマット
                let dateTimeStr = '';
                if (exchangedAt) {
                    const date = new Date(exchangedAt);
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    const hours = String(date.getHours()).padStart(2, '0');
                    const minutes = String(date.getMinutes()).padStart(2, '0');
                    dateTimeStr = `${year}年${month}月${day}日 ${hours}:${minutes}`;
                }
                
                modal.innerHTML = `
                    <div style="background: white; padding: 30px; border-radius: 15px; text-align: center; max-width: 90%;">
                        <h2 style="color: #999; margin: 0 0 20px 0;">✅ すでに景品と交換済みです</h2>
                        <div style="background: #f5f5f5; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                            <div style="font-size: 14px; color: #666; margin-bottom: 10px;">景品コード</div>
                            <div style="font-size: 28px; font-weight: bold; color: #333; letter-spacing: 3px; margin-bottom: 15px;">${code}</div>
                            ${dateTimeStr ? `<div style="font-size: 14px; color: #666; border-top: 1px solid #ddd; padding-top: 10px;">交換日時: ${dateTimeStr}</div>` : ''}
                        </div>
                        <button onclick="this.parentElement.parentElement.remove()" style="padding: 12px 30px; background: #999; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer;">閉じる</button>
                    </div>
                `;
                
                document.body.appendChild(modal);
            }
            
            // 景品交換ボタンの状態を更新
            async function updatePrizeButton() {
                const button = document.getElementById('exchange-prize-button');
                if (!button) return;
                
                const collectedStamps = getCollectedStamps();
                const collectedCount = Object.keys(collectedStamps).length;
                const hasEnoughStamps = collectedCount >= 10; // 10匹以上で景品交換可能
                
                // サーバーステータスをチェック
                const serverStatus = await checkPrizeExchangeStatus();
                
                // 使用済み（管理者が承認済み）の場合
                if (serverStatus.isRedeemed) {
                    button.disabled = true;
                    button.style.backgroundColor = '#999';
                    button.textContent = '使用済み';
                    return;
                }
                
                // 交換済みだが未使用の場合（コードを再表示可能）
                if (serverStatus.hasExchanged && serverStatus.prizeCode) {
                    button.disabled = false;
                    button.style.backgroundColor = '#4CAF50';
                    button.textContent = '景品コードを表示';
                    return;
                }
                
                // 10匹未満の場合
                if (!hasEnoughStamps) {
                    button.disabled = true;
                    button.style.backgroundColor = '#ccc';
                    button.textContent = `10匹以上で交換可能 (${collectedCount}/10)`;
                } else {
                    // 新規交換可能
                    button.disabled = false;
                    button.style.backgroundColor = '#4CAF50';
                    button.textContent = '景品と交換する';
                }
            }
            
            // 景品交換ボタンのイベントリスナー
            const exchangePrizeButton = document.getElementById('exchange-prize-button');
            if (exchangePrizeButton) {
                exchangePrizeButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    exchangePrize();
                });
            }
            
            // ========== 景品交換機能ここまで ==========
            
            // スタンプリセットボタン
            const clearStampsButton = document.getElementById('clear-stamps');
            clearStampsButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // カスタム確認ダイアログを表示
                showConfirmDialog();
            }, false);
            
            // カスタム確認ダイアログ
            function showConfirmDialog() {
                const overlay = document.getElementById('confirm-overlay');
                const dialog = document.getElementById('confirm-dialog');
                
                overlay.style.display = 'block';
                dialog.style.display = 'block';
            }
            
            function hideConfirmDialog() {
                const overlay = document.getElementById('confirm-overlay');
                const dialog = document.getElementById('confirm-dialog');
                
                overlay.style.display = 'none';
                dialog.style.display = 'none';
            }
            
            // 確認ダイアログの「リセット」ボタン
            document.querySelector('#confirm-dialog .confirm-yes').addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Clear stamps confirmed');
                
                // ダイアログを閉じる
                hideConfirmDialog();
                
                // スタンプ帳モーダルを閉じる
                const modal = document.getElementById('stamp-book-modal');
                modal.style.display = 'none';
                
                // LocalStorageをクリア（スタンプ + 捕獲状態）
                localStorage.removeItem('ar-stamp-rally-202603');
                localStorage.removeItem('ar-captured-animals-202603');
                
                // 全てのモデルの状態をリセット
                const modelIds = ['sheep-model', 'fox-model', 'pengin-model', 'tonakai-model', 'pig-model', 'tora-model', 'gollira-model', 't-rex-model', 'whiteDuck-model', 'burger-model', 'hamstar-model', 'araiguma-model', 'wolf-model', 'namakemono-model', 'duck-model', 'cat-model', 'bear-model', 'harinezumi-model', 'whiteTiger-model', 'santa-model'];
                modelIds.forEach(modelId => {
                    const model = document.getElementById(modelId);
                    if (model && model.resetCaptureState) {
                        model.resetCaptureState();
                        console.log('Model state reset:', modelId);
                    }
                });
                
                // 捕獲済みメッセージを非表示
                hideCapturedMessage();
                
                // バッジを更新
                updateStampBadge();
                
                console.log('✓ All stamps and capture states cleared successfully');
            }, false);
            
            // 確認ダイアログの「キャンセル」ボタン
            document.querySelector('#confirm-dialog .confirm-no').addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Clear stamps cancelled');
                hideConfirmDialog();
            }, false);
            
            // 回転ボタンのイベントリスナー (一時的に無効化 - 復帰するには下のコメントを外してください)
            /*
            const rotateUpButton = document.getElementById('rotate-up');
            const rotateDownButton = document.getElementById('rotate-down');

            rotateUpButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (activeModel) {
                    // 上に30度回転（X軸を-30度）
                    currentRotationX -= 30;
                    activeModel.setAttribute('rotation', {
                        x: currentRotationX,
                        y: currentRotationY,
                        z: 0
                    });
                    console.log('Rotated up. Current X rotation:', currentRotationX);
                }
            });

            rotateDownButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (activeModel) {
                    // 下に30度回転（X軸を+30度）
                    currentRotationX += 30;
                    activeModel.setAttribute('rotation', {
                        x: currentRotationX,
                        y: currentRotationY,
                        z: 0
                    });
                    console.log('Rotated down. Current X rotation:', currentRotationX);
                }
            });
            */
            
            // オーバーレイクリックでダイアログを閉じる
            document.getElementById('confirm-overlay').addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                hideConfirmDialog();
            }, false);
            
            // モーダル背景クリックで閉じる
            const stampBookModal = document.getElementById('stamp-book-modal');
            stampBookModal.addEventListener('click', function(e) {
                // モーダルの背景部分のみクリック時に閉じる（コンテンツ部分は除外）
                if (e.target === stampBookModal) {
                    e.preventDefault();
                    e.stopPropagation();
                    stampBookModal.style.display = 'none';
                    
                    // アクティブモデルを安全にリセット
                    if (typeof activeModel !== 'undefined' && activeModel) {
                        console.log('Resetting active model via helper:', activeModel.id);
                        resetActiveModel(activeModel);
                        activeModel = null;
                    }
                    
                    // カメラを再開（フリーズ防止）
                    setTimeout(() => {
                        resumeCamera();
                    }, 100);
                }
            });
            
            // モーダルコンテンツのクリックイベントが背景に伝播しないようにする
            const stampBookContent = document.getElementById('stamp-book-content');
            stampBookContent.addEventListener('click', function(e) {
                e.stopPropagation();
            });
            
            // 初期化：バッジを更新
            updateStampBadge();
            
            // カメラ切り替え機能
            switchCameraButton.addEventListener('click', async function(e) {
                e.stopPropagation();
                console.log('Switching camera...');
                
                try {
                    // 現在のビデオストリームを停止
                    const video = document.querySelector('video');
                    if (video && video.srcObject) {
                        const tracks = video.srcObject.getTracks();
                        tracks.forEach(track => track.stop());
                    }
                    
                    // カメラの向きを切り替え
                    currentFacingMode = currentFacingMode === 'environment' ? 'user' : 'environment';
                    console.log('New facing mode:', currentFacingMode);
                    
                    // 新しいカメラストリームを取得
                    const constraints = {
                        video: {
                            facingMode: currentFacingMode,
                            width: { ideal: 1280 },
                            height: { ideal: 960 }
                        }
                    };
                    
                    const stream = await navigator.mediaDevices.getUserMedia(constraints);
                    
                    // ビデオ要素に新しいストリームを設定
                    if (video) {
                        video.srcObject = stream;
                        await video.play();
                        console.log('Camera switched successfully to:', currentFacingMode);
                    }
                    
                    // AR.jsを再初期化（必要に応じて）
                    if (scene.systems['arjs']) {
                        const arjsSystem = scene.systems['arjs'];
                        if (arjsSystem.onVideoCanPlay) {
                            arjsSystem.onVideoCanPlay();
                        }
                    }
                    
                } catch (error) {
                    console.error('Error switching camera:', error);
                    alert('カメラの切り替えに失敗しました。\n' + error.message);
                }
            });
            
            // 動画撮影機能
            videoButton.addEventListener('click', function(e) {
                e.stopPropagation();
                
                if (!isRecording) {
                    startRecording();
                } else {
                    stopRecording();
                }
            });
            
            function startRecording() {
                try {
                    const scene = document.querySelector('a-scene');
                    const arCanvas = scene.canvas;
                    const video = document.querySelector('video');
                    
                    if (!arCanvas || !video) {
                        console.error('Canvas or video not found');
                        alert('動画撮影の準備ができていません');
                        return;
                    }
                    
                    // 合成用の新しいキャンバスを作成
                    const compositeCanvas = document.createElement('canvas');
                    const screenWidth = window.innerWidth;
                    const screenHeight = window.innerHeight;
                    // iPhoneでのパフォーマンス向上のため、解像度を調整
                    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
                    const dpr = isIOS ? Math.min(window.devicePixelRatio || 1, 2) : (window.devicePixelRatio || 1);
                    
                    compositeCanvas.width = screenWidth * dpr;
                    compositeCanvas.height = screenHeight * dpr;
                    const ctx = compositeCanvas.getContext('2d', { 
                        alpha: false,
                        desynchronized: true // パフォーマンス向上
                    });
                    
                    // 合成処理を定期的に実行
                    let lastFrameTime = 0;
                    const targetFPS = isIOS ? 24 : 30; // iOSでは24fpsに制限
                    const frameInterval = 1000 / targetFPS;
                    
                    function compositeFrame(timestamp) {
                        if (!isRecording) return;
                        
                        // フレームレート制御
                        if (timestamp - lastFrameTime < frameInterval) {
                            requestAnimationFrame(compositeFrame);
                            return;
                        }
                        lastFrameTime = timestamp;
                        
                        ctx.clearRect(0, 0, compositeCanvas.width, compositeCanvas.height);
                        ctx.save();
                        ctx.scale(dpr, dpr);
                        
                        // 1. 背景（カメラ映像）を描画
                        const videoAspect = video.videoWidth / video.videoHeight;
                        const screenAspect = screenWidth / screenHeight;
                        
                        let drawWidth, drawHeight, offsetX, offsetY;
                        
                        if (videoAspect > screenAspect) {
                            drawHeight = screenHeight;
                            drawWidth = drawHeight * videoAspect;
                            offsetX = (screenWidth - drawWidth) / 2;
                            offsetY = 0;
                        } else {
                            drawWidth = screenWidth;
                            drawHeight = drawWidth / videoAspect;
                            offsetX = 0;
                            offsetY = (screenHeight - drawHeight) / 2;
                        }
                        
                        ctx.drawImage(video, offsetX, offsetY, drawWidth, drawHeight);
                        
                        // 2. ARコンテンツを重ねる
                        const arAspect = arCanvas.width / arCanvas.height;
                        const targetAspect = screenWidth / screenHeight;
                        
                        let arDrawWidth, arDrawHeight, arOffsetX, arOffsetY;
                        
                        if (arAspect > targetAspect) {
                            arDrawHeight = screenHeight;
                            arDrawWidth = arDrawHeight * arAspect;
                            arOffsetX = (screenWidth - arDrawWidth) / 2;
                            arOffsetY = 0;
                        } else {
                            arDrawWidth = screenWidth;
                            arDrawHeight = arDrawWidth / arAspect;
                            arOffsetX = 0;
                            arOffsetY = (screenHeight - arDrawHeight) / 2;
                        }
                        
                        ctx.drawImage(arCanvas, arOffsetX, arOffsetY, arDrawWidth, arDrawHeight);
                        ctx.restore();
                        
                        requestAnimationFrame(compositeFrame);
                    }
                    
                    // ストリームを取得（フレームレートを調整）
                    const stream = compositeCanvas.captureStream(targetFPS);
                    
                    // MediaRecorderの設定（iPhoneでも再生可能な形式を優先）
                    let options = {};
                    
                    // ビットレートをデバイスに応じて調整
                    const bitrate = isIOS ? 3000000 : 5000000; // iOSは3Mbps、その他は5Mbps
                    
                    // iOSではMP4をサポート、AndroidではWebMをサポート
                    if (MediaRecorder.isTypeSupported('video/mp4')) {
                        options = {
                            mimeType: 'video/mp4',
                            videoBitsPerSecond: bitrate
                        };
                    } else if (MediaRecorder.isTypeSupported('video/webm;codecs=vp9')) {
                        options = {
                            mimeType: 'video/webm;codecs=vp9',
                            videoBitsPerSecond: bitrate
                        };
                    } else if (MediaRecorder.isTypeSupported('video/webm;codecs=vp8')) {
                        options = {
                            mimeType: 'video/webm;codecs=vp8',
                            videoBitsPerSecond: bitrate
                        };
                    } else if (MediaRecorder.isTypeSupported('video/webm')) {
                        options = {
                            mimeType: 'video/webm',
                            videoBitsPerSecond: bitrate
                        };
                    } else {
                        // デフォルト（ブラウザが自動選択）
                        options = {
                            videoBitsPerSecond: bitrate
                        };
                    }
                    
                    recordedChunks = [];
                    mediaRecorder = new MediaRecorder(stream, options);
                    
                    mediaRecorder.ondataavailable = function(event) {
                        if (event.data.size > 0) {
                            recordedChunks.push(event.data);
                        }
                    };
                    
                    mediaRecorder.onstop = function() {
                        const mimeType = mediaRecorder.mimeType || 'video/webm';
                        const blob = new Blob(recordedChunks, { type: mimeType });
                        console.log('Recording stopped, blob size:', blob.size, 'type:', mimeType);
                        console.log('FPS:', targetFPS, 'Bitrate:', bitrate, 'DPR:', dpr);
                        
                        if (blob.size === 0) {
                            console.error('❌ Recorded blob is empty!');
                            alert('動画の録画に失敗しました。データがありません。');
                            return;
                        }
                        
                        // ファイル拡張子を決定
                        let extension = 'webm';
                        if (mimeType.includes('mp4')) {
                            extension = 'mp4';
                        }
                        
                        console.log('Creating video preview...');
                        
                        // プレビューに動画を表示
                        const videoElement = document.createElement('video');
                        videoElement.src = URL.createObjectURL(blob);
                        videoElement.controls = true;
                        videoElement.playsinline = true; // iOSで重要
                        videoElement.style.maxWidth = '100%';
                        videoElement.style.maxHeight = '70vh';
                        videoElement.style.borderRadius = '5px';
                        
                        // 動画読み込みエラーハンドリング
                        videoElement.onerror = function(e) {
                            console.error('❌ Video element error:', e);
                            alert('動画プレビューの表示に失敗しました');
                        };
                        
                        videoElement.onloadedmetadata = function() {
                            console.log('✓ Video metadata loaded, duration:', videoElement.duration);
                        };
                        
                        // プレビュー画像を動画要素に置き換え
                        const previewContainer = document.getElementById('photo-preview');
                        const existingPreview = document.querySelector('#photo-preview img, #photo-preview video');
                        
                        if (!existingPreview) {
                            console.error('❌ Preview element not found!');
                            return;
                        }
                        
                        existingPreview.replaceWith(videoElement);
                        console.log('✓ Video element replaced in preview');
                        
                        // ダウンロードボタンの動作を変更（拡張子も保存）
                        capturedImageData = {
                            blob: blob,
                            extension: extension,
                            mimeType: mimeType
                        };
                        
                        photoPreview.style.display = 'flex';
                        console.log('✓ Preview displayed');
                        
                        recordedChunks = [];
                    };
                    
                    // 録画開始
                    mediaRecorder.start();
                    isRecording = true;
                    recordingStartTime = Date.now();
                    videoButton.classList.add('recording');
                    videoButton.textContent = '⏹️';
                    requestAnimationFrame(compositeFrame);
                    
                    console.log('Recording started with mimeType:', options.mimeType);
                    console.log('Target FPS:', targetFPS, 'Bitrate:', bitrate / 1000000 + 'Mbps');
                    
                } catch (error) {
                    console.error('Error starting recording:', error);
                    alert('動画撮影の開始に失敗しました\n' + error.message);
                }
            }
            
            function stopRecording() {
                if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                    mediaRecorder.stop();
                    isRecording = false;
                    videoButton.classList.remove('recording');
                    videoButton.textContent = '🎥';
                    
                    const duration = Math.round((Date.now() - recordingStartTime) / 1000);
                    console.log('Recording duration:', duration, 'seconds');
                }
            }
            
            // 写真撮影機能
            cameraButton.addEventListener('click', function(e) {
                e.stopPropagation();
                console.log('Taking photo...');
                
                // フラッシュエフェクト
                flash.classList.add('active');
                setTimeout(() => {
                    flash.classList.remove('active');
                }, 200);
                
                // レンダリングサイクルに合わせて撮影
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        try {
                            // ビデオ要素とcanvasを取得
                            const video = document.querySelector('video');
                            const arCanvas = scene.canvas;
                            
                            if (!video) {
                                console.error('Video element not found');
                                return;
                            }
                            
                            if (!arCanvas) {
                                console.error('AR Canvas not found');
                                return;
                            }
                            
                            // 実際の画面サイズを取得
                            const screenWidth = window.innerWidth;
                            const screenHeight = window.innerHeight;
                            
                            // デバイスピクセル比を考慮
                            const dpr = window.devicePixelRatio || 1;
                            
                            console.log('Screen:', screenWidth, 'x', screenHeight);
                            console.log('DPR:', dpr);
                            console.log('Video:', video.videoWidth, 'x', video.videoHeight);
                            console.log('Canvas:', arCanvas.width, 'x', arCanvas.height);
                            
                            // 撮影用の新しいキャンバスを作成（画面サイズに合わせる）
                            const outputCanvas = document.createElement('canvas');
                            outputCanvas.width = screenWidth * dpr;
                            outputCanvas.height = screenHeight * dpr;
                            const ctx = outputCanvas.getContext('2d');
                            
                            // スケーリングを設定
                            ctx.scale(dpr, dpr);
                            
                            // 1. 背景（カメラ映像）を描画
                            ctx.save();
                            
                            // ビデオのアスペクト比を計算
                            const videoAspect = video.videoWidth / video.videoHeight;
                            const screenAspect = screenWidth / screenHeight;
                            
                            let drawWidth, drawHeight, offsetX, offsetY;
                            
                            if (videoAspect > screenAspect) {
                                // ビデオが横長：高さを画面に合わせる
                                drawHeight = screenHeight;
                                drawWidth = drawHeight * videoAspect;
                                offsetX = (screenWidth - drawWidth) / 2;
                                offsetY = 0;
                            } else {
                                // ビデオが縦長：幅を画面に合わせる
                                drawWidth = screenWidth;
                                drawHeight = drawWidth / videoAspect;
                                offsetX = 0;
                                offsetY = (screenHeight - drawHeight) / 2;
                            }
                            
                            ctx.drawImage(video, offsetX, offsetY, drawWidth, drawHeight);
                            ctx.restore();
                            console.log('Background drawn');
                            
                            // 2. ARコンテンツ（3Dモデル）を重ねる
                            ctx.save();
                            ctx.globalCompositeOperation = 'source-over';
                            
                            // ARキャンバスのアスペクト比を計算
                            const arAspect = arCanvas.width / arCanvas.height;
                            const targetAspect = screenWidth / screenHeight;
                            
                            let arDrawWidth, arDrawHeight, arOffsetX, arOffsetY;
                            
                            if (arAspect > targetAspect) {
                                // ARキャンバスが横長：高さを画面に合わせる
                                arDrawHeight = screenHeight;
                                arDrawWidth = arDrawHeight * arAspect;
                                arOffsetX = (screenWidth - arDrawWidth) / 2;
                                arOffsetY = 0;
                            } else {
                                // ARキャンバスが縦長：幅を画面に合わせる
                                arDrawWidth = screenWidth;
                                arDrawHeight = arDrawWidth / arAspect;
                                arOffsetX = 0;
                                arOffsetY = (screenHeight - arDrawHeight) / 2;
                            }
                            
                            ctx.drawImage(arCanvas, arOffsetX, arOffsetY, arDrawWidth, arDrawHeight);
                            ctx.restore();
                            console.log('AR content drawn with correct aspect ratio');
                            console.log('AR draw size:', arDrawWidth, 'x', arDrawHeight, 'at', arOffsetX, arOffsetY);
                            
                            // 画像データを取得
                            capturedImageData = outputCanvas.toDataURL('image/jpeg', 0.92);
                            
                            if (capturedImageData && capturedImageData.length > 1000) {
                                console.log('✓ Photo captured! Size:', Math.round(capturedImageData.length / 1024), 'KB');
                                console.log('Output size:', outputCanvas.width, 'x', outputCanvas.height);
                                
                                previewImage.src = capturedImageData;
                                
                                // 画像読み込みエラーハンドリング
                                previewImage.onerror = function() {
                                    console.error('❌ Failed to load preview image');
                                    alert('写真プレビューの表示に失敗しました');
                                };
                                
                                previewImage.onload = function() {
                                    console.log('✓ Preview image loaded successfully');
                                    photoPreview.style.display = 'flex';
                                };
                                
                            } else {
                                console.error('❌ Image data too small, capture failed');
                                console.error('Data length:', capturedImageData ? capturedImageData.length : 'null');
                                alert('写真の撮影に失敗しました');
                            }
                            
                        } catch (error) {
                            console.error('Error capturing photo:', error);
                        }
                    });
                });
            });
            
            // ダウンロードボタン
            downloadButton.addEventListener('click', async function() {
                if (!capturedImageData) {
                    console.error('No data to download');
                    return;
                }
                
                try {
                    let blob;
                    let filename;
                    let mimeType;
                    
                    // 動画オブジェクトの場合
                    if (capturedImageData.blob) {
                        blob = capturedImageData.blob;
                        filename = 'AR_video_' + new Date().getTime() + '.' + capturedImageData.extension;
                        mimeType = capturedImageData.mimeType;
                    } 
                    // Blobオブジェクトの場合（古い形式、互換性のため残す）
                    else if (capturedImageData instanceof Blob) {
                        blob = capturedImageData;
                        filename = 'AR_video_' + new Date().getTime() + '.webm';
                        mimeType = 'video/webm';
                    } 
                    // Data URLの場合（写真）
                    else {
                        const response = await fetch(capturedImageData);
                        blob = await response.blob();
                        filename = 'AR_photo_' + new Date().getTime() + '.jpg';
                        mimeType = 'image/jpeg';
                    }
                    
                    console.log('Saving file:', filename, 'type:', mimeType, 'size:', blob.size);
                    
                    // iOSやAndroidでWeb Share APIが使える場合
                    if (navigator.share && navigator.canShare) {
                        const file = new File([blob], filename, { type: mimeType });
                        
                        if (navigator.canShare({ files: [file] })) {
                            try {
                                await navigator.share({
                                    files: [file],
                                    title: mimeType.startsWith('video') ? 'AR動画' : 'AR写真',
                                    text: mimeType.startsWith('video') ? 'ARで撮影した動画' : 'ARで撮影した写真'
                                });
                                console.log('File shared successfully');
                                return;
                            } catch (shareError) {
                                console.log('Share cancelled or failed:', shareError);
                            }
                        }
                    }
                    
                    // Web Share APIが使えない場合：従来のダウンロード方式
                    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                    
                    if (isIOS && mimeType.startsWith('image')) {
                        // iOSの場合：画像を長押しで保存を促す
                        alert('画像を長押しして「写真に追加」を選択してください');
                    } else {
                        // その他のデバイス：通常のダウンロード
                        const link = document.createElement('a');
                        link.download = filename;
                        link.href = URL.createObjectURL(blob);
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        URL.revokeObjectURL(link.href);
                        console.log('File downloaded:', filename);
                    }
                } catch (error) {
                    console.error('Error downloading file:', error);
                    alert('保存に失敗しました');
                }
            });
            
            // 閉じるボタン
            closeButton.addEventListener('click', function() {
                console.log('Closing preview');
                photoPreview.style.display = 'none';
                
                // 動画要素を画像要素に戻す
                const videoElement = document.querySelector('#photo-preview video');
                if (videoElement) {
                    console.log('Replacing video with image element');
                    // Blob URLを解放
                    if (videoElement.src && videoElement.src.startsWith('blob:')) {
                        URL.revokeObjectURL(videoElement.src);
                    }
                    
                    const imgElement = document.createElement('img');
                    imgElement.id = 'preview-image';
                    imgElement.src = '';
                    imgElement.alt = '撮影した写真';
                    imgElement.style.maxWidth = '100%';
                    imgElement.style.maxHeight = '70vh';
                    imgElement.style.borderRadius = '5px';
                    videoElement.replaceWith(imgElement);
                }
                
                capturedImageData = null;
                console.log('Preview closed and reset');
            });
            
            // Double-tap behavior removed - no global touch handlers required.
            
            // ========== 新しいポケボール操作ロジック ==========
            (function() {
                let isHoldingBall = false;
                let touchStartX = 0;
                let touchStartY = 0;
                let ballEntity = document.querySelector('#holding-pokeball');
                let canThrow = true;
                
                // 画面下部中央のエリア定義（ボールがあるあたり）
                function isBallArea(x, y) {
                    const w = window.innerWidth;
                    const h = window.innerHeight;
                    // 下部30%、横幅40% (中央)
                    return y > h * 0.7 && x > w * 0.3 && x < w * 0.7;
                }
                
                // タッチ開始
                document.addEventListener('touchstart', (e) => {
                    if (!canThrow || !ballEntity) return;
                    
                    // UIボタン上のタッチは無視
                    const touch = e.touches[0];
                    const element = document.elementFromPoint(touch.clientX, touch.clientY);
                    if (isUIButton(element)) return;
                    
                    if (isBallArea(touch.clientX, touch.clientY)) {
                        isHoldingBall = true;
                        touchStartX = touch.clientX;
                        touchStartY = touch.clientY;
                        
                        // ボールを持ち上げる演出
                        ballEntity.setAttribute('position', '0 -0.23 -0.5');
                        
                        // デフォルトのスクロール等を防止
                        if (e.cancelable) e.preventDefault();
                    }
                }, { passive: false });
                
                // タッチ移動（スワイプ）
                document.addEventListener('touchmove', (e) => {
                    if (!isHoldingBall) return;
                    if (e.cancelable) e.preventDefault();
                    
                    // 指に合わせて少し動かす演出（オプション）
                    // const touch = e.touches[0];
                    // const dx = (touch.clientX - touchStartX) * 0.001;
                    // const dy = (touch.clientY - touchStartY) * 0.001;
                    // ballEntity.setAttribute('position', `${dx} ${-0.2 - dy} -0.5`);
                }, { passive: false });
                
                // タッチ終了（投げる）
                document.addEventListener('touchend', (e) => {
                    if (!isHoldingBall) return;
                    isHoldingBall = false;
                    
                    const touch = e.changedTouches[0];
                    const dx = touch.clientX - touchStartX;
                    const dy = touch.clientY - touchStartY;
                    const distance = Math.sqrt(dx*dx + dy*dy);
                    
                    // 投げる処理
                    throwBall(dx, dy, distance);
                    
                    // 手元のボールを隠す
                    ballEntity.setAttribute('visible', 'false');
                    // 位置を戻す
                    ballEntity.setAttribute('position', '0 -0.24 -0.5');
                    canThrow = false;
                });
                
                // PCでのデバッグ用（マウス操作）
                document.addEventListener('mousedown', (e) => {
                    if (!canThrow || !ballEntity) return;
                    const element = document.elementFromPoint(e.clientX, e.clientY);
                    if (isUIButton(element)) return;
                    
                    if (isBallArea(e.clientX, e.clientY)) {
                        isHoldingBall = true;
                        touchStartX = e.clientX;
                        touchStartY = e.clientY;
                        ballEntity.setAttribute('position', '0 -0.23 -0.5');
                    }
                });
                
                document.addEventListener('mouseup', (e) => {
                    if (!isHoldingBall) return;
                    isHoldingBall = false;
                    
                    const dx = e.clientX - touchStartX;
                    const dy = e.clientY - touchStartY;
                    const distance = Math.sqrt(dx*dx + dy*dy);
                    
                    throwBall(dx, dy, distance);
                    
                    ballEntity.setAttribute('visible', 'false');
                    ballEntity.setAttribute('position', '0 -0.24 -0.5');
                    canThrow = false;
                });
                
                function throwBall(dx, dy, distance) {
                    const scene = document.querySelector('a-scene');
                    const camera = scene.camera;
                    if (!camera) return;
                    
                    // 新しいボールを生成
                    const newBall = document.createElement('a-entity');
                    
                    // 手元のボールのワールド座標を取得して初期位置とする
                    const worldPos = new THREE.Vector3();
                    ballEntity.object3D.getWorldPosition(worldPos);
                    
                    newBall.setAttribute('position', worldPos);
                    newBall.setAttribute('gltf-model', '{{ asset("cg/poke_ball_05.glb") }}');
                    newBall.setAttribute('scale', '0.075 0.075 0.075'); // 投げるときは少し大きく
                    newBall.setAttribute('pokeball-throwable', '');
                    
                    // 投擲ベクトル計算
                    const direction = new THREE.Vector3(0, 0, -1); // カメラ前方
                    
                    // スワイプによる補正
                    // 画面幅に対する割合で計算
                    const factor = 0.002; 
                    direction.x += dx * factor;
                    // direction.y += -dy * factor; // 縦方向（Y）の角度変化は無効化（常に正面へ）
                    
                    // カメラの回転を適用
                    direction.applyQuaternion(camera.quaternion);
                    direction.normalize();
                    
                    // 速度決定（スワイプ時の最大速度を基本速度の2倍程度に制限）
                    let speed = 7.5; // 基本速度
                    if (distance > 50) {
                        // 加速分を追加するが、係数を調整
                        speed += distance * 0.025; 
                    }
                    // 最大速度を基本速度の約2倍（15.0）に制限
                    speed = Math.min(speed, 15.0);
                    
                    scene.appendChild(newBall);
                    
                    // コンポーネントが初期化されたら投げる
                    newBall.addEventListener('loaded', () => {
                        // マテリアル調整（既存コードと同様）
                        const model = newBall.getObject3D('mesh');
                        if (model) {
                            model.traverse(function(node) {
                                if (node.isMesh) {
                                    if (node.geometry) node.geometry.computeVertexNormals();
                                    if (node.material) {
                                        const materials = Array.isArray(node.material) ? node.material : [node.material];
                                        materials.forEach(mat => {
                                            mat.side = THREE.DoubleSide;
                                            mat.depthWrite = true;
                                            mat.depthTest = true;
                                            mat.flatShading = false;
                                            mat.transparent = false;
                                            mat.opacity = 1.0;
                                            mat.needsUpdate = true;
                                        });
                                    }
                                    node.frustumCulled = false;
                                }
                            });
                        }
                        
                        // 投げる処理を実行（当たり判定はコンポーネント内で行うため、ここでのsetIntervalは削除）
                        newBall.components['pokeball-throwable'].throw(direction, speed);
                    });
                    
                    // 削除イベント監視（寿命 or ヒットで消滅時）
                    newBall.addEventListener('pokeball-gone', () => {
                        console.log('Pokeball gone, reloading...');
                        setTimeout(() => {
                            if (ballEntity) {
                                ballEntity.setAttribute('visible', 'true');
                                canThrow = true;
                            }
                        }, 500); // 0.5秒後に再表示
                    });
                }
            })();
            // ========== 新しいポケボール操作ロジック ここまで ==========
            // Previously, a double-tap on the scene triggered click/animation toggles on the active model.
            // That behavior was intentionally removed per design — do not add new global touch handlers here.
            
            // PC用：マウス操作は削除（回転ボタンのみ使用）
            
            // (removed duplicate body-level wheel handler; scene-level wheel already handles zoom)
            
            // 画面全体のタップを検出（削除：ダブルタップに置き換え）
            // シングルタップでのアニメーション切り替えは無効化
            
            }); // end of DOMContentLoaded

            // ページ終了時のクリーンアップ（Android 7対策）
            window.addEventListener('pagehide', function() {
                console.log('Page hiding, cleaning up resources...');
                try {
                    // AR.jsのビデオ停止
                    const video = document.querySelector('video');
                    if (video && video.srcObject) {
                        const tracks = video.srcObject.getTracks();
                        tracks.forEach(track => track.stop());
                    }
                    
                    // シーンのレンダラー破棄
                    const scene = document.querySelector('a-scene');
                    if (scene && scene.renderer) {
                        scene.renderer.dispose();
                        scene.renderer.forceContextLoss();
                    }
                } catch (e) {
                    console.warn('Cleanup error:', e);
                }
            });
    </script>
</body>
</html>
