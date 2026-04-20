        // ========== スタンプ定義 ==========
        const STAMPS = {
            'model_01': { name: 'モデル01', icon: '🐾', model: '202605/Model_01.glb' },
            'model_02': { name: 'モデル02', icon: '🐾', model: '202605/Model_02.glb' },
            'model_03': { name: 'モデル03', icon: '🐾', model: '202605/Model_03.glb' },
            'model_04': { name: 'モデル04', icon: '🐾', model: '202605/Model_04.glb' },
            'model_05': { name: 'モデル05', icon: '🐾', model: '202605/Model_05.glb' },
            'model_06': { name: 'モデル06', icon: '🐾', model: '202605/Model_06.glb' },
            'model_07': { name: 'モデル07', icon: '🐾', model: '202605/Model_07.glb' },
            'model_08': { name: 'モデル08', icon: '🐾', model: '202605/Model_08.glb' },
            'model_09': { name: 'モデル09', icon: '🐾', model: '202605/Model_09.glb' },
            'model_10': { name: 'モデル10', icon: '🐾', model: '202605/Model_10.glb' }
        };

        const TOTAL_STAMP_SLOTS = 10;

        // LocalStorageキー (202605固有)
        const LOCAL_STORAGE_KEY   = 'ar-stamp-rally-202605';
        const CAPTURED_KEY        = 'ar-captured-animals-202605';

        // サウンド
        const soundStamp01 = new Audio("{{ asset('cg/sound_stamp01.mp3') }}");
        const soundStamp02 = new Audio("{{ asset('cg/sound_stamp02.mp3') }}");
        soundStamp01.preload = 'auto';
        soundStamp02.preload = 'auto';

        // ========== LocalStorage操作 ==========

        function getCollectedStamps() {
            const stored = localStorage.getItem(LOCAL_STORAGE_KEY);
            if (!stored) return {};
            try { return JSON.parse(stored); } catch (e) {
                localStorage.removeItem(LOCAL_STORAGE_KEY);
                return {};
            }
        }

        function saveCollectedStamps(stamps) {
            localStorage.setItem(LOCAL_STORAGE_KEY, JSON.stringify(stamps));
        }

        function getCapturedAnimals202605() {
            const stored = localStorage.getItem(CAPTURED_KEY);
            if (!stored) return {};
            try { return JSON.parse(stored); } catch (e) {
                localStorage.removeItem(CAPTURED_KEY);
                return {};
            }
        }

        function saveCapturedAnimals202605(captured) {
            localStorage.setItem(CAPTURED_KEY, JSON.stringify(captured));
        }

        function markAnimalCaptured(stampId) {
            const captured = getCapturedAnimals202605();
            captured[stampId] = true;
            saveCapturedAnimals202605(captured);
        }

        function isAnimalCaptured(stampId) {
            const captured = getCapturedAnimals202605();
            return captured[stampId] === true;
        }

        // ========== ギャラリー表示選択管理 ==========

        var GALLERY_SELECTION_KEY = 'ar-gallery-selection-202605';
        var GALLERY_MAX_DISPLAY   = 5;

        function getGallerySelection() {
            var stored = localStorage.getItem(GALLERY_SELECTION_KEY);
            var selection = null;
            if (stored) {
                try { selection = JSON.parse(stored); } catch (e) { selection = null; }
            }
            // 保存済みがあれば、捕獲済みかつ最大5匹にフィルタ
            if (selection && Array.isArray(selection)) {
                var captured = getCapturedAnimals202605();
                selection = selection.filter(function (id) { return captured[id] === true; });
                return selection.slice(0, GALLERY_MAX_DISPLAY);
            }
            // 未設定: 捕獲順で先着5匹を自動選択
            var stamps = getCollectedStamps();
            var sorted = Object.keys(stamps).sort(function (a, b) {
                return (stamps[a].collectedAt || '') < (stamps[b].collectedAt || '') ? -1 : 1;
            });
            return sorted.slice(0, GALLERY_MAX_DISPLAY);
        }

        function saveGallerySelection(arr) {
            localStorage.setItem(GALLERY_SELECTION_KEY, JSON.stringify(arr));
        }

        function toggleGallerySelection(stampId) {
            var selection = getGallerySelection();
            var idx = selection.indexOf(stampId);
            if (idx !== -1) {
                selection.splice(idx, 1);
                saveGallerySelection(selection);
                return false; // OFF
            }
            if (selection.length >= GALLERY_MAX_DISPLAY) {
                alert('ギャラリーに表示できるのは最大' + GALLERY_MAX_DISPLAY + '匹までです。\n他を外してから選択してください。');
                return null; // 変更なし
            }
            selection.push(stampId);
            saveGallerySelection(selection);
            return true; // ON
        }

        // ========== ギャラリー自動追加 ==========

        function autoAddToGallerySelection(stampId) {
            var selection = getGallerySelection();
            if (selection.indexOf(stampId) !== -1) return;
            if (selection.length < GALLERY_MAX_DISPLAY) {
                selection.push(stampId);
            } else {
                selection.shift();
                selection.push(stampId);
            }
            saveGallerySelection(selection);
        }

        // ========== スタンプ登録 ==========

        function collectStamp(stampId, screenshot) {
            if (!stampId) return false;
            screenshot = screenshot || null;
            try {
                var stamps = getCollectedStamps();
                if (!stamps[stampId]) {
                    var name = (STAMPS[stampId] && STAMPS[stampId].name) ? STAMPS[stampId].name : stampId;
                    stamps[stampId] = { collectedAt: new Date().toISOString(), name: name, screenshot: screenshot };
                    saveCollectedStamps(stamps);
                    updateStampBadge();

                    try { recordMarkerScan(stampId, name, 'ball_hit'); } catch (e) {}

                    var total = Object.keys(stamps).length;
                    var isComplete = total === Object.keys(STAMPS).length;
                    if (isComplete) { playSound(soundStamp02); showCompleteParticles(); }
                    else            { playSound(soundStamp01); showNormalParticles(); }
                    showStampNotification(stampId, isComplete);
                    return true;
                } else {
                    if (screenshot && (!stamps[stampId].screenshot || stamps[stampId].screenshot.length < 100)) {
                        stamps[stampId].screenshot = screenshot;
                        saveCollectedStamps(stamps);
                    }
                    return false;
                }
            } catch (err) {
                try { localStorage.removeItem(LOCAL_STORAGE_KEY); } catch (e) {}
                return false;
            }
        }

        function collectAndMarkWithRetry(stampId, screenshot, maxRetries, intervalMs) {
            if (!stampId) return false;
            screenshot  = screenshot  || null;
            maxRetries  = maxRetries  || 3;
            intervalMs  = intervalMs  || 2000;
            try {
                collectStamp(stampId, screenshot);
                var stamps = getCollectedStamps();
                if (stamps && stamps[stampId]) {
                    try { markAnimalCaptured(stampId); } catch (e) {}
                    try { autoAddToGallerySelection(stampId); } catch (e) {}
                    try { if (typeof showCapturedMessage === 'function') showCapturedMessage(stampId); } catch (e) {}
                    return true;
                }
                var retries = 0;
                var handle = setInterval(function () {
                    retries++;
                    try {
                        collectStamp(stampId, screenshot);
                        var ss = getCollectedStamps();
                        if (ss && ss[stampId]) {
                            try { markAnimalCaptured(stampId); } catch (e) {}
                            try { autoAddToGallerySelection(stampId); } catch (e) {}
                            try { if (typeof showCapturedMessage === 'function') showCapturedMessage(stampId); } catch (e) {}
                            clearInterval(handle);
                        }
                    } catch (err) {}
                    if (retries >= maxRetries) clearInterval(handle);
                }, intervalMs);
                return false;
            } catch (err) {
                return false;
            }
        }

        // ========== 音声再生 ==========

        function playSound(audioElement) {
            try {
                audioElement.currentTime = 0;
                audioElement.play().catch(function () {});
            } catch (e) {}
        }

        // ========== 捕獲メッセージ ==========

        var _currentCapturedAnimal = null;

        var _capturedMessageTimer = null;

        function showCapturedMessage(stampId) {
            var msg = document.getElementById('captured-message');
            var nameEl = document.getElementById('captured-animal-name');
            if (!msg || !nameEl) return;
            if (STAMPS[stampId]) {
                nameEl.textContent = STAMPS[stampId].icon + ' ' + STAMPS[stampId].name;
                _currentCapturedAnimal = stampId;
                msg.style.display = '';
                msg.style.pointerEvents = '';
                msg.style.visibility = '';
                msg.style.opacity = '';
                msg.classList.add('show');

                // 1.5秒後に自動で閉じる
                if (_capturedMessageTimer) clearTimeout(_capturedMessageTimer);
                _capturedMessageTimer = setTimeout(function () {
                    hideCapturedMessage();
                    _capturedMessageTimer = null;
                }, 1500);
            }
        }

        function hideCapturedMessage() {
            var msg = document.getElementById('captured-message');
            if (!msg) return;
            msg.classList.remove('show');
            msg.style.display = 'none';
            msg.style.opacity = '0';
            msg.style.pointerEvents = 'none';
            msg.style.visibility = 'hidden';
            _currentCapturedAnimal = null;
        }

        // ========== パーティクル ==========

        function showNormalParticles() {
            var icons = ['✨', '⭐', '💫', '🌟', '💥'];
            for (var i = 0; i < 15; i++) {
                (function (idx) {
                    setTimeout(function () { createParticle(icons[Math.floor(Math.random() * icons.length)], false); }, idx * 50);
                })(i);
            }
        }

        function showCompleteParticles() {
            var icons = ['🎉', '🎊', '🎈', '✨', '⭐', '💫', '🌟', '💥', '🎆', '🎇'];
            for (var i = 0; i < 40; i++) {
                (function (idx) {
                    setTimeout(function () { createParticle(icons[Math.floor(Math.random() * icons.length)], true); }, idx * 30);
                })(i);
            }
            setTimeout(function () {
                for (var j = 0; j < 20; j++) {
                    (function (idx) {
                        setTimeout(function () { createParticle(icons[Math.floor(Math.random() * icons.length)], true); }, idx * 40);
                    })(j);
                }
            }, 500);
        }

        function createParticle(icon, isLarge) {
            var p = document.createElement('div');
            p.className = isLarge ? 'particle large' : 'particle';
            p.textContent = icon;
            p.style.left = (Math.random() * window.innerWidth) + 'px';
            p.style.top  = (Math.random() * window.innerHeight * 0.7 + window.innerHeight * 0.15) + 'px';
            document.body.appendChild(p);
            setTimeout(function () { if (p.parentNode) p.parentNode.removeChild(p); }, isLarge ? 3000 : 2000);
        }

        // ========== 通知 ==========

        function showStampNotification(stampId, isComplete) {
            var n = document.createElement('div');
            if (isComplete) {
                n.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:linear-gradient(135deg,#f093fb 0%,#f5576c 100%);color:white;padding:30px 40px;border-radius:20px;font-size:24px;font-weight:bold;z-index:10002;box-shadow:0 8px 24px rgba(0,0,0,.4);text-align:center;animation:celebratePop .6s ease-out';
                n.innerHTML = '🎊 全種類コンプリート！ 🎊<br><div style="font-size:18px;margin-top:10px;">おめでとうございます！</div>';
                document.body.appendChild(n);
                setTimeout(function () { n.style.animation = 'fadeOut .5s ease-in'; setTimeout(function () { if (n.parentNode) n.parentNode.removeChild(n); }, 500); }, 3000);
            } else {
                n.style.cssText = 'position:fixed;top:100px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;padding:15px 30px;border-radius:10px;font-size:18px;font-weight:bold;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,.3);animation:slideDown .5s ease-out';
                n.innerHTML = '🎉 ' + STAMPS[stampId].icon + ' ' + STAMPS[stampId].name + ' をゲット！';
                document.body.appendChild(n);
                setTimeout(function () { n.style.animation = 'slideUp .5s ease-in'; setTimeout(function () { if (n.parentNode) n.parentNode.removeChild(n); }, 500); }, 2000);
            }
        }

        // ========== スタンプバッジ更新 ==========

        function updateStampBadge() {
            var count = Object.keys(getCollectedStamps()).length;
            var badge = document.querySelector('#stamp-book-button .badge');
            if (badge) { badge.textContent = count; badge.style.display = count > 0 ? 'flex' : 'none'; }
        }

        // ========== スタンプ帳表示 ==========

        function showStampBook() {
            var stamps  = getCollectedStamps();
            var grid    = document.getElementById('stamps-grid');
            var cntEl   = document.getElementById('collected-count');
            var cmEl    = document.getElementById('complete-message-container');
            if (!grid) return;
            grid.innerHTML = '';

            var keys  = Object.keys(STAMPS);
            var total = TOTAL_STAMP_SLOTS;
            var gallerySelection = getGallerySelection();

            for (var i = 0; i < total; i++) {
                var item = document.createElement('div');
                if (i < keys.length) {
                    var sid = keys[i];
                    var s   = STAMPS[sid];
                    var collected = stamps[sid] !== undefined;
                    var inGallery = gallerySelection.indexOf(sid) !== -1;
                    item.className = 'stamp-item ' + (collected ? 'collected' : 'not-collected') + (inGallery ? ' gallery-selected' : '');

                    var iconContent = collected && stamps[sid].screenshot
                        ? '<img src="' + stamps[sid].screenshot + '" alt="' + s.name + '" style="width:100%;height:100%;object-fit:contain;">'
                        : (collected ? s.icon : '🐾');
                    var nameText = collected ? s.name : '？？？';
                    var dateText = '';
                    if (collected && stamps[sid].collectedAt) {
                        var d = new Date(stamps[sid].collectedAt);
                        dateText = '<div class="stamp-date">' + (d.getMonth() + 1) + '/' + d.getDate() + ' ' + d.getHours() + ':' + String(d.getMinutes()).padStart(2, '0') + '</div>';
                    }
                    var checkMark = collected ? '<div class="gallery-check' + (inGallery ? ' active' : '') + '">✓</div>' : '';
                    item.innerHTML = '<div class="stamp-icon">' + iconContent + '</div><div class="stamp-name">' + nameText + '</div>' + dateText + checkMark;

                    // 捕獲済みのみタップでギャラリー選択切替
                    if (collected) {
                        (function (stampId, itemEl) {
                            itemEl.addEventListener('click', function () {
                                var result = toggleGallerySelection(stampId);
                                if (result !== null) showStampBook(); // UI再描画
                            });
                        })(sid, item);
                    }
                } else {
                    item.className = 'stamp-item not-collected';
                    item.innerHTML = '<div class="stamp-icon">🐾</div><div class="stamp-name">？？？</div>';
                }
                grid.appendChild(item);
            }

            var count = Object.keys(stamps).length;
            if (cntEl) cntEl.textContent = count;
            var totalEl = document.getElementById('total-slots');
            if (totalEl) totalEl.textContent = total;
            if (cmEl) {
                cmEl.innerHTML = count === total
                    ? '<div class="complete-message">🎊 おめでとうございます！ 🎊<br>全' + total + '種類コンプリート！</div>'
                    : '';
            }

            var modal = document.getElementById('stamp-book-modal');
            if (modal) {
                modal.style.display = 'block';
                requestAnimationFrame(function () { modal.scrollTop = 0; });
            }
            if (typeof updatePrizeButton === 'function') updatePrizeButton();
        }
