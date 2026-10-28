
        window.activeBalls = 0;
        window.allHitboxes = [];
        window.allGalleryHitboxes = [];
        window.guideModalOpen = false;
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
            try {
                const url = new URL(window.location.href);
                if (url.searchParams.get('lowres') === '1') return true;
                if (detectOldAndroid()) return true;
                // 低スペックAndroidの精緻化: 少数コアまたは小メモリなら低検出レートモード
                // (navigator が非対応(0/undefined)の場合は判定しない=従来挙動を維持)
                if (/Android/i.test(navigator.userAgent || '')) {
                    const cores = navigator.hardwareConcurrency || 0;
                    const mem   = navigator.deviceMemory || 0;
                    if ((cores > 0 && cores <= 2) || (mem > 0 && mem <= 2)) return true;
                }
            } catch (e) {}
            return false;
        })();

        // 2026-09-11 修正（FR-9）:
        // タイムアウトで「カメラが起動できません」を表示しても監視は継続し、
        // video が後から ready になればエラー画面等を自動非表示にする。
        // （低スペックiOSで7秒以内に初フレームが来ても、その後に起動するケースの誤表示を解消）
        function monitorCameraStartup(timeoutMs) {
            timeoutMs = timeoutMs || 6000;
            const start = Date.now();
            let timedOut = false;
            function hideCameraErrorUI() {
                try {
                    const el = document.getElementById('camera-error');
                    if (el) el.style.display = 'none';
                    const hm = document.getElementById('camera-help-modal');
                    if (hm && hm.style.display !== 'none') { hm.style.display = 'none'; hm.setAttribute('aria-hidden', 'true'); }
                    const ld = document.querySelector('.arjs-loader');
                    if (ld) ld.style.display = 'none';
                } catch (e) {}
            }
            const interval = setInterval(function() {
                // ★根本修正: AR.js の <video> は document.body 直下に配置されるため
                //   「#ar-scene video」は常に null を返し、カメラ監視が永遠にタイムアウトした。
                //   動作実績のある「video」（202605 / 202609@e0c369c と同一）に復旧。
                const v = document.querySelector('video');
                if (v && (v.readyState >= 2 || v.currentTime > 0 || !v.paused)) {
                    clearInterval(interval);
                    window.arjsVideoReady = true;
                    window._pendingCameraError = false;
                    hideCameraErrorUI();
                    return;
                }
                if (!timedOut && Date.now() - start > timeoutMs) {
                    timedOut = true; // 監視は解除せず継続（video が遅れて起動しても自動復帰する）
                    if (window.guideModalOpen) { window._pendingCameraError = true; return; }
                    const el = document.getElementById('camera-error');
                    if (el) el.style.display = 'flex';
                    const lb = document.getElementById('retry-camera-lowres');
                    if (lb) lb.style.display = 'inline-block';
                }
            }, 500);
        }

        window.ensureCameraAccess = function() {
            // ★根本修正: 同上（「#ar-scene video」は null）→「video」に復旧
            const v = document.querySelector('video');
            if (v && v.srcObject && (v.readyState >= 2 || !v.paused || v.currentTime > 0)) { window.arjsVideoReady = true; return; }
            // 2026-09-11 修正（FR-10）:
            // AR.js が既にストリームを確保済み（srcObject あり）の場合は、2度目の
            // getUserMedia を行わない（iOS では既存ストリームと競合し全制約セットが失敗する）。
            // 代わりにこのタップgesture内で play() を試行し、失敗しても監視継続で自動復帰する。
            if (v && v.srcObject) {
                try {
                    if (!v.hasAttribute('playsinline')) v.setAttribute('playsinline', '');
                    v.muted = true;
                    const p = v.play();
                    if (p && p.catch) {
                        p.catch(function (e) {
                            console.warn('[AR202611] ensureCameraAccess: play() blocked, monitor will recover', e);
                        });
                    }
                } catch (e) {
                    console.warn('[AR202611] ensureCameraAccess: play() failed', e);
                }
                return;
            }
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
                        var key = 'ar-camera-reload-202611';
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
    