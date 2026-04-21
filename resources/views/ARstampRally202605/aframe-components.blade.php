<script>
    // ========== A-Frame カスタムコンポーネント定義 ==========

    // ---- ポケボール投擲コンポーネント ----
    if (!AFRAME.components['pokeball-throwable'])
    AFRAME.registerComponent('pokeball-throwable', {
        schema: {
            autoGetStampId: { type: 'string', default: '' },
            autoGetDelayMs: { type: 'number', default: 1000 }
        },

        init: function () {
            this.velocity = new THREE.Vector3();
            this.gravity = -4.5;
            this.isThrown = false;
            this.lifetime = 0;
            this.maxLifetime = 8;
            this.prevPosition = new THREE.Vector3();
            this.autoGetTimer = null;
            this.autoGetTriggered = false;
            window.activeBalls = (window.activeBalls || 0) + 1;
        },

        remove: function () {
            if (this.autoGetTimer) {
                clearTimeout(this.autoGetTimer);
                this.autoGetTimer = null;
            }
            window.activeBalls = Math.max(0, (window.activeBalls || 1) - 1);
        },

        throw: function (direction, speed) {
            this.velocity.copy(direction).multiplyScalar(speed);
            this.velocity.y += 2.5;
            this.isThrown = true;
            this.lifetime = 0;
            this.prevPosition.copy(this.el.object3D.position);

            if (this.autoGetTimer) {
                clearTimeout(this.autoGetTimer);
                this.autoGetTimer = null;
            }
            this.autoGetTriggered = false;

            if (this.data.autoGetStampId) {
                var self = this;
                var delay = Math.max(0, parseInt(this.data.autoGetDelayMs, 10) || 1000);
                this.autoGetTimer = setTimeout(function () {
                    self.autoGetTimer = null;
                    self.tryAutoGet();
                }, delay);
            }
        },

        tryAutoGet: function () {
            if (this.autoGetTriggered) return;
            this.autoGetTriggered = true;

            var stampId = this.data.autoGetStampId;
            if (!stampId) return;

            try {
                if (typeof collectAndMarkWithRetry === 'function') {
                    collectAndMarkWithRetry(stampId, null, 3, 2000);
                }

                var modelId = stampId.replace('model_', 'model-');
                var hitModel = document.getElementById(modelId);
                if (hitModel) {
                    try { hitModel.setAttribute('visible', 'false'); } catch (e) {}
                    try { if (typeof hitModel.setCapturedState === 'function') hitModel.setCapturedState(true); } catch (e) {}
                }
                try { if (typeof showCapturedMessage === 'function') showCapturedMessage(stampId); } catch (e) {}
            } catch (e) {}
        },

        tick: function (time, deltaTime) {
            if (!this.isThrown) return;
            const dt = (deltaTime || 16) / 1000;
            this.lifetime += dt;
            this.prevPosition.copy(this.el.object3D.position);

            this.velocity.y += this.gravity * dt;
            const pos = this.el.object3D.position;
            pos.x += this.velocity.x * dt;
            pos.y += this.velocity.y * dt;
            pos.z += this.velocity.z * dt;

            this.el.object3D.rotation.x += dt * 4;
            this.el.object3D.rotation.z += dt * 2.5;

            if (this.lifetime > this.maxLifetime || pos.y < -5) {
                this.el.emit('pokeball-gone');
                if (this.el.parentNode) this.el.parentNode.removeChild(this.el);
                return;
            }

            const currentPos = this.el.object3D.position;
            const direction = new THREE.Vector3().subVectors(currentPos, this.prevPosition);
            const distance = direction.length();
            if (distance < 0.001) return;

            direction.normalize();
            const ray = new THREE.Ray(this.prevPosition, direction);

            if (typeof window.allHitboxes !== 'undefined') {
                for (var i = 0; i < window.allHitboxes.length; i++) {
                    const hitbox = window.allHitboxes[i];
                    if (!hitbox.el.object3D.visible) continue;
                    if (hitbox.el.parentElement && hitbox.el.parentElement.object3D && !hitbox.el.parentElement.object3D.visible) continue;

                    var isHit = false;
                    const ballWorldPos = this.el.object3D.getWorldPosition(new THREE.Vector3());
                    if (hitbox.checkCollision(ballWorldPos)) {
                        isHit = true;
                    } else if (hitbox.checkIntersection) {
                        if (hitbox.checkIntersection(ray, distance)) {
                            isHit = true;
                        }
                    }

                    if (isHit) {
                        this.handleHit(hitbox);
                        break;
                    }
                }
            }
        },

        handleHit: function (hitbox) {
            if (this.autoGetTimer) {
                clearTimeout(this.autoGetTimer);
                this.autoGetTimer = null;
            }
            this.autoGetTriggered = true;

            const stampId = hitbox.data.stampId;
            const hitModel = hitbox.el;

            var animationPromise = Promise.resolve();
            if (hitModel && hitModel.playHitAnimation) {
                try {
                    animationPromise = hitModel.playHitAnimation();
                } catch (e) {
                    animationPromise = Promise.resolve();
                }
            } else {
                try {
                    if (typeof collectAndMarkWithRetry === 'function') collectAndMarkWithRetry(stampId, null, 3, 2000);
                    if (hitModel && typeof hitModel.setCapturedState === 'function') hitModel.setCapturedState(true);
                } catch (e) {}
            }

            if (typeof showHitEffect === 'function') showHitEffect(this.el, hitbox);

            this.velocity.multiplyScalar(-0.6);
            this.velocity.y += 3;

            try { destroyAndFreeEntity(this.el); } catch (e) {}

            animationPromise.then(function () {
                try {
                    try {
                        var saved = (typeof collectAndMarkWithRetry === 'function') ? collectAndMarkWithRetry(stampId, null, 3, 2000) : false;
                        try { if (typeof markAnimalCaptured === 'function') markAnimalCaptured(stampId); } catch (e) {}
                        try { hitModel.setAttribute('visible', 'false'); } catch (e) {}
                        try { if (hitModel && typeof hitModel.setCapturedState === 'function') hitModel.setCapturedState(true); } catch (e) {}
                        try { if (typeof showCapturedMessage === 'function') showCapturedMessage(stampId); } catch (e) {}
                    } catch (err) {
                        try { hitModel.setAttribute('visible', 'false'); } catch (e) {}
                        try { if (hitModel && typeof hitModel.setCapturedState === 'function') hitModel.setCapturedState(true); } catch (e) {}
                        try { if (typeof showCapturedMessage === 'function') showCapturedMessage(stampId); } catch (e) {}
                    }
                } catch (e) {}
            });
        }
    });

    // ---- 当たり判定ボックスコンポーネント ----
    if (!AFRAME.components['hitbox'])
    AFRAME.registerComponent('hitbox', {
        schema: {
            stampId: { type: 'string', default: '' },
            width: { type: 'number', default: 1 },
            height: { type: 'number', default: 1 },
            depth: { type: 'number', default: 1 },
            offset: { type: 'vec3', default: { x: 0, y: 0, z: 0 } },
            autoCenter: { type: 'boolean', default: true }
        },

        init: function () {
            if (typeof window.allHitboxes !== 'undefined' && !window.allHitboxes.includes(this)) {
                window.allHitboxes.push(this);
            }
            this.box = new THREE.Box3();
            this.updateBox();
        },

        remove: function () {
            if (typeof window.allHitboxes !== 'undefined') {
                const index = window.allHitboxes.indexOf(this);
                if (index > -1) window.allHitboxes.splice(index, 1);
            }
        },

        updateBox: function () {
            const data = this.data;
            const pos = this.el.object3D.getWorldPosition(new THREE.Vector3());
            const worldScale = new THREE.Vector3();
            this.el.object3D.getWorldScale(worldScale);

            const halfWidth = (data.width / 2) * (worldScale.x || 1);
            const halfHeight = (data.height / 2) * (worldScale.y || 1);
            const halfDepth = (data.depth / 2) * (worldScale.z || 1);

            const center = pos.clone();
            const scaledOffset = new THREE.Vector3(data.offset.x, data.offset.y, data.offset.z);
            if (data.autoCenter) scaledOffset.y += data.height / 2;
            scaledOffset.multiply(worldScale);
            center.add(scaledOffset);

            this.box.min.set(center.x - halfWidth, center.y - halfHeight, center.z - halfDepth);
            this.box.max.set(center.x + halfWidth, center.y + halfHeight, center.z + halfDepth);
        },

        tick: function () {
            if (!this.el.object3D.visible) return;
            if (this.el.parentElement && this.el.parentElement.object3D && !this.el.parentElement.object3D.visible) return;
            if (!window.activeBalls || window.activeBalls <= 0) return;
            this.updateBox();
        },

        checkCollision: function (point) {
            return this.box.containsPoint(point);
        },

        checkIntersection: function (ray, maxDistance) {
            if (!this.box) return false;
            const intersection = ray.intersectBox(this.box, new THREE.Vector3());
            if (intersection) {
                const dist = ray.origin.distanceTo(intersection);
                return dist <= maxDistance;
            }
            return false;
        }
    });

    // ---- 遅延読み込みコンポーネント ----
    if (!AFRAME.components['lazy-model'])
    AFRAME.registerComponent('lazy-model', {
        schema: {
            src: { type: 'string' },
            timeout: { type: 'number', default: 5000 }
        },

        init: function () {
            this.timer = null;
            this.isLoaded = false;

            this.el.sceneEl.addEventListener('markerFound', function (e) {
                if (e.target === this.el.parentElement) this.onMarkerFound();
            }.bind(this));

            this.el.sceneEl.addEventListener('markerLost', function (e) {
                if (e.target === this.el.parentElement) this.onMarkerLost();
            }.bind(this));
        },

        onMarkerFound: function () {
            if (this.timer) { clearTimeout(this.timer); this.timer = null; }
            if (!this.isLoaded) {
                this.el.setAttribute('gltf-model', this.data.src);
                this.isLoaded = true;
            }
        },

        onMarkerLost: function () {
            if (this.timer) clearTimeout(this.timer);
            this.timer = setTimeout(function () {
                this.el.removeAttribute('gltf-model');
                this.isLoaded = false;
                this.el.emit('model-unloaded');
                const mesh = this.el.getObject3D('mesh');
                if (mesh) {
                    mesh.traverse(function (node) {
                        if (node.isMesh) {
                            if (node.geometry) node.geometry.dispose();
                            if (node.material) {
                                if (Array.isArray(node.material)) { node.material.forEach(function (m) { m.dispose(); }); }
                                else { node.material.dispose(); }
                            }
                        }
                    });
                }
            }.bind(this), this.data.timeout);
        }
    });

    // ---- クリックアニメーションコンポーネント ----
    // anime01: アイドルループ, anime02: ヒット1回再生, anime03: ギャラリーループ（maker00用）
    if (!AFRAME.components['click-animation'])
    AFRAME.registerComponent('click-animation', {
        schema: {
            clip: { type: 'string', default: 'anime01' }
        },

        init: function () {
            const el = this.el;
            var mixer = null;
            var action01 = null;
            var action02 = null;
            var currentAnimation = 1;
            var markerVisible = false;
            var modelCaptured = false;

            const hitboxAttr = el.getAttribute('hitbox');
            const stampId = hitboxAttr ? hitboxAttr.split(':')[1].split(';')[0].trim() : '';

            el.addEventListener('model-loaded', function () {
                const model = el.getObject3D('mesh');
                if (model) {
                    model.traverse(function (node) {
                        if (node.isMesh) node.frustumCulled = false;
                    });
                }

                if (!model || !model.animations || model.animations.length === 0) return;

                mixer = new THREE.AnimationMixer(model);
                this.mixer = mixer;

                var clip01 = THREE.AnimationClip.findByName(model.animations, 'anime01') || model.animations[0];
                var clip02 = THREE.AnimationClip.findByName(model.animations, 'anime02')
                          || (model.animations.length > 1 ? model.animations[1] : model.animations[0]);

                action01 = mixer.clipAction(clip01);
                action01.setLoop(THREE.LoopRepeat, Infinity);
                action01.stop();

                action02 = mixer.clipAction(clip02);
                action02.setLoop(THREE.LoopOnce, 1);
                action02.clampWhenFinished = true;
                action02.stop();

                this.action01 = action01;
                this.action02 = action02;

                mixer.addEventListener('finished', function (e) {
                    if (e.action !== action02) return;
                    const persistedCaptured = (typeof isAnimalCaptured === 'function') ? isAnimalCaptured(stampId) : false;
                    if (modelCaptured || persistedCaptured) {
                        el.setAttribute('visible', 'false');
                    } else {
                        try {
                            if (action02) action02.stop();
                            if (action01) { action01.reset(); action01.play(); currentAnimation = 1; }
                            el.setAttribute('visible', 'true');
                        } catch (err) {}
                    }
                });

                if (markerVisible && !modelCaptured && action01) {
                    action01.reset();
                    action01.play();
                    currentAnimation = 1;
                }
            }.bind(this));

            const marker = el.parentElement;

            el.resetCaptureState = function () {
                modelCaptured = false;
                if (action01) { action01.reset(); action01.play(); currentAnimation = 1; }
            };

            el.setCapturedState = function (flag) {
                modelCaptured = !!flag;
                if (modelCaptured) {
                    try { el.setAttribute('visible', 'false'); } catch (e) {}
                    if (typeof hideCapturedMessage === 'function') hideCapturedMessage();
                } else {
                    try { el.setAttribute('visible', 'true'); } catch (e) {}
                }
            };

            marker.addEventListener('markerFound', function () {
                if (modelCaptured) {
                    el.setAttribute('visible', 'false');
                    // if (typeof showCapturedMessage === 'function') showCapturedMessage(stampId);
                    return;
                }
                const isCaptured = typeof isAnimalCaptured === 'function' && isAnimalCaptured(stampId);
                if (isCaptured) {
                    el.setAttribute('visible', 'false');
                    modelCaptured = true;
                    // if (typeof showCapturedMessage === 'function') showCapturedMessage(stampId);
                    return;
                }
                el.setAttribute('visible', 'true');
                markerVisible = true;
                if (action01) { action01.reset(); action01.play(); currentAnimation = 1; }
                try {
                    if (typeof recordMarkerDetection === 'function') {
                        const name = (STAMPS[stampId] && STAMPS[stampId].name) ? STAMPS[stampId].name : stampId;
                        recordMarkerDetection(stampId, name);
                    }
                } catch (e) {}
            });

            marker.addEventListener('markerLost', function () {
                markerVisible = false;
                if (typeof hideCapturedMessage === 'function') hideCapturedMessage();
                el.setAttribute('visible', 'false');
                if (action01) action01.stop();
                if (action02) action02.stop();
            });

            el.playHitAnimation = function () {
                return new Promise(function (resolve) {
                    if (action01) action01.stop();
                    if (action02) {
                        try {
                            action02.reset();
                            action02.setLoop(THREE.LoopOnce, 0);
                            action02.clampWhenFinished = true;
                            action02.play();
                            currentAnimation = 2;

                            var resolved = false;
                            var onFinished = function (ev) {
                                try {
                                    if (ev && ev.action === action02) {
                                        try { if (mixer) mixer.removeEventListener('finished', onFinished); } catch (e) {}
                                        if (!resolved) { resolved = true; resolve(); }
                                    }
                                } catch (err) {}
                            };
                            if (mixer) mixer.addEventListener('finished', onFinished);

                            var clipDuration = 1.0;
                            try {
                                if (action02._clip && action02._clip.duration) clipDuration = action02._clip.duration;
                            } catch (e) {}
                            setTimeout(function () {
                                if (!resolved) {
                                    resolved = true;
                                    try { if (mixer) mixer.removeEventListener('finished', onFinished); } catch (e) {}
                                    resolve();
                                }
                            }, (clipDuration * 1000) + 120);
                        } catch (e) { resolve(); }
                    } else {
                        resolve();
                    }

                    try {
                        var saved = (typeof collectAndMarkWithRetry === 'function') ? collectAndMarkWithRetry(stampId, null, 3, 2000) : false;
                        var stamps = typeof getCollectedStamps === 'function' ? getCollectedStamps() : {};
                        if (stamps && stamps[stampId]) {
                            modelCaptured = true;
                            try { if (saved && typeof showCapturedMessage === 'function') showCapturedMessage(stampId); } catch (e) {}
                        } else {
                            modelCaptured = false;
                        }
                    } catch (e) {
                        modelCaptured = false;
                        var retryCount = 0;
                        var retryInterval = setInterval(function () {
                            retryCount++;
                            try {
                                var r = (typeof collectStamp === 'function') ? collectStamp(stampId, null) : false;
                                if (r) {
                                    if (typeof markAnimalCaptured === 'function') markAnimalCaptured(stampId);
                                    modelCaptured = true;
                                    clearInterval(retryInterval);
                                }
                            } catch (er) {}
                            if (retryCount >= 3) clearInterval(retryInterval);
                        }, 2000);
                    }
                });
            };
        },

        tick: function (time, deltaTime) {
            if (!this.el.object3D.visible) return;
            if (this.el.parentElement && this.el.parentElement.object3D && !this.el.parentElement.object3D.visible) return;
            if (!this.mixer) return;
            const dt = Math.min(deltaTime / 1000, 0.1);
            this.mixer.update(dt);
        }
    });
</script>
