<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, shrink-to-fit=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="robots" content="noindex,nofollow">
    <title>AR Workshop 01</title>
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
        <button type="button" class="rotation-control-btn" data-role="rotation-mode" data-mode="free">自由回転</button>
        <button type="button" class="rotation-control-btn" data-role="reset">初期位置へ</button>
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

        <a-marker type="pattern" url="{{ asset('cg/202606/pattern-maker00.patt') }}" id="marker-maker00">
            <a-entity
                class="workshop-model"
                visible="false"
                gltf-model="{{ asset('cg/202606/AnimePistol_Textured_00081_.glb') }}"
                position="0 0 0"
                rotation="0 0 0"
                scale="1.5 0.8 1.5">
            </a-entity>
        </a-marker>

        <a-marker type="pattern" url="{{ asset('cg/pattern-ar-meishi03.patt') }}" id="marker-ar-meishi03">
            <a-entity
                class="workshop-model"
                visible="false"
                gltf-model="{{ asset('cg/202606/AnimePistol_Textured_00105_.glb') }}"
                position="0 0 0"
                rotation="0 0 0"
                scale="1.5 0.8 1.5">
            </a-entity>
        </a-marker>

        @for ($i = 1; $i <= 20; $i++)
            <a-marker type="pattern" url="{{ asset('cg/202606/pattern-maker' . sprintf('%02d', $i) . '.patt') }}" id="marker-maker{{ sprintf('%02d', $i) }}">
                <a-entity
                    class="workshop-model"
                    visible="false"
                    gltf-model="{{ asset('cg/202606/AnimePistol_Textured_' . sprintf('%05d', 102 - $i) . '_.glb') }}"
                    position="0 0 0"
                    rotation="0 0 0"
                    scale="1.5 0.8 1.5">
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
            if (input.value.trim() === '385252') {
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
            let rotationMode = 'y';
            let initialRotationStates = [];
            const minScale = 1.0;
            const maxScale = 3.0;
            const rotationSpeed = 0.35; // degrees per pixel

            function getTouchDistance(touchA, touchB) {
                const dx = touchA.clientX - touchB.clientX;
                const dy = touchA.clientY - touchB.clientY;
                return Math.sqrt(dx * dx + dy * dy);
            }

            function getScaleVector(model) {
                const scale = model.getAttribute('scale');
                if (!scale) {
                    return { x: 1.5, y: 0.8, z: 1.5 };
                }
                const parts = scale.split(' ').map(Number);
                return {
                    x: parts[0] || 1.5,
                    y: parts[1] || 0.8,
                    z: parts[2] || 1.5
                };
            }

            function getModelRotation(model) {
                const rotation = model.getAttribute('rotation');
                if (!rotation) {
                    return { x: 0, y: 0, z: 0 };
                }
                if (typeof rotation === 'string') {
                    const parts = rotation.split(' ').map(Number);
                    return {
                        x: parts[0] || 0,
                        y: parts[1] || 0,
                        z: parts[2] || 0
                    };
                }
                return {
                    x: Number(rotation.x || 0),
                    y: Number(rotation.y || 0),
                    z: Number(rotation.z || 0)
                };
            }

            function captureInitialRotationStates() {
                initialRotationStates = Array.from(document.querySelectorAll('.workshop-model')).map((model) => getModelRotation(model));
            }

            function resetModelsToInitialPosition() {
                document.querySelectorAll('.workshop-model').forEach((model, index) => {
                    const base = initialRotationStates[index] || { x: 0, y: 0, z: 0 };
                    model.setAttribute('rotation', `${base.x} ${base.y} ${base.z}`);
                });
            }

            function setRotationMode(mode) {
                rotationMode = mode;
                document.querySelectorAll('.rotation-control-btn[data-role="rotation-mode"]').forEach((button) => {
                    button.classList.toggle('active', button.dataset.mode === mode);
                });
                resetModelsToInitialPosition();
                dragStartRotationStates = [];
            }

            function setModelRotationByDrag(deltaX, deltaY) {
                document.querySelectorAll('.workshop-model').forEach((model, index) => {
                    const startState = dragStartRotationStates[index];
                    if (!startState) {
                        return;
                    }

                    const nextX = rotationMode === 'free' ? startState.x + deltaY * rotationSpeed : startState.x;
                    const nextY = startState.y + (rotationMode === 'free' ? deltaX : deltaX) * rotationSpeed;
                    model.setAttribute('rotation', `${nextX} ${nextY} ${startState.z}`);
                });
            }

            function setModelScale(scaleFactor) {
                const clamped = Math.max(minScale, Math.min(maxScale, scaleFactor));
                document.querySelectorAll('.workshop-model').forEach((model) => {
                    const base = model.dataset.pinchBaseScale ? model.dataset.pinchBaseScale.split(' ').map(Number) : [1.5, 0.8, 1.5];
                    model.setAttribute('scale', `${base[0] * clamped} ${base[1] * clamped} ${base[2] * clamped}`);
                });
            }

            function captureBaseScale() {
                document.querySelectorAll('.workshop-model').forEach((model) => {
                    const base = getScaleVector(model);
                    model.dataset.pinchBaseScale = `${base.x} ${base.y} ${base.z}`;
                });
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
                document.querySelectorAll('.workshop-model').forEach(function(model) {
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

            if (scene) {
                scene.addEventListener('renderstart', adjustRendererAlpha);
                window.addEventListener('arjs-video-loaded', adjustRendererAlpha);
                initWorkshopModelFixes();
            }

            captureInitialRotationStates();
            setRotationMode(rotationMode);

            document.querySelectorAll('.rotation-control-btn[data-role="rotation-mode"]').forEach((button) => {
                button.addEventListener('click', () => {
                    setRotationMode(button.dataset.mode);
                });
            });

            document.querySelectorAll('.rotation-control-btn[data-role="reset"]').forEach((button) => {
                button.addEventListener('click', () => {
                    resetModelsToInitialPosition();
                    dragStartRotationStates = [];
                });
            });

            scene.addEventListener('touchstart', function(e) {
                if (e.touches && e.touches.length === 2) {
                    isPinching = true;
                    pinchStartDistance = getTouchDistance(e.touches[0], e.touches[1]);
                    captureBaseScale();
                    if (e.cancelable) e.preventDefault();
                    return;
                }

                if (e.touches && e.touches.length === 1) {
                    isDragging = true;
                    dragStartX = e.touches[0].clientX;
                    dragStartY = e.touches[0].clientY;
                    dragStartRotationStates = Array.from(document.querySelectorAll('.workshop-model')).map((model) => getModelRotation(model));
                    if (e.cancelable) e.preventDefault();
                }
            }, { passive: false });

            scene.addEventListener('touchmove', function(e) {
                if (isPinching && e.touches && e.touches.length === 2) {
                    if (e.cancelable) e.preventDefault();
                    const currentDistance = getTouchDistance(e.touches[0], e.touches[1]);
                    if (pinchStartDistance <= 0) return;
                    const ratio = currentDistance / pinchStartDistance;
                    setModelScale(ratio);
                    return;
                }

                if (isDragging && e.touches && e.touches.length === 1) {
                    if (e.cancelable) e.preventDefault();
                    const deltaX = e.touches[0].clientX - dragStartX;
                    const deltaY = e.touches[0].clientY - dragStartY;
                    setModelRotationByDrag(deltaX, deltaY);
                }
            }, { passive: false });

            scene.addEventListener('touchend', function(e) {
                if (isPinching && (!e.touches || e.touches.length < 2)) {
                    isPinching = false;
                    pinchStartDistance = 0;
                }
                if (isDragging && (!e.touches || e.touches.length === 0)) {
                    isDragging = false;
                    dragStartRotationStates = [];
                }
            }, { passive: false });

            scene.addEventListener('touchcancel', function() {
                isPinching = false;
                pinchStartDistance = 0;
                isDragging = false;
                dragStartRotationStates = [];
            }, { passive: false });
        });
    </script>
</body>
</html>
