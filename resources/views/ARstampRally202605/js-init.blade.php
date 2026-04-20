<script>
document.addEventListener('DOMContentLoaded', function () {

    // ===== 1. Android 640x480 上書き =====
    if (/Android/i.test(navigator.userAgent)) {
        var _scene = document.getElementById('ar-scene');
        if (_scene) {
            _scene.setAttribute('arjs', 'sourceType: webcam; debugUIEnabled: false; sourceWidth: 640; sourceHeight: 480; displayWidth: 640; displayHeight: 480; detectionMode: mono; maxDetectionRate: 12;');
        }
    }

    // ===== 2. 変数 =====
    var scene = document.getElementById('ar-scene');
    var activeModel = null;
    var currentMarkerStampId = null;
    var currentScale = 1;
    var isPinching = false;
    var pinchStartDistance = 0;
    var pinchInitialScale = 1;
    var guideLang = 'jp';

    // ===== 3. ヘルパー: ピンチズーム =====
    function getTouchesDistance(t0, t1) {
        var dx = t0.clientX - t1.clientX;
        var dy = t0.clientY - t1.clientY;
        return Math.hypot(dx, dy);
    }

    function setBaseScaleIfMissing(el) {
        if (!el) return;
        try {
            if (!el.dataset.baseScale) {
                var s = el.getAttribute('scale');
                var base = 1;
                if (s && typeof s === 'string') {
                    var parts = s.trim().split(/\s+/);
                    var n = parseFloat(parts[0]);
                    if (!isNaN(n)) base = n;
                } else if (s && s.x) {
                    base = parseFloat(s.x) || 1;
                }
                el.dataset.baseScale = base;
            }
        } catch (e) {
            console.warn('setBaseScaleIfMissing failed', el, e);
        }
    }

    function applyCurrentScaleTo(el) {
        if (!el) return;
        try {
            var base = parseFloat(el.dataset.baseScale || 1);
            var clamped = Math.max(1, Math.min(3, currentScale));
            var v = base * clamped;
            el.setAttribute('scale', v + ' ' + v + ' ' + v);
            try {
                if (el.object3D && el.object3D.scale) {
                    el.object3D.scale.set(v, v, v);
                }
                if (el.object3D && el.object3D.children) {
                    el.object3D.traverse(function (node) {
                        if (node.isMesh) {
                            if (node.scale) node.scale.set(v, v, v);
                            node.frustumCulled = false;
                        }
                    });
                }
            } catch (err) {
                console.debug('applyCurrentScaleTo: three.js traversal failed', err);
            }
        } catch (e) {
            console.warn('applyCurrentScaleTo failed', el, e);
        }
    }

    function resetActiveModel(el) {
        if (!el) return;
        try { el.setAttribute('visible', 'false'); } catch (err) {}
        try {
            var clickComp = el.components && el.components['click-animation'];
            if (clickComp) {
                if (clickComp.action01) try { clickComp.action01.stop(); } catch (e) {}
                if (clickComp.action02) try { clickComp.action02.stop(); } catch (e) {}
                if (clickComp.mixer) try { clickComp.mixer.stopAllAction(); } catch (e) {}
            }
        } catch (e) {}
        try {
            if (typeof hideCapturedMessage === 'function') hideCapturedMessage();
        } catch (e) {}
    }

    // ===== 4. ガイドモーダル: 言語切り替え =====
    var guideLangJPBtn = document.getElementById('lang-jp');
    var guideLangENBtn = document.getElementById('lang-en');
    var closeGuideBtn  = document.getElementById('close-guide');
    var closeGuideTopBtn = document.getElementById('close-guide-top');
    var guideModal     = document.getElementById('guide-modal');

    function setGuideLanguage(lang) {
        guideLang = lang;
        var title     = document.getElementById('guide-title');
        var stepFind  = document.getElementById('guide-step-find');
        var stepZoom  = document.getElementById('guide-step-zoom');
        var stepPhoto = document.getElementById('guide-step-photo');
        var stepThrow = document.getElementById('guide-step-throw');
        var stepPrize = document.getElementById('guide-step-prize');
        var stepOthers = document.getElementById('guide-step-others');

        if (lang === 'jp') {
            if (title) title.textContent = '遊び方';
            try {
                var noteEl = document.getElementById('stamp-rally-note');
                if (noteEl) noteEl.innerHTML = '<p style="margin:0;"><strong>スタンプラリーについて</strong></p>';
            } catch (e) {}
            if (stepFind) stepFind.innerHTML = `
                <div class="step-text">
                    <strong>マーカーを見つける</strong>
                    <p>会場に設置されたマーカーにカメラを向けると、3Dキャラクターが現れます。</p>
                </div>`;
            if (stepZoom) stepZoom.innerHTML = `
                <div class="step-text">
                    <strong>ズーム</strong>
                    <p>スマートフォン: ピンチ操作で拡大・縮小できます。パソコン: マウスホイールでズームします。</p>
                </div>`;
            if (stepPhoto) stepPhoto.innerHTML = `
                <div class="step-text">
                    <strong>写真・動画撮影</strong>
                    <p>3Dキャラクターと一緒に写真や動画を撮影できます。撮影したデータは端末のみに保存されます。</p>
                </div>`;
            if (stepThrow) stepThrow.innerHTML = `
                <div class="step-text">
                    <strong>ボールを投げる</strong>
                    <p>画面をタップするとモンスターボールが投げられます。キャラクターに当てるとスタンプが押されます。</p>
                </div>`;
            if (stepPrize) stepPrize.innerHTML = `
                <div class="step-text">
                    <strong>景品交換</strong>
                    <p>会場で6種類以上のマーカーを集めると景品と交換できます。</p>
                    <p>場所・日時は会場スタッフにお尋ねください。</p>
                </div>`;
            if (stepOthers) stepOthers.innerHTML = `
                <div class="step-text">
                    <hr class="guide-sep" style="border:none;border-top:1px solid #eee;margin:12px 0;">
                    <p><strong>プライバシー</strong> — 写真・動画のデータは当方で収集しません。データは端末にのみ保存されます。</p>
                    <p><strong>クッキー</strong> — 利用状況の集計や改善のためにクッキーを使用する場合があります。</p>
                    <p><strong>生徒制作</strong> — この作品は誠英高校の福祉クラスの生徒が授業の一環として制作したものです。</p>
                </div>`;
            try {
                var stepHints = document.getElementById('guide-step-hints');
                if (stepHints) stepHints.innerHTML = `
                    <div class="step-text">
                        <strong>マーカー設置場所のヒント</strong>
                        <p>景品交換所にて、ヒントマップを配布予定です。</p>
                    </div>`;
            } catch (e) {}
            if (closeGuideBtn) closeGuideBtn.textContent = '閉じる';
            if (guideLangJPBtn) { guideLangJPBtn.classList.add('active'); guideLangJPBtn.setAttribute('aria-pressed', 'true'); }
            if (guideLangENBtn) { guideLangENBtn.classList.remove('active'); guideLangENBtn.setAttribute('aria-pressed', 'false'); }
        } else {
            if (title) title.textContent = 'How to play';
            try {
                var noteEl = document.getElementById('stamp-rally-note');
                if (noteEl) noteEl.innerHTML = '<p style="margin:0;"><strong>About this stamp rally</strong></p>';
            } catch (e) {}
            if (stepFind) stepFind.innerHTML = `
                <div class="step-text">
                    <strong>Find a marker</strong>
                    <p>Point your camera at markers placed in the venue to make a 3D character appear.</p>
                </div>`;
            if (stepZoom) stepZoom.innerHTML = `
                <div class="step-text">
                    <strong>Zoom</strong>
                    <p>Mobile: pinch to zoom in and out. Desktop: use the mouse wheel to zoom.</p>
                </div>`;
            if (stepPhoto) stepPhoto.innerHTML = `
                <div class="step-text">
                    <strong>Photo &amp; video</strong>
                    <p>You can take photos and videos with the 3D characters. Files are saved to your device only.</p>
                </div>`;
            if (stepThrow) stepThrow.innerHTML = `
                <div class="step-text">
                    <strong>Throw the ball</strong>
                    <p>Tap the screen to throw a Poké Ball. Hit a character to collect a stamp.</p>
                </div>`;
            if (stepPrize) stepPrize.innerHTML = `
                <div class="step-text">
                    <strong>Prize exchange</strong>
                    <p>Collect 6 or more markers in the venue to exchange for a prize.</p>
                    <p>Please ask venue staff for location and time details.</p>
                </div>`;
            if (stepOthers) stepOthers.innerHTML = `
                <div class="step-text">
                    <hr class="guide-sep" style="border:none;border-top:1px solid #eee;margin:12px 0;">
                    <p><strong>Privacy</strong> — We do NOT collect data from your photos or videos. Files are saved to your device only.</p>
                    <p><strong>Cookies</strong> — We may use cookies to aggregate usage statistics and improve the app.</p>
                    <p><strong>Made by students</strong> — This project was created by students at Seiei High School as part of their coursework.</p>
                </div>`;
            try {
                var stepHints = document.getElementById('guide-step-hints');
                if (stepHints) stepHints.innerHTML = `
                    <div class="step-text">
                        <strong>Marker location hints</strong>
                        <p>Hint maps will be available at the prize exchange area.</p>
                    </div>`;
            } catch (e) {}
            if (closeGuideBtn) closeGuideBtn.textContent = 'Close';
            if (guideLangENBtn) { guideLangENBtn.classList.add('active'); guideLangENBtn.setAttribute('aria-pressed', 'true'); }
            if (guideLangJPBtn) { guideLangJPBtn.classList.remove('active'); guideLangJPBtn.setAttribute('aria-pressed', 'false'); }
        }
    }

    if (guideLangJPBtn) guideLangJPBtn.addEventListener('click', function () { setGuideLanguage('jp'); });
    if (guideLangENBtn) guideLangENBtn.addEventListener('click', function () { setGuideLanguage('en'); });

    setGuideLanguage(guideLang);

    // ===== 5. ガイドモーダル open/close =====
    function onGuideModalClosed() {
        window.guideModalOpen = false;
        if (typeof ensureCameraAccess === 'function') ensureCameraAccess();
        if (window._pendingCameraHelpArgs) {
            var args = window._pendingCameraHelpArgs;
            window._pendingCameraHelpArgs = null;
            try { showCameraHelp(args[0], args[1]); } catch (e) {}
        }
        if (window._pendingCameraError) {
            window._pendingCameraError = false;
            var errDiv = document.getElementById('camera-error');
            if (errDiv) errDiv.style.display = 'flex';
        }
    }

    if (closeGuideBtn) {
        closeGuideBtn.addEventListener('click', function () {
            closeGuideBtn.blur();
            if (guideModal) {
                guideModal.style.display = 'none';
                guideModal.setAttribute('aria-hidden', 'true');
            }
            onGuideModalClosed();
        }, false);
    }

    if (closeGuideTopBtn) {
        closeGuideTopBtn.addEventListener('click', function () {
            closeGuideTopBtn.blur();
            if (guideModal) {
                guideModal.style.display = 'none';
                guideModal.setAttribute('aria-hidden', 'true');
            }
            onGuideModalClosed();
        }, false);
    }

    if (guideModal) {
        guideModal.addEventListener('click', function (e) {
            if (e.target === guideModal) {
                guideModal.style.display = 'none';
                guideModal.setAttribute('aria-hidden', 'true');
                onGuideModalClosed();
            }
        });
    }

    // ガイドモーダルは起動時に非表示（ガイドボタンから手動で開く）

    // ===== 6. カメラ権限ヘルプモーダル =====
    var cameraHelpModal = document.getElementById('camera-help-modal');
    var cameraHelpClose = document.getElementById('camera-help-close');

    function showCameraHelp(langKey, reason) {
        if (window.guideModalOpen) {
            window._pendingCameraHelpArgs = [langKey, reason];
            return;
        }
        var title = document.getElementById('camera-help-title');
        var body  = document.getElementById('camera-help-body');
        var isIOS = /iPhone|iPad|iPod/.test(navigator.userAgent);

        if (langKey === 'jp') {
            if (title) title.textContent = 'カメラアクセスがブロックされている可能性があります';
            if (body) {
                body.innerHTML = isIOS
                    ? '<p>iPhoneの設定でカメラをブロックしている場合、ページ内でカメラが起動できません。</p><ol><li>設定 → Safari を開く</li><li>カメラ項目で「すべてのWebサイトを許可」に設定</li></ol>'
                    : '<p>カメラアクセスがブロックされているようです。ブラウザのサイトごとのカメラ許可を確認してください。</p>';
            }
        } else {
            if (title) title.textContent = 'Camera access may be blocked';
            if (body) {
                body.innerHTML = isIOS
                    ? '<p>If your iPhone blocks camera access, this page cannot start the camera.</p><ol><li>Open Settings → Safari</li><li>Set Camera to Allow Access to All Websites</li></ol>'
                    : '<p>Your browser seems to block camera access. Please check site camera permissions in your browser settings.</p>';
            }
        }
        if (cameraHelpModal) {
            cameraHelpModal.style.display = 'flex';
            cameraHelpModal.setAttribute('aria-hidden', 'false');
        }
    }

    function hideCameraHelp() {
        if (cameraHelpModal) {
            cameraHelpModal.style.display = 'none';
            cameraHelpModal.setAttribute('aria-hidden', 'true');
        }
    }

    if (cameraHelpClose) cameraHelpClose.addEventListener('click', hideCameraHelp, false);

    // ===== 7. カメラ権限チェック =====
    function checkCameraPermissions() {
        var isIOS = /iPhone|iPad|iPod/.test(navigator.userAgent);

        function showIfNoAR(reason, delay) {
            var effectiveDelay = isIOS ? Math.max(delay, 10000) : delay;
            setTimeout(function () {
                if (!window.arjsVideoReady) {
                    var v = document.querySelector('video');
                    if (v && (v.readyState >= 2 || v.currentTime > 0 || !v.paused)) {
                        window.arjsVideoReady = true;
                        return;
                    }
                    showCameraHelp(guideLang, reason || 'no-start');
                }
            }, effectiveDelay);
        }

        if (navigator.permissions && typeof navigator.permissions.query === 'function') {
            try {
                navigator.permissions.query({ name: 'camera' }).then(function (result) {
                    if (result && result.state === 'denied') {
                        setTimeout(function () { showCameraHelp(guideLang, 'denied'); }, 150);
                    } else if (result && result.state === 'prompt') {
                        showIfNoAR('no-start', 2200);
                    }
                    try {
                        if (result && typeof result.addEventListener === 'function') {
                            result.addEventListener('change', function () {
                                if (result.state === 'denied') showCameraHelp(guideLang, 'denied');
                                else if (result.state === 'granted') hideCameraHelp();
                            });
                        }
                    } catch (e) {}
                }).catch(function () { showIfNoAR('no-start', 2500); });
            } catch (e) { showIfNoAR('no-start', 2500); }
        } else {
            showIfNoAR('no-start', 3000);
        }

        if (navigator.mediaDevices && typeof navigator.mediaDevices.enumerateDevices === 'function') {
            navigator.mediaDevices.enumerateDevices().then(function (devices) {
                var hasVideo = devices.some(function (d) { return d.kind && d.kind.toLowerCase() === 'videoinput'; });
                if (!hasVideo) showIfNoAR('no-devices', 500);
            }).catch(function () {});
        }
    }

    checkCameraPermissions();

    // ===== 8. marker01-10 イベント =====
    @for ($i = 1; $i <= 10; $i++)
    @php $id = str_pad($i, 2, '0', STR_PAD_LEFT); $stampId = 'model_' . str_pad($i, 2, '0', STR_PAD_LEFT); @endphp
    (function () {
        var marker = document.getElementById('marker-{{ $id }}');
        var model  = document.getElementById('model-{{ $id }}');
        if (!marker || !model) return;

        marker.addEventListener('markerFound', function () {
            activeModel = model;
            setBaseScaleIfMissing(model);
            applyCurrentScaleTo(model);
            currentMarkerStampId = '{{ $stampId }}';
            if (model.components && model.components.hitbox) {
                var hbs = window.allHitboxes || [];
                if (!hbs.includes(model.components.hitbox)) hbs.push(model.components.hitbox);
                window.allHitboxes = hbs;
            }
        });

        marker.addEventListener('markerLost', function () {
            if (activeModel === model) {
                activeModel = null;
            }
            if (currentMarkerStampId === '{{ $stampId }}') {
                currentMarkerStampId = null;
            }
        });
    })();
    @endfor

    // ===== 9. ピンチズームイベント (scene) =====
    if (scene) {
        scene.addEventListener('touchstart', function (event) {
            if (event.touches && event.touches.length >= 2) {
                isPinching = true;
                pinchStartDistance = getTouchesDistance(event.touches[0], event.touches[1]);
                pinchInitialScale = currentScale;
                if (event.cancelable) event.preventDefault();
                return;
            }
        }, { passive: false });

        scene.addEventListener('touchmove', function (event) {
            if (!isPinching) return;
            if (!(event.touches && event.touches.length >= 2)) return;
            var d = getTouchesDistance(event.touches[0], event.touches[1]);
            if (pinchStartDistance <= 0) return;
            var factor = d / pinchStartDistance;
            currentScale = Math.max(1, Math.min(3, pinchInitialScale * factor));
            applyCurrentScaleTo(activeModel);
            if (event.cancelable) event.preventDefault();
        }, { passive: false });

        scene.addEventListener('touchend', function (event) {
            if (isPinching && (!event.touches || event.touches.length < 2)) {
                isPinching = false;
            }
        }, { passive: true });

        scene.addEventListener('wheel', function (e) {
            var step = -e.deltaY * 0.0018;
            currentScale = Math.max(1, Math.min(3, currentScale + step));
            applyCurrentScaleTo(activeModel);
            if (e.cancelable) e.preventDefault();
        }, { passive: false });
    }

    // ===== 10. スタンプ帳モーダル =====
    var stampBookButton = document.getElementById('stamp-book-button');
    var stampBookModal  = document.getElementById('stamp-book-modal');
    var closeStampBook  = document.getElementById('close-stamp-book');
    var stampBookContent = document.getElementById('stamp-book-content');

    if (stampBookButton) {
        stampBookButton.addEventListener('click', function (e) {
            e.stopPropagation();
            if (typeof showStampBook === 'function') showStampBook();
        }, false);
    }

    if (closeStampBook) {
        closeStampBook.addEventListener('click', function (e) {
            e.stopPropagation();
            if (stampBookModal) stampBookModal.style.display = 'none';
            if (typeof window.refreshGallery === 'function') window.refreshGallery();
            setTimeout(function () { resumeCamera(); }, 100);
        }, false);
    }

    if (stampBookModal) {
        stampBookModal.addEventListener('click', function (e) {
            if (e.target === stampBookModal) {
                e.preventDefault();
                e.stopPropagation();
                stampBookModal.style.display = 'none';
                if (typeof window.refreshGallery === 'function') window.refreshGallery();
                setTimeout(function () { resumeCamera(); }, 100);
            }
        });
    }

    if (stampBookContent) {
        stampBookContent.addEventListener('click', function (e) { e.stopPropagation(); });
    }

    // ===== 11. 確認ダイアログ =====
    function showConfirmDialog() {
        var overlay = document.getElementById('confirm-overlay');
        var dialog  = document.getElementById('confirm-dialog');
        if (overlay) overlay.style.display = 'block';
        if (dialog)  dialog.style.display = 'block';
    }

    function hideConfirmDialog() {
        var overlay = document.getElementById('confirm-overlay');
        var dialog  = document.getElementById('confirm-dialog');
        if (overlay) overlay.style.display = 'none';
        if (dialog)  dialog.style.display = 'none';
    }

    var clearStampsButton = document.getElementById('clear-stamps');
    if (clearStampsButton) {
        clearStampsButton.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            showConfirmDialog();
        }, false);
    }

    var confirmYes = document.querySelector('#confirm-dialog .confirm-yes');
    if (confirmYes) {
        confirmYes.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            hideConfirmDialog();
            if (stampBookModal) stampBookModal.style.display = 'none';

            localStorage.removeItem('ar-stamp-rally-202605');
            localStorage.removeItem('ar-captured-animals-202605');

            // 全モデルの捕獲状態リセット
            for (var i = 1; i <= 10; i++) {
                var mid = 'model-' + String(i).padStart(2, '0');
                var m = document.getElementById(mid);
                if (m && typeof m.resetCaptureState === 'function') m.resetCaptureState();
            }

            if (typeof hideCapturedMessage === 'function') hideCapturedMessage();
            if (typeof updateStampBadge === 'function') updateStampBadge();
        }, false);
    }

    var confirmNo = document.querySelector('#confirm-dialog .confirm-no');
    if (confirmNo) {
        confirmNo.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            hideConfirmDialog();
        }, false);
    }

    var confirmOverlay = document.getElementById('confirm-overlay');
    if (confirmOverlay) {
        confirmOverlay.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            hideConfirmDialog();
        }, false);
    }

    // ===== 12. 景品交換ボタン =====
    var exchangePrizeButton = document.getElementById('exchange-prize-button');
    if (exchangePrizeButton) {
        exchangePrizeButton.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof exchangePrize === 'function') exchangePrize();
        });
    }

    // ===== 13. ガイドボタン =====
    var guideOpenButton = document.getElementById('guide-button');
    if (guideOpenButton) {
        guideOpenButton.addEventListener('click', function (e) {
            e.stopPropagation();
            if (guideModal) {
                guideModal.style.display = 'block';
                guideModal.setAttribute('aria-hidden', 'false');
                window.guideModalOpen = true;
                setGuideLanguage(guideLang);
            }
        }, false);
    }

    // ===== 14. カメラエラー・リトライ =====
    var cameraRetryBtn   = document.getElementById('retry-camera');
    var cameraLowresBtn  = document.getElementById('retry-camera-lowres');

    if (cameraRetryBtn) {
        cameraRetryBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var errDiv = document.getElementById('camera-error');
            if (errDiv) errDiv.style.display = 'none';
            if (typeof ensureCameraAccess === 'function') ensureCameraAccess();
        }, false);
    }

    if (cameraLowresBtn) {
        cameraLowresBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            window.AR_FORCE_LOWRES = true;
            var errDiv = document.getElementById('camera-error');
            if (errDiv) errDiv.style.display = 'none';
            if (scene) {
                scene.setAttribute('arjs', 'sourceType: webcam; debugUIEnabled: false; sourceWidth: 320; sourceHeight: 240; detectionMode: mono; maxDetectionRate: 8;');
                scene.setAttribute('renderer', 'antialias: false; alpha: true; precision: lowp;');
            }
            if (typeof ensureCameraAccess === 'function') ensureCameraAccess();
        }, false);
    }

    // ===== 15. resumeCamera =====
    function resumeCamera() {
        try {
            var video = document.querySelector('video');
            if (video && video.paused) {
                video.play().catch(function (e) { console.warn('resumeCamera play() failed', e); });
            }
        } catch (e) {}
    }

    // ===== 16. galleryMixers アニメーション更新 =====
    var _galleryRAFId = null;
    var _lastGalleryTime = 0;
    var _galleryLoopRunning = false;
    function updateGalleryMixers(timestamp) {
        if (!_galleryLoopRunning) { _galleryRAFId = null; return; }
        _galleryRAFId = requestAnimationFrame(updateGalleryMixers);
        if (!_lastGalleryTime) { _lastGalleryTime = timestamp; return; }
        var delta = (timestamp - _lastGalleryTime) / 1000;
        _lastGalleryTime = timestamp;
        if (delta <= 0 || delta > 0.5) return;
        var mixers = window.galleryMixers;
        if (mixers && mixers.length) {
            for (var mi = 0; mi < mixers.length; mi++) {
                try { mixers[mi].update(delta); } catch (e) {}
            }
        }
    }
    window.startGalleryMixerLoop = function () {
        if (_galleryLoopRunning) return;
        _galleryLoopRunning = true;
        _lastGalleryTime = 0;
        _galleryRAFId = requestAnimationFrame(updateGalleryMixers);
    };
    window.stopGalleryMixerLoop = function () {
        _galleryLoopRunning = false;
        if (_galleryRAFId) { cancelAnimationFrame(_galleryRAFId); _galleryRAFId = null; }
    };

    // ===== 17. 初期化 =====
    if (typeof updateStampBadge === 'function') updateStampBadge();
    if (typeof updatePrizeButton === 'function') updatePrizeButton();

    // AR.js video-ready 検知 → ローダーも非表示に
    function hideArjsLoader() {
        try { var ldr = document.querySelector('.arjs-loader'); if (ldr) ldr.style.display = 'none'; } catch (e) {}
    }

    if (scene) {
        scene.addEventListener('arjs-video-loaded', function () {
            window.arjsVideoReady = true;
            hideArjsLoader();
            try { var chm = document.getElementById('camera-help-modal'); if (chm) { chm.style.display = 'none'; chm.setAttribute('aria-hidden','true'); } } catch (e) {}
        });
    }

    // フォールバック: 3秒後にローダーを強制非表示（arjs-video-loaded が発火しない端末向け）
    setTimeout(hideArjsLoader, 3000);

    // ===== 18. window.load: カメラ監視開始 =====
    window.addEventListener('load', function () {
        if (typeof monitorCameraStartup === 'function') monitorCameraStartup(7000);
    });

}); // end DOMContentLoaded
</script>
