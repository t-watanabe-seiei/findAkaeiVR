        // ========== 写真・動画撮影 ==========

        var capturedImageData  = null;
        var isRecording        = false;
        var mediaRecorder      = null;
        var recordedChunks     = [];
        var recordingStartTime = 0;
        var currentFacingMode  = 'environment';

        (function () {
            var cameraButton      = document.getElementById('camera-button');
            var videoButton       = document.getElementById('video-button');
            var switchCameraBtn   = document.getElementById('switch-camera-button');
            var photoPreview      = document.getElementById('photo-preview');
            var previewImage      = document.getElementById('preview-image');
            var downloadButton    = document.getElementById('download-button');
            var closeButton       = document.getElementById('close-button');
            var flash             = document.getElementById('flash');

            // ---- カメラ切り替え ----
            if (switchCameraBtn) {
                switchCameraBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var video = document.querySelector('video');
                    if (video && video.srcObject) {
                        video.srcObject.getTracks().forEach(function (t) { t.stop(); });
                    }
                    currentFacingMode = currentFacingMode === 'environment' ? 'user' : 'environment';
                    navigator.mediaDevices.getUserMedia({
                        video: { facingMode: currentFacingMode, width: { ideal: 1280 }, height: { ideal: 960 } }
                    }).then(function (stream) {
                        if (video) { video.srcObject = stream; video.play().catch(function () {}); }
                    }).catch(function (err) { alert('カメラの切り替えに失敗しました。\n' + err.message); });
                });
            }

            // ---- 動画撮影 ----
            if (videoButton) {
                videoButton.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (!isRecording) { startRecording(); } else { stopRecording(); }
                });
            }

            function startRecording() {
                try {
                    var scene    = document.getElementById('ar-scene');
                    var arCanvas = scene ? scene.canvas : null;
                    var video    = document.querySelector('video');
                    if (!arCanvas || !video) { alert('動画撮影の準備ができていません'); return; }

                    var isIOS   = /iPad|iPhone|iPod/.test(navigator.userAgent);
                    var dpr     = isIOS ? Math.min(window.devicePixelRatio || 1, 2) : (window.devicePixelRatio || 1);
                    var sw      = window.innerWidth;
                    var sh      = window.innerHeight;
                    var fps     = isIOS ? 24 : 30;
                    var bitrate = isIOS ? 3000000 : 5000000;

                    var compositeCanvas = document.createElement('canvas');
                    compositeCanvas.width  = sw * dpr;
                    compositeCanvas.height = sh * dpr;
                    var ctx = compositeCanvas.getContext('2d', { alpha: false, desynchronized: true });

                    var lastFrameTime = 0;
                    var frameInterval = 1000 / fps;

                    function compositeFrame(timestamp) {
                        if (!isRecording) return;
                        if (timestamp - lastFrameTime < frameInterval) { requestAnimationFrame(compositeFrame); return; }
                        lastFrameTime = timestamp;
                        ctx.clearRect(0, 0, compositeCanvas.width, compositeCanvas.height);
                        ctx.save(); ctx.scale(dpr, dpr);

                        // カメラ映像
                        var va = video.videoWidth / video.videoHeight, sa = sw / sh;
                        var dw, dh, ox, oy;
                        if (va > sa) { dh = sh; dw = dh * va; ox = (sw - dw) / 2; oy = 0; }
                        else         { dw = sw; dh = dw / va; ox = 0; oy = (sh - dh) / 2; }
                        ctx.drawImage(video, ox, oy, dw, dh);

                        // AR コンテンツ
                        var aa = arCanvas.width / arCanvas.height;
                        var adw, adh, aox, aoy;
                        if (aa > sa) { adh = sh; adw = adh * aa; aox = (sw - adw) / 2; aoy = 0; }
                        else         { adw = sw; adh = adw / aa; aox = 0; aoy = (sh - adh) / 2; }
                        ctx.drawImage(arCanvas, aox, aoy, adw, adh);
                        ctx.restore();
                        requestAnimationFrame(compositeFrame);
                    }

                    var stream  = compositeCanvas.captureStream(fps);
                    var options = {};
                    if (MediaRecorder.isTypeSupported('video/mp4'))                   { options = { mimeType: 'video/mp4', videoBitsPerSecond: bitrate }; }
                    else if (MediaRecorder.isTypeSupported('video/webm;codecs=vp9')) { options = { mimeType: 'video/webm;codecs=vp9', videoBitsPerSecond: bitrate }; }
                    else if (MediaRecorder.isTypeSupported('video/webm;codecs=vp8')) { options = { mimeType: 'video/webm;codecs=vp8', videoBitsPerSecond: bitrate }; }
                    else if (MediaRecorder.isTypeSupported('video/webm'))             { options = { mimeType: 'video/webm', videoBitsPerSecond: bitrate }; }
                    else                                                               { options = { videoBitsPerSecond: bitrate }; }

                    recordedChunks = [];
                    mediaRecorder  = new MediaRecorder(stream, options);

                    mediaRecorder.ondataavailable = function (ev) { if (ev.data.size > 0) recordedChunks.push(ev.data); };

                    mediaRecorder.onstop = function () {
                        var mimeType = mediaRecorder.mimeType || 'video/webm';
                        var blob     = new Blob(recordedChunks, { type: mimeType });
                        if (blob.size === 0) { alert('動画の録画に失敗しました。'); return; }
                        var ext = mimeType.includes('mp4') ? 'mp4' : 'webm';

                        var videoEl = document.createElement('video');
                        videoEl.src        = URL.createObjectURL(blob);
                        videoEl.controls   = true;
                        videoEl.setAttribute('playsinline', '');
                        videoEl.style.cssText = 'max-width:100%;max-height:70vh;border-radius:5px;';

                        var existing = document.querySelector('#photo-preview img, #photo-preview video');
                        if (existing) existing.replaceWith(videoEl);

                        capturedImageData = { blob: blob, extension: ext, mimeType: mimeType };
                        if (photoPreview) photoPreview.style.display = 'flex';
                        recordedChunks = [];
                    };

                    mediaRecorder.start();
                    isRecording        = true;
                    recordingStartTime = Date.now();
                    if (videoButton) { videoButton.classList.add('recording'); videoButton.textContent = '⏹️'; }
                    requestAnimationFrame(compositeFrame);
                } catch (err) {
                    alert('動画撮影の開始に失敗しました\n' + err.message);
                }
            }

            function stopRecording() {
                if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                    mediaRecorder.stop();
                    isRecording = false;
                    if (videoButton) { videoButton.classList.remove('recording'); videoButton.textContent = '📹'; }
                }
            }

            // ---- 写真撮影 ----
            if (cameraButton) {
                cameraButton.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (flash) { flash.classList.add('active'); setTimeout(function () { flash.classList.remove('active'); }, 200); }

                    requestAnimationFrame(function () {
                        requestAnimationFrame(function () {
                            try {
                                var video    = document.querySelector('video');
                                var scene    = document.getElementById('ar-scene');
                                var arCanvas = scene ? scene.canvas : null;
                                if (!video || !arCanvas) return;

                                var sw  = window.innerWidth, sh = window.innerHeight;
                                var dpr = window.devicePixelRatio || 1;
                                var out = document.createElement('canvas');
                                out.width  = sw * dpr;
                                out.height = sh * dpr;
                                var ctx = out.getContext('2d');
                                ctx.scale(dpr, dpr);

                                // カメラ映像
                                var va = video.videoWidth / video.videoHeight, sa = sw / sh;
                                var dw, dh, ox, oy;
                                if (va > sa) { dh = sh; dw = dh * va; ox = (sw - dw) / 2; oy = 0; }
                                else         { dw = sw; dh = dw / va; ox = 0; oy = (sh - dh) / 2; }
                                ctx.drawImage(video, ox, oy, dw, dh);

                                // AR コンテンツ
                                var aa = arCanvas.width / arCanvas.height;
                                var adw, adh, aox, aoy;
                                if (aa > sa) { adh = sh; adw = adh * aa; aox = (sw - adw) / 2; aoy = 0; }
                                else         { adw = sw; adh = adw / aa; aox = 0; aoy = (sh - adh) / 2; }
                                ctx.drawImage(arCanvas, aox, aoy, adw, adh);

                                var dataUrl = out.toDataURL('image/jpeg', 0.92);
                                if (dataUrl && dataUrl.length > 1000) {
                                    capturedImageData = dataUrl;
                                    if (previewImage) {
                                        previewImage.src = dataUrl;
                                        previewImage.onload = function () { if (photoPreview) photoPreview.style.display = 'flex'; };
                                    }
                                } else {
                                    alert('写真の撮影に失敗しました');
                                }
                            } catch (err) {
                                console.error('Photo capture error:', err);
                            }
                        });
                    });
                });
            }

            // ---- ダウンロード ----
            if (downloadButton) {
                downloadButton.addEventListener('click', function () {
                    if (!capturedImageData) return;
                    var blob, filename, mimeType;
                    if (capturedImageData && capturedImageData.blob) {
                        blob = capturedImageData.blob;
                        filename = 'AR_video_' + Date.now() + '.' + capturedImageData.extension;
                        mimeType = capturedImageData.mimeType;
                    } else {
                        fetch(capturedImageData).then(function (r) { return r.blob(); }).then(function (b) {
                            blob     = b;
                            filename = 'AR_photo_' + Date.now() + '.jpg';
                            mimeType = 'image/jpeg';
                            saveFile(blob, filename, mimeType);
                        }).catch(function () { alert('保存に失敗しました'); });
                        return;
                    }
                    saveFile(blob, filename, mimeType);
                });
            }

            function saveFile(blob, filename, mimeType) {
                if (navigator.share && navigator.canShare) {
                    var file = new File([blob], filename, { type: mimeType });
                    if (navigator.canShare({ files: [file] })) {
                        navigator.share({ files: [file], title: mimeType.startsWith('video') ? 'AR動画' : 'AR写真' })
                            .catch(function () { downloadFallback(blob, filename, mimeType); });
                        return;
                    }
                }
                downloadFallback(blob, filename, mimeType);
            }

            function downloadFallback(blob, filename, mimeType) {
                var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                if (isIOS && mimeType.startsWith('image')) {
                    alert('画像を長押しして「写真に追加」を選択してください');
                } else {
                    var link = document.createElement('a');
                    link.download = filename;
                    link.href     = URL.createObjectURL(blob);
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(link.href);
                }
            }

            // ---- プレビューを閉じる ----
            if (closeButton) {
                closeButton.addEventListener('click', function () {
                    if (photoPreview) photoPreview.style.display = 'none';
                    var videoEl = document.querySelector('#photo-preview video');
                    if (videoEl) {
                        if (videoEl.src && videoEl.src.startsWith('blob:')) URL.revokeObjectURL(videoEl.src);
                        var imgEl = document.createElement('img');
                        imgEl.id  = 'preview-image';
                        imgEl.src = '';
                        imgEl.alt = '撮影した写真';
                        imgEl.style.cssText = 'max-width:100%;max-height:70vh;border-radius:5px;';
                        videoEl.replaceWith(imgEl);
                    }
                    capturedImageData = null;
                });
            }
        })();
