
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

                // アニメーション終了後にモデルのスクリーンショットを取り、モデルを非表示にする
                animationPromise.then(() => {
                    try {
                        if (typeof captureModelScreenshot === 'function') {
                            // ボールは既に非表示になっているのでモデルのみ撮影
                            captureModelScreenshot(function(screenshot) {
                                if (screenshot && typeof updateStampScreenshot === 'function') {
                                    updateStampScreenshot(stampId, screenshot);
                                }
                                // モデルを非表示（捕獲済み扱い）
                                try { hitModel.setAttribute('visible', 'false'); } catch (e) { /* ignore */ }
                                try { if (hitModel && typeof hitModel.setCapturedState === 'function') hitModel.setCapturedState(true); } catch (e) {}
                                // アイコン表示
                                try { if (typeof showCapturedMessage === 'function') showCapturedMessage(stampId); } catch (e) { console.warn('showCapturedMessage failed in handleHit capture callback', e); }
                            });
                        } else {
                            try { hitModel.setAttribute('visible', 'false'); } catch (e) { /* ignore */ }
                            try { if (hitModel && typeof hitModel.setCapturedState === 'function') hitModel.setCapturedState(true); } catch (e) {}
                            try { if (typeof showCapturedMessage === 'function') showCapturedMessage(stampId); } catch (e) {}
                        }
                    } catch (e) { console.warn('Post-animation handling failed', e); }
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
    