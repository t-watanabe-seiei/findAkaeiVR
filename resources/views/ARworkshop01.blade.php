<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, shrink-to-fit=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
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
                gltf-model="{{ asset('cg/202606/Model_00.glb') }}"
                position="0 0 0"
                rotation="0 0 0"
                scale="1.2 1.2 1.2"
                auto-rotate-z>
            </a-entity>
        </a-marker>

        @for ($i = 1; $i <= 20; $i++)
            <a-marker type="pattern" url="{{ asset('cg/202606/pattern-maker' . sprintf('%02d', $i) . '.patt') }}" id="marker-maker{{ sprintf('%02d', $i) }}">
                <a-entity
                    class="workshop-model"
                    gltf-model="{{ asset('cg/202606/Model_' . sprintf('%02d', $i) . '.glb') }}"
                    position="0 0 0"
                    rotation="0 0 0"
                    scale="1.2 1.2 1.2"
                    auto-rotate-z>
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

        AFRAME.registerComponent('auto-rotate-z', {
            schema: {
                speed: { type: 'number', default: 15 }
            },
            tick: function(time, deltaTime) {
                if (!deltaTime) return;
                const rotation = this.el.object3D.rotation;
                rotation.z += THREE.Math.degToRad(this.data.speed * deltaTime / 1000);
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const scene = document.querySelector('a-scene');
            let isPinching = false;
            let pinchStartDistance = 0;
            let pinchStartScale = 1.2;
            const minScale = 0.5;
            const maxScale = 2.5;

            function getTouchDistance(touchA, touchB) {
                const dx = touchA.clientX - touchB.clientX;
                const dy = touchA.clientY - touchB.clientY;
                return Math.sqrt(dx * dx + dy * dy);
            }

            function setModelScale(scaleValue) {
                const clamped = Math.max(minScale, Math.min(maxScale, scaleValue));
                document.querySelectorAll('.workshop-model').forEach((model) => {
                    model.setAttribute('scale', `${clamped} ${clamped} ${clamped}`);
                });
            }

            scene.addEventListener('touchstart', function(e) {
                if (e.touches && e.touches.length === 2) {
                    isPinching = true;
                    pinchStartDistance = getTouchDistance(e.touches[0], e.touches[1]);
                    const model = document.querySelector('.workshop-model');
                    const currentScale = model ? model.getAttribute('scale') : '1.2 1.2 1.2';
                    const parts = currentScale.split(' ').map(Number);
                    pinchStartScale = parts[0] || 1.2;
                    if (e.cancelable) e.preventDefault();
                }
            }, { passive: false });

            scene.addEventListener('touchmove', function(e) {
                if (!isPinching || !e.touches || e.touches.length !== 2) return;
                if (e.cancelable) e.preventDefault();
                const currentDistance = getTouchDistance(e.touches[0], e.touches[1]);
                if (pinchStartDistance <= 0) return;
                const ratio = currentDistance / pinchStartDistance;
                setModelScale(pinchStartScale * ratio);
            }, { passive: false });

            scene.addEventListener('touchend', function(e) {
                if (isPinching && e.touches.length < 2) {
                    isPinching = false;
                    pinchStartDistance = 0;
                }
            }, { passive: false });

            scene.addEventListener('touchcancel', function() {
                isPinching = false;
                pinchStartDistance = 0;
            }, { passive: false });
        });
    </script>
</body>
</html>
