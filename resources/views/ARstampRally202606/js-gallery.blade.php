        // ========== maker00 ギャラリー機能 ==========
        // marker-00 が見つかったとき、
        //   - Model_00 を (0,0,0) に固定表示（anime01ループ + gallery-hitbox）
        //   - 捕獲済み選択モデル最大4体を GALLERY_POSITIONS に配置（anime01ループ + gallery-hitbox）
        //   - ボールヒット時: anime02一度再生 → anime01ループに戻る（スタンプ取得なし）

        (function () {
            var GALLERY_POSITIONS = [
                { x: 0, y: 0, z:  1 },
                { x: 0, y: 0, z: -1 },
                { x:  1, y: 0, z: 0 },
                { x: -1, y: 0, z: 0 }
            ];
            var DEBOUNCE_MS      = 300;
            var LOAD_INTERVAL_MS = 500;

            var galleryCache  = {};   // { stampId: entityElement }
            var galleryMixers = [];
            var markerVisible = false;
            var debounceTimer = null;
            var loadQueue     = [];
            var loadingActive = false;

            // --- Model_00 管理変数 ---
            var model00Entity = null;
            var model00Mixer  = null;
            var model00Action01 = null;
            var model00Action02 = null;

            var marker00 = document.getElementById('marker-00');
            if (!marker00) return;

            // --- Model_00 初期化 ---
            model00Entity = document.getElementById('model-00');
            if (model00Entity) {
                model00Entity.addEventListener('model-loaded', function () {
                    var model = model00Entity.getObject3D('mesh');
                    if (!model || !model.animations || model.animations.length === 0) return;

                    model.traverse(function (node) {
                        if (node.isMesh) node.frustumCulled = false;
                    });

                    model00Mixer = new THREE.AnimationMixer(model);
                    galleryMixers.push(model00Mixer);
                    window.galleryMixers = galleryMixers;

                    model00Entity._galleryMixer   = model00Mixer;

                    var clip01 = THREE.AnimationClip.findByName(model.animations, 'anime01') || model.animations[0];
                    var clip02 = THREE.AnimationClip.findByName(model.animations, 'anime02')
                               || (model.animations.length > 1 ? model.animations[1] : model.animations[0]);

                    model00Action01 = model00Mixer.clipAction(clip01);
                    model00Action01.setLoop(THREE.LoopRepeat, Infinity);
                    model00Action01.stop();

                    model00Action02 = model00Mixer.clipAction(clip02);
                    model00Action02.setLoop(THREE.LoopOnce, 1);
                    model00Action02.clampWhenFinished = true;
                    model00Action02.stop();

                    model00Entity._galleryAction01 = model00Action01;
                    model00Entity._galleryAction02 = model00Action02;

                    // gallery-hitbox を付与
                    model00Entity.setAttribute('gallery-hitbox', 'width: 1.0; height: 2.0; depth: 1.0');

                    // マーカーが既に見えていれば即再生
                    if (markerVisible) {
                        model00Action01.reset();
                        model00Action01.play();
                    }
                });

                model00Entity.addEventListener('model-unloaded', function () {
                    if (model00Mixer) {
                        var idx = galleryMixers.indexOf(model00Mixer);
                        if (idx !== -1) galleryMixers.splice(idx, 1);
                        window.galleryMixers = galleryMixers;
                    }
                    // gallery-hitbox を外す
                    try { model00Entity.removeAttribute('gallery-hitbox'); } catch (e) {}
                    model00Mixer    = null;
                    model00Action01 = null;
                    model00Action02 = null;
                });
            }

            // --- markerFound（デバウンス付き） ---
            marker00.addEventListener('markerFound', function () {
                markerVisible = true;
                if (window.startGalleryMixerLoop) window.startGalleryMixerLoop();
                if (model00Entity) model00Entity.setAttribute('visible', 'true');
                if (debounceTimer) clearTimeout(debounceTimer);
                debounceTimer = setTimeout(onMarkerConfirmed, DEBOUNCE_MS);
            });

            // --- markerLost（モデル非表示のみ、破棄しない） ---
            marker00.addEventListener('markerLost', function () {
                markerVisible = false;
                if (debounceTimer) { clearTimeout(debounceTimer); debounceTimer = null; }
                loadQueue     = [];
                loadingActive = false;
                hideGallery();
                if (window.stopGalleryMixerLoop) window.stopGalleryMixerLoop();
            });

            // --- デバウンス後に呼ばれる本処理 ---
            function onMarkerConfirmed() {
                debounceTimer = null;
                if (!markerVisible) return;

                var ids = (typeof getGallerySelection === 'function') ? getGallerySelection() : [];

                // キャッシュ済みで選択外のモデルは非表示
                Object.keys(galleryCache).forEach(function (key) {
                    if (ids.indexOf(key) === -1) {
                        try { galleryCache[key].setAttribute('visible', 'false'); } catch (e) {}
                    }
                });

                // キャッシュ済みエンティティを再表示（位置を GALLERY_POSITIONS で更新）
                var newIds = [];
                ids.forEach(function (stampId, index) {
                    var pos = GALLERY_POSITIONS[index];
                    if (!pos) return;
                    if (galleryCache[stampId]) {
                        var entity = galleryCache[stampId];
                        entity.setAttribute('position', pos.x + ' ' + pos.y + ' ' + pos.z);
                        entity.setAttribute('visible', 'true');
                    } else {
                        newIds.push({ stampId: stampId, posIndex: index });
                    }
                });

                // 新規モデルは逐次ロード
                if (newIds.length > 0) {
                    loadQueue = newIds.slice();
                    if (!loadingActive) loadNextModel();
                }
            }

            // --- 逐次ロード: 1体ずつ LOAD_INTERVAL_MS 間隔で生成 ---
            function loadNextModel() {
                if (loadQueue.length === 0 || !markerVisible) {
                    loadingActive = false;
                    return;
                }
                loadingActive = true;

                var item    = loadQueue.shift();
                var stampId = item.stampId;
                var pos     = GALLERY_POSITIONS[item.posIndex];
                if (!pos) { setTimeout(loadNextModel, 50); return; }

                // 二重チェック
                if (galleryCache[stampId]) {
                    galleryCache[stampId].setAttribute('visible', 'true');
                    setTimeout(loadNextModel, 50);
                    return;
                }

                var modelPath = STAMPS[stampId] ? STAMPS[stampId].model : null;
                if (!modelPath) { setTimeout(loadNextModel, 50); return; }

                var modelUrl = '{{ asset("cg") }}/' + modelPath;
                var entity   = document.createElement('a-entity');
                entity.setAttribute('position', pos.x + ' ' + pos.y + ' ' + pos.z);
                entity.setAttribute('scale', '0.6 0.6 0.6');
                entity.setAttribute('rotation', '0 0 0');
                entity.setAttribute('visible', markerVisible ? 'true' : 'false');

                entity.addEventListener('model-loaded', function () {
                    var model = entity.getObject3D('mesh');
                    if (!model || !model.animations || model.animations.length === 0) return;

                    model.traverse(function (node) {
                        if (node.isMesh) node.frustumCulled = false;
                    });

                    var mixer = new THREE.AnimationMixer(model);
                    entity._galleryMixer = mixer;
                    galleryMixers.push(mixer);
                    window.galleryMixers = galleryMixers;

                    var clip01 = THREE.AnimationClip.findByName(model.animations, 'anime01') || model.animations[0];
                    var clip02 = THREE.AnimationClip.findByName(model.animations, 'anime02')
                               || (model.animations.length > 1 ? model.animations[1] : model.animations[0]);

                    var action01 = mixer.clipAction(clip01);
                    action01.setLoop(THREE.LoopRepeat, Infinity);
                    action01.play();

                    var action02 = mixer.clipAction(clip02);
                    action02.setLoop(THREE.LoopOnce, 1);
                    action02.clampWhenFinished = true;
                    action02.stop();

                    entity._galleryAction01 = action01;
                    entity._galleryAction02 = action02;

                    // gallery-hitbox を付与
                    entity.setAttribute('gallery-hitbox', 'width: 1.0; height: 2.0; depth: 1.0');
                });

                entity.setAttribute('gltf-model', modelUrl);
                marker00.appendChild(entity);
                galleryCache[stampId] = entity;

                setTimeout(loadNextModel, LOAD_INTERVAL_MS);
            }

            // --- 全ギャラリーエンティティを非表示（破棄しない） ---
            function hideGallery() {
                Object.keys(galleryCache).forEach(function (key) {
                    try { galleryCache[key].setAttribute('visible', 'false'); } catch (e) {}
                });
                if (model00Entity) model00Entity.setAttribute('visible', 'false');
            }

            // --- ギャラリーヒット時アニメーション ---
            // pokeball-throwable の handleGalleryHit から呼ばれる
            window.playGalleryHitAnimation = function (entity) {
                if (!entity) return;
                var action01 = entity._galleryAction01;
                var action02 = entity._galleryAction02;
                var mixer    = entity._galleryMixer;
                if (!mixer || !action02) return;

                // anime01停止 → anime02再生
                if (action01) action01.stop();
                try {
                    action02.reset();
                    action02.setLoop(THREE.LoopOnce, 1);
                    action02.clampWhenFinished = true;
                    action02.play();

                    var resolved = false;
                    var onFinished = function (ev) {
                        if (ev && ev.action === action02) {
                            try { if (mixer) mixer.removeEventListener('finished', onFinished); } catch (e) {}
                            if (!resolved) {
                                resolved = true;
                                // anime01に戻す
                                try {
                                    action02.stop();
                                    if (action01) { action01.reset(); action01.play(); }
                                } catch (e) {}
                            }
                        }
                    };
                    mixer.addEventListener('finished', onFinished);

                    // タイムアウト保険
                    var clipDuration = 1.0;
                    try {
                        if (action02._clip && action02._clip.duration) clipDuration = action02._clip.duration;
                    } catch (e) {}
                    setTimeout(function () {
                        if (!resolved) {
                            resolved = true;
                            try { if (mixer) mixer.removeEventListener('finished', onFinished); } catch (e) {}
                            try {
                                action02.stop();
                                if (action01) { action01.reset(); action01.play(); }
                            } catch (e) {}
                        }
                    }, (clipDuration * 1000) + 120);
                } catch (e) {
                    // フォールバック: anime01再生
                    try { if (action01) { action01.reset(); action01.play(); } } catch (e2) {}
                }
            };

            // --- 外部からギャラリー再描画を呼べるように公開 ---
            window.refreshGallery = onMarkerConfirmed;

            // --- AnimationMixer更新はjs-init.blade.phpのupdateGalleryMixersに一本化 ---
        })();
