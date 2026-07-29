<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, shrink-to-fit=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="robots" content="noindex,nofollow">
    <title>AR Workshop 101</title>
    <script src="https://polyfill.io/v3/polyfill.min.js?features=Promise%2CObject.assign%2CArray.from%2CArray.prototype.find%2CArray.prototype.includes%2CString.prototype.includes%2CNumber.isNaN"></script>
    <script src="https://aframe.io/releases/1.4.2/aframe.min.js"></script>
    <script src="https://raw.githack.com/AR-js-org/AR.js/master/aframe/build/aframe-ar.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html {
            height: 100%;
            overflow: hidden;
            touch-action: none;
            -webkit-touch-callout: none;
            -webkit-tap-highlight-color: transparent;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            overflow: hidden;
            position: fixed;
            width: 100%;
            height: 100%;
            background: #000;
            touch-action: none;
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        .arjs-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            color: white;
            font-size: 18px;
            font-weight: 500;
        }
        .passcode-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.95);
            z-index: 11000;
        }
        .passcode-panel {
            width: min(360px, 90%);
            padding: 24px 20px;
            border-radius: 16px;
            background: #111;
            color: #fff;
            text-align: center;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
        }
        .passcode-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            line-height: 1.4;
        }
        .passcode-input {
            width: 100%;
            padding: 12px 14px;
            font-size: 18px;
            border-radius: 12px;
            border: 1px solid #444;
            background: #121212;
            color: #fff;
            outline: none;
            margin-bottom: 12px;
            box-sizing: border-box;
        }
        .passcode-button {
            width: 100%;
            padding: 12px 14px;
            font-size: 16px;
            border: none;
            border-radius: 12px;
            background: #4a90e2;
            color: #fff;
            cursor: pointer;
        }
        .passcode-button:hover {
            background: #5aa3f0;
        }
        .passcode-error {
            margin-top: 10px;
            color: #ff6666;
            font-size: 0.95rem;
            display: none;
        }
        .rotation-controls {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 12000;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            pointer-events: auto;
        }
        .rotation-control-btn {
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(17, 17, 17, 0.9);
            color: #fff;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            backdrop-filter: blur(8px);
        }
        .rotation-control-btn.active {
            background: #4a90e2;
            border-color: #4a90e2;
        }
        a-scene {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: block;
            touch-action: none;
        }
        a-scene canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100% !important;
            height: 100% !important;
        }
        .a-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100% !important;
            height: 100% !important;
        }
        video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100% !important;
            height: 100% !important;
            object-fit: contain;
        }
    </style>
</head>
<body>
    <div class="arjs-loader">
        <div>カメラを起動中...</div>
    </div>

    <div class="passcode-overlay">
        <div class="passcode-panel">
            <div class="passcode-title">6桁のパスコードを入力してください</div>
            <input id="workshop-passcode" class="passcode-input" type="text" inputmode="numeric" maxlength="6" pattern="[0-9]*" placeholder="123456" />
            <button id="workshop-passcode-submit" class="passcode-button" type="button">確認</button>
            <div class="passcode-error">パスコードが違います</div>
        </div>
    </div>

    <div class="rotation-controls" aria-label="回転操作">
        <button type="button" class="rotation-control-btn active" data-role="rotation-mode" data-mode="y">Y軸回転</button>
    </div>

    <a-scene
        embedded
        arjs="sourceType: webcam; debugUIEnabled: false; detectionMode: mono; maxDetectionRate: 15;"
        vr-mode-ui="enabled: false"        device-orientation-permission-ui="enabled: false"        renderer="logarithmicDepthBuffer: true; antialias: false; alpha: true; premultipliedAlpha: false; precision: highp; powerPreference: high-performance; colorManagement: false; sortObjects: true;"
        ar-aspect-fix>

        <a-entity camera="near: 0.2; far: 800; fov: 65;"></a-entity>

        <a-light type="ambient" color="#d9d9d9" intensity="0.9"></a-light>
        <a-light type="directional" color="#ffffff" intensity="0.45" position="1 1 1"></a-light>
        <a-light type="directional" color="#ffffff" intensity="0.25" position="-1 0.5 -1"></a-light>

        @for ($i = 1; $i <= 5; $i++)
            <a-marker type="pattern" url="{{ asset('cg/202606/pattern-maker' . sprintf('%02d', $i) . '.patt') }}" id="marker-maker{{ sprintf('%02d', $i) }}">
                <a-entity
                    class="workshop-model"
                    visible="false"
                    gltf-model="{{ asset('cg/202607/AnimePistol_Textured_101' . sprintf('%02d', $i) . '.glb') }}"
                    position="0 0 0"
                    rotation="0 0 0"
                    scale="1.5 1.0 1.5">
                </a-entity>
            </a-marker>
        @endfor

    </a-scene>

    <script>
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

        function grantWorkshopAccess() {
            const overlay = document.querySelector('.passcode-overlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
            document.querySelectorAll('.workshop-model').forEach((model) => {
                model.setAttribute('visible', 'true');
            });
        }

        function validateWorkshopPasscode() {
            const input = document.querySelector('#workshop-passcode');
            const error = document.querySelector('.passcode-error');
            if (!input) return;
            if (input.value.trim() === '000106') {
                if (error) {
                    error.style.display = 'none';
                }
                grantWorkshopAccess();
                return;
            }
            if (error) {
                error.style.display = 'block';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const submitButton = document.querySelector('#workshop-passcode-submit');
            const input = document.querySelector('#workshop-passcode');
            if (submitButton) {
                submitButton.addEventListener('click', validateWorkshopPasscode);
            }
            if (input) {
                input.addEventListener('keypress', function(event) {
                    if (event.key === 'Enter') {
                        validateWorkshopPasscode();
                    }
                });
            }
        });

        AFRAME.registerComponent('ar-aspect-fix', {
            init: function() {
                this.correctAspect = this.correctAspect.bind(this);
                this.appliedCorrection = false;

                window.addEventListener('arjs-video-loaded', () => {
                    setTimeout(() => this.correctAspect(), 200);
                    setTimeout(() => this.correctAspect(), 500);
                    setTimeout(() => this.correctAspect(), 1000);
                });

                window.addEventListener('resize', () => {
                    this.appliedCorrection = false;
                    this.correctAspect();
                });

                setTimeout(() => this.correctAspect(), 100);
                setTimeout(() => this.correctAspect(), 500);
                setTimeout(() => this.correctAspect(), 1000);
            },
            correctAspect: function() {
                const sceneEl = this.el;
                const camera = sceneEl.camera;

                if (!camera) {
                    setTimeout(() => this.correctAspect(), 200);
                    return;
                }

                const originalFov = camera.fov;
                const compressionFactor = 0.75;
                const newFov = originalFov * compressionFactor;

                camera.fov = newFov;
                camera.updateProjectionMatrix();
                console.log('Camera FOV adjusted from', originalFov, 'to', newFov, 'for vertical compression');
                this.appliedCorrection = true;
            },
            remove: function() {
                window.removeEventListener('resize', this.correctAspect);
            }
        });


        document.addEventListener('DOMContentLoaded', function() {
            const scene = document.querySelector('a-scene');
            let isPinching = false;
            let pinchStartDistance = 0;
            let isDragging = false;
            let dragStartX = 0;
            let dragStartY = 0;
            let dragStartRotationStates = [];
            let initialRotationStates = [];
            let workshopModels = [];
            // defaultModelScale は HTML の scale 属性値と一致させること
            const defaultModelScale = [1.5, 1.0, 1.5];
            const minScaleFactor = 0.3;  // デフォルトスケールの30%まで縮小可
            const maxScaleFactor = 3.0;  // デフォルトスケールの300%まで拡大可
            let currentScaleFactor = 1.0;   // 現在の拡大率 (デフォルト=1.0)
            let pinchBaseScaleFactor = 1.0; // ピンチ開始時点の拡大率スナップショット
            const rotationSpeed = 0.35; // degrees per pixel

            // モデル一覧は毎回 DOM 検索せず、一度だけキャッシュして使い回す
            // (タッチ中に何度もクエリするとパフォーマンスが悪化するため)。
            function refreshWorkshopModelsCache() {
                workshopModels = Array.from(document.querySelectorAll('.workshop-model'));
            }

            function getTouchDistance(touchA, touchB) {
                const dx = touchA.clientX - touchB.clientX;
                const dy = touchA.clientY - touchB.clientY;
                return Math.sqrt(dx * dx + dy * dy);
            }

            function getModelRotation(model) {
                const fallback = { x: 0, y: 0, z: 0 };
                const rotation = model.getAttribute('rotation');
                if (!rotation) {
                    return fallback;
                }
                if (typeof rotation === 'string') {
                    const parts = rotation.split(' ').map(Number);
                    return {
                        x: Number.isFinite(parts[0]) ? parts[0] : fallback.x,
                        y: Number.isFinite(parts[1]) ? parts[1] : fallback.y,
                        z: Number.isFinite(parts[2]) ? parts[2] : fallback.z
                    };
                }
                return {
                    x: Number.isFinite(rotation.x) ? rotation.x : fallback.x,
                    y: Number.isFinite(rotation.y) ? rotation.y : fallback.y,
                    z: Number.isFinite(rotation.z) ? rotation.z : fallback.z
                };
            }

            function captureInitialRotationStates() {
                initialRotationStates = workshopModels.map((model) => getModelRotation(model));
            }

            // 実際に適用されている rotation/scale 属性そのものに NaN や 0以下の
            // 不正値が入り込んでいないかを確認し、壊れていれば安全な値に戻す。
            // (拡大縮小/回転中の丸め誤差や異常値でモデルが完全に動かなくなる問題への対策)
            function sanitizeWorkshopModels() {
                workshopModels.forEach((model, index) => {
                    const rawRotation = model.getAttribute('rotation');
                    const rotationBroken = !rawRotation ||
                        (typeof rawRotation === 'object' && (!Number.isFinite(rawRotation.x) || !Number.isFinite(rawRotation.y) || !Number.isFinite(rawRotation.z)));
                    if (rotationBroken) {
                        const fallback = initialRotationStates[index] || { x: 0, y: 0, z: 0 };
                        model.setAttribute('rotation', `${fallback.x} ${fallback.y} ${fallback.z}`);
                    }

                    const rawScale = model.getAttribute('scale');
                    const scaleBroken = !rawScale ||
                        (typeof rawScale === 'object' && (
                            !Number.isFinite(rawScale.x) || !Number.isFinite(rawScale.y) || !Number.isFinite(rawScale.z) ||
                            rawScale.x <= 0 || rawScale.y <= 0 || rawScale.z <= 0
                        ));
                    if (scaleBroken) {
                        model.setAttribute('scale', '1.5 1.0 1.5');
                        currentScaleFactor = 1.0;
                        pinchBaseScaleFactor = 1.0;
                    }
                });
            }

            // ピンチ/ドラッグの内部状態を強制的に初期化する。
            // 例外発生時や、マーカーロスト/リカバリ、タブ非表示化など
            // タッチシーケンスが正常に完了しない状況からの自己復旧に使う。
            function resetGestureState() {
                isPinching = false;
                isDragging = false;
                pinchStartDistance = 0;
                dragStartRotationStates = [];
                sanitizeWorkshopModels();
            }

            function setModelRotationByDrag(deltaX, deltaY) {
                if (!Number.isFinite(deltaX) || !Number.isFinite(deltaY)) {
                    return;
                }
                workshopModels.forEach((model, index) => {
                    const startState = dragStartRotationStates[index];
                    if (!startState) {
                        return;
                    }

                    const nextX = startState.x;
                    const nextY = startState.y + deltaX * rotationSpeed;
                    model.setAttribute('rotation', `${nextX} ${nextY} ${startState.z}`);
                });
            }

            function setModelScale(ratio) {
                // NaN/Infinity/0以下の比率は無視する。
                // pinchBaseScaleFactor に ratio を掛けた絶対倍率でモデルを拡縮する。
                // これにより縮小(ratio<1)も正しく反映され、「縮小できなくなる」問題を解消する。
                if (!Number.isFinite(ratio) || ratio <= 0) {
                    return;
                }
                const newFactor = Math.max(minScaleFactor, Math.min(maxScaleFactor, pinchBaseScaleFactor * ratio));
                currentScaleFactor = newFactor;
                workshopModels.forEach((model) => {
                    model.setAttribute('scale',
                        `${defaultModelScale[0] * newFactor} ${defaultModelScale[1] * newFactor} ${defaultModelScale[2] * newFactor}`
                    );
                });
            }

            function captureBaseScale() {
                // ピンチ開始時の拡大率を記録する。次のピンチ開始まで変わらない。
                pinchBaseScaleFactor = currentScaleFactor;
            }

            function adjustRendererAlpha() {
                if (!scene || !scene.renderer) {
                    return;
                }
                scene.renderer.setClearColor(0x000000, 0);
                scene.renderer.premultipliedAlpha = false;
                scene.renderer.sortObjects = true;
                scene.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
            }

            function fixWorkshopModelMaterials(object3D) {
                if (!object3D) {
                    return;
                }
                object3D.traverse(function(node) {
                    if (!node.isMesh || !node.material) {
                        return;
                    }
                    const materials = Array.isArray(node.material) ? node.material : [node.material];
                    materials.forEach(function(mat) {
                        if (mat) {
                            mat.side = THREE.FrontSide;
                            mat.transparent = false;
                            mat.opacity = 1.0;
                            mat.alphaTest = 0.001;
                            mat.depthWrite = true;
                            mat.depthTest = true;
                            mat.depthFunc = THREE.LessEqualDepth;
                            mat.blending = THREE.NormalBlending;
                            mat.premultipliedAlpha = false;
                            mat.polygonOffset = true;
                            mat.polygonOffsetFactor = 1;
                            mat.polygonOffsetUnits = 1;
                            mat.needsUpdate = true;
                        }
                    });
                });
            }

            function initWorkshopModelFixes() {
                workshopModels.forEach(function(model) {
                    model.addEventListener('model-loaded', function() {
                        const object3D = this.object3D || this.getObject3D('mesh');
                        fixWorkshopModelMaterials(object3D);
                        setTimeout(function() {
                            fixWorkshopModelMaterials(object3D);
                        }, 200);
                    });
                    setTimeout(function() {
                        const object3D = model.object3D || model.getObject3D('mesh');
                        fixWorkshopModelMaterials(object3D);
                    }, 500);
                });
            }

            refreshWorkshopModelsCache();

            if (scene) {
                scene.addEventListener('renderstart', adjustRendererAlpha);
                window.addEventListener('arjs-video-loaded', adjustRendererAlpha);
                initWorkshopModelFixes();
            }

            captureInitialRotationStates();
            document.querySelectorAll('.rotation-control-btn[data-role="rotation-mode"]').forEach((button) => {
                button.classList.add('active');
            });

            scene.addEventListener('touchstart', function(e) {
                try {
                    if (e.touches && e.touches.length >= 2) {
                        isDragging = false;
                        isPinching = true;
                        pinchStartDistance = getTouchDistance(e.touches[0], e.touches[1]);
                        captureBaseScale();
                        if (e.cancelable) e.preventDefault();
                        return;
                    }

                    if (e.touches && e.touches.length === 1) {
                        isPinching = false;
                        pinchStartDistance = 0;
                        isDragging = true;
                        dragStartX = e.touches[0].clientX;
                        dragStartY = e.touches[0].clientY;
                        dragStartRotationStates = workshopModels.map((model) => getModelRotation(model));
                        if (e.cancelable) e.preventDefault();
                    }
                } catch (err) {
                    console.error('[workshop] touchstart error, resetting gesture state', err);
                    resetGestureState();
                }
            }, { passive: false });

            scene.addEventListener('touchmove', function(e) {
                try {
                    if (isPinching && e.touches && e.touches.length >= 2) {
                        if (e.cancelable) e.preventDefault();
                        const currentDistance = getTouchDistance(e.touches[0], e.touches[1]);
                        if (!Number.isFinite(currentDistance) || currentDistance <= 0) {
                            return;
                        }
                        if (pinchStartDistance <= 0) {
                            // 異常値で固定されてしまった場合は現在の距離で基準を再取得し、
                            // ジェスチャーが二度と反応しなくなるのを防ぐ。
                            pinchStartDistance = currentDistance;
                            return;
                        }
                        const ratio = currentDistance / pinchStartDistance;
                        if (!Number.isFinite(ratio) || ratio <= 0) {
                            return;
                        }
                        setModelScale(ratio);
                        return;
                    }

                    if (isDragging && e.touches && e.touches.length === 1) {
                        if (e.cancelable) e.preventDefault();
                        const deltaX = e.touches[0].clientX - dragStartX;
                        const deltaY = e.touches[0].clientY - dragStartY;
                        setModelRotationByDrag(deltaX, deltaY);
                    }
                } catch (err) {
                    console.error('[workshop] touchmove error, resetting gesture state', err);
                    resetGestureState();
                }
            }, { passive: false });

            scene.addEventListener('touchend', function(e) {
                try {
                    const remaining = e.touches ? e.touches.length : 0;

                    if (remaining >= 2) {
                        // 3本指以上から1本減っただけなら、ピンチ基準距離だけ再取得して継続する。
                        isPinching = true;
                        isDragging = false;
                        pinchStartDistance = getTouchDistance(e.touches[0], e.touches[1]);
                        captureBaseScale();
                        return;
                    }

                    if (remaining === 1) {
                        // ピンチ(2本指)から1本指に減った場合、そのままドラッグへ引き継げるように
                        // ドラッグ開始状態を今の指位置で再取得する。
                        isPinching = false;
                        pinchStartDistance = 0;
                        isDragging = true;
                        dragStartX = e.touches[0].clientX;
                        dragStartY = e.touches[0].clientY;
                        dragStartRotationStates = workshopModels.map((model) => getModelRotation(model));
                        return;
                    }

                    resetGestureState();
                } catch (err) {
                    console.error('[workshop] touchend error, resetting gesture state', err);
                    resetGestureState();
                }
            }, { passive: false });

            scene.addEventListener('touchcancel', function() {
                resetGestureState();
            }, { passive: false });

            // タブが非表示になった場合のみジェスチャー状態を破棄する。
            // blur は iOS で頻繁に誤発火するため使用しない。
            // markerFound/markerLost は AR.js が追跡中に毎秒複数回発火するため、
            // resetGestureState をフックすると進行中のジェスチャーを常に中断してしまう。
            // タッチのキャンセルは touchcancel が担う。
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    resetGestureState();
                }
            });
        });
    </script>
</body>
</html>
