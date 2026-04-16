        // ========== maker00 ギャラリー機能 ==========
        // marker-00 が見つかったとき、捕獲済みモデルを Y軸方向に並べて anime03 でループ表示する

        (function () {
            var galleryEntities = [];
            var galleryMixers   = [];
            var GALLERY_Y_SPACING = 0.6; // モデル間の Y 間隔（単位: A-Frame 空間）

            var marker00 = document.getElementById('marker-00');
            if (!marker00) return;

            marker00.addEventListener('markerFound', function () {
                // 前回のエンティティを念のりクリア
                clearGallery();

                var captured = getCapturedAnimals202605();
                var ids = Object.keys(captured).filter(function (id) { return captured[id] === true; });

                if (ids.length === 0) return; // まだ何も捕獲していない

                ids.forEach(function (stampId, index) {
                    var entity = document.createElement('a-entity');

                    // ModelパスをSTAMPSから取得（例: '202605/Model_01.glb'）
                    var modelPath = STAMPS[stampId] ? STAMPS[stampId].model : null;
                    if (!modelPath) return;

                    // asset() の代わりに public パスを直接組み立てる
                    var modelUrl = '{{ asset("cg") }}/' + modelPath;

                    entity.setAttribute('gltf-model', modelUrl);
                    entity.setAttribute('position', '0 ' + (index * GALLERY_Y_SPACING) + ' 0');
                    entity.setAttribute('scale', '1.1 1.1 1.1');
                    entity.setAttribute('rotation', '0 90 0');

                    entity.addEventListener('model-loaded', function () {
                        var model = entity.getObject3D('mesh');
                        if (!model || !model.animations || model.animations.length === 0) return;

                        // フラスタムカリング無効化（モデルが途切れる問題の回避）
                        model.traverse(function (node) {
                            if (node.isMesh) node.frustumCulled = false;
                        });

                        var mixer = new THREE.AnimationMixer(model);
                        entity._galleryMixer = mixer;
                        galleryMixers.push(mixer);

                        // anime03 → 見つからなければ3番目、それもなければ最初のアニメーション
                        var clip = THREE.AnimationClip.findByName(model.animations, 'anime03')
                                 || (model.animations.length > 2 ? model.animations[2] : null)
                                 || model.animations[0];

                        if (clip) {
                            var action = mixer.clipAction(clip);
                            action.setLoop(THREE.LoopRepeat, Infinity);
                            action.play();
                        }
                    });

                    marker00.appendChild(entity);
                    galleryEntities.push(entity);
                });

                // scene の tick から参照できるようにグローバルに公開
                window.galleryMixers = galleryMixers;
            });

            marker00.addEventListener('markerLost', function () {
                clearGallery();
            });

            function clearGallery() {
                // DOM から除去
                galleryEntities.forEach(function (e) {
                    try { if (e.parentNode) e.parentNode.removeChild(e); } catch (ex) {}
                });
                galleryEntities = [];

                // Mixer を停止
                galleryMixers.forEach(function (m) {
                    try { m.stopAllAction(); } catch (ex) {}
                });
                galleryMixers = [];
                window.galleryMixers = [];
            }
        })();
