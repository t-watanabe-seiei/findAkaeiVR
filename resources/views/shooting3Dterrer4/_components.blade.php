<script>
    // デバッグモード制御
    window.DEBUG_MODE = false;
    window.debugLog = function(...args) { if (window.DEBUG_MODE) console.log(...args); };

    // ゲーム状態管理
    window.gameStarted = false;
    window.gameEnded = false;
    window.totalScore = 0;
    window.gameTimer = null;
    window.gameTimeLeft = 90;
    window.comboCount = 0;
    window.maxComboCount = 0;
    window.enemiesDefeated = 0;
    window.lastBallHit = false;
    window.bossSpawned = false;
    window.respawningModels = {};
    window.ammoByGun = { 1: 20, 2: 20 };

    // ステージ・武器管理
    window.currentStage = 1;
    window.selectedGun = 1;

    // ステージ設定
    window.STAGE_CONFIG = {
        1: {
            timeLimit: 100,
            bgmId: 'sound_bgm_s1',
            skyId: 'sky_s1',
            models: [
                { id: 'modelGroup_01', gltf: '#model_s1_01' },
                { id: 'modelGroup_02', gltf: '#model_s1_02' },
                { id: 'modelGroup_03', gltf: '#model_s1_03' },
                { id: 'modelGroup_04', gltf: '#model_s1_04' },
                { id: 'modelGroup_05', gltf: '#model_s1_05' },
            ],
            bossModel: '#model_boss_s1',
            requiredHits: 1,
            bossRequiredHits: 15,
            gameMode: 'terrer4_s1',
            resultMenuId: 'resultMenu_s1',
            rankingDisplayId: 'rankingDisplay_s1',
            resultTitle: 'STAGE 1 CLEAR',
        },
        2: {
            timeLimit: 80,
            bgmId: 'sound_bgm_s2',
            skyId: 'sky_s2',
            models: [
                { id: 'modelGroup_01', gltf: '#model_s2_01' },
                { id: 'modelGroup_02', gltf: '#model_s2_02' },
                { id: 'modelGroup_03', gltf: '#model_s2_03' },
                { id: 'modelGroup_04', gltf: '#model_s2_04' },
                { id: 'modelGroup_05', gltf: '#model_s2_05' },
            ],
            bossModel: '#model_boss_s2',
            requiredHits: 2,
            bossRequiredHits: 15,
            gameMode: 'terrer4_s2',
            resultMenuId: 'resultMenu_s2',
            rankingDisplayId: 'rankingDisplay_s2',
            resultTitle: 'GAME OVER',
        }
    };

    // 武器設定
    window.GUN_CONFIG = {
        1: { ball: 'cg/poke_ball_05.glb',      modelSrc: 'cg/gun_01.glb' },
        2: { ball: 'cg/poke_ball_06.glb',   modelSrc: 'cg/gun_02.glb' }
    };

    // THREE.jsキャッシュ
    window.cachedBallEmissiveColor = null;
    window.cachedHitEmissiveColor = null;
    window.cachedFlashColors = null;
    window.cachedEnhanceMaterialColor = null;

    // ボール・タイマー管理
    window.activeBalls = [];
    window.activeTimers = [];
    window._cachedModelPos = new THREE.Vector3();
    window._cachedCameraPos = new THREE.Vector3();
    window._cachedParticlePos = new THREE.Vector3();

    // アラート音管理
    window.alertSoundPlaying = false;
    window.currentAlertModel = null;
    window.usedPatterns = {};

    window.registerTimeout = function(callback, delay) {
        const id = setTimeout(callback, delay);
        window.activeTimers.push(id);
        return id;
    };

    window.getAvailablePattern = function(patterns, modelId) {
        const usedIndices = Object.keys(window.usedPatterns)
            .filter(id => id !== modelId).map(id => window.usedPatterns[id]);
        const available = patterns.map((_, i) => i).filter(i => !usedIndices.includes(i));
        const pool = available.length > 0 ? available : patterns.map((_, i) => i);
        const idx = pool[Math.floor(Math.random() * pool.length)];
        window.usedPatterns[modelId] = idx;
        return { pattern: patterns[idx], index: idx };
    };

    window.updateDebug = function(message) {
        const el = document.getElementById('debugText');
        if (el) el.setAttribute('value', `${new Date().toLocaleTimeString()}: ${message}`);
    };

    window.updateAmmoDisplay = function() {
        const gun1El = document.getElementById('ammoTextGun1');
        const gun2El = document.getElementById('ammoTextGun2');
        const weaponText = document.getElementById('weaponText');
        const selected = window.selectedGun || 1;

        if (gun1El) {
            gun1El.setAttribute('value', `GUN1: ${window.ammoByGun[1]}`);
            gun1El.setAttribute('color', selected === 1 ? '#FFFF00' : '#FFFFFF');
        }
        if (gun2El) {
            gun2El.setAttribute('value', `GUN2: ${window.ammoByGun[2]}`);
            gun2El.setAttribute('color', selected === 2 ? '#FFFF00' : '#FFFFFF');
        }
        if (weaponText) {
            weaponText.setAttribute('value', `WEAPON: Gun ${selected} (${window.ammoByGun[selected]})`);
        }
    };

    window.tryConsumeAmmo = function(gunNo) {
        if (!window.ammoByGun[gunNo] || window.ammoByGun[gunNo] <= 0) return false;
        window.ammoByGun[gunNo] -= 1;
        window.updateAmmoDisplay();
        return true;
    };

    window.addAmmoToInactiveGun = function(amount) {
        const inactiveGun = window.selectedGun === 1 ? 2 : 1;
        window.ammoByGun[inactiveGun] = (window.ammoByGun[inactiveGun] || 0) + amount;
        window.updateAmmoDisplay();
    };

    window.spawnAmmoPickupToCamera = function(fromPos, amount) {
        const sceneEl = document.querySelector('a-scene');
        const camEl = sceneEl && sceneEl.camera ? sceneEl.camera.el : document.querySelector('[camera]');
        if (!sceneEl || !camEl || !fromPos) {
            window.addAmmoToInactiveGun(amount);
            return;
        }

        const target = new THREE.Vector3();
        camEl.object3D.getWorldPosition(target);
        target.z -= 0.5;

        const pickup = document.createElement('a-entity');
        const inactiveGun = window.selectedGun === 1 ? 2 : 1;
        pickup.setAttribute('gltf-model', window.GUN_CONFIG[inactiveGun].ball);
        pickup.setAttribute('position', `${fromPos.x} ${fromPos.y + 1.0} ${fromPos.z}`);
        pickup.setAttribute('scale', '0.11 0.11 0.11');
        pickup.setAttribute('animation__spin', { property: 'rotation', to: '0 720 0', dur: 900, easing: 'linear' });
        pickup.setAttribute('animation__toCamera', {
            property: 'position',
            to: `${target.x} ${target.y} ${target.z}`,
            dur: 900,
            easing: 'easeInQuad'
        });

        let granted = false;
        const grantAmmo = () => {
            if (granted) return;
            granted = true;
            window.addAmmoToInactiveGun(amount);
            if (pickup.parentNode) pickup.parentNode.removeChild(pickup);
        };

        pickup.addEventListener('animationcomplete__toCamera', grantAmmo, { once: true });
        pickup.addEventListener('animationcomplete', (evt) => {
            if (!evt || !evt.detail || evt.detail.name === 'animation__toCamera') {
                grantAmmo();
            }
        });
        setTimeout(grantAmmo, 1200);

        sceneEl.appendChild(pickup);
    };

    window.stopAllParticles = function() {
        const ids = ['particle-normal', 'particle-tier1', 'particle-tier2', 'particle-tier3', 'particle-celebration'];
        ids.forEach(id => {
            const root = document.getElementById(id);
            if (!root) return;

            if (root.components && root.components['particle-system']) {
                root.components['particle-system'].stopParticles();
            }
            root.querySelectorAll('[particle-system]').forEach(node => {
                const ps = node.components && node.components['particle-system'];
                if (ps) ps.stopParticles();
            });
            root.setAttribute('visible', 'false');
        });
    };

    window.fadeOutAndStopAudio = function(audioEl, duration = 5000, onComplete = null) {
        if (!audioEl) {
            if (typeof onComplete === 'function') onComplete();
            return;
        }
        const initialVolume = typeof audioEl.volume === 'number' ? audioEl.volume : 1;
        if (initialVolume <= 0) {
            audioEl.pause();
            audioEl.currentTime = 0;
            if (typeof onComplete === 'function') onComplete();
            return;
        }

        const stepMs = 100;
        const steps = Math.max(1, Math.floor(duration / stepMs));
        const volumeStep = initialVolume / steps;
        let currentStep = 0;

        const intervalId = setInterval(() => {
            currentStep++;
            const nextVolume = Math.max(0, initialVolume - (volumeStep * currentStep));
            audioEl.volume = nextVolume;

            if (currentStep >= steps || nextVolume <= 0.001) {
                clearInterval(intervalId);
                audioEl.pause();
                audioEl.currentTime = 0;
                audioEl.volume = initialVolume;
                if (typeof onComplete === 'function') onComplete();
            }
        }, stepMs);
    };

    window.updateAmmoDisplay();

    // ─────────────────────────────────────────
    // enhance-materials コンポーネント
    // ─────────────────────────────────────────
    AFRAME.registerComponent('enhance-materials', {
        init: function () {
            this.el.addEventListener('model-loaded', () => {
                const mesh = this.el.getObject3D('mesh');
                if (!mesh) return;
                mesh.traverse((node) => {
                    if (node.isMesh && node.material) {
                        if (node.material.map) node.material.map.anisotropy = 2;
                        node.material.needsUpdate = true;
                        if (node.material.metalnessMap) node.material.metalnessMap.anisotropy = 2;
                        if (node.material.roughnessMap) node.material.roughnessMap.anisotropy = 2;
                        if (node.material.normalMap) node.material.normalMap.anisotropy = 2;
                    }
                });
            });
        }
    });

    // ─────────────────────────────────────────
    // face-camera コンポーネント
    // ─────────────────────────────────────────
    AFRAME.registerComponent('face-camera', {
        init: function() {
            this.cameraEl = null;
            this._cameraPos = new THREE.Vector3();
            this._lastUpdate = 0;
        },
        tick: function (time) {
            if (time - this._lastUpdate < 50) return;
            this._lastUpdate = time;
            if (!this.cameraEl) {
                if (this.el.sceneEl && this.el.sceneEl.camera) this.cameraEl = this.el.sceneEl.camera.el;
                if (!this.cameraEl) return;
            }
            this.cameraEl.object3D.getWorldPosition(this._cameraPos);
            this.el.object3D.lookAt(this._cameraPos);
            this.el.object3D.rotation.set(0, this.el.object3D.rotation.y, 0);
        }
    });

    // ─────────────────────────────────────────
    // start-menu コンポーネント
    // ─────────────────────────────────────────
    AFRAME.registerComponent('start-menu', {
        init: function() {
            this.handleClick  = this.handleClick.bind(this);
            this.handleTouch  = this.handleTouch.bind(this);
            this.switchGun    = this.switchGun.bind(this);
            this.clickBlocked = false;
            this.controllersUpdated = false;
            this.menuHidden = false;

            const startButton = document.getElementById('startButton');
            if (startButton) {
                startButton.addEventListener('click', this.handleClick);
                startButton.addEventListener('touchstart', this.handleTouch, { passive: false });
            }
            const weaponDisplay = document.getElementById('weaponDisplay');
            if (weaponDisplay) {
                weaponDisplay.addEventListener('click', this.switchGun);
                weaponDisplay.addEventListener('touchstart', (e) => { e.preventDefault(); this.switchGun(); }, { passive: false });
            }
            this._gripHandler = () => { this.switchGun(); };
            const leftCtrl  = document.getElementById('leftController');
            const rightCtrl = document.getElementById('rightController');
            const evts = ['gripdown', 'abuttondown', 'bbuttondown'];
            evts.forEach(ev => {
                if (leftCtrl)  leftCtrl.addEventListener(ev, this._gripHandler);
                if (rightCtrl) rightCtrl.addEventListener(ev, this._gripHandler);
            });
        },

        switchGun: function() {
            window.selectedGun = window.selectedGun === 1 ? 2 : 1;
            const rightCtrl = document.getElementById('rightController');
            if (rightCtrl) {
                const gunEntity = rightCtrl.querySelector('#controllerGunModel');
                if (gunEntity) gunEntity.setAttribute('gltf-model', window.GUN_CONFIG[window.selectedGun].modelSrc);
            }
            window.updateAmmoDisplay();
        },

        tick: function(time) {
            if (!this._lastTick) this._lastTick = 0;
            if (time - this._lastTick < 100) return;
            this._lastTick = time;
            const mc = document.getElementById('mouseCursor');
            const lc = document.getElementById('leftController');
            const rc = document.getElementById('rightController');
            if (window.gameStarted && !window.gameEnded) {
                if (!this.menuHidden) {
                    this.el.setAttribute('visible', false);
                    this.el.setAttribute('scale', '0 0 0');
                    this.menuHidden = true;
                }
                if (!this.controllersUpdated) {
                    if (mc) mc.setAttribute('raycaster', 'objects: .collidable');
                    if (lc) lc.setAttribute('raycaster', 'objects: .collidable; far: 5; showLine: false');
                    if (rc) rc.setAttribute('raycaster', 'objects: .collidable; far: 5; showLine: true');
                    this.controllersUpdated = true;
                }
                this.clickBlocked = true;
            } else {
                if (this.menuHidden) this.menuHidden = false;
                if (this.controllersUpdated) {
                    if (mc) mc.setAttribute('raycaster', 'objects: .clickable, .collidable');
                    if (lc) lc.setAttribute('raycaster', 'objects: .collidable, .clickable; far: 5; showLine: false');
                    if (rc) rc.setAttribute('raycaster', 'objects: .collidable, .clickable; far: 5; showLine: true');
                    this.controllersUpdated = false;
                }
                this.clickBlocked = window.gameEnded;
            }
        },

        handleClick: function(event) {
            if (window.gameStarted && !window.gameEnded) { event.stopPropagation(); event.preventDefault(); return false; }
            if (this.clickBlocked) { event.stopPropagation(); event.preventDefault(); return false; }
            const vis = this.el.getAttribute('visible');
            if (vis === false || vis === 'false') { event.stopPropagation(); event.preventDefault(); return false; }
            if (window.gameEnded) { event.stopPropagation(); event.preventDefault(); return false; }
            this.startGame();
        },

        handleTouch: function(event) {
            if (window.gameStarted && !window.gameEnded) { event.preventDefault(); event.stopPropagation(); return false; }
            if (this.clickBlocked) { event.preventDefault(); event.stopPropagation(); return false; }
            const vis = this.el.getAttribute('visible');
            if (vis === false || vis === 'false') { event.preventDefault(); event.stopPropagation(); return false; }
            if (window.gameEnded) { event.preventDefault(); event.stopPropagation(); return false; }
            event.preventDefault(); event.stopPropagation();
            this.startGame();
            return true;
        },

        startGame: function() {
            this.clickBlocked = true;
            this.el.setAttribute('visible', false);
            this.el.setAttribute('scale', '0 0 0');
            this.el.object3D.visible = false;
            this.el.querySelectorAll('.clickable').forEach(el => {
                el.classList.remove('clickable');
                el.classList.add('non-clickable');
            });
            const mc = document.getElementById('mouseCursor');
            const lc = document.getElementById('leftController');
            const rc = document.getElementById('rightController');
            if (mc) mc.setAttribute('raycaster', 'objects: .collidable');
            if (lc) lc.setAttribute('raycaster', 'objects: .collidable; far: 5; showLine: false');
            if (rc) rc.setAttribute('raycaster', 'objects: .collidable; far: 5; showLine: true');
            this.controllersUpdated = true;

            const cfg = window.STAGE_CONFIG[window.currentStage];
            const bgm = document.getElementById(cfg.bgmId);
            if (bgm) { bgm.volume = 0.7; bgm.currentTime = 0; bgm.play().catch(() => {}); }

            if (window.gameTimer) { clearInterval(window.gameTimer); window.gameTimer = null; }
            window.gameStarted   = true;
            window.gameEnded     = false;
            window.totalScore    = 0;
            window.gameTimeLeft  = cfg.timeLimit;
            window.comboCount    = 0;
            window.maxComboCount = 0;
            window.enemiesDefeated = 0;
            window.lastBallHit   = false;
            window.bossSpawned   = false;
            window.respawningModels = {};
            window.usedPatterns  = {};

            const sceneEl = document.querySelector('a-scene');
            this.activateModels(sceneEl);

            const timerDisplay = document.getElementById('timerDisplay');
            if (timerDisplay) timerDisplay.setAttribute('visible', true);
            const timerText = document.getElementById('timerText');
            if (timerText) timerText.setAttribute('value', `TIME: ${window.gameTimeLeft}s`);
            const scoreText = document.getElementById('currentScore');
            if (scoreText) scoreText.setAttribute('value', 'SCORE: 0.0');
            window.updateAmmoDisplay();

            this.startTimer();
        },

        startTimer: function() {
            const timerText = document.getElementById('timerText');
            const self = this;
            if (window.gameTimer) { clearInterval(window.gameTimer); window.gameTimer = null; }

            window.gameTimer = setInterval(() => {
                window.gameTimeLeft--;
                if (timerText) timerText.setAttribute('value', `TIME: ${window.gameTimeLeft}s`);

                // ボス出現（残り15秒）
                if (window.gameTimeLeft === 15 && !window.bossSpawned) {
                    window.bossSpawned = true;
                    self.spawnBoss();
                }

                if (window.gameTimeLeft <= 0) {
                    clearInterval(window.gameTimer);
                    window.gameTimer  = null;
                    window.gameEnded  = true;
                    window.gameStarted = false;

                    // 全モデル死亡アニメーション
                    document.querySelectorAll('[id^="modelGroup_"]').forEach(model => {
                        if (model.getAttribute('visible')) {
                            const me = model.querySelector('[gltf-model]');
                            if (me) {
                                me.removeAttribute('animation-mixer');
                                window.registerTimeout(() => me.setAttribute('animation-mixer', 'clip: anime02; loop: repeat; timeScale: 1'), 50);
                            }
                            window.registerTimeout(() => model.setAttribute('animation__fadeout', { property: 'scale', to: '0 0 0', dur: 500, easing: 'easeInQuad' }), 1500);
                            window.registerTimeout(() => model.setAttribute('visible', false), 2000);
                        }
                    });

                    const dieSound = document.getElementById('sound_zombie_die');
                    if (dieSound) { dieSound.currentTime = 0; dieSound.play().catch(() => {}); }

                    const timerDisplay = document.getElementById('timerDisplay');
                    if (timerDisplay) timerDisplay.setAttribute('visible', false);

                    window.registerTimeout(() => {
                        const menuId = window.STAGE_CONFIG[window.currentStage].resultMenuId;
                        const resultMenu = document.getElementById(menuId);
                        if (resultMenu) self.showResult(resultMenu);
                    }, 2500);
                }
            }, 1000);
        },

        spawnBoss: function() {
            const sceneEl = document.querySelector('a-scene');
            const cfg = window.STAGE_CONFIG[window.currentStage];
            const patterns = [
                { startPos: { x: -4, y: 0, z: 0 }, speed: 0.15 },
                { startPos: { x:  4, y: 0, z: 0 }, speed: 0.15 },
                { startPos: { x:  0, y: 0, z: 4 }, speed: 0.15 },
                { startPos: { x:  0, y: 0, z:-4 }, speed: 0.15 }
            ];
            const p = patterns[Math.floor(Math.random() * patterns.length)];
            const rot = Math.atan2(p.startPos.x, -p.startPos.z) * (180 / Math.PI);

            const bossGroup = document.createElement('a-entity');
            bossGroup.setAttribute('id', 'modelGroup_boss');
            bossGroup.setAttribute('position', `${p.startPos.x} ${p.startPos.y} ${p.startPos.z}`);
            bossGroup.setAttribute('rotation', `0 ${rot} 0`);
            bossGroup.setAttribute('scale', '0 0 0');
            bossGroup.setAttribute('approach-camera', { speed: p.speed, startPos: p.startPos, useCamera: true, autoRespawn: false, waitTime: 4000 });

            const bossEntity = document.createElement('a-entity');
            bossEntity.setAttribute('gltf-model', cfg.bossModel);
            bossEntity.setAttribute('animation-mixer', 'clip: anime01; loop: repeat');
            bossEntity.setAttribute('enhance-materials', '');
            bossEntity.setAttribute('scale', '1.8 1.8 1.8');
            bossGroup.appendChild(bossEntity);

            const hitBox = document.createElement('a-entity');
            hitBox.setAttribute('id', 'hit-boxed_boss');
            hitBox.setAttribute('hit-box', 'isBoss: true');
            hitBox.setAttribute('position', '0 1.0 0');
            const cyl = document.createElement('a-entity');
            cyl.setAttribute('geometry', 'primitive: cylinder');
            cyl.setAttribute('material', 'color: red; opacity: 0.0; transparent: true');
            cyl.setAttribute('scale', '1.8 3.2 1.8');
            cyl.setAttribute('class', 'collidable');
            hitBox.appendChild(cyl);
            bossGroup.appendChild(hitBox);
            sceneEl.appendChild(bossGroup);

            const sound = document.getElementById('sound_zombie_appear');
            if (sound) { sound.currentTime = 0; sound.play().catch(() => {}); }

            window.registerTimeout(() => {
                bossGroup.setAttribute('animation__fadein', { property: 'scale', to: '1 1 1', dur: 2000, easing: 'easeOutQuad' });
            }, 100);
        },

        activateModels: function(sceneEl) {
            const cfg = window.STAGE_CONFIG[window.currentStage];
            const initPats = [
                { startPos: {x:-5,y:0,z:-10}, speed:0.4, useCamera:true,  waitTime:4000 },
                { startPos: {x: 5,y:0,z:-10}, speed:0.3, useCamera:true,  waitTime:4000 },
                { startPos: {x: 0,y:0,z:-12}, speed:0.2, useCamera:true,  waitTime:4000 },
                { startPos: {x:-6,y:0,z: -8}, endPos:{x:6,y:0,z:-8},  speed:0.35, useCamera:false, waitTime:4000 },
                { startPos: {x: 6,y:0,z: -8}, endPos:{x:-6,y:0,z:-8}, speed:0.35, useCamera:false, waitTime:4000 },
            ];
            const colors = ['blue','green','red','yellow','cyan'];
            const speedMult = window.currentStage === 2 ? 1.2 : 1.0;

            cfg.models.forEach((m, i) => {
                window.registerTimeout(() => {
                    const p = initPats[i % initPats.length];
                    const rot = p.useCamera
                        ? Math.atan2(p.startPos.x, -p.startPos.z) * (180 / Math.PI)
                        : Math.atan2(p.endPos.x - p.startPos.x, -(p.endPos.z - p.startPos.z)) * (180 / Math.PI);
                    const apCfg = { speed: p.speed * speedMult, startPos: p.startPos, useCamera: p.useCamera, autoRespawn: true, waitTime: p.waitTime };
                    if (p.endPos) apCfg.endPos = p.endPos;

                    const entity = document.createElement('a-entity');
                    entity.setAttribute('id', m.id);
                    entity.setAttribute('position', `${p.startPos.x} ${p.startPos.y} ${p.startPos.z}`);
                    entity.setAttribute('rotation', `0 ${rot} 0`);
                    entity.setAttribute('scale', '0 0 0');
                    entity.setAttribute('approach-camera', apCfg);

                    const me = document.createElement('a-entity');
                    me.setAttribute('gltf-model', m.gltf);
                    me.setAttribute('animation-mixer', 'clip: anime01; loop: repeat');
                    me.setAttribute('enhance-materials', '');
                    entity.appendChild(me);

                    const hb = document.createElement('a-entity');
                    hb.setAttribute('id', m.id.replace('modelGroup', 'hit-boxed'));
                    hb.setAttribute('hit-box', '');
                    hb.setAttribute('position', '0 1.0 0');
                    const cyl = document.createElement('a-entity');
                    cyl.setAttribute('geometry', 'primitive: cylinder');
                    cyl.setAttribute('material', `color: ${colors[i]}; opacity: 0.0; transparent: true`);
                    cyl.setAttribute('scale', '0.75 1.5 0.75');
                    cyl.setAttribute('class', 'collidable');
                    hb.appendChild(cyl);
                    entity.appendChild(hb);
                    sceneEl.appendChild(entity);

                    window.registerTimeout(() => {
                        entity.setAttribute('animation__fadein', { property: 'scale', to: '1 1 1', dur: 1000, easing: 'easeOutQuad' });
                    }, 100);
                }, i * 1000);
            });
        },

        showResult: function(resultMenu) {
            if (!resultMenu) return;
            const stg = window.currentStage;
            const cfg = window.STAGE_CONFIG[stg];
            const sfx = stg === 2 ? '_s2' : '_s1';

            const el = (id) => document.getElementById(id + sfx);
            const titleEl   = el('resultStageTitle');
            const scoreEl   = el('resultScore');
            const comboEl   = el('maxComboText');
            const commentEl = el('resultComment');

            if (titleEl)   titleEl.setAttribute('value', cfg.resultTitle);
            if (scoreEl)   scoreEl.setAttribute('value', `SCORE: ${window.totalScore.toFixed(1)}`);
            if (comboEl)   comboEl.setAttribute('value', `MAX COMBO: ${window.maxComboCount}`);

            let comment = 'KEEP PRACTICING!';
            if (window.totalScore >= 1000)     comment = 'AMAZING! PERFECT SNIPER!';
            else if (window.totalScore >= 800) comment = 'EXCELLENT! GREAT JOB!';
            else if (window.totalScore >= 600) comment = 'VERY GOOD! NICE SHOOTING!';
            else if (window.totalScore >= 400) comment = 'GOOD! KEEP IT UP!';
            else if (window.totalScore >= 200) comment = 'NOT BAD! TRY AGAIN!';
            if (commentEl) commentEl.setAttribute('value', comment);

            const startMenu = document.getElementById('startMenu');
            if (startMenu) startMenu.setAttribute('visible', false);

            resultMenu.setAttribute('visible', true);
            resultMenu.setAttribute('scale', '0 0 0');
            resultMenu.removeAttribute('animation');
            window.registerTimeout(() => {
                resultMenu.setAttribute('animation', { property: 'scale', to: '1 1 1', dur: 500, easing: 'easeOutBack' });
            }, 50);

            if (stg === 1) {
                window.registerTimeout(() => window.stopAllParticles(), 5000);
            }

            this.saveScoreToDatabase(window.totalScore);
        },

        saveScoreToDatabase: function(score) {
            const cfg = window.STAGE_CONFIG[window.currentStage];
            fetch('api/shooting-scores', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    name: 'noName', score: score, level: 1,
                    game_mode: cfg.gameMode,
                    max_combo: window.maxComboCount || 0,
                    enemies_defeated: window.enemiesDefeated || 0,
                })
            })
            .then(r => r.json())
            .then(data => {
                window.lastSavedScoreId = (data && data.data && data.data.id) ? data.data.id : null;
                this.fetchAndDisplayRankings();
            })
            .catch(e => console.error('Score save error:', e));
        },

        fetchAndDisplayRankings: function() {
            const cfg = window.STAGE_CONFIG[window.currentStage];
            fetch(`api/shooting-scores/top5?level=1&game_mode=${cfg.gameMode}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data) {
                        this.displayRankings(data.data, cfg.rankingDisplayId);
                        const isTop5 = data.data.some(item =>
                            (window.lastSavedScoreId && item.id === window.lastSavedScoreId) ||
                            (Math.abs(item.score - window.totalScore) < 0.01 && item.name === 'noName')
                        );
                        if (isTop5) this.celebrateTop5();
                    }
                })
                .catch(e => console.error('Ranking fetch error:', e));
        },

        celebrateTop5: function() {
            const menuId = window.STAGE_CONFIG[window.currentStage].resultMenuId;
            const menu = document.getElementById(menuId);
            if (!menu) return;
            const pos = menu.getAttribute('position');
            const cp = document.getElementById('particle-celebration');
            if (cp) {
                cp.setAttribute('position', `${pos.x} ${pos.y} ${pos.z}`);
                cp.setAttribute('visible', 'true');
                const particleNodes = cp.querySelectorAll('[particle-system]');
                particleNodes.forEach(node => {
                    const ps = node.components && node.components['particle-system'];
                    if (ps) ps.startParticles();
                });
                window.registerTimeout(() => {
                    particleNodes.forEach(node => {
                        const ps = node.components && node.components['particle-system'];
                        if (ps) ps.stopParticles();
                    });
                    cp.setAttribute('visible', 'false');
                }, 5000);
            }
        },

        displayRankings: function(rankings, rankingDisplayId) {
            const display = document.getElementById(rankingDisplayId);
            if (!display) return;
            while (display.firstChild) display.removeChild(display.firstChild);

            const header = document.createElement('a-text');
            header.setAttribute('value', `Stage ${window.currentStage} Ranking`);
            header.setAttribute('position', '0 0.65 0');
            header.setAttribute('align', 'center');
            header.setAttribute('color', window.currentStage === 2 ? '#FF6600' : '#00FF00');
            header.setAttribute('width', '4');
            header.setAttribute('font', 'mozillavr');
            header.setAttribute('shader', 'msdf');
            display.appendChild(header);

            const currentId = window.lastSavedScoreId || null;
            rankings.forEach((item, i) => {
                const isMine = (currentId && item.id === currentId) || (Math.abs(item.score - window.totalScore) < 0.01);
                const txt = document.createElement('a-text');
                txt.setAttribute('value', `${i+1}. ${item.score.toFixed(1)}pt  Combo:${item.max_combo||0}  Zombies:${item.enemies_defeated||0}`);
                txt.setAttribute('position', `0 ${0.1 - i * 0.25} 0`);
                txt.setAttribute('align', 'center');
                txt.setAttribute('color', isMine ? '#FF1493' : '#FFFFFF');
                txt.setAttribute('width', '4.5');
                txt.setAttribute('font', 'roboto');
                txt.setAttribute('shader', 'msdf');
                display.appendChild(txt);
            });
        },
    });

    // ─────────────────────────────────────────
    // result-menu コンポーネント
    // ─────────────────────────────────────────
    AFRAME.registerComponent('result-menu', {
        init: function() {
            this.handleNextStage = this.handleNextStage.bind(this);
            this.handleGameOver  = this.handleGameOver.bind(this);

            const nsBtn = document.getElementById('nextStageButton');
            if (nsBtn) {
                nsBtn.addEventListener('click', this.handleNextStage);
                nsBtn.addEventListener('touchstart', (e) => { e.preventDefault(); e.stopPropagation(); this.handleNextStage(); }, { passive: false });
            }
            const goBtn = document.getElementById('gameOverButton');
            if (goBtn) {
                goBtn.addEventListener('click', this.handleGameOver);
                goBtn.addEventListener('touchstart', (e) => { e.preventDefault(); e.stopPropagation(); this.handleGameOver(); }, { passive: false });
            }
        },

        _fadeOverlayIn: function(callback) {
            const fo = document.getElementById('fadeOverlay');
            const fp = fo ? fo.querySelector('a-plane') : null;
            if (fo) fo.setAttribute('visible', true);
            if (fp) fp.setAttribute('animation__fadein', { property: 'material.opacity', from: 0, to: 1, dur: 1000, easing: 'easeInQuad' });
            window.registerTimeout(callback, 1100);
        },

        _fadeOverlayOut: function() {
            const fo = document.getElementById('fadeOverlay');
            const fp = fo ? fo.querySelector('a-plane') : null;
            if (fp) fp.setAttribute('animation__fadeout', { property: 'material.opacity', from: 1, to: 0, dur: 1000, easing: 'easeOutQuad' });
            window.registerTimeout(() => { if (fo) fo.setAttribute('visible', false); }, 1100);
        },

        handleNextStage: function() {
            if (window.currentStage !== 1 || window.__closingInProgress) return;
            const prevBgm = document.getElementById(window.STAGE_CONFIG[1].bgmId);
            this._fadeOverlayIn(() => {
                // ステージ1リザルト非表示
                const rsS1 = document.getElementById('resultMenu_s1');
                if (rsS1) rsS1.setAttribute('visible', false);

                // 全モデル削除
                const sceneEl = document.querySelector('a-scene');
                ['modelGroup_01','modelGroup_02','modelGroup_03','modelGroup_04','modelGroup_05','modelGroup_boss'].forEach(id => {
                    sceneEl.querySelectorAll(`#${id}`).forEach(m => {
                        if (m.components && m.components['approach-camera']) m.removeAttribute('approach-camera');
                        if (m.parentNode) m.parentNode.removeChild(m);
                    });
                });

                // shootキャッシュクリア
                const cam = document.getElementById('my_camera');
                if (cam && cam.components && cam.components['shoot']) {
                    cam.components['shoot'].modelsList = null;
                    cam.components['shoot'].modelsCache = {};
                    cam.components['shoot'].hitBoxCache = {};
                }

                // ボール削除
                window.activeBalls.forEach(bd => { if (bd.ball && bd.ball.parentNode) bd.ball.parentNode.removeChild(bd.ball); });
                window.activeBalls = [];

                // タイマークリア
                if (window.activeTimers) { window.activeTimers.forEach(t => clearTimeout(t)); window.activeTimers = []; }

                // ステージ2に切り替え
                window.currentStage    = 2;
                window.gameStarted     = false;
                window.gameEnded       = false;
                window.totalScore      = 0;
                window.comboCount      = 0;
                window.maxComboCount   = 0;
                window.enemiesDefeated = 0;
                window.bossSpawned     = false;
                window.respawningModels = {};
                window.usedPatterns    = {};
                window.respawnModelGlobal = null;

                // 背景・BGM切り替え
                const sky = document.getElementById('aSky');
                if (sky) sky.setAttribute('src', '#sky_s2');
                window.fadeOutAndStopAudio(prevBgm, 5000, () => {
                    const bgm = document.getElementById('sound_bgm_s2');
                    if (bgm) { bgm.volume = 0.7; bgm.currentTime = 0; bgm.play().catch(() => {}); }
                });

                // スコアリセット
                const scoreEl = document.getElementById('currentScore');
                if (scoreEl) scoreEl.setAttribute('value', 'SCORE: 0.0');

                // フェードアウト（明るく戻す）
                this._fadeOverlayOut();

                // ゲーム開始
                window.gameStarted  = true;
                window.gameTimeLeft = window.STAGE_CONFIG[2].timeLimit;

                const timerDisplay = document.getElementById('timerDisplay');
                if (timerDisplay) timerDisplay.setAttribute('visible', true);

                const startMenuEl = document.getElementById('startMenu');
                if (startMenuEl && startMenuEl.components['start-menu']) {
                    const sm = startMenuEl.components['start-menu'];
                    window.updateAmmoDisplay();
                    sm.activateModels(sceneEl);
                    window.registerTimeout(() => sm.startTimer(), 200);
                }
            });
        },

        handleGameOver: function(event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            if (window.__closingInProgress) return;
            window.__closingInProgress = true;
            window.gameStarted = false;
            window.gameEnded = true;
            if (window.gameTimer) {
                clearInterval(window.gameTimer);
                window.gameTimer = null;
            }
            if (window.activeTimers) {
                window.activeTimers.forEach(t => clearTimeout(t));
                window.activeTimers = [];
            }

            const currentBgmCfg = window.STAGE_CONFIG[window.currentStage];
            if (currentBgmCfg) {
                const currentBgm = document.getElementById(currentBgmCfg.bgmId);
                window.fadeOutAndStopAudio(currentBgm, 5000);
            }

            this._fadeOverlayIn(() => {
                window.registerTimeout(() => this.performClose(), 5000);
            });
        },

        performClose: function() {
            const sceneEl = document.querySelector('a-scene');
            const isVR = sceneEl && sceneEl.is('vr-mode');
            const finish = () => {
                this._fadeOverlayOut();
                window.registerTimeout(() => {
                    window.__closingInProgress = false;
                }, 200);
            };

            if (isVR) {
                sceneEl.exitVR().then(() => window.registerTimeout(finish, 300)).catch(finish);
            } else {
                finish();
            }
        }
    });

    // ─────────────────────────────────────────
    // shoot コンポーネント
    // ─────────────────────────────────────────
    AFRAME.registerComponent('shoot', {
        init: function () {
            this.shoot        = this.shoot.bind(this);
            this.onKeyDown    = this.onKeyDown.bind(this);
            this.onClick      = this.onClick.bind(this);
            this.onTouchStart = this.onTouchStart.bind(this);
            this.lastShootTime = 0;
            this.shootCooldown = 200;
            window.addEventListener('keydown', this.onKeyDown);
            this.setupCanvasListeners();
            this.setupControllerListeners();
            this.el.sceneEl.addEventListener('enter-vr', () => {
                window.registerTimeout(() => { this.setupCanvasListeners(); this.setupControllerListeners(); }, 100);
            });
            this.el.sceneEl.addEventListener('exit-vr', () => {
                window.registerTimeout(() => this.setupCanvasListeners(), 100);
            });
        },

        tick: function(time, timeDelta) {
            const cnt = window.activeBalls.length;
            if (cnt === 0) return;
            const now = Date.now();
            for (let i = cnt - 1; i >= 0; i--) {
                const bd = window.activeBalls[i];
                if (bd && bd.ball && bd.ball.parentNode) this.updateBallPosition(bd, now);
                else window.activeBalls.splice(i, 1);
            }
        },

        updateBallPosition: function(ballData, currentTime) {
            if (ballData.hasHit) return;
            const { ball, startPos, velocity, startTime, direction } = ballData;
            const elapsedTime = (currentTime - startTime) / 1000;
            if (!ballData._currentPos) ballData._currentPos = new THREE.Vector3();
            const cp = ballData._currentPos;
            cp.set(
                startPos.x + velocity.x * elapsedTime,
                startPos.y + velocity.y * elapsedTime - 2.45 * elapsedTime * elapsedTime,
                startPos.z + velocity.z * elapsedTime
            );
            ball.object3D.position.copy(cp);
            ballData.frameCount++;

            if (!this.modelsList) {
                this.modelsList = [
                    { id: 'modelGroup_01', hitBoxId: 'hit-boxed_01', radius: 0.75, height: 1.5 },
                    { id: 'modelGroup_02', hitBoxId: 'hit-boxed_02', radius: 0.75, height: 1.5 },
                    { id: 'modelGroup_03', hitBoxId: 'hit-boxed_03', radius: 0.75, height: 1.5 },
                    { id: 'modelGroup_04', hitBoxId: 'hit-boxed_04', radius: 0.75, height: 1.5 },
                    { id: 'modelGroup_05', hitBoxId: 'hit-boxed_05', radius: 0.75, height: 1.5 },
                    { id: 'modelGroup_boss', hitBoxId: 'hit-boxed_boss', radius: 1.8, height: 3.2 },
                ];
                this.modelsCache = {};
                this.hitBoxCache = {};
            }

            for (let i = 0; i < this.modelsList.length; i++) {
                const mi = this.modelsList[i];
                let mg = this.modelsCache[mi.id];
                if (!mg || !mg.parentNode) { mg = document.getElementById(mi.id); if (mg) this.modelsCache[mi.id] = mg; }
                if (!mg || !mg.parentNode || !mg.object3D.visible) continue;

                let hb = this.hitBoxCache[mi.hitBoxId];
                if (!hb || !hb.parentNode) { hb = mg.querySelector(`#${mi.hitBoxId}`); if (hb) this.hitBoxCache[mi.hitBoxId] = hb; }
                if (!hb) continue;

                const mp = mg.object3D.position;
                const dx = cp.x - mp.x;
                const dz = cp.z - mp.z;
                if (Math.abs(dx) > mi.radius + 0.5 || Math.abs(dz) > mi.radius + 0.5) continue;

                const cy = mp.y + 1.0;
                const hh = mi.height / 2;
                if (cp.y < cy - hh || cp.y > cy + hh) continue;

                if (dx * dx + dz * dz < mi.radius * mi.radius) {
                    ballData.hasHit = true;
                    hb.emit('ball-hit');
                    ball.removeAttribute('animation__spin');
                    const mesh = ball.getObject3D('mesh');
                    if (mesh) mesh.traverse(n => { if (n.isMesh && n.material) { if (window.cachedHitEmissiveColor) n.material.emissive = window.cachedHitEmissiveColor; n.material.emissiveIntensity = 1.5; } });
                    ball.setAttribute('animation__fade', { property: 'scale', to: '0 0 0', dur: 300, easing: 'easeInQuad' });
                    window.registerTimeout(() => { if (ball.parentNode) ball.parentNode.removeChild(ball); }, 300);
                    return;
                }
            }

            const ddx = cp.x - startPos.x, ddy = cp.y - startPos.y, ddz = cp.z - startPos.z;
            if (cp.y < -2 || elapsedTime > 3 || ddx*ddx+ddy*ddy+ddz*ddz > 400) {
                if (!ballData.hasHit) { window.comboCount = 0; window.lastBallHit = false; }
                if (ball.parentNode) ball.parentNode.removeChild(ball);
                ballData.hasHit = true;
            }
        },

        setupCanvasListeners: function() {
            const canvas = this.el.sceneEl.canvas;
            if (canvas) {
                canvas.removeEventListener('click', this.onClick, true);
                canvas.removeEventListener('touchstart', this.onTouchStart, true);
                canvas.addEventListener('click', this.onClick);
                canvas.addEventListener('touchstart', this.onTouchStart, { passive: false });
            }
        },

        setupControllerListeners: function() {
            const lc = document.getElementById('leftController');
            const rc = document.getElementById('rightController');
            if (lc) { lc.removeEventListener('triggerdown', this.shoot); lc.addEventListener('triggerdown', this.shoot); }
            if (rc) { rc.removeEventListener('triggerdown', this.shoot); rc.addEventListener('triggerdown', this.shoot); }
        },

        onKeyDown: function(e) { if (e.code === 'Space') { e.preventDefault(); this.shoot(e); } },
        onClick: function(e) {
            if (Date.now() - this.lastShootTime < this.shootCooldown) { e.stopPropagation(); e.preventDefault(); return; }
            this.shoot(e);
        },
        onTouchStart: function(e) {
            if (Date.now() - this.lastShootTime < this.shootCooldown) { e.stopPropagation(); e.preventDefault(); return; }
            e.preventDefault(); e.stopPropagation(); this.shoot(e);
        },

        shoot: function(event) {
            if (event && event.stopPropagation) event.stopPropagation();
            if (event && event.preventDefault) event.preventDefault();
            if (!window.gameStarted || window.gameEnded || window.activeBalls.length >= 2) return;
            if (!window.tryConsumeAmmo(window.selectedGun)) return;
            this.lastShootTime = Date.now();

            const sceneEl = this.el.sceneEl;
            const ball = document.createElement('a-entity');
            ball.setAttribute('gltf-model', window.GUN_CONFIG[window.selectedGun].ball);
            ball.setAttribute('scale', '0.1 0.1 0.1');
            ball.addEventListener('model-loaded', () => {
                const mesh = ball.getObject3D('mesh');
                if (mesh) mesh.traverse(n => { if (n.isMesh && n.material) { if (window.cachedBallEmissiveColor) n.material.emissive = window.cachedBallEmissiveColor; n.material.emissiveIntensity = 0.1; n.material.needsUpdate = true; } });
            }, { once: true });
            ball.setAttribute('animation__spin', { property: 'rotation', to: '-1080 0 0', dur: 1000, loop: true, easing: 'linear' });

            let position = new THREE.Vector3();
            let direction = new THREE.Vector3();
            if (event && event.type === 'triggerdown') {
                const ctrl = event.target;
                ctrl.object3D.getWorldPosition(position);
                const rc = ctrl.components.raycaster;
                if (rc && rc.raycaster) { direction.copy(rc.raycaster.ray.direction).normalize(); }
                else { direction.set(0,0,-1); direction.applyQuaternion(ctrl.object3D.quaternion); direction.normalize(); }
            } else {
                const isVR = sceneEl.is('vr-mode');
                if (isVR && sceneEl.camera) { sceneEl.camera.getWorldPosition(position); direction.set(0,0,-1); direction.applyQuaternion(sceneEl.camera.quaternion); direction.normalize(); }
                else { this.el.object3D.getWorldPosition(position); direction.set(0,0,-1); direction.applyQuaternion(this.el.object3D.quaternion); direction.normalize(); }
            }

            const startPos = position.clone().add(direction.clone().multiplyScalar(0.3));
            ball.setAttribute('position', `${startPos.x} ${startPos.y} ${startPos.z}`);
            sceneEl.appendChild(ball);
            window.activeBalls.push({ ball, startPos, velocity: direction.clone().multiplyScalar(40), direction, startTime: Date.now(), hasHit: false, frameCount: 0 });
        }
    });

    // ─────────────────────────────────────────
    // approach-camera コンポーネント
    // ─────────────────────────────────────────
    AFRAME.registerComponent('approach-camera', {
        schema: {
            speed:       { type: 'number',  default: 0.25 },
            startPos:    { type: 'vec3',    default: {x:0,y:0,z:-5} },
            endPos:      { type: 'vec3',    default: {x:0,y:0,z:-1} },
            useCamera:   { type: 'boolean', default: true },
            autoRespawn: { type: 'boolean', default: true },
            waitTime:    { type: 'number',  default: 3000 }
        },

        init: function() {
            this.isMoving = true; this.hasReachedEnd = false; this.reachedTime = 0;
            this.startPosition = null; this.targetPosition = null; this.isRespawning = false;
            this.isPlayingAlert = false; this.alertSound = null;
            this._direction = new THREE.Vector3();
            const sp = this.data.startPos;
            this.startPosition = (sp.x === 0 && sp.y === 0 && sp.z === -5)
                ? (() => { const p = this.el.getAttribute('position'); return new THREE.Vector3(p.x,p.y,p.z); })()
                : new THREE.Vector3(sp.x, sp.y, sp.z);
            this.alertSound = document.getElementById('sound_alert');
        },

        tick: function(time, timeDelta) {
            if (window.gameEnded) { this.isMoving = false; return; }
            if (!window.gameStarted) return;
            if (!this.isMoving) {
                if (this.hasReachedEnd && this.data.autoRespawn && !this.isRespawning && !window.gameEnded) {
                    if (Date.now() - this.reachedTime >= this.data.waitTime) { this.isRespawning = true; this.despawnAndRespawn(); }
                }
                return;
            }
            if (!this.targetPosition) {
                if (this.data.useCamera) {
                    const cam = this.el.sceneEl.camera ? this.el.sceneEl.camera.el : document.querySelector('[camera]');
                    if (!cam) return;
                    this.targetPosition = new THREE.Vector3();
                    cam.object3D.getWorldPosition(this.targetPosition);
                    this.targetPosition.y = 0;
                } else {
                    const ep = this.data.endPos;
                    this.targetPosition = new THREE.Vector3(ep.x, ep.y, ep.z);
                }
            }
            const mp = this.el.object3D.position;
            const dir = this._direction;
            dir.subVectors(this.targetPosition, mp);
            dir.y = 0;
            const dist = dir.length();
            const isBoss = this.el.id === 'modelGroup_boss';
            if (dist < (isBoss ? 1.6 : 0.99)) {
                this.isMoving = false; this.hasReachedEnd = true; this.reachedTime = Date.now(); return;
            }
            dir.normalize().multiplyScalar(this.data.speed * (timeDelta / 1000));
            mp.add(dir);
            this.el.object3D.rotation.y = Math.atan2(dir.x, dir.z);
        },

        stop: function() { this.isMoving = false; },

        despawnAndRespawn: function() {
            if (window.gameEnded) return;
            const mg = this.el;
            const modelId = mg.id;
            const me = mg.querySelector('[gltf-model]');
            const src = me ? me.getAttribute('gltf-model') : null;
            if (!src) return;
            mg.setAttribute('animation__fadeout', { property: 'scale', to: '0 0 0', dur: 500, easing: 'easeInQuad' });
            window.registerTimeout(() => {
                if (window.usedPatterns && window.usedPatterns[modelId] !== undefined) delete window.usedPatterns[modelId];
                if (mg.parentNode) mg.parentNode.removeChild(mg);
                if (window.respawnModelGlobal) window.respawnModelGlobal(modelId, src);
            }, 500);
        },

        remove: function() { this._direction = null; this.startPosition = null; this.targetPosition = null; }
    });

    // ─────────────────────────────────────────
    // hit-box コンポーネント
    // ─────────────────────────────────────────
    AFRAME.registerComponent('hit-box', {
        schema: { isBoss: { type: 'boolean', default: false } },

        init: function () {
            const modelGroup = this.el.parentEl;
            const modelEntity = modelGroup.querySelector('[gltf-model]');
            let hitFlag = false;
            let hitCount = 0;
            const modelId  = modelGroup.id;
            const gltfSrc  = modelEntity ? modelEntity.getAttribute('gltf-model') : null;
            const isBoss   = this.data.isBoss;

            if (!window.respawnModelGlobal) window.respawnModelGlobal = this.respawnModel.bind(this);

            this.el.addEventListener('ball-hit', () => {
                if (hitFlag) return;
                hitCount++;
                const stageCfg   = window.STAGE_CONFIG[window.currentStage];
                const reqHits    = isBoss ? stageCfg.bossRequiredHits : stageCfg.requiredHits;

                if (hitCount < reqHits) {
                    const hitSound = document.getElementById('sound_hit');
                    if (hitSound) { hitSound.currentTime = 0; hitSound.play().catch(() => {}); }
                    if (modelEntity) {
                        const colors = window.cachedFlashColors || {
                            blue: new THREE.Color(0x0000FF), green: new THREE.Color(0x00FF00),
                            yellow: new THREE.Color(0xFFFF00), red: new THREE.Color(0xFF0000),
                            black: new THREE.Color(0x000000)
                        };
                        let fc = colors.red;
                        if (isBoss) {
                            if (hitCount <= 3) fc = colors.blue;
                            else if (hitCount <= 6) fc = colors.green;
                            else if (hitCount <= 10) fc = colors.yellow;
                        }
                        const mesh = modelEntity.getObject3D('mesh');
                        if (mesh) mesh.traverse(n => {
                            if (n.isMesh && n.material) {
                                const orig = n.material.emissive ? n.material.emissive.clone() : colors.black;
                                n.material.emissive = fc; n.material.emissiveIntensity = 0.5;
                                window.registerTimeout(() => { n.material.emissive = orig; n.material.emissiveIntensity = 0; }, 200);
                            }
                        });
                    }
                    return;
                }

                hitFlag = true;
                window.enemiesDefeated++;

                const hitBox = this.el;
                if (hitBox && hitBox.parentNode) hitBox.parentNode.removeChild(hitBox);

                const ac = modelGroup.components['approach-camera'];
                if (ac && ac.isPlayingAlert && ac.alertSound) {
                    ac.alertSound.pause(); ac.alertSound.currentTime = 0; ac.isPlayingAlert = false;
                    if (window.currentAlertModel === modelId) { window.alertSoundPlaying = false; window.currentAlertModel = null; }
                }

                const hitSound = document.getElementById('sound_hit');
                if (hitSound) { hitSound.currentTime = 0; hitSound.play().catch(() => {}); }

                const sceneEl = document.querySelector('a-scene');
                const cam = sceneEl.camera ? sceneEl.camera.el : document.querySelector('[camera]');
                let distance = 0;
                if (cam) {
                    modelGroup.object3D.getWorldPosition(window._cachedModelPos);
                    cam.object3D.getWorldPosition(window._cachedCameraPos);
                    distance = window._cachedModelPos.distanceTo(window._cachedCameraPos);
                }

                let baseScore = Math.round(distance * 100) / 10;
                if (isBoss) baseScore *= 10;

                window.comboCount++;
                window.lastBallHit = true;
                let mult = 1.0, bonus = '', tier = 0;
                if (window.comboCount >= 6)      { mult = 1.3; bonus = 'x1.3'; tier = 3; }
                else if (window.comboCount >= 4) { mult = 1.2; bonus = 'x1.2'; tier = 2; }
                else if (window.comboCount >= 2) { mult = 1.1; bonus = 'x1.1'; tier = 1; }

                const finalScore = baseScore * mult;
                window.totalScore += finalScore;
                if (window.comboCount > window.maxComboCount) window.maxComboCount = window.comboCount;

                let ammoReward = 0;
                if (gltfSrc === '#model_s1_01') ammoReward = 5;
                else if (gltfSrc === '#model_s2_01') ammoReward = 10;
                if (ammoReward > 0) {
                    window.spawnAmmoPickupToCamera(modelGroup.object3D.position.clone(), ammoReward);
                }

                const cst = document.getElementById('currentScore');
                if (cst) cst.setAttribute('value', `SCORE: ${window.totalScore.toFixed(1)}`);

                let sw = distance > 8 ? 16 : distance > 4 ? 10 : 6;
                if (bonus) sw *= 1.1;

                const st = document.createElement('a-text');
                st.setAttribute('value', bonus ? `Combo ${window.comboCount} ${bonus}\n${finalScore.toFixed(1)}pt` : `${finalScore.toFixed(1)}pt`);
                st.setAttribute('align', 'center');
                st.setAttribute('color', bonus ? '#FF6600' : '#FFD700');
                st.setAttribute('width', sw);
                st.setAttribute('font', 'mozillavr');
                st.setAttribute('shader', 'msdf');
                st.setAttribute('anchor', 'center');
                modelGroup.object3D.getWorldPosition(window._cachedModelPos);
                st.setAttribute('position', `${window._cachedModelPos.x} ${window._cachedModelPos.y + 0.5} ${window._cachedModelPos.z}`);
                st.setAttribute('face-camera', '');
                sceneEl.appendChild(st);

                if (bonus) {
                    const sm = 1.1 + (tier * 0.2);
                    st.setAttribute('scale', `${sm} ${sm} ${sm}`);
                    st.setAttribute('animation__scale', { property: 'scale', to: '1 1 1', dur: 400, easing: 'easeOutElastic' });
                }
                window.registerTimeout(() => {
                    const cp = st.getAttribute('position');
                    st.setAttribute('animation__scoreup', { property: 'position', to: `${cp.x} ${cp.y + 0.5} ${cp.z}`, dur: 1500, easing: 'easeOutQuad' });
                    st.setAttribute('animation__scorefade', { property: 'material.opacity', from: 1, to: 0, dur: 1500, easing: 'linear' });
                    window.registerTimeout(() => { if (st.parentNode) st.parentNode.removeChild(st); }, 1500);
                }, 100);

                if (ac) ac.stop();

                if (modelEntity) {
                    modelEntity.removeAttribute('animation-mixer');
                    window.registerTimeout(() => {
                        modelEntity.setAttribute('animation-mixer', 'clip: anime02; loop: repeat; timeScale: 1');
                        if (isBoss) {
                            const ds = document.getElementById('sound_zombie_die');
                            if (ds) { ds.currentTime = 0; ds.play().catch(() => {}); }
                        }
                    }, 50);
                }

                window.registerTimeout(() => {
                    if (modelGroup && modelGroup.parentNode) {
                        modelGroup.setAttribute('animation__modelfadeout', { property: 'scale', to: '0 0 0', dur: 500, easing: 'easeInQuad' });
                        window.registerTimeout(() => {
                            if (modelGroup.parentNode) {
                                modelGroup.parentNode.removeChild(modelGroup);
                                if (window.usedPatterns && window.usedPatterns[modelId] !== undefined) delete window.usedPatterns[modelId];
                                if (!window.respawningModels) window.respawningModels = {};
                                if (window.respawningModels[modelId]) return;
                                window.respawningModels[modelId] = true;
                                window.registerTimeout(() => {
                                    this.respawnModel(modelId, gltfSrc);
                                    delete window.respawningModels[modelId];
                                }, 4000);
                            }
                        }, 500);
                    }
                }, 1500);
            });
        },

        respawnModel: function(modelId, gltfSrc) {
            if (window.gameEnded || !window.gameStarted) return;
            const sceneEl = document.querySelector('a-scene');
            const existing = document.getElementById(modelId);
            if (existing) {
                if (existing.object3D) existing.object3D.traverse(n => { if (n.geometry) n.geometry.dispose(); if (n.material) { if (Array.isArray(n.material)) n.material.forEach(m => m.dispose()); else n.material.dispose(); } });
                if (existing.parentNode) existing.parentNode.removeChild(existing);
                window.registerTimeout(() => this.createNewModel(modelId, gltfSrc, sceneEl), 100);
                return;
            }
            this.createNewModel(modelId, gltfSrc, sceneEl);
        },

        createNewModel: function(modelId, gltfSrc, sceneEl) {
            if (window.gameEnded || !window.gameStarted) return;
            const pats = [
                { startPos: {x:-3,y:0,z:-3}, speed:0.25, useCamera:true, waitTime:4000 },
                { startPos: {x: 0,y:0,z:-4}, speed:0.25, useCamera:true, waitTime:4000 },
                { startPos: {x: 3,y:0,z:-3}, speed:0.25, useCamera:true, waitTime:4000 },
                { startPos: {x: 4,y:0,z: 0}, speed:0.25, useCamera:true, waitTime:4000 },
                { startPos: {x: 5,y:0,z: 3}, speed:0.25, useCamera:true, waitTime:4000 },
                { startPos: {x:-6,y:0,z: 0}, speed:0.25, useCamera:true, waitTime:4000 },
                { startPos: {x: 0,y:0,z: 4}, speed:0.15, useCamera:true, waitTime:4000 },
                { startPos: {x: 3,y:0,z: 6}, speed:0.25, useCamera:true, waitTime:4000 },
                { startPos: {x:-4,y:0,z: 6}, speed:0.25, useCamera:true, waitTime:4000 },
                { startPos: {x:-7,y:0,z: 2}, speed:0.25, useCamera:true, waitTime:4000 },
            ];
            const speedMult = window.currentStage === 2 ? 1.2 : 1.0;
            const { pattern: p } = window.getAvailablePattern(pats, modelId);
            const sp = p.startPos;
            const rot = Math.atan2(sp.x, -sp.z) * (180 / Math.PI);

            const entity = document.createElement('a-entity');
            entity.setAttribute('id', modelId);
            entity.setAttribute('position', `${sp.x} ${sp.y} ${sp.z}`);
            entity.setAttribute('rotation', `0 ${rot} 0`);
            entity.setAttribute('scale', '0 0 0');
            entity.setAttribute('approach-camera', { speed: p.speed * speedMult, startPos: sp, useCamera: true, autoRespawn: true, waitTime: p.waitTime });

            const me = document.createElement('a-entity');
            me.setAttribute('gltf-model', gltfSrc);
            me.setAttribute('animation-mixer', 'clip: anime01; loop: repeat');
            me.setAttribute('enhance-materials', '');
            entity.appendChild(me);

            const hb = document.createElement('a-entity');
            hb.setAttribute('id', modelId.replace('modelGroup', 'hit-boxed'));
            hb.setAttribute('hit-box', '');
            hb.setAttribute('position', '0 1.0 0');
            const cyl = document.createElement('a-entity');
            cyl.setAttribute('geometry', 'primitive: cylinder');
            cyl.setAttribute('material', 'color: blue; opacity: 0.0; transparent: true');
            cyl.setAttribute('scale', '0.75 1.5 0.75');
            cyl.setAttribute('class', 'collidable');
            hb.appendChild(cyl);
            entity.appendChild(hb);
            sceneEl.appendChild(entity);

            window.registerTimeout(() => {
                entity.setAttribute('animation__fadein', { property: 'scale', to: '1 1 1', dur: 1000, easing: 'easeOutQuad' });
            }, 100);
        }
    });

    // ─────────────────────────────────────────
    // auto-enter-vr コンポーネント
    // ─────────────────────────────────────────
    AFRAME.registerComponent('auto-enter-vr', {
        init: function () {
            const sceneEl = this.el;
            sceneEl.addEventListener('loaded', () => {
                if (typeof THREE !== 'undefined') {
                    window.cachedBallEmissiveColor   = window.cachedBallEmissiveColor   || new THREE.Color(0x444444);
                    window.cachedHitEmissiveColor    = window.cachedHitEmissiveColor    || new THREE.Color(0xFFFFFF);
                    window.cachedEnhanceMaterialColor = window.cachedEnhanceMaterialColor || new THREE.Color(0x444444);
                    if (!window.cachedFlashColors) window.cachedFlashColors = {
                        blue: new THREE.Color(0x0000FF), green: new THREE.Color(0x00FF00),
                        yellow: new THREE.Color(0xFFFF00), red: new THREE.Color(0xFF0000),
                        black: new THREE.Color(0x000000)
                    };
                }
                if (navigator.xr) {
                    navigator.xr.isSessionSupported('immersive-vr').then(supported => {
                        if (supported) window.registerTimeout(() => sceneEl.enterVR(), 1000);
                    }).catch(() => {});
                }
            });
        }
    });

    // vr-controller コンポーネント
    AFRAME.registerComponent('vr-controller', {
        dependencies: ['raycaster'],
        init: function () {}
    });
</script>
