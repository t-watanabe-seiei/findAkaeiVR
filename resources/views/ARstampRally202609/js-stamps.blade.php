        // ========== スタンプ定義 ==========
        const STAMPS = {
            'model_01': { name: 'シマウマ', icon: '🐾', model: '202609/Model_01.glb' },
            'model_02': { name: 'シカ', icon: '🐾', model: '202609/Model_02.glb' },
            'model_03': { name: 'とら', icon: '🐾', model: '202609/Model_03.glb' },
            'model_04': { name: 'とり', icon: '🐾', model: '202609/Model_04.glb' },
            'model_05': { name: 'ぶた', icon: '🐾', model: '202609/Model_05.glb' },
            'model_06': { name: 'ビーバー', icon: '🐾', model: '202609/Model_06.glb' },
            'model_07': { name: 'レッサーパンダ', icon: '🐾', model: '202609/Model_07.glb' },
            'model_08': { name: 'きりん', icon: '🐾', model: '202609/Model_08.glb' },
            'model_09': { name: 'いぬ', icon: '🐾', model: '202609/Model_09.glb' },
            'model_10': { name: 'リス', icon: '🐾', model: '202609/Model_10.glb' },
            'model_11': { name: 'あらいぐま', icon: '🐾', model: '202609/Model_11.glb' },
            'model_12': { name: 'チーター', icon: '🐾', model: '202609/Model_12.glb' },
            'model_13': { name: 'きつね', icon: '🐾', model: '202609/Model_13.glb' },
            'model_14': { name: 'パンダ', icon: '🐾', model: '202609/Model_14.glb' },
            'model_15': { name: 'ぞう', icon: '🐾', model: '202609/Model_15.glb' },
            'model_16': { name: 'カタツムリ1', icon: '🐾', model: '202609/Model_16.glb' },
            'model_17': { name: 'カタツムリ2', icon: '🐾', model: '202609/Model_17.glb' },
            'model_18': { name: 'カタツムリ3', icon: '🐾', model: '202609/Model_18.glb' },
            'model_19': { name: 'カタツムリ4', icon: '🐾', model: '202609/Model_19.glb' },
            'model_20': { name: 'イオちゃん', icon: '🐾', model: '202609/Model_20.glb' }
        };

        const TOTAL_STAMP_SLOTS = 20;

        // LocalStorageキー (202609固有)
        const LOCAL_STORAGE_KEY   = 'ar-stamp-rally-202609';
        const CAPTURED_KEY        = 'ar-captured-animals-202609';

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

        function getCapturedAnimals202609() {
            const stored = localStorage.getItem(CAPTURED_KEY);
            if (!stored) return {};
            try { return JSON.parse(stored); } catch (e) {
                localStorage.removeItem(CAPTURED_KEY);
                return {};
            }
        }

        function saveCapturedAnimals202609(captured) {
            localStorage.setItem(CAPTURED_KEY, JSON.stringify(captured));
        }

        function markAnimalCaptured(stampId) {
            const captured = getCapturedAnimals202609();
            captured[stampId] = true;
            saveCapturedAnimals202609(captured);
        }

        function isAnimalCaptured(stampId) {
            const captured = getCapturedAnimals202609();
            return captured[stampId] === true;
        }

        // ========== ギャラリー表示選択管理 ==========

        var GALLERY_SELECTION_KEY = 'ar-gallery-selection-202609';
        var GALLERY_MAX_DISPLAY   = 4;

        function getGallerySelection() {
            var stored = localStorage.getItem(GALLERY_SELECTION_KEY);
            var selection = null;
            if (stored) {
                try { selection = JSON.parse(stored); } catch (e) { selection = null; }
            }
            // 保存済みがあれば、捕獲済みかつ最大4匹にフィルタ
            if (selection && Array.isArray(selection)) {
                var captured = getCapturedAnimals202609();
                selection = selection.filter(function (id) { return captured[id] === true; });
                return selection.slice(0, GALLERY_MAX_DISPLAY);
            }
            // 未設定: 捕獲順で先着4匹を自動選択
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
                alert('ギャラリーに表示できるのは最大' + GALLERY_MAX_DISPLAY + '体までです。\n他を外してから選択してください。');
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
                // クォータ超過等の例外時は、screenshotをnull化して再保存（スタンプ個数・名前は保持）
                try {
                    if (typeof stamps !== 'undefined' && stamps) {
                        Object.keys(stamps).forEach(function (sid) { stamps[sid].screenshot = null; });
                        saveCollectedStamps(stamps);
                    }
                } catch (e) {
                    // 再保存も失敗した場合の最終フォールバック
                    try { localStorage.removeItem(LOCAL_STORAGE_KEY); } catch (e2) {}
                }
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

                    // Safe DOM construction (XSS prevention — no innerHTML with user data)
                    var iconDiv = document.createElement('div');
                    iconDiv.className = 'stamp-icon';
                    if (collected && stamps[sid].screenshot) {
                        var img = document.createElement('img');
                        img.src = stamps[sid].screenshot;
                        img.alt = s.name;
                        img.style.width = '100%';
                        img.style.height = '100%';
                        img.style.objectFit = 'contain';
                        iconDiv.appendChild(img);
                    } else {
                        iconDiv.textContent = collected ? s.icon : '🐾';
                    }
                    item.appendChild(iconDiv);

                    var nameDiv = document.createElement('div');
                    nameDiv.className = 'stamp-name';
                    nameDiv.textContent = collected ? s.name : '？？？';
                    item.appendChild(nameDiv);

                    if (collected && stamps[sid].collectedAt) {
                        var d = new Date(stamps[sid].collectedAt);
                        var dateDiv = document.createElement('div');
                        dateDiv.className = 'stamp-date';
                        dateDiv.textContent = (d.getMonth() + 1) + '/' + d.getDate() + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
                        item.appendChild(dateDiv);
                    }

                    if (collected) {
                        var checkDiv = document.createElement('div');
                        checkDiv.className = 'gallery-check' + (inGallery ? ' active' : '');
                        checkDiv.textContent = '✓';
                        item.appendChild(checkDiv);

                        // 捕獲済みのみタップでギャラリー選択切替
                        (function (stampId, itemEl) {
                            itemEl.addEventListener('click', function () {
                                var result = toggleGallerySelection(stampId);
                                if (result !== null) showStampBook(); // UI再描画
                            });
                        })(sid, item);
                    }
                } else {
                    item.className = 'stamp-item not-collected';
                    var iconDiv2 = document.createElement('div');
                    iconDiv2.className = 'stamp-icon';
                    iconDiv2.textContent = '🐾';
                    item.appendChild(iconDiv2);
                    var nameDiv2 = document.createElement('div');
                    nameDiv2.className = 'stamp-name';
                    nameDiv2.textContent = '？？？';
                    item.appendChild(nameDiv2);
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
