        // ========== maker00 ギャラリー機能 ==========
        // marker-00 が見つかったとき、捕獲済みモデルを Y軸方向に並べて anime03 でループ表示する
        // 修正: キャッシュ＋デバウンス＋逐次ロードでiPhone SEフリーズ対策

        (function () {
            var GALLERY_Y_SPACING = 0.35;
            var DEBOUNCE_MS       = 300;   // markerFound デバウンス
            var LOAD_INTERVAL_MS  = 500;   // 逐次ロード間隔

            var galleryCache  = {};        // { stampId: entityElement }
            var galleryMixers = [];
            var markerVisible = false;
            var debounceTimer = null;
            var loadQueue     = [];        // 逐次ロード用キュー
            var loadingActive = false;

            var marker00 = document.getElementById('marker-00');
            if (!marker00) return;

            // --- markerFound（デバウンス付き） ---
            marker00.addEventListener('markerFound', function () {
                markerVisible = true;
                if (debounceTimer) clearTimeout(debounceTimer);
                debounceTimer = setTimeout(onMarkerConfirmed, DEBOUNCE_MS);
            });

            // --- markerLost（モデル非表示のみ、破棄しない） ---
            marker00.addEventListener('markerLost', function () {
                markerVisible = false;
                if (debounceTimer) { clearTimeout(debounceTimer); debounceTimer = null; }
                // 逐次ロードキューを中断
                loadQueue = [];
                loadingActive = false;
                hideGallery();
            });

            // --- デバウンス後に呼ばれる本処理 ---
            function onMarkerConfirmed() {
                debounceTimer = null;
                if (!markerVisible) return;

                var captured = getCapturedAnimals202605();
                var ids = Object.keys(captured).filter(function (id) { return captured[id] === true; });
                if (ids.length === 0) return;

                // キャッシュ済みエンティティを再表示
                var newIds = [];
                ids.forEach(function (stampId, index) {
                    if (galleryCache[stampId]) {
                        // 既にキャッシュ済み → 位置更新してvisible=true
                        var entity = galleryCache[stampId];
                        entity.setAttribute('position', '0 ' + (3.4 - index * GALLERY_Y_SPACING) + ' 0');
                        entity.setAttribute('visible', 'true');
                    } else {
                        newIds.push({ stampId: stampId, index: index });
                    }
                });

                // 新規モデルは逐次ロード
                if (newIds.length > 0) {
                    loadQueue = newIds.slice(); // コピー
                    if (!loadingActive) loadNextModel();
                }
            }

            // --- 逐次ロード: 1体ずつ500ms間隔で生成 ---
            function loadNextModel() {
                if (loadQueue.length === 0 || !markerVisible) {
                    loadingActive = false;
                    return;
                }
                loadingActive = true;

                var item = loadQueue.shift();
                var stampId = item.stampId;
                var index   = item.index;

                // 二重チェック（ロード待ちの間にキャッシュされた可能性）
                if (galleryCache[stampId]) {
                    galleryCache[stampId].setAttribute('visible', 'true');
                    setTimeout(loadNextModel, 50);
                    return;
                }

                var modelPath = STAMPS[stampId] ? STAMPS[stampId].model : null;
                if (!modelPath) { setTimeout(loadNextModel, 50); return; }

                var modelUrl = '{{ asset("cg") }}/' + modelPath;
                var entity = document.createElement('a-entity');
                entity.setAttribute('position', '0 ' + (index * GALLERY_Y_SPACING) + ' 0');
                entity.setAttribute('scale', '0.6 0.6 0.6');
                entity.setAttribute('rotation', '0 90 0');
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

                    var clip = THREE.AnimationClip.findByName(model.animations, 'anime03')
                             || (model.animations.length > 2 ? model.animations[2] : null)
                             || model.animations[0];
                    if (clip) {
                        var action = mixer.clipAction(clip);
                        action.setLoop(THREE.LoopRepeat, Infinity);
                        action.play();
                    }
                });

                // GLBロード開始
                entity.setAttribute('gltf-model', modelUrl);
                marker00.appendChild(entity);
                galleryCache[stampId] = entity;

                // 次のモデルをLOAD_INTERVAL_MS後にロード
                setTimeout(loadNextModel, LOAD_INTERVAL_MS);
            }

            // --- 全ギャラリーエンティティを非表示（破棄しない） ---
            function hideGallery() {
                Object.keys(galleryCache).forEach(function (key) {
                    try { galleryCache[key].setAttribute('visible', 'false'); } catch (e) {}
                });
            }

            // --- AnimationMixer更新ループ ---
            var prevTime = 0;
            function tickGalleryMixers(time) {
                requestAnimationFrame(tickGalleryMixers);
                if (galleryMixers.length === 0) return;
                var dt = prevTime ? Math.min((time - prevTime) / 1000, 0.1) : 0.016;
                prevTime = time;
                for (var i = 0; i < galleryMixers.length; i++) {
                    try { galleryMixers[i].update(dt); } catch (e) {}
                }
            }
            requestAnimationFrame(tickGalleryMixers);
        })();
