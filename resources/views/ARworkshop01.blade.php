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

    <a-scene
        embedded
        arjs="sourceType: webcam; debugUIEnabled: false; detectionMode: mono; maxDetectionRate: 15;"
        vr-mode-ui="enabled: false"
        renderer="logarithmicDepthBuffer: false; antialias: false; alpha: true; precision: lowp; powerPreference: low-power; colorManagement: false;"
        ar-aspect-fix>

        <a-entity camera="near: 0.2; far: 800; fov: 65;"></a-entity>

        <a-light type="ambient" intensity="1.5"></a-light>
        <a-light type="directional" intensity="0.8" position="1 1 1"></a-light>

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
            let pinchStartScale = { x: 1.5, y: 0.8, z: 1.5 };
            let isDragging = false;
            let lastDragX = 0;
            const minScale = 0.5;
            const maxScale = 2.5;

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

            function rotateVisibleModels(deltaDegrees) {
                document.querySelectorAll('.workshop-model').forEach((model) => {
                    if (model.getAttribute('visible') !== 'true') return;
                    const rotationAttr = model.getAttribute('rotation');
                    let rotation = { x: 0, y: 0, z: 0 };
                    if (typeof rotationAttr === 'string') {
                        const parts = rotationAttr.split(' ').map(Number);
                        rotation = {
                            x: parts[0] || 0,
                            y: parts[1] || 0,
                            z: parts[2] || 0
                        };
                    } else if (rotationAttr && typeof rotationAttr === 'object') {
                        rotation = rotationAttr;
                    }
                    rotation.y += deltaDegrees;
                    model.setAttribute('rotation', `${rotation.x} ${rotation.y} ${rotation.z}`);
                });
            }

            function beginDrag(clientX) {
                isDragging = true;
                lastDragX = clientX;
            }

            function updateDrag(clientX) {
                const deltaX = clientX - lastDragX;
                lastDragX = clientX;
                if (Math.abs(deltaX) > 0) {
                    rotateVisibleModels(deltaX * 0.3);
                }
            }

            function setModelScale(scaleFactor) {
                const clamped = Math.max(minScale, Math.min(maxScale, scaleFactor));
                document.querySelectorAll('.workshop-model').forEach((model) => {
                    if (model.getAttribute('visible') !== 'true') return;
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

            scene.addEventListener('touchstart', function(e) {
                if (e.touches && e.touches.length === 2) {
                    isPinching = true;
                    pinchStartDistance = getTouchDistance(e.touches[0], e.touches[1]);
                    captureBaseScale();
                    if (e.cancelable) e.preventDefault();
                    return;
                }
                if (e.touches && e.touches.length === 1) {
                    beginDrag(e.touches[0].clientX);
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
                    updateDrag(e.touches[0].clientX);
                }
            }, { passive: false });

            scene.addEventListener('touchend', function(e) {
                if (isPinching && (!e.touches || e.touches.length < 2)) {
                    isPinching = false;
                    pinchStartDistance = 0;
                }
                if (isDragging && (!e.touches || e.touches.length === 0)) {
                    isDragging = false;
                }
            }, { passive: false });

            scene.addEventListener('touchcancel', function() {
                isPinching = false;
                pinchStartDistance = 0;
                isDragging = false;
            }, { passive: false });

            scene.addEventListener('mousedown', function(e) {
                isDragging = true;
                lastDragX = e.clientX;
            });

            scene.addEventListener('mousemove', function(e) {
                if (!isDragging) return;
                updateDrag(e.clientX);
            });

            document.addEventListener('mouseup', function() {
                isDragging = false;
            });
        });
    </script>
</body>
</html>
