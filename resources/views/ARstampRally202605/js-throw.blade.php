        // ========== ポケボール投擲（タップ即投げ方式） ==========
        // HUDスワイプを廃止し、画面をタップしたらその方向に即時投げる

        var _tapThrowStart = null;
        var _isThrowing   = false;
        var _tapStartTime = 0;

        // UIボタン上のタップは投げ判定から除外する
        function isUIButton(element) {
            if (!element) return false;
            return !!(
                element.closest('#stamp-book-button')   ||
                element.closest('#guide-button')        ||
                element.closest('#camera-button')       ||
                element.closest('#video-button')        ||
                element.closest('#switch-camera-button') ||
                element.closest('#stamp-book-modal')    ||
                element.closest('#guide-modal')         ||
                element.closest('#photo-preview')       ||
                element.closest('#camera-help-modal')   ||
                element.closest('#confirm-dialog')      ||
                element.closest('#confirm-overlay')     ||
                element.closest('#camera-error')        ||
                element.closest('.arjs-loader')
            );
        }

        // 画面指定方向へポケボールを投げる
        function throwPokeballInDirection(forwardDir, speed) {
            var scene = document.getElementById('ar-scene');
            if (!scene || !scene.camera) return;

            var camera   = scene.camera;
            var cameraPos = camera.getWorldPosition(new THREE.Vector3());

            var pokeball = document.createElement('a-entity');
            pokeball.setAttribute('gltf-model', '{{ asset("cg/poke_ball_seiei2.glb") }}');
            pokeball.setAttribute('scale', '0.15 0.15 0.15');
            pokeball.setAttribute('pokeball-throwable', '');
            pokeball.setAttribute('position', cameraPos.x + ' ' + cameraPos.y + ' ' + cameraPos.z);

            scene.appendChild(pokeball);

            pokeball.addEventListener('loaded', function () {
                // マテリアル最適化（Android向け）
                var model = pokeball.getObject3D('mesh');
                if (model) {
                    var meshIdx = 0;
                    model.traverse(function (node) {
                        if (!node.isMesh) return;
                        if (node.geometry) node.geometry.computeVertexNormals();
                        if (node.material) {
                            var mats = Array.isArray(node.material) ? node.material : [node.material];
                            mats.forEach(function (mat, idx) {
                                mat.side              = THREE.FrontSide;
                                mat.depthWrite        = true;
                                mat.depthTest         = true;
                                mat.polygonOffset     = true;
                                mat.polygonOffsetFactor = meshIdx + idx + 1;
                                mat.polygonOffsetUnits  = meshIdx + idx + 1;
                                mat.flatShading       = false;
                                mat.transparent       = false;
                                mat.opacity           = 1.0;
                                mat.alphaTest         = 0;
                                mat.depthFunc         = THREE.LessEqualDepth;
                                mat.dithering         = true;
                                if (mat.metalness !== undefined) { mat.metalness = 0.2; mat.roughness = 0.5; }
                                mat.needsUpdate       = true;
                            });
                            node.renderOrder = 1000 + meshIdx * 10;
                        }
                        meshIdx++;
                    });
                }
                if (pokeball.components['pokeball-throwable']) {
                    pokeball.components['pokeball-throwable'].throw(forwardDir, speed);
                }
            });
        }

        // タッチ開始: 始点とスワイプ方向を記録する（UIボタン上は無視）
        document.addEventListener('touchstart', function (e) {
            if (e.touches.length >= 2) {
                // 2本指ピンチはズーム操作。投げ判定をキャンセル
                _tapThrowStart = null;
                return;
            }
            var touch   = e.touches[0];
            var element = document.elementFromPoint(touch.clientX, touch.clientY);
            if (isUIButton(element)) { _tapThrowStart = null; return; }
            _tapThrowStart = { x: touch.clientX, y: touch.clientY };
            _tapStartTime  = Date.now();
        }, { passive: true });

        // タッチ終了: 始点〜終点の差分からスワイプ方向を計算して投げる
        document.addEventListener('touchend', function (e) {
            if (!_tapThrowStart) return;
            if (_isThrowing) { _tapThrowStart = null; return; }

            var touch = e.changedTouches[0];
            var dx    = touch.clientX - _tapThrowStart.x;
            var dy    = touch.clientY - _tapThrowStart.y;
            _tapThrowStart = null;

            var scene = document.getElementById('ar-scene');
            if (!scene || !scene.camera) return;

            _isThrowing = true;
            setTimeout(function () { _isThrowing = false; }, 500);

            var camera  = scene.camera;
            var camQuat = camera.quaternion.clone();

            // カメラ前方ベクトル（正面）
            var forward = new THREE.Vector3(0, 0, -1).applyQuaternion(camQuat).normalize();

            // カメラ右方向・上方向
            var right = new THREE.Vector3(1, 0, 0).applyQuaternion(camQuat).normalize();
            var up    = new THREE.Vector3(0, 1, 0).applyQuaternion(camQuat).normalize();

            // スワイプ量を画面サイズで正規化してオフセット化する（最大 ±0.4 rad 程度）
            var swipeX =  dx / window.innerWidth  * 0.8; // 左右
            var swipeY = -dy / window.innerHeight * 0.8; // 上下（Y軸反転）

            // スワイプが小さい場合は前方へ（ほぼタップ）
            var dist = Math.sqrt(dx * dx + dy * dy);
            var dir;
            if (dist < 20) {
                dir = forward.clone();
            } else {
                dir = forward.clone()
                    .addScaledVector(right, swipeX)
                    .addScaledVector(up,    swipeY)
                    .normalize();
            }

            // 長押し時間で速度を変化（0.1s 以下: 15, 長押し: 最大 25）
            var holdMs = Date.now() - _tapStartTime;
            var speed  = Math.min(15 + holdMs / 100, 25);

            throwPokeballInDirection(dir, speed);
        }, { passive: true });

        // PC マウスクリックでも投げられるようにする（タップと同じ動き）
        (function () {
            var _mouseDown = null;
            var _mouseDownTime = 0;
            document.addEventListener('mousedown', function (e) {
                var el = document.elementFromPoint(e.clientX, e.clientY);
                if (isUIButton(el)) { _mouseDown = null; return; }
                _mouseDown     = { x: e.clientX, y: e.clientY };
                _mouseDownTime = Date.now();
            });
            document.addEventListener('mouseup', function (e) {
                if (!_mouseDown || _isThrowing) { _mouseDown = null; return; }
                var dx = e.clientX - _mouseDown.x;
                var dy = e.clientY - _mouseDown.y;
                _mouseDown = null;

                var scene = document.getElementById('ar-scene');
                if (!scene || !scene.camera) return;

                _isThrowing = true;
                setTimeout(function () { _isThrowing = false; }, 500);

                var camQuat = scene.camera.quaternion.clone();
                var forward = new THREE.Vector3(0, 0, -1).applyQuaternion(camQuat).normalize();
                var right   = new THREE.Vector3(1, 0, 0).applyQuaternion(camQuat).normalize();
                var up      = new THREE.Vector3(0, 1, 0).applyQuaternion(camQuat).normalize();

                var dist = Math.sqrt(dx * dx + dy * dy);
                var dir;
                if (dist < 10) {
                    dir = forward.clone();
                } else {
                    var swipeX =  dx / window.innerWidth  * 0.8;
                    var swipeY = -dy / window.innerHeight * 0.8;
                    dir = forward.clone()
                        .addScaledVector(right, swipeX)
                        .addScaledVector(up,    swipeY)
                        .normalize();
                }
                var holdMs = Date.now() - _mouseDownTime;
                var speed  = Math.min(15 + holdMs / 100, 25);
                throwPokeballInDirection(dir, speed);
            });
        })();

        // showHitEffect: ヒット時の視覚エフェクト（スタンプ帳の更新など）
        function showHitEffect(pokeball, hitbox) {
            // パーティクルを出す（すでに showNormalParticles 等が collectStamp 内で呼ばれるため軽量版のみ）
            try {
                var icons = ['💥', '✨', '⭐'];
                for (var i = 0; i < 5; i++) {
                    (function (idx) {
                        setTimeout(function () {
                            createParticle(icons[Math.floor(Math.random() * icons.length)], false);
                        }, idx * 40);
                    })(i);
                }
            } catch (e) {}
        }
