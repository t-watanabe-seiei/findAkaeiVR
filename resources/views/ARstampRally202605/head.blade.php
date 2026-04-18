<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AR Stamp Rally 202605</title>
    <script>
        window.activeBalls = 0;
        window.allHitboxes = [];
        window.guideModalOpen = true;
        window._pendingCameraHelpArgs = null;
        window._pendingCameraError = false;
        window.galleryMixers = []; // maker00ギャラリー用mixerリスト

        window.addEventListener('error', function(e) {
            try { console.error('Global error:', e && e.message ? e.message : e); } catch (err) {}
        });
        window.addEventListener('unhandledrejection', function(e) {
            try { console.error('UnhandledPromiseRejection:', e && e.reason ? e.reason : e); } catch (err) {}
        });

        function detectOldAndroid() {
            try {
                const ua = navigator.userAgent || '';
                const m = ua.match(/Android\s([0-9]+)/i);
                if (m && m[1]) return parseInt(m[1], 10) <= 7;
            } catch (e) {}
            return false;
        }

        window.AR_FORCE_LOWRES = (function() {
            const url = new URL(window.location.href);
            if (url.searchParams.get('lowres') === '1') return true;
            return detectOldAndroid();
        })();

        function monitorCameraStartup(timeoutMs) {
            timeoutMs = timeoutMs || 6000;
            const start = Date.now();
            const interval = setInterval(function() {
                const v = document.querySelector('video');
                if (v && (v.readyState >= 2 || v.currentTime > 0 || !v.paused)) {
                    clearInterval(interval);
                    window.arjsVideoReady = true;
                    const el = document.getElementById('camera-error');
                    if (el) el.style.display = 'none';
                    try {
                        const hm = document.getElementById('camera-help-modal');
                        if (hm && hm.style.display !== 'none') { hm.style.display = 'none'; hm.setAttribute('aria-hidden', 'true'); }
                    } catch (e) {}
                    return;
                }
                if (Date.now() - start > timeoutMs) {
                    clearInterval(interval);
                    if (window.guideModalOpen) { window._pendingCameraError = true; return; }
                    const el = document.getElementById('camera-error');
                    if (el) el.style.display = 'flex';
                    const lb = document.getElementById('retry-camera-lowres');
                    if (lb) lb.style.display = 'inline-block';
                }
            }, 500);
        }

        window.ensureCameraAccess = function() {
            const v = document.querySelector('video');
            if (v && v.srcObject && (v.readyState >= 2 || !v.paused || v.currentTime > 0)) { window.arjsVideoReady = true; return; }
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return;
            if (window._ensureCameraInProgress) return;
            window._ensureCameraInProgress = true;
            const sets = [
                { video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } } },
                { video: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 480 } } },
                { video: { facingMode: 'environment' } },
                { video: true }
            ];
            function attempt(idx) {
                if (idx >= sets.length) { window._ensureCameraInProgress = false; try { var el = document.getElementById('camera-error'); if (el) el.style.display = 'flex'; } catch(e){} return; }
                navigator.mediaDevices.getUserMedia(sets[idx]).then(function(stream) {
                    var videoEl = document.querySelector('video');
                    if (videoEl) {
                        if (!videoEl.srcObject || videoEl.paused) {
                            videoEl.srcObject = stream;
                            if (!videoEl.hasAttribute('playsinline')) videoEl.setAttribute('playsinline', '');
                            if (!videoEl.hasAttribute('autoplay')) videoEl.setAttribute('autoplay', '');
                            videoEl.muted = true;
                            videoEl.play().then(function() {
                                window.arjsVideoReady = true; window._ensureCameraInProgress = false;
                                try { var e1 = document.getElementById('camera-error'); if (e1) e1.style.display = 'none'; } catch(e){}
                                try { var e2 = document.querySelector('.arjs-loader'); if (e2) e2.style.display = 'none'; } catch(e){}
                                try { var e3 = document.getElementById('camera-help-modal'); if (e3) { e3.style.display = 'none'; e3.setAttribute('aria-hidden','true'); } } catch(e){}
                            }).catch(function() { window._ensureCameraInProgress = false; });
                        } else { stream.getTracks().forEach(function(t) { t.stop(); }); window._ensureCameraInProgress = false; }
                    } else {
                        stream.getTracks().forEach(function(t) { t.stop(); });
                        var key = 'ar-camera-reload-202605';
                        var count = parseInt(sessionStorage.getItem(key) || '0', 10);
                        if (count < 2) { sessionStorage.setItem(key, String(count + 1)); window._ensureCameraInProgress = false; setTimeout(function() { location.reload(); }, 500); }
                        else { sessionStorage.removeItem(key); window._ensureCameraInProgress = false; try { var el = document.getElementById('camera-error'); if (el) el.style.display = 'flex'; } catch(e){} }
                    }
                }).catch(function() { attempt(idx + 1); });
            }
            attempt(0);
        };

        function destroyAndFreeEntity(el) {
            if (!el) return;
            try {
                try { el.setAttribute('visible', 'false'); } catch (e) {}
                const mesh = el.getObject3D && el.getObject3D('mesh');
                if (mesh) {
                    mesh.traverse(function(node) {
                        try {
                            if (node.isMesh) {
                                if (node.geometry) { try { node.geometry.dispose(); } catch(e){} node.geometry = undefined; }
                                if (node.material) {
                                    const mats = Array.isArray(node.material) ? node.material.slice() : [node.material];
                                    mats.forEach(function(mat) {
                                        try {
                                            ['map','metalnessMap','roughnessMap','normalMap','emissiveMap','aoMap','alphaMap'].forEach(function(k) {
                                                if (mat[k] && typeof mat[k].dispose === 'function') { try { mat[k].dispose(); mat[k] = null; } catch(e){} }
                                            });
                                            if (typeof mat.dispose === 'function') mat.dispose();
                                        } catch(e){}
                                    });
                                    node.material = undefined;
                                }
                            }
                        } catch(e){}
                    });
                }
                try { el.emit('pokeball-gone'); } catch(e){}
                if (el.parentNode) { try { el.parentNode.removeChild(el); } catch(e){} }
            } catch(err) {
                try { if (el.parentNode) el.parentNode.removeChild(el); } catch(e){}
            }
        }

        // Ctrl+U ソースコード表示ブロック
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'u' || e.key === 'U' || e.keyCode === 85)) {
                e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation(); return false;
            }
        }, true);

        // ブラウザズーム防止
        document.addEventListener('gesturestart', function(e) { e.preventDefault(); }, { passive: false });
        document.addEventListener('gesturechange', function(e) { e.preventDefault(); }, { passive: false });
        document.addEventListener('gestureend', function(e) { e.preventDefault(); }, { passive: false });
        let _lastTouchEnd = 0;
        document.addEventListener('touchend', function(e) {
            const now = Date.now();
            if (now - _lastTouchEnd <= 300) e.preventDefault();
            _lastTouchEnd = now;
        }, { passive: false });
        document.addEventListener('touchmove', function(e) {
            if (e.touches && e.touches.length > 1) e.preventDefault();
        }, { passive: false });
        document.addEventListener('contextmenu', function(e) { e.preventDefault(); e.stopPropagation(); return false; }, true);
    </script>
    <script src="{{ asset('js/ar-engine.min.js') }}"></script>
    <script src="{{ asset('js/ar-tracking.min.js') }}"></script>
    <style>
        body {
            margin: 0;
            overflow: hidden;
            touch-action: pan-x pan-y;
            -webkit-user-select: none;
            user-select: none;
        }
        a-scene { touch-action: none; }

        /* ========== Androidカメラズーム防止 ========== */
        /* AR.jsが生成するvideo要素に object-fit:contain を強制し
           カメラ映像がズームされて見える問題を修正 */
        video {
            object-fit: contain !important;
        }

        .arjs-loader {
            height: 100%; width: 100%; position: absolute; top: 0; left: 0;
            background-color: rgba(0,0,0,0.8); z-index: 9999;
            display: flex; justify-content: center; align-items: center;
        }
        .arjs-loader div { text-align: center; font-size: 1.25em; color: white; }

        #camera-button {
            position: fixed; bottom: 30px; right: 30px; width: 70px; height: 70px;
            background-color: rgba(255,255,255,0.9); border: 3px solid #333; border-radius: 50%;
            cursor: pointer; z-index: 1000; display: flex; justify-content: center; align-items: center;
            font-size: 35px; box-shadow: 0 4px 8px rgba(0,0,0,0.3); transition: transform 0.1s, background-color 0.2s;
        }
        #camera-button:active { transform: scale(0.9); background-color: rgba(200,200,200,0.9); }

        #switch-camera-button {
            position: fixed; top: 30px; left: 30px; width: 60px; height: 60px;
            background-color: rgba(255,255,255,0.9); border: 3px solid #333; border-radius: 50%;
            cursor: pointer; z-index: 1000; display: flex; justify-content: center; align-items: center;
            font-size: 28px; box-shadow: 0 4px 8px rgba(0,0,0,0.3); transition: transform 0.1s, background-color 0.2s;
        }
        #switch-camera-button:active { transform: scale(0.9) rotate(180deg); background-color: rgba(200,200,200,0.9); }

        #video-button {
            position: fixed; bottom: 110px; right: 30px; width: 60px; height: 60px;
            background-color: rgba(255,255,255,0.9); border: 3px solid #333; border-radius: 50%;
            cursor: pointer; z-index: 1000; display: flex; justify-content: center; align-items: center;
            font-size: 28px; box-shadow: 0 4px 8px rgba(0,0,0,0.3); transition: transform 0.1s, background-color 0.2s;
        }
        #video-button:active { transform: scale(0.9); }
        #video-button.recording { background-color: rgba(255,100,100,0.9); animation: pulse 1s infinite; }
        @keyframes pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.1); } }

        #stamp-book-button {
            position: fixed; top: 30px; right: 30px; width: 60px; height: 60px;
            background-color: rgba(255,255,255,0.9); border: 3px solid #333; border-radius: 50%;
            cursor: pointer; z-index: 1000; display: flex; justify-content: center; align-items: center;
            font-size: 28px; box-shadow: 0 4px 8px rgba(0,0,0,0.3); transition: transform 0.1s, background-color 0.2s;
        }
        #stamp-book-button:active { transform: scale(0.9); background-color: rgba(200,200,200,0.9); }
        #stamp-book-button .badge {
            position: absolute; top: -5px; right: -5px; background-color: #ff4444; color: white;
            border-radius: 50%; width: 24px; height: 24px; font-size: 12px; font-weight: bold;
            display: flex; justify-content: center; align-items: center; border: 2px solid white;
        }

        #guide-button {
            position: fixed; top: 30px; right: 100px; width: 56px; height: 56px;
            background-color: rgba(255,255,255,0.92); border: 2px solid #333; border-radius: 50%;
            cursor: pointer; z-index: 1000; display: flex; justify-content: center; align-items: center;
            font-size: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.25); transition: transform 0.1s, background-color 0.2s;
        }
        #guide-button:active { transform: scale(0.95); }

        .captured-message {
            position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%);
            background: rgba(0,0,0,0.85); color: white; padding: 30px 40px; border-radius: 15px;
            text-align: center; z-index: 2000; display: none; pointer-events: none;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5); opacity: 0; transition: opacity 0.3s;
            -webkit-transform: translate(-50%,-50%); max-width: 80vw;
        }
        .captured-message.show { display: block; pointer-events: auto; opacity: 1; }
        .captured-message h2 { font-size: 24px; margin: 0 0 20px 0; color: #ffeb3b; }
        .captured-message .animal-name { font-size: 28px; margin: 10px 0; font-weight: bold; color: #4CAF50; }

        #stamp-book-modal {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(0,0,0,0.8); display: none; z-index: 10001;
            overflow-y: scroll !important; -webkit-overflow-scrolling: touch !important; overflow-x: hidden;
        }
        #stamp-book-content {
            background-color: white; border-radius: 15px; padding: 18px 12px;
            max-width: 500px; margin: 20px auto; box-sizing: border-box;
        }
        #stamp-book-content h2 { text-align: center; color: #333; margin: 0 0 6px 0; font-size: 20px; }
        #stamp-book-content .progress { text-align: center; color: #666; margin-bottom: 12px; font-size: 14px; }
        #stamp-book-content .complete-message {
            background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); color: white;
            padding: 10px; border-radius: 8px; text-align: center; margin-bottom: 12px;
            font-weight: bold; font-size: 13px;
        }
        #stamp-book-content .stamps-grid {
            display: grid; grid-template-columns: repeat(5,1fr); gap: 8px; margin-bottom: 15px;
        }
        #stamp-book-content .stamp-item {
            background-color: #f5f5f5; border: 2px solid #ddd; border-radius: 8px;
            padding: 8px 4px; text-align: center; transition: all 0.3s;
        }
        #stamp-book-content .stamp-item.collected {
            background: linear-gradient(135deg,#84fab0 0%,#8fd3f4 100%);
            border-color: #4CAF50; box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        #stamp-book-content .stamp-item.not-collected { opacity: 0.4; }
        #stamp-book-content .stamp-item .stamp-icon {
            font-size: 28px; margin-bottom: 3px; width: 100%; height: 60px;
            display: flex; justify-content: center; align-items: center;
            overflow: hidden; border-radius: 4px; background-color: white;
        }
        #stamp-book-content .stamp-item .stamp-icon img { max-width: 100%; max-height: 100%; object-fit: contain; }
        #stamp-book-content .stamp-item .stamp-name { font-size: 11px; font-weight: bold; color: #333; }
        #stamp-book-content .stamp-item .stamp-date { font-size: 8px; color: #666; margin-top: 2px; }
        #stamp-book-content .stamp-item { position: relative; cursor: default; }
        #stamp-book-content .stamp-item.collected { cursor: pointer; }
        #stamp-book-content .stamp-item.gallery-selected { border-color: #2e7d32; box-shadow: 0 0 0 2px #4CAF50, 0 4px 8px rgba(0,0,0,0.2); }
        .gallery-check {
            position: absolute; top: -6px; right: -6px; width: 20px; height: 20px;
            border-radius: 50%; background: #ccc; color: white; font-size: 12px;
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; line-height: 1; pointer-events: none;
        }
        .gallery-check.active { background: #4CAF50; }

        .button-row { display: flex; gap: 10px; margin-top: 15px; }
        #close-stamp-book {
            flex: 1; padding: 12px; background-color: #999; color: white; border: none;
            border-radius: 8px; font-size: 15px; font-weight: bold; cursor: pointer;
        }
        #exchange-prize-button {
            flex: 1; padding: 12px; color: white; border: none; border-radius: 8px;
            font-size: 15px; font-weight: bold; cursor: pointer; background-color: #4CAF50;
            transition: background-color 0.3s, opacity 0.3s;
        }
        #exchange-prize-button:disabled { cursor: not-allowed; opacity: 0.7; background-color: #999 !important; }
        #clear-stamps {
            flex: 1; padding: 12px; background-color: #f44336; color: white; border: none;
            border-radius: 8px; font-size: 15px; font-weight: bold; cursor: pointer;
        }

        #guide-modal {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(0,0,0,0.85); display: none; z-index: 10002;
            -webkit-overflow-scrolling: touch; overflow-y: auto !important; overflow-x: hidden;
        }
        #guide-content {
            background: #fff; border-radius: 12px; width: 92vw; max-width: 1000px;
            margin: 24px auto; padding: 16px 18px; box-sizing: border-box; position: relative;
        }
        #guide-content h2 { margin: 6px 0 10px 0; text-align: center; font-size: 18px; color: #222; }
        .guide-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .lang-switch { display: flex; gap: 6px; }
        .lang-btn { background: rgba(255,255,255,0.9); border: 1px solid rgba(0,0,0,0.08); padding: 6px 8px; border-radius: 6px; cursor: pointer; font-size: 13px; }
        .lang-btn.active { background: linear-gradient(135deg,#7fc7ff 0%,#4aa0ff 100%); color: white; border-color: rgba(0,0,0,0.14); }
        .guide-steps { display: flex; flex-direction: column; gap: 8px; }
        .guide-steps .step { display: flex; gap: 8px; align-items: flex-start; }
        .guide-steps .step.main { justify-content: center; }
        .guide-steps .step img { width: 120px; height: 84px; object-fit: cover; border-radius: 8px; border: 1px solid #eee; }
        .guide-steps .step.main img.howto-main { width: 80%; max-width: 480px; height: auto; max-height: 360px; object-fit: cover; border-radius: 12px; border: 1px solid #eee; display: block; margin: 0 auto 10px auto; }
        .guide-steps .step .step-text { font-size: 14px; color: #333; line-height: 1.12; }
        .guide-steps .step .step-text strong { display: block; margin-bottom: 6px; font-size: 15px; }
        .guide-close-row { text-align: right; margin-top: 12px; }
        #close-guide { padding: 8px 12px; border-radius: 8px; background: #333; color: #fff; border: none; cursor: pointer; margin-bottom: 5px; margin-right: 5px; }
        .close-guide-x {
            position: absolute; top: 12px; right: 12px; width: 32px; height: 32px;
            background: rgba(0,0,0,0.6); color: #fff; border: none; border-radius: 50%;
            font-size: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10;
        }
        .guide-note { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 14px; padding: 12px 14px; background: linear-gradient(135deg,#fffdf0 0%,#fff3d6 100%); border: 1px solid #ffd66b; border-radius: 8px; color: #2b2b2b; font-weight: 500; }
        .guide-note strong { display: block; margin-bottom: 6px; font-weight: 700; }
        .guide-note p { margin: 0; line-height: 1.25; }

        #confirm-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.5); z-index: 10002; display: none; }
        #confirm-dialog {
            position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%);
            background-color: white; border-radius: 15px; padding: 25px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.4); z-index: 10003; display: none; min-width: 280px; text-align: center;
        }
        #confirm-dialog .confirm-message { font-size: 16px; color: #333; margin-bottom: 20px; font-weight: bold; }
        #confirm-dialog .confirm-buttons { display: flex; gap: 10px; justify-content: center; }
        #confirm-dialog .confirm-buttons button { padding: 10px 20px; border: none; border-radius: 8px; font-size: 14px; font-weight: bold; cursor: pointer; }
        #confirm-dialog .confirm-yes { background-color: #f44336; color: white; }
        #confirm-dialog .confirm-no { background-color: #999; color: white; }

        #camera-help-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 10010; justify-content: center; align-items: center; padding: 20px; }
        #camera-help-modal .camera-help-content { background: white; border-radius: 12px; max-width: 640px; width: 100%; padding: 18px 22px; box-shadow: 0 12px 36px rgba(0,0,0,0.3); }
        #camera-help-modal .camera-help-content h3 { margin-top: 0; }
        #camera-help-modal .camera-help-actions { text-align: right; margin-top: 12px; }
        #camera-help-modal .camera-help-actions button { margin-left: 8px; }

        #flash { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: white; opacity: 0; pointer-events: none; z-index: 9998; transition: opacity 0.2s; }
        #flash.active { opacity: 0.8; }

        #photo-preview {
            position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%);
            max-width: 90%; max-height: 90%; background-color: rgba(0,0,0,0.9);
            padding: 10px; border-radius: 10px; display: none; z-index: 10000;
            flex-direction: column; align-items: center;
        }
        #photo-preview img, #photo-preview video { max-width: 100%; max-height: 70vh; border-radius: 5px; }
        #photo-preview .buttons { margin-top: 15px; display: flex; gap: 10px; }
        #photo-preview button { padding: 12px 24px; font-size: 16px; border: none; border-radius: 5px; cursor: pointer; color: white; font-weight: bold; }
        #download-button { background-color: #4CAF50; }
        #close-button { background-color: #f44336; }

        .particle { position: fixed; pointer-events: none; z-index: 9998; font-size: 30px; animation: particle-float 2s ease-out forwards; }
        @keyframes particle-float { 0% { opacity: 1; transform: translateY(0) rotate(0deg); } 100% { opacity: 0; transform: translateY(-200px) rotate(360deg); } }
        .particle.large { font-size: 50px; animation: particle-float-large 3s ease-out forwards; }
        @keyframes particle-float-large { 0% { opacity: 1; transform: translateY(0) rotate(0deg) scale(0.5); } 50% { transform: translateY(-100px) rotate(180deg) scale(1.2); } 100% { opacity: 0; transform: translateY(-300px) rotate(360deg) scale(0.5); } }

        @keyframes slideDown { from { opacity: 0; transform: translateX(-50%) translateY(-20px); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }
        @keyframes slideUp { from { opacity: 1; transform: translateX(-50%) translateY(0); } to { opacity: 0; transform: translateX(-50%) translateY(-20px); } }
        @keyframes celebratePop { 0% { opacity: 0; transform: translate(-50%,-50%) scale(0.5); } 50% { transform: translate(-50%,-50%) scale(1.1); } 100% { opacity: 1; transform: translate(-50%,-50%) scale(1); } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
    </style>
</head>
