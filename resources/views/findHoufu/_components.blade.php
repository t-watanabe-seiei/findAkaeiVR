<script>
    // ═══ グローバル設定 ═══
    window.DEBUG_MODE = false;
    function debugLog(...a) { if (window.DEBUG_MODE) console.log('[findHoufu]', ...a); }

    window.STAGE_CONFIG = {
        1: { timeLimit: 10, skyId: 'sky01', bgmId: 'bgm_s1', isResult: false },
        2: { timeLimit: 12, skyId: 'sky02', bgmId: 'bgm_s1', isResult: false },
        3: { timeLimit: 12, skyId: 'sky03', bgmId: 'bgm_s2', isResult: false },
        4: { timeLimit: 12, skyId: 'sky04', bgmId: 'bgm_s2', isResult: false },
        5: { timeLimit: 12, skyId: 'sky05', bgmId: 'bgm_s3', isResult: false },
        6: { timeLimit: 12, skyId: 'sky06', bgmId: 'bgm_s3', isResult: false },
        7: { timeLimit: 12, skyId: 'sky01', bgmId: 'bgm_s4', isResult: true  },
    };

    window.LOCATIONS = [
        { pos: '-2 -0.6 1',       rot: '0 120 0',  scale: '1.4 1.4 1.4' },
        { pos: '10 -0.88 -1.9',   rot: '0 -90 0',  scale: '2.7 2.7 2.7' },
        { pos: '-1.325 1.0 4.00', rot: '0 150 0',  scale: '1.4 1.4 1.4' },
        { pos: '6.0 0 0.13',      rot: '0 -120 0', scale: '2.1 2.1 2.1' },
        { pos: '-0.5 0 -0.5',     rot: '0 0 0',    scale: '1 1 1' },
        { pos: '-4.5 0.9 4.6',    rot: '0 130 0',  scale: '1.9 1.9 1.9' },
    ];

    window.gameStarted   = false;
    window.gameEnded     = false;
    window.currentStage  = 1;
    window.totalScore    = 0;
    window.comboCount    = 0;
    window.maxComboCount = 0;
    window.hitCount      = 0;
    window.modelAppearTime = 0;
    window.modelActive   = false;
    window.gameTimer     = null;
    window.gameTimeLeft  = 0;
    window.activeBalls   = [];
    window.ballPool      = [];
    window.activeTimers  = [];
    window._cachedPos    = new THREE.Vector3();
    window._cachedDir    = new THREE.Vector3();
    window._cachedBallColor = new THREE.Color(0x444444);
    window.maxSimultaneousBalls = 2;
    window.ballPoolSize  = 4;

    // ═══ ユーティリティ関数 ═══
    function registerTimeout(cb, delay) {
        const id = setTimeout(function () {
            const idx = window.activeTimers.indexOf(id);
            if (idx !== -1) window.activeTimers.splice(idx, 1);
            cb();
        }, delay);
        window.activeTimers.push(id);
        return id;
    }

    function clearAllTimers() {
        window.activeTimers.forEach(function (id) { clearTimeout(id); });
        window.activeTimers = [];
    }

    function disposeEntityResources(entity) {
        if (!entity) return;
        const el = typeof entity === 'string' ? document.getElementById(entity) : entity;
        if (!el || !el.getObject3D) return;
        const obj3d = el.getObject3D('mesh');
        if (obj3d) {
            const root = obj3d.parent || obj3d;
            root.traverse(function (child) {
                if (child.geometry) child.geometry.dispose();
                if (child.material) {
                    const mats = Array.isArray(child.material) ? child.material : [child.material];
                    mats.forEach(function (m) {
                        for (const key in m) {
                            if (m[key] && m[key].isTexture) m[key].dispose();
                        }
                        m.dispose();
                    });
                }
            });
        }
    }

    function disposeAndRemoveEntity(entity) {
        if (!entity) return;
        const el = typeof entity === 'string' ? document.getElementById(entity) : entity;
        if (!el) return;
        disposeEntityResources(el);
        if (el.parentNode) el.parentNode.removeChild(el);
    }

    function playSound(id, volume) {
        try {
            const s = document.getElementById(id);
            if (s) { s.currentTime = 0; s.volume = volume !== undefined ? volume : 0.8; s.play().catch(function () {}); }
        } catch (e) { debugLog('playSound error:', e); }
    }

    function fadeOutAndStopAudio(el, duration, callback) {
        if (!el) { if (callback) callback(); return; }
        if (el.paused) { if (callback) callback(); return; }
        const step = 100;
        const totalSteps = Math.max(1, Math.floor(duration / step));
        let currentStep = 0;
        const stepFn = function () {
            currentStep++;
            el.volume = Math.max(0, 0.8 * (1 - currentStep / totalSteps));
            if (currentStep >= totalSteps) {
                el.pause(); el.currentTime = 0; el.volume = 0.8;
                if (callback) callback();
            } else {
                registerTimeout(stepFn, step);
            }
        };
        registerTimeout(stepFn, step);
    }

    function preloadNextStage(nextStage) {
        const cfg = window.STAGE_CONFIG[nextStage];
        if (!cfg) return;
        const img = new Image();
        const skyEl = document.getElementById(cfg.skyId);
        if (skyEl && skyEl.src) img.src = skyEl.src;
    }

    // ═══ ボールプーリング ═══
    function createBallEntity(sceneEl) {
        const ball = document.createElement('a-entity');
        ball.setAttribute('gltf-model', '{{ asset('cg/poke_ball_05.glb') }}');
        ball.setAttribute('scale', '0.3 0.3 0.3');
        ball.setAttribute('visible', 'false');
        sceneEl.appendChild(ball);
        return ball;
    }

    function acquireBall(sceneEl) {
        if (window.ballPool.length > 0) {
            const b = window.ballPool.pop();
            b.setAttribute('visible', 'true');
            return b;
        }
        return createBallEntity(sceneEl);
    }

    function releaseBall(ball) {
        if (!ball) return;
        window.activeBalls = window.activeBalls.filter(function (b) { return b.el !== ball; });
        ball.setAttribute('visible', 'false');
        if (window.ballPool.length < window.ballPoolSize) {
            window.ballPool.push(ball);
        } else {
            disposeAndRemoveEntity(ball);
        }
    }

    function releaseAllBalls() {
        window.activeBalls.slice().forEach(function (bd) {
            releaseBall(bd.el);
        });
        window.activeBalls = [];
    }

    // ─── HUD 更新 ───
    function updateHUD() {
        var timerText = document.getElementById('timerText');
        if (timerText) timerText.setAttribute('value', 'TIME: ' + Math.max(0, window.gameTimeLeft) + 's');

        var scoreText = document.getElementById('scoreText');
        if (scoreText) scoreText.setAttribute('value', 'SCORE: ' + (window.totalScore || 0));

        var comboText = document.getElementById('comboText');
        if (comboText) {
            if (window.comboCount > 1) {
                comboText.setAttribute('value', window.comboCount + 'x COMBO!');
            } else {
                comboText.setAttribute('value', '');
            }
        }
    }

    // ═══ A-Frame コンポーネント ═══

    // ─── auto-enter-vr ───
    AFRAME.registerComponent('auto-enter-vr', {
        init: function () {
            const sceneEl = this.el;
            sceneEl.addEventListener('loaded', function () {
                if (navigator.xr) {
                    navigator.xr.isSessionSupported('immersive-vr').then(function (supported) {
                        if (supported) {
                            registerTimeout(function () {
                                if (sceneEl.sessionMode !== 'vr') sceneEl.enterVR();
                            }, 1000);
                        }
                    }).catch(function () {});
                }
            });
        }
    });

    // ─── vr-controller ───
    AFRAME.registerComponent('vr-controller', {
        dependencies: ['raycaster'],
        init: function () {}
    });

    // ─── start-menu ───
    AFRAME.registerComponent('start-menu', {
        schema: { clickBlocked: { type: 'boolean', default: false }, menuHidden: { type: 'boolean', default: false } },

        init: function () {
            this.clickBlocked = false;
            this.menuHidden = this.data.menuHidden;
            this.tickLast = 0;
            this.bgmAudio = null;
            this.menuEl = this.el;
            this.startButton = document.getElementById('startButton');
            if (this.startButton) {
                this.startButton.addEventListener('click', this.handleClick.bind(this));
                this.startButton.addEventListener('touchstart', this.handleClick.bind(this), { passive: false });
            }
        },

        remove: function () {
            if (this.startButton) {
                this.startButton.removeEventListener('click', this.handleClick);
                this.startButton.removeEventListener('touchstart', this.handleClick);
            }
            if (window.gameTimer) { clearInterval(window.gameTimer); window.gameTimer = null; }
            clearAllTimers();
        },

        tick: function (time) {
            if (!window.gameStarted || window.gameEnded) return;
            if (time - this.tickLast < 100) return;
            this.tickLast = time;
        },

        handleClick: function (e) {
            if (e) { e.preventDefault(); }
            if (this.clickBlocked) return;
            this.clickBlocked = true;
            this.startGame();
        },

        startGame: function () {
            window.gameStarted = true;
            window.gameEnded = false;
            window.currentStage = 1;
            window.totalScore = 0;
            window.comboCount = 0;
            window.maxComboCount = 0;
            window.hitCount = 0;
            releaseAllBalls();
            clearAllTimers();

            // Hide start menu
            this.menuEl.setAttribute('visible', 'false');

            // Show HUD
            const hud = document.getElementById('timerDisplay');
            if (hud) hud.setAttribute('visible', 'true');

            this.advanceStage(1);
        },

        advanceStage: function (stageNum) {
            const cfg = window.STAGE_CONFIG[stageNum];
            if (!cfg) return;
            window.currentStage = stageNum;

            // Switch sky
            const skyEl = document.getElementById('aSky');
            if (skyEl) skyEl.setAttribute('src', '#' + cfg.skyId);

            // Switch BGM
            const bgmEl = document.getElementById(cfg.bgmId);
            if (this.bgmAudio && this.bgmAudio !== bgmEl) {
                fadeOutAndStopAudio(this.bgmAudio, 500, null);
            }
            if (bgmEl) {
                bgmEl.volume = 0.5;
                bgmEl.currentTime = 0;
                bgmEl.play().catch(function () {});
                this.bgmAudio = bgmEl;
            }

            // Preload next stage
            if (stageNum < 7) preloadNextStage(stageNum + 1);

            if (cfg.isResult) {
                this.showResult();
            } else {
                // Start timer
                window.gameTimeLeft = cfg.timeLimit;
                updateHUD();
                if (window.gameTimer) clearInterval(window.gameTimer);
                window.gameTimer = setInterval(function () {
                    window.gameTimeLeft -= 1;
                    updateHUD();
                    if (window.gameTimeLeft <= 0) {
                        clearInterval(window.gameTimer);
                        window.gameTimer = null;
                        this.onStageEnd(stageNum);
                    }
                }.bind(this), 1000);

                // Spawn model immediately
                this.spawnModel();
            }
        },

        spawnModel: function () {
            if (window.gameEnded) return;
            const locIdx = Math.floor(Math.random() * window.LOCATIONS.length);
            this.placeModelAt(locIdx);
        },

        onStageEnd: function (stageNum) {
            releaseAllBalls();
            const model = document.getElementById('bucchiModel');
            if (model) {
                model.setAttribute('visible', 'false');
                registerTimeout(function () { disposeAndRemoveEntity(model); }, 300);
            }
            window.modelActive = false;
            this.fadeToBlack(function () {
                this.advanceStage(stageNum + 1);
                this.fadeFromBlack();
            }.bind(this));
        },

        fadeToBlack: function (callback) {
            const overlay = document.getElementById('fadeOverlay');
            if (!overlay) { if (callback) callback(); return; }
            overlay.setAttribute('visible', 'true');
            const plane = overlay.querySelector('a-plane');
            if (!plane) { if (callback) callback(); return; }
            let opacity = 0;
            const step = function () {
                opacity += 0.1;
                plane.setAttribute('opacity', Math.min(1, opacity));
                if (opacity >= 1) { if (callback) callback(); }
                else registerTimeout(step, 50);
            };
            step();
        },

        fadeFromBlack: function () {
            const overlay = document.getElementById('fadeOverlay');
            if (!overlay) return;
            const plane = overlay.querySelector('a-plane');
            if (!plane) return;
            let opacity = 1;
            const step = function () {
                opacity -= 0.1;
                plane.setAttribute('opacity', Math.max(0, opacity));
                if (opacity <= 0) overlay.setAttribute('visible', 'false');
                else registerTimeout(step, 50);
            };
            step();
        },

        showResult: function () {
            window.gameEnded = true;
            if (window.gameTimer) { clearInterval(window.gameTimer); window.gameTimer = null; }
            releaseAllBalls();
            const hud = document.getElementById('timerDisplay');
            if (hud) hud.setAttribute('visible', 'false');
            this.placeModelAt(0);
            const resultMenu = document.getElementById('resultMenu');
            if (resultMenu) resultMenu.setAttribute('visible', 'true');
            const scoreEl = document.getElementById('resultScore');
            if (scoreEl) scoreEl.setAttribute('value', 'SCORE: ' + window.totalScore);
            const comboEl = document.getElementById('resultCombo');
            if (comboEl) comboEl.setAttribute('value', 'MAX COMBO: x' + window.maxComboCount);
            const hitsEl = document.getElementById('resultHits');
            if (hitsEl) hitsEl.setAttribute('value', 'HITS: ' + window.hitCount);
            const particles = document.getElementById('particle-celebration');
            if (particles) particles.setAttribute('visible', 'true');
            this.saveScore();
            this.fetchRankings();
            registerTimeout(function () {
                clearAllTimers();
                if (this.bgmAudio) fadeOutAndStopAudio(this.bgmAudio, 1000, null);
                this.fadeToBlack(function () {
                    const sceneEl = this.el.sceneEl;
                    if (sceneEl.session && sceneEl.session.end) {
                        sceneEl.session.end().catch(function () {}).then(function () { location.reload(); });
                    } else { location.reload(); }
                }.bind(this));
            });
        },

        placeModelAt: function (locIdx) {
            const cfg = window.LOCATIONS[locIdx];
            const sceneEl = this.el.sceneEl;
            const existing = document.getElementById('bucchiModel');
            if (existing) disposeAndRemoveEntity(existing);
            const model = document.createElement('a-entity');
            model.id = 'bucchiModel';
            model.setAttribute('position', cfg.pos);
            model.setAttribute('rotation', cfg.rot);
            model.setAttribute('scale', cfg.scale);
            model.setAttribute('gltf-model', '#model_bucchi');
            model.setAttribute('animation-mixer', 'clip: anime01; loop: repeat; timeScale: 1');
            model.setAttribute('visible', 'false');
            sceneEl.appendChild(model);
            const hitBox = document.createElement('a-cylinder');
            hitBox.id = 'bucchiHitBox';
            hitBox.setAttribute('radius', '0.8');
            hitBox.setAttribute('height', '2');
            hitBox.setAttribute('segments-radial', '8');
            hitBox.setAttribute('material', 'color: #ff0000; opacity: 0; transparent: true');
            hitBox.setAttribute('hit-box', '');
            hitBox.setAttribute('visible', 'false');
            model.appendChild(hitBox);
            registerTimeout(function () {
                if (!window.gameStarted || window.gameEnded) return;
                model.setAttribute('visible', 'true');
                window.modelActive = true;
                window.modelAppearTime = Date.now();
                playSound('sound_appear', 0.6);
            }, 50);
        },

        saveScore: function () {
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            const token = csrfToken ? csrfToken.content : '';
            const baseUrl = '{{ env("MIX_ASSET_URL", "") }}';
            fetch(baseUrl + 'api/findhoufu-scores', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ name: 'noName', score: window.totalScore, max_combo: window.maxComboCount, hits: window.hitCount })
            }).then(function (r) { return r.json(); })
              .then(function (d) { debugLog('Score saved:', d); })
              .catch(function (e) { debugLog('Score save error:', e); });
        },

        fetchRankings: function () {
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            const token = csrfToken ? csrfToken.content : '';
            const baseUrl = '{{ env("MIX_ASSET_URL", "") }}';
            fetch(baseUrl + 'api/findhoufu-scores/top5', { headers: { 'X-CSRF-TOKEN': token } })
              .then(function (r) { return r.json(); })
              .then(function (data) {
                const container = document.getElementById('rankingDisplay');
                if (!container) return;
                container.innerHTML = '';
                const list = Array.isArray(data) ? data : (data.data || []);
                list.slice(0, 5).forEach(function (entry, i) {
                    const rank = document.createElement('a-text');
                    rank.setAttribute('value', (i + 1) + '. ' + (entry.name || '---') + ' - ' + (entry.score || 0));
                    rank.setAttribute('position', '0 ' + (i * -0.3) + ' 0.01');
                    rank.setAttribute('align', 'center');
                    rank.setAttribute('color', i === 0 ? '#FFD700' : '#FFFFFF');
                    rank.setAttribute('width', '4');
                    rank.setAttribute('font', 'roboto');
                    rank.setAttribute('shader', 'msdf');
                    container.appendChild(rank);
                });
              })
              .catch(function (e) { debugLog('Ranking fetch error:', e); });
        }
    });

    // ─── shoot ───
    AFRAME.registerComponent('shoot', {
        schema: { cooldown: { type: 'number', default: 300 } },
        init: function () {
            this.lastShot = 0;
            this.cooldown = this.data.cooldown;
            this.gravity = new THREE.Vector3(0, -2.45, 0);
            this.speed = 20;

            // VR: コントローラー triggerdown
            var vrTriggerFn = function (e) {
                if (window.gameStarted && !window.gameEnded) this.shoot(e);
            }.bind(this);
            var lc = document.getElementById('leftController');
            var rc = document.getElementById('rightController');
            if (lc) lc.addEventListener('triggerdown', vrTriggerFn);
            if (rc) rc.addEventListener('triggerdown', vrTriggerFn);

            // PC: mousedown / touchstart
            var pcFn = function (e) {
                if (window.gameStarted && !window.gameEnded) {
                    e.preventDefault();
                    this.shoot(e);
                }
            }.bind(this);
            document.addEventListener('mousedown', pcFn);
            document.addEventListener('touchstart', pcFn, { passive: false });

            this._vrTriggerFn = vrTriggerFn;
            this._pcFn = pcFn;
        },
        remove: function () {
            var lc = document.getElementById('leftController');
            var rc = document.getElementById('rightController');
            if (lc) lc.removeEventListener('triggerdown', this._vrTriggerFn);
            if (rc) rc.removeEventListener('triggerdown', this._vrTriggerFn);
            document.removeEventListener('mousedown', this._pcFn);
            document.removeEventListener('touchstart', this._pcFn);
            releaseAllBalls();
        },
        shoot: function (e) {
            const now = performance.now();
            if (now - this.lastShot < this.cooldown) return;
            if (window.activeBalls.length >= window.maxSimultaneousBalls) return;
            this.lastShot = now;
            const sceneEl = this.el.sceneEl;
            const ball = acquireBall(sceneEl);
            if (!ball) return;
            const obj3d = this.el.getObject3D('camera');
            const startPos = obj3d.position.clone();
            const dir = new THREE.Vector3();
            obj3d.getWorldDirection(dir);
            startPos.add(dir.clone().multiplyScalar(0.5));
            ball.setAttribute('position', startPos.x + ' ' + startPos.y + ' ' + startPos.z);
            ball.setAttribute('visible', 'true');
            window.activeBalls.push({
                el: ball, startPos: startPos,
                velocity: dir.multiplyScalar(this.speed),
                startTime: performance.now(),
                gravity: this.gravity, hit: false
            });
        },
        tick: function (time) {
            if (window.activeBalls.length === 0) return;
            const now = performance.now();
            const toRemove = [];
            window.activeBalls.forEach(function (bd) {
                if (bd.hit) return;
                const t = (now - bd.startTime) / 1000;
                const x = bd.startPos.x + bd.velocity.x * t;
                const y = bd.startPos.y + bd.velocity.y * t + 0.5 * bd.gravity.y * t * t;
                const z = bd.startPos.z + bd.velocity.z * t;
                if (y < -5 || t > 5) { toRemove.push(bd); return; }
                bd.el.setAttribute('position', x + ' ' + y + ' ' + z);
                if (window.modelActive) {
                    const hitBox = document.getElementById('bucchiHitBox');
                    if (hitBox) {
                        const obj = hitBox.getObject3D('mesh');
                        if (obj) {
                            const hbPos = new THREE.Vector3();
                            obj.getWorldPosition(hbPos);
                            const dy = y - (hbPos.y + 1);
                            const dist = Math.sqrt((x - hbPos.x) * (x - hbPos.x) + dy * dy + (z - hbPos.z) * (z - hbPos.z));
                            if (dist < 1.5) {
                                bd.hit = true;
                                toRemove.push(bd);
                                this.el.sceneEl.dispatchEvent(new CustomEvent('ball-hit'));
                                return;
                            }
                        }
                    }
                }
            }.bind(this));
            toRemove.forEach(function (bd) { releaseBall(bd.el); });
        }
    });

    // ─── hit-box ───
    AFRAME.registerComponent('hit-box', {
        init: function () {
            this.hitFlag = false;
            this.sceneEl = this.el.sceneEl;
            this.onBallHit = this.onBallHit.bind(this);
            this.sceneEl.addEventListener('ball-hit', this.onBallHit);
        },
        remove: function () {
            this.sceneEl.removeEventListener('ball-hit', this.onBallHit);
        },
        onBallHit: function () {
            if (this.hitFlag) return;
            this.hitFlag = true;
            window.modelActive = false;
            // Calculate score
            const hitTime = (Date.now() - window.modelAppearTime) / 1000;
            const stageCfg = window.STAGE_CONFIG[window.currentStage];
            const timeLimit = stageCfg ? stageCfg.timeLimit : 12;
            const baseScore = Math.max(5, Math.round(50 * (1 - hitTime / timeLimit)));
            window.comboCount++;
            if (window.comboCount > window.maxComboCount) window.maxComboCount = window.comboCount;
            const comboMult = 1 + Math.min(window.comboCount, 10) * 0.1;
            const finalScore = Math.round(baseScore * comboMult * 10) / 10;
            window.totalScore = Math.round((window.totalScore + finalScore) * 10) / 10;
            window.hitCount++;
            updateHUD();
            playSound('sound_hit', 0.8);
            // Switch to anime02 + fade out
            const model = document.getElementById('bucchiModel');
            if (model) {
                model.setAttribute('animation-mixer', 'clip: anime02; loop: 1; timeScale: 1.5');
                registerTimeout(function () {
                    if (!model || !model.parentNode) return;
                    let opacity = 1;
                    const fadeStep = function () {
                        opacity -= 0.1;
                        model.setAttribute('opacity', Math.max(0, opacity));
                        if (opacity <= 0) {
                            model.setAttribute('visible', 'false');
                            registerTimeout(function () {
                                disposeAndRemoveEntity(model);
                                if (window.gameStarted && !window.gameEnded) {
                                    registerTimeout(function () {
                                        if (window.gameStarted && !window.gameEnded) {
                                            const menuEl = document.getElementById('startMenu');
                                            if (menuEl && menuEl.components['start-menu']) {
                                                menuEl.components['start-menu'].spawnModel();
                                            }
                                        }
                                    }, 500);
                                }
                            }, 100);
                        } else {
                            registerTimeout(fadeStep, 50);
                        }
                    };
                    fadeStep();
                }, 500);
            }
            registerTimeout(function () { this.hitFlag = false; }.bind(this), 1500);
        }
    });
</script>