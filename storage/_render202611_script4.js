
// ========== スタンプ・LocalStorage管理 ==========
// ========== スタンプ定義 ==========
        const STAMPS = {
            'model_01': { name: 'ひつじ', icon: '🐾', model: '202610/Model_01.glb' },
            'model_02': { name: 'コウモリ', icon: '🐾', model: '202610/Model_02.glb' },
            'model_03': { name: 'アジ', icon: '🐾', model: '202610/Model_03.glb' },
            'model_04': { name: 'コアラ', icon: '🐾', model: '202610/Model_04.glb' },
            'model_05': { name: 'あざらし', icon: '🐾', model: '202610/Model_05.glb' },
            'model_06': { name: 'ぶた', icon: '🐾', model: '202610/Model_06.glb' },
            'model_07': { name: 'モモンガ', icon: '🐾', model: '202610/Model_07.glb' },
            'model_08': { name: 'ミーアキャット', icon: '🐾', model: '202610/Model_08.glb' },
            'model_09': { name: 'こづめかわうそ', icon: '🐾', model: '202610/Model_09.glb' },
            'model_10': { name: 'コンドル', icon: '🐾', model: '202610/Model_10.glb' },
            'model_11': { name: 'ハリセンボン', icon: '🐾', model: '202610/Model_11.glb' },
            'model_12': { name: 'カバ', icon: '🐾', model: '202610/Model_12.glb' },
            'model_13': { name: 'カメレオン', icon: '🐾', model: '202610/Model_13.glb' },
            'model_14': { name: 'ウーパールーパー', icon: '🐾', model: '202610/Model_14.glb' },
            'model_15': { name: '白いネコ', icon: '🐾', model: '202610/Model_15.glb' },
            'model_16': { name: 'むささび', icon: '🐾', model: '202610/Model_16.glb' },
            'model_17': { name: 'カラス', icon: '🐾', model: '202610/Model_17.glb' },
            'model_18': { name: 'かわうそ', icon: '🐾', model: '202610/Model_18.glb' },
            'model_19': { name: 'すずめ', icon: '🐾', model: '202610/Model_19.glb' },
            'model_20': { name: 'キリン', icon: '🐾', model: '202610/Model_20.glb' }
        };

        const TOTAL_STAMP_SLOTS = 20;

        // LocalStorageキー (202611固有)
        const LOCAL_STORAGE_KEY   = 'ar-stamp-rally-202611';
        const CAPTURED_KEY        = 'ar-captured-animals-202611';

        // サウンド（P2-8: 初回使用時に遅延生成）
        var soundStamp01 = null;
        var soundStamp02 = null;
        function _ensureSound(which) {
            if (which === 1) {
                if (!soundStamp01) soundStamp01 = new Audio("http://localhost/cg/sound_stamp01.mp3");
                return soundStamp01;
            }
            if (!soundStamp02) soundStamp02 = new Audio("http://localhost/cg/sound_stamp02.mp3");
            return soundStamp02;
        }

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

        function getCapturedAnimals202611() {
            const stored = localStorage.getItem(CAPTURED_KEY);
            if (!stored) return {};
            try { return JSON.parse(stored); } catch (e) {
                localStorage.removeItem(CAPTURED_KEY);
                return {};
            }
        }

        function saveCapturedAnimals202611(captured) {
            localStorage.setItem(CAPTURED_KEY, JSON.stringify(captured));
        }

        function markAnimalCaptured(stampId) {
            const captured = getCapturedAnimals202611();
            captured[stampId] = true;
            saveCapturedAnimals202611(captured);
        }

        function isAnimalCaptured(stampId) {
            const captured = getCapturedAnimals202611();
            return captured[stampId] === true;
        }

        // ========== ギャラリー表示選択管理 ==========

        var GALLERY_SELECTION_KEY = 'ar-gallery-selection-202611';
        var GALLERY_MAX_DISPLAY   = 4;

        function getGallerySelection() {
            var stored = localStorage.getItem(GALLERY_SELECTION_KEY);
            var selection = null;
            if (stored) {
                try { selection = JSON.parse(stored); } catch (e) { selection = null; }
            }
            // 保存済みがあれば、捕獲済みかつ最大4匹にフィルタ
            if (selection && Array.isArray(selection)) {
                var captured = getCapturedAnimals202611();
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
                    if (isComplete) { playSound(_ensureSound(2)); showCompleteParticles(); }
                    else            { playSound(_ensureSound(1)); showNormalParticles(); }
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
            // ガラポン描画 → 応募状態 → サーバーの収集種数（check-status）の順で更新
            if (typeof refreshStampBookRewards202611 === 'function') refreshStampBookRewards202611();
        }
// ========== 景品交換・UUID管理 ==========
// ========== IndexedDB / Cookie / UUID ヘルパー ==========

        var UserIdDB202611 = {
            dbName:    'ARStampRallyDB202611',
            storeName: 'userIdStore',
            version:   1,
            openDB: function () {
                var self = this;
                return new Promise(function (resolve, reject) {
                    var req = indexedDB.open(self.dbName, self.version);
                    req.onerror = function () { reject(req.error); };
                    req.onsuccess = function () { resolve(req.result); };
                    req.onupgradeneeded = function (e) {
                        var db = e.target.result;
                        if (!db.objectStoreNames.contains(self.storeName)) {
                            db.createObjectStore(self.storeName);
                        }
                    };
                });
            },
            saveUserId: function (userId) {
                var self = this;
                return self.openDB().then(function (db) {
                    return new Promise(function (resolve, reject) {
                        var tx = db.transaction([self.storeName], 'readwrite');
                        tx.objectStore(self.storeName).put(userId, 'userId');
                        tx.oncomplete = function () { resolve(); };
                        tx.onerror    = function () { reject(tx.error); };
                    });
                }).catch(function (err) { console.warn('[AR202611] saveUserId error', err); });
            },
            getUserId: function () {
                var self = this;
                return self.openDB().then(function (db) {
                    return new Promise(function (resolve, reject) {
                        var tx  = db.transaction([self.storeName], 'readonly');
                        var req = tx.objectStore(self.storeName).get('userId');
                        req.onsuccess = function () { resolve(req.result); };
                        req.onerror   = function () { reject(req.error); };
                    });
                }).catch(function () { return null; });
            }
        };

        var CookieHelper202611 = {
            set: function (name, value, days) {
                days = days || 365;
                var exp = new Date();
                exp.setTime(exp.getTime() + days * 24 * 60 * 60 * 1000);
                document.cookie = name + '=' + value + ';expires=' + exp.toUTCString() + ';path=/;SameSite=Strict' + (location.protocol === 'https:' ? ';Secure' : '');
            },
            get: function (name) {
                var prefix = name + '=';
                var ca     = document.cookie.split(';');
                for (var i = 0; i < ca.length; i++) {
                    var c = ca[i].trim();
                    if (c.indexOf(prefix) === 0) return c.substring(prefix.length);
                }
                return null;
            }
        };

        function generateUUID202611() {
            if (typeof crypto !== 'undefined' && crypto.randomUUID) {
                return 'uid_' + crypto.randomUUID();
            }
            return 'uid_' + 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = Math.random() * 16 | 0;
                var v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }

        function getUserId202611() {
            var storageKey = 'ar-user-id-202611';
            var cookieName = 'ar_user_id_202611';
            var userId = localStorage.getItem(storageKey) || CookieHelper202611.get(cookieName);
            if (userId) {
                localStorage.setItem(storageKey, userId);
                CookieHelper202611.set(cookieName, userId);
                UserIdDB202611.saveUserId(userId);
                return Promise.resolve(userId);
            }
            return UserIdDB202611.getUserId().then(function (uid) {
                if (uid) {
                    localStorage.setItem(storageKey, uid);
                    CookieHelper202611.set(cookieName, uid);
                    return uid;
                }
                var newId = generateUUID202611();
                localStorage.setItem(storageKey, newId);
                CookieHelper202611.set(cookieName, newId);
                UserIdDB202611.saveUserId(newId);
                return newId;
            });
        }

        function collectDeviceInfo() {
            return {
                userAgent:   navigator.userAgent,
                platform:    navigator.platform,
                language:    navigator.language,
                screenWidth: screen.width,
                screenHeight: screen.height,
                colorDepth:  screen.colorDepth,
                pixelRatio:  window.devicePixelRatio,
                timezone:    Intl.DateTimeFormat().resolvedOptions().timeZone,
                isIOS:       /iPad|iPhone|iPod/.test(navigator.userAgent)
                || (navigator.maxTouchPoints > 1 && /MacIntel/.test(navigator.platform)),
                isAndroid:   /Android/.test(navigator.userAgent)
            };
        }

        function generateFingerprint() {
            return getUserId202611().then(function (userId) {
                var data = [
                    userId,
                    navigator.userAgent,
                    navigator.language,
                    screen.width + 'x' + screen.height,
                    screen.colorDepth,
                    new Date().getTimezoneOffset(),
                    navigator.hardwareConcurrency || 'unknown',
                    navigator.deviceMemory || 'unknown'
                ].join('|');
                var hash = 0;
                for (var i = 0; i < data.length; i++) {
                    var c = data.charCodeAt(i);
                    hash = ((hash << 5) - hash) + c;
                    hash = hash & hash;
                }
                return 'fp_' + Math.abs(hash).toString(36);
            });
        }

        // ===== marker-scan-cache クリーンアップ（P3-4）=====
        // キー形式: marker-scan-cache-202611-{markerId}-{YYYY-MM-DD}
        // 初回ロード時に「今日より古い」日付のキーのみを削除
        function cleanupOldMarkerScanCache() {
            var prefix = 'marker-scan-cache-202611-';
            var today  = new Date().toISOString().split('T')[0];
            try {
                for (var i = localStorage.length - 1; i >= 0; i--) {
                    var key = localStorage.key(i);
                    if (!key || key.indexOf(prefix) !== 0) continue;
                    var lastDash = key.lastIndexOf('-');
                    if (lastDash <= prefix.length) continue;
                    var datePart = key.substring(lastDash + 1);
                    if (datePart < today) {
                        localStorage.removeItem(key);
                    }
                }
            } catch (e) {
                console.warn('[AR202611] cleanupOldMarkerScanCache error', e);
            }
        }

        // ========== マーカースキャン記録 ==========

        function recordMarkerDetection(markerId, markerName) {
            var today    = new Date().toISOString().split('T')[0];
            var cacheKey = 'marker-scan-cache-202611-' + markerId + '-' + today;
            if (localStorage.getItem(cacheKey)) return;
            recordMarkerScan(markerId, markerName, 'marker_scan').then(function () {
                localStorage.setItem(cacheKey, JSON.stringify({ scanned: true, timestamp: new Date().toISOString() }));
            }).catch(function (err) { console.warn('[AR202611] recordMarkerDetection error', err); });
        }

        // ========== オフライン対応: スキャン記録キュー（FR-24）==========
        // 通信不可時に record-scan を LocalStorage へ蓄積し、online 復帰時に自動送信する。
        var SCAN_QUEUE_KEY = 'ar-scan-queue-202611';
        var SCAN_QUEUE_MAX = 200;
        var _flushingScanQueue = false;

        function readScanQueue() {
            try {
                var raw = localStorage.getItem(SCAN_QUEUE_KEY);
                if (!raw) return [];
                var arr = JSON.parse(raw);
                return Array.isArray(arr) ? arr : [];
            } catch (e) { return []; }
        }
        function writeScanQueue(arr) {
            try { localStorage.setItem(SCAN_QUEUE_KEY, JSON.stringify(arr)); } catch (e) {}
        }
        function enqueueScan(payload) {
            var arr = readScanQueue();
            arr.push(payload);
            if (arr.length > SCAN_QUEUE_MAX) arr = arr.slice(arr.length - SCAN_QUEUE_MAX);
            writeScanQueue(arr);
            console.log('[AR202611] scan queued offline:', payload.markerId, 'pending=' + arr.length);
            if (typeof window.updateOfflineBanner === 'function') window.updateOfflineBanner();
        }
        function pendingScanCount() { return readScanQueue().length; }

        // CSRF トークン再取得（キャッシュ済み HTML のトークン陳腐化対策・FR-26）
        function refreshCsrfToken() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (!meta) return Promise.resolve(false);
            return fetch('http://localhost/stamp202611/csrf', {
                method: 'GET', credentials: 'same-origin', cache: 'no-store'
            }).then(function (r) { return r.ok ? r.json() : null; })
              .then(function (d) {
                  if (d && d.token) { meta.setAttribute('content', d.token); return true; }
                  return false;
              }).catch(function () { return false; });
        }

        function postRecordScan(payload) {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return fetch('http://localhost/stamp202611/record-scan', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': meta ? meta.content : '' },
                body:    JSON.stringify(payload)
            }).then(function (r) {
                return { ok: r.ok, tokenExpired: r.status === 419, status: r.status };
            });
        }

        // キューのフラッシュ（FR-24）: 時系列順に送信、失敗以降は未送信分を保持
        function flushScanQueue() {
            if (_flushingScanQueue) return Promise.resolve(0);
            var queue = readScanQueue();
            if (!queue.length) return Promise.resolve(0);
            if (!navigator.onLine) return Promise.resolve(0);
            _flushingScanQueue = true;
            var sent = 0;

            function step(i) {
                if (i >= queue.length) return Promise.resolve(sent);
                var item = queue[i];
                return postRecordScan(item).then(function (res) {
                    if (res.tokenExpired) {
                        return refreshCsrfToken().then(function (ok) {
                            if (!ok) return { ok: false };
                            return postRecordScan(item);
                        });
                    }
                    return res;
                }).then(function (res) {
                    if (res && res.ok) {
                        sent++;
                        return step(i + 1);
                    }
                    console.warn('[AR202611] flush stopped at', item.markerId);
                    return sent;
                }).catch(function (err) {
                    console.warn('[AR202611] flush error', err);
                    return sent;
                });
            }

            return step(0).then(function (done) {
                var left = queue.slice(done);
                writeScanQueue(left);
                _flushingScanQueue = false;
                if (done > 0) console.log('[AR202611] flushed scans:', done, 'remaining:', left.length);
                if (typeof window.updateOfflineBanner === 'function') window.updateOfflineBanner();
                return done;
            });
        }

        function recordMarkerScan(markerId, markerName, captureType) {
            captureType = captureType || 'ball_hit';
            return generateFingerprint().then(function (fingerprint) {
                var payload = {
                    markerId: markerId, markerName: markerName,
                    fingerprint: fingerprint, deviceInfo: collectDeviceInfo(),
                    captureType: captureType, scannedAt: new Date().toISOString()
                };

                // オフライン時はキューへ蓄積（FR-24）
                if (!navigator.onLine) {
                    enqueueScan(payload);
                    return { queued: true };
                }

                return postRecordScan(payload).then(function (res) {
                    if (res.ok) return { sent: true };
                    if (res.tokenExpired) {
                        // トークン陳腐化: 更新して 1 回だけ再送（FR-26）
                        return refreshCsrfToken().then(function (ok) {
                            if (!ok) return { error: 'csrf' };
                            return postRecordScan(payload).then(function (res2) {
                                if (res2.ok) return { sent: true };
                                enqueueScan(payload);
                                return { queued: true };
                            });
                        });
                    }
                    console.warn('[AR202611] recordMarkerScan HTTP ' + res.status);
                    return { error: 'HTTP ' + res.status };
                }).catch(function (err) {
                    // fetch 例外（通信断）: キューへ退避して後で再送
                    console.warn('[AR202611] recordMarkerScan error', err);
                    enqueueScan(payload);
                    return { queued: true };
                });
            });
        }


        // ========== 市内共通商品券の抽選応募（FR-41〜FR-44）==========
        // 閾値・ガラポン段階・応募期限は config/ar202611.php からの注入（FR-34 / design §3.1）
        var VOUCHER_THRESHOLD   = 10;
        var CANDY_TIERS         = [5,10,15,20];
        var APPLY_DEADLINE_ISO  = '2026-11-16 23:59:59';
        var VOUCHER_APPLIED_KEY = 'ar-voucher-applied-202611';

        // check-status の直近応答。倍率の文言は常にサーバー応答から描画する（FR-42 / FR-43）
        var _voucherStatus     = null;
        var _applyingVoucher   = false;

        function isVoucherAppliedLocal202611() {
            return localStorage.getItem(VOUCHER_APPLIED_KEY) === 'true';
        }

        // 応募期限の判定（サーバー応答の applyDeadline を優先・design §8.6）
        function isApplyDeadlinePassed202611() {
            var iso = (_voucherStatus && _voucherStatus.applyDeadline) ? _voucherStatus.applyDeadline : APPLY_DEADLINE_ISO;
            var d = new Date(iso);
            if (isNaN(d.getTime())) return false;
            return Date.now() > d.getTime();
        }

        // 収集種数と応募状態の取得（check-status は倍率の自動昇格も行う・design §5.2）
        function checkStatus202611() {
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (!csrfMeta) return Promise.resolve(null);

            return generateFingerprint().then(function (fp) {
                return fetch('http://localhost/stamp202611/check-status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfMeta.content },
                    body:   JSON.stringify({ fingerprint: fp })
                });
            }).then(function (r) {
                if (!r || !r.ok) {
                    console.warn('[AR202611] checkStatus HTTP ' + (r ? r.status : 'no response'));
                    return null;
                }
                return r.json();
            }).then(function (data) {
                if (!data || !data.success) return null;
                _voucherStatus = data;
                // 応募済みフラグの正本は DB。キャッシュは応答で復旧・破棄する（design §9）
                if (data.voucher) {
                    if (data.voucher.applied) localStorage.setItem(VOUCHER_APPLIED_KEY, 'true');
                    else localStorage.removeItem(VOUCHER_APPLIED_KEY);
                }
                if (typeof window.updateVoucherUI202611 === 'function') window.updateVoucherUI202611();
                return data;
            }).catch(function (err) {
                console.warn('[AR202611] checkStatus error', err);
                return null;
            });
        }

        function voucherStatus202611() { return _voucherStatus; }

        // 応募（冪等・design §5.3）。応募直前に未送信キューをフラッシュ → CSRF トークン更新（design §8.5）
        function applyVoucher202611(email, name) {
            if (_applyingVoucher) return Promise.resolve({ success: false, error: 'busy' });
            if (!navigator.onLine) return Promise.resolve({ success: false, error: 'offline' });

            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (!csrfMeta) return Promise.resolve({ success: false, error: 'csrf' });

            _applyingVoucher = true;

            return Promise.resolve(typeof flushScanQueue === 'function' ? flushScanQueue() : 0)
                .then(function () {
                    return typeof refreshCsrfToken === 'function' ? refreshCsrfToken() : false;
                })
                .then(function () { return generateFingerprint(); })
                .then(function (fp) {
                    return fetch('http://localhost/stamp202611/apply-voucher', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfMeta.content },
                        body: JSON.stringify({
                            fingerprint: fp,
                            deviceInfo: collectDeviceInfo(),
                            email: email,
                            name: name
                        })
                    }).then(function (r) {
                        return r.json().catch(function () { return {}; }).then(function (d) {
                            return { status: r.status, data: d || {} };
                        });
                    });
                })
                .then(function (res) {
                    var data = res.data;

                    if (res.status === 200 && data.success) {
                        localStorage.setItem(VOUCHER_APPLIED_KEY, 'true');
                        // 倍率の文言は check-status の応答で描画し直す（FR-42 / FR-43）
                        return checkStatus202611().then(function () {
                            return { success: true, weight: parseInt(data.weight, 10) || 1 };
                        });
                    }

                    if (res.status === 419) {
                        // CSRF 陳腐化: 更新して 1 回だけ再試行（FR-26）
                        return refreshCsrfToken().then(function (ok) {
                            if (!ok) return { success: false, error: 'csrf' };
                            _applyingVoucher = false;
                            return applyVoucher202611(email, name);
                        });
                    }

                    return {
                        success: false,
                        error: data.error || ('HTTP ' + res.status),
                        message: data.message || ''
                    };
                })
                .catch(function (err) {
                    console.warn('[AR202611] applyVoucher error', err);
                    return { success: false, error: 'network' };
                })
                .then(function (out) {
                    _applyingVoucher = false;
                    if (typeof window.updateVoucherUI202611 === 'function') window.updateVoucherUI202611();
                    return out;
                });
        }


        // ========== お菓子のガラポン（FR-37〜FR-40・LocalStorage のみ・サーバー送信なし）==========
        var GACHA_DRAWS_KEY = 'ar-gacha-draws-202611';

        function getGachaDraws202611() {
            try {
                var raw = localStorage.getItem(GACHA_DRAWS_KEY);
                if (!raw) return [];
                var arr = JSON.parse(raw);
                if (!Array.isArray(arr)) return [];
                // config の CANDY_TIERS に含まれる値のみ採用（段階変更時の整合性）
                return arr.filter(function (t) { return CANDY_TIERS.indexOf(t) !== -1; });
            } catch (e) { return []; }
        }

        function saveGachaDraws202611(draws) {
            try { localStorage.setItem(GACHA_DRAWS_KEY, JSON.stringify(draws)); } catch (e) {}
        }

        // この端末の収集種数（LocalStorage）。応募可否の判定はサーバー応答を使う（design §8.6）
        function collectedCount202611() {
            try { return Object.keys(getCollectedStamps()).length; } catch (e) { return 0; }
        }

        function reachableTiers202611() {
            var count = collectedCount202611();
            return CANDY_TIERS.filter(function (t) { return count >= t; });
        }

        // 押下処理: 同一段階の再押下不可・最大 CANDY_TIERS.length 回（FR-39 / AC-9）
        function drawGacha202611(tier) {
            var draws = getGachaDraws202611();
            if (draws.indexOf(tier) !== -1) return { error: 'already_drawn' };
            if (draws.length >= CANDY_TIERS.length) return { error: 'all_drawn' };
            if (reachableTiers202611().indexOf(tier) === -1) return { error: 'not_reached' };

            draws.push(tier);
            draws.sort(function (a, b) { return a - b; });
            saveGachaDraws202611(draws);
            return { success: true, draws: draws };
        }

        // ========== スタンプ帳内のガラポン・抽選応募 UI（FR-40〜FR-44 / FR-52）==========
        // 倍率の文言（FR-42）。weight は常に check-status の応答を使う
        function voucherMessage202611(weight) {
            if (weight >= 3) return '市内共通商品券の抽選会に応募しました。（20個すべて集めたので当選確率3倍です。）';
            if (weight >= 2) return '市内共通商品券の抽選会に応募しました。（15個以上集めたので当選確率2倍です。）';
            return '市内共通商品券の抽選会に応募しました。';
        }

        function formatDeadline202611() {
            var iso = (_voucherStatus && _voucherStatus.applyDeadline) ? _voucherStatus.applyDeadline : APPLY_DEADLINE_ISO;
            var d = new Date(iso);
            if (isNaN(d.getTime())) return iso;
            return d.getFullYear() + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/'
                 + String(d.getDate()).padStart(2, '0') + ' '
                 + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        }

        function showVoucherToast202611(text) {
            var t = document.createElement('div');
            t.textContent = text;
            t.style.cssText = 'position:fixed;left:50%;transform:translateX(-50%);bottom:24px;z-index:10010;'
                + 'background:rgba(0,0,0,.88);color:#fff;padding:12px 18px;border-radius:10px;'
                + 'font-size:15px;line-height:1.5;max-width:90%;text-align:center;';
            document.body.appendChild(t);
            setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 3600);
        }

        // ガラポンボタン（到達済み段階のみ有効・引き済みは無効・オフラインでも押下可）
        function renderGacha202611() {
            var box  = document.getElementById('gacha-buttons');
            var note = document.getElementById('gacha-note');
            if (!box) return;

            var draws   = getGachaDraws202611();
            var reached = reachableTiers202611();
            var count   = collectedCount202611();

            box.innerHTML = '';
            CANDY_TIERS.forEach(function (tier) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'gacha-button';

                if (draws.indexOf(tier) !== -1) {
                    b.disabled = true;
                    b.textContent = tier + '種ガラポン ✅ 引き済み';
                } else if (reached.indexOf(tier) === -1) {
                    b.disabled = true;
                    b.textContent = tier + '種で出現（現在' + count + '種）';
                } else {
                    b.disabled = false;
                    b.textContent = tier + '種ガラポンを引く';
                }

                b.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var res = drawGacha202611(tier);
                    if (res.error === 'already_drawn') showVoucherToast202611('この段階のガラポンはすでに引いています。');
                    else if (res.error === 'not_reached') showVoucherToast202611(tier + '種類集めてから引けます。');
                    else if (res.error === 'all_drawn') showVoucherToast202611('ガラポンはすべて引きました。');
                    else showVoucherToast202611('会場スタッフにこの画面を見せてガラポンを回してください。');
                    renderGacha202611();
                });

                box.appendChild(b);
            });

            if (note) {
                note.textContent = draws.length >= CANDY_TIERS.length
                    ? 'ガラポンはすべて引きました（' + draws.length + '回）。お菓子は会場スタッフにお受け取りください。'
                    : 'ガラポン ' + draws.length + ' / ' + CANDY_TIERS.length + ' 回（各段階1回まで・回数はこの端末のみ記録）';
            }
        }


        // 応募状態の表示（正本は check-status の応答・LocalStorage 数だけ表示しない・design §8.6）
        function updateVoucherUI202611() {
            var statusEl  = document.getElementById('voucher-status');
            var button    = document.getElementById('voucher-apply-button');
            var form      = document.getElementById('voucher-form');
            var pendingEl = document.getElementById('pending-scans');
            if (!statusEl || !button) return;

            // 未送信キューが 1 件以上ある間は応募ボタンを非表示（FR-52）
            var pending = 0;
            try { if (typeof pendingScanCount === 'function') pending = pendingScanCount(); } catch (e) {}
            if (pendingEl) {
                pendingEl.style.display = pending > 0 ? 'block' : 'none';
                pendingEl.textContent   = pending > 0
                    ? '未送信のスタンプ ' + pending + ' 件を自動送信します（送信完了後に応募できます）'
                    : '';
            }

            var serverCount = _voucherStatus ? parseInt(_voucherStatus.stampCount, 10) : null;
            if (isNaN(serverCount)) serverCount = null;
            var voucher  = (_voucherStatus && _voucherStatus.voucher) ? _voucherStatus.voucher : null;
            var applied  = voucher ? (voucher.applied === true) : isVoucherAppliedLocal202611();
            var weight   = voucher ? (parseInt(voucher.weight, 10) || 0) : 0;
            var deadline = formatDeadline202611();

            var lines = [];
            lines.push('サーバー上の収集: '
                + (serverCount === null ? '読み込み中…' : serverCount + ' 種')
                + '（この端末: ' + collectedCount202611() + ' 種）');
            lines.push('応募期限: ' + deadline);
            if (applied) {
                lines.push(voucherMessage202611(weight));
            } else if (serverCount !== null && serverCount >= VOUCHER_THRESHOLD) {
                lines.push('抽選応募が可能です（' + VOUCHER_THRESHOLD + '種以上）');
            }

            statusEl.innerHTML = '';
            lines.forEach(function (text) {
                var p = document.createElement('p');
                p.textContent = text;
                statusEl.appendChild(p);
            });

            // ボタンの表示可否（応募済み / 未送信あり / 期限経過後 / 応答なし は非表示）
            var showButton = true;
            var disabled   = false;

            if (applied) {
                showButton = false;
            } else if (pending > 0) {
                showButton = false;
            } else if (isApplyDeadlinePassed202611()) {
                showButton = false;
                var over = document.createElement('p');
                over.textContent = '応募期限（' + deadline + '）を過ぎたため応募できません';
                statusEl.appendChild(over);
            } else if (serverCount === null) {
                showButton = false;
            } else if (serverCount < VOUCHER_THRESHOLD) {
                disabled = true;
                button.textContent = '抽選応募には' + VOUCHER_THRESHOLD + '種以上必要（現在' + serverCount + '種）';
            } else {
                button.textContent = '商品券抽選に応募する';
            }

            button.style.display = showButton ? 'block' : 'none';
            button.disabled = disabled;

            if (form && !showButton) form.style.display = 'none';
        }


        // 応募フォームの開閉と送信（email / name・FR-41 / FR-44）
        function initVoucherUI202611() {
            var button = document.getElementById('voucher-apply-button');
            var form   = document.getElementById('voucher-form');
            if (!button || !form) return;

            var submit  = document.getElementById('voucher-submit');
            var cancel  = document.getElementById('voucher-cancel');
            var errEl   = document.getElementById('voucher-error');
            var emailEl = document.getElementById('voucher-email');
            var nameEl  = document.getElementById('voucher-name');

            function closeForm() {
                form.style.display = 'none';
                if (errEl) errEl.textContent = '';
            }

            button.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (!navigator.onLine) {
                    showVoucherToast202611('抽選応募には通信が必要です。オンラインになってからもう一度お試しください。');
                    return;
                }
                form.style.display = 'block';
                if (errEl) errEl.textContent = '';
                if (emailEl) emailEl.focus();
            });

            if (cancel) {
                cancel.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeForm();
                });
            }

            if (submit) {
                submit.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    var email = emailEl ? emailEl.value.trim() : '';
                    var name  = nameEl ? nameEl.value.trim() : '';

                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        if (errEl) errEl.textContent = 'メールアドレスを入力してください。';
                        return;
                    }
                    if (!name) {
                        if (errEl) errEl.textContent = 'お名前を入力してください。';
                        return;
                    }

                    submit.disabled = true;
                    submit.textContent = '応募中…';

                    applyVoucher202611(email, name).then(function (res) {
                        submit.disabled = false;
                        submit.textContent = '応募する';

                        if (res.success) {
                            closeForm();
                            var st = voucherStatus202611();
                            var weight = (st && st.voucher) ? (parseInt(st.voucher.weight, 10) || 1) : 1;
                            showVoucherToast202611(voucherMessage202611(weight));
                            updateVoucherUI202611();
                            return;
                        }

                        var msg = '応募に失敗しました。時間をおいてもう一度お試しください。';
                        if (res.error === 'already_applied') msg = 'すでに応募済みです。1 メールアドレスにつき 1 口です。';
                        else if (res.error === 'not_eligible') msg = res.message || '抽選応募の条件を満たしていません。';
                        else if (res.error === 'deadline_passed') msg = '応募期限を過ぎたため応募できません。';
                        else if (res.error === 'offline') msg = '抽選応募には通信が必要です。';
                        else if (res.error === 'csrf') msg = 'セッションが切れました。ページを再読み込みしてください。';
                        else if (res.message) msg = res.message;

                        if (errEl) errEl.textContent = msg;
                        updateVoucherUI202611();
                    });
                });
            }
        }

        // スタンプ帳を開いた時の更新（ガラポン描画 → 応募状態 → サーバーの種数）
        function refreshStampBookRewards202611() {
            renderGacha202611();
            updateVoucherUI202611();
            checkStatus202611();
        }

        window.checkStatus202611             = checkStatus202611;
        window.applyVoucher202611            = applyVoucher202611;
        window.voucherStatus202611           = voucherStatus202611;
        window.renderGacha202611             = renderGacha202611;
        window.updateVoucherUI202611         = updateVoucherUI202611;
        window.initVoucherUI202611           = initVoucherUI202611;
        window.refreshStampBookRewards202611 = refreshStampBookRewards202611;

// ========== ポケボール投擲（タップ投げ） ==========
// ========== ポケボール投擲（タップ即投げ方式） ==========
        // HUDスワイプを廃止し、画面をタップしたらその方向に即時投げる

        var _tapThrowStart = null;
        var _isThrowing   = false;
        var _tapStartTime = 0;

        // ---- ポケボールGLBプリロード (NEXT-5: 毎投擲再パース回避) ----
        var _pokeballTemplate  = null;
        var _pokeballReady     = false;
        var _pokeballPreloadEl = null;

        function initPokeballPool() {
            if (_pokeballReady) return;
            try {
                var scene = document.getElementById('ar-scene');
                if (!scene) return;
                _pokeballPreloadEl = document.createElement('a-entity');
                _pokeballPreloadEl.setAttribute('gltf-model', 'http://localhost/cg/poke_ball_seinei2.glb');
                _pokeballPreloadEl.setAttribute('visible', 'false');
                scene.appendChild(_pokeballPreloadEl);

                function onPokeballLoaded() {
                    if (_pokeballReady) return;
                    var mesh = _pokeballPreloadEl.getObject3D('mesh');
                    if (!mesh) return;
                    _pokeballTemplate = mesh;
                    _pokeballReady = true;
                    // マテリアル最適化をテンプレートに1回のみ適用
                    (function () {
                        var meshIdx = 0;
                        mesh.traverse(function (node) {
                            if (!node.isMesh) return;
                            if (node.geometry) node.geometry.computeVertexNormals();
                            if (node.material) {
                                var mats = Array.isArray(node.material) ? node.material : [node.material];
                                mats.forEach(function (mat, idx) {
                                    mat.side = THREE.FrontSide;
                                    mat.depthWrite = true;
                                    mat.depthTest = true;
                                    mat.polygonOffset = true;
                                    mat.polygonOffsetFactor = meshIdx + idx + 1;
                                    mat.polygonOffsetUnits  = meshIdx + idx + 1;
                                    mat.flatShading = false;
                                    mat.transparent = false;
                                    mat.opacity = 1.0;
                                    mat.alphaTest = 0;
                                    mat.depthFunc = THREE.LessEqualDepth;
                                    mat.dithering = true;
                                    if (mat.metalness !== undefined) { mat.metalness = 0.2; mat.roughness = 0.5; }
                                    mat.needsUpdate = true;
                                });
                                node.renderOrder = 1000 + meshIdx * 10;
                            }
                            meshIdx++;
                        });
                    })();
                    console.log('[pokeball-pool] Preload complete');
                }

                _pokeballPreloadEl.addEventListener('model-loaded', onPokeballLoaded);
                _pokeballPreloadEl.addEventListener('loaded', onPokeballLoaded);
            } catch (e) {
                console.warn('[pokeball-pool] init failed:', e);
            }
        }

        function createPokeballFromPool(cameraPos) {
            var pokeball = document.createElement('a-entity');
            pokeball.setAttribute('scale', '0.15 0.15 0.15');

            if (_pokeballReady && _pokeballTemplate) {
                // 深さ複製: geometry / material を各自 clone（共有 dispose 防止）
                var mesh = _pokeballTemplate.clone(true);
                mesh.traverse(function (node) {
                    if (node.isMesh) {
                        if (node.geometry) node.geometry = node.geometry.clone();
                        if (Array.isArray(node.material)) {
                            node.material = node.material.map(function (m) { return m.clone(); });
                        } else if (node.material) {
                            node.material = node.material.clone();
                        }
                    }
                });
                pokeball.setObject3D('mesh', mesh);
            } else {
                // フォールバック: 従来の GLB ロード
                pokeball.setAttribute('gltf-model', 'http://localhost/cg/poke_ball_seinei2.glb');
            }

            pokeball.setAttribute('position', cameraPos.x + ' ' + cameraPos.y + ' ' + cameraPos.z);
            return pokeball;
        }

        function getActiveVisibleStampId() {
            if (!window.allHitboxes || !Array.isArray(window.allHitboxes)) return '';
            for (var i = 0; i < window.allHitboxes.length; i++) {
                var hb = window.allHitboxes[i];
                if (!hb || !hb.el || !hb.el.object3D) continue;
                if (!hb.el.object3D.visible) continue;
                if (hb.el.parentElement && hb.el.parentElement.object3D && !hb.el.parentElement.object3D.visible) continue;
                if (hb.data && hb.data.stampId) return hb.data.stampId;
            }
            return '';
        }

        // UIボタン上のタップは投げ判定から除外する
        function isUIButton(element) {
            if (!element) return false;
            return !!(
                element.closest('#stamp-book-button')   ||
                element.closest('#guide-button')        ||
                element.closest('#camera-button')       ||
                element.closest('#video-button')        ||

                element.closest('#stamp-book-modal')    ||
                element.closest('#guide-modal')         ||
                element.closest('#photo-preview')       ||
                element.closest('#camera-help-modal')   ||
                element.closest('#confirm-dialog')      ||
                element.closest('#confirm-overlay')     ||
                element.closest('#camera-error')        ||
                element.closest('.arjs-loader')
            );
        }

        // 画面指定方向へポケボールを投げる（NEXT-5: GLB プール利用）
        function throwPokeballInDirection(forwardDir, speed, autoGetStampId) {
            var scene = document.getElementById('ar-scene');
            if (!scene || !scene.camera) return;

            var camera   = scene.camera;
            var cameraPos = camera.getWorldPosition(new THREE.Vector3());

            var throwableConfig = 'autoGetDelayMs: 1000';
            if (autoGetStampId) throwableConfig += '; autoGetStampId: ' + autoGetStampId;

            var pokeball = createPokeballFromPool(cameraPos);
            pokeball.setAttribute('pokeball-throwable', throwableConfig);
            scene.appendChild(pokeball);

            if (_pokeballReady && _pokeballTemplate) {
                // ---- プールパス: マテリアル最適化済みテンプレートの深さ複製 → 即 throw ----
                if (pokeball.components['pokeball-throwable']) {
                    pokeball.components['pokeball-throwable'].throw(forwardDir, speed);
                }
            } else {
                // ---- フォールバック: GLB ロード待ち → マテリアル最適化 → throw ----
                pokeball.addEventListener('loaded', function () {
                    var model = pokeball.getObject3D('mesh');
                    if (model) {
                        var meshIdx = 0;
                        model.traverse(function (node) {
                            if (!node.isMesh) return;
                            if (node.geometry) node.geometry.computeVertexNormals();
                            if (node.material) {
                                var mats = Array.isArray(node.material) ? node.material : [node.material];
                                mats.forEach(function (mat, idx) {
                                    mat.side              = THREE.FrontSide;
                                    mat.depthWrite        = true;
                                    mat.depthTest         = true;
                                    mat.polygonOffset     = true;
                                    mat.polygonOffsetFactor = meshIdx + idx + 1;
                                    mat.polygonOffsetUnits  = meshIdx + idx + 1;
                                    mat.flatShading       = false;
                                    mat.transparent       = false;
                                    mat.opacity           = 1.0;
                                    mat.alphaTest         = 0;
                                    mat.depthFunc         = THREE.LessEqualDepth;
                                    mat.dithering         = true;
                                    if (mat.metalness !== undefined) { mat.metalness = 0.2; mat.roughness = 0.5; }
                                    mat.needsUpdate       = true;
                                });
                                node.renderOrder = 1000 + meshIdx * 10;
                            }
                            meshIdx++;
                        });
                    }
                    if (pokeball.components['pokeball-throwable']) {
                        pokeball.components['pokeball-throwable'].throw(forwardDir, speed);
                    }
                });
            }
        }

        // タッチ開始: 始点とスワイプ方向を記録する（UIボタン上は無視）
        document.addEventListener('touchstart', function (e) {
            if (e.touches.length >= 2) {
                // 2本指ピンチはズーム操作。投げ判定をキャンセル
                _tapThrowStart = null;
                return;
            }
            var touch   = e.touches[0];
            var element = document.elementFromPoint(touch.clientX, touch.clientY);
            if (isUIButton(element)) { _tapThrowStart = null; return; }
            _tapThrowStart = { x: touch.clientX, y: touch.clientY };
            _tapStartTime  = Date.now();
        }, { passive: true });

        // タッチ終了: 始点〜終点の差分からスワイプ方向を計算して投げる
        document.addEventListener('touchend', function (e) {
            if (!_tapThrowStart) return;
            if (_isThrowing) { _tapThrowStart = null; return; }

            var touch = e.changedTouches[0];
            var dx    = touch.clientX - _tapThrowStart.x;
            var dy    = touch.clientY - _tapThrowStart.y;
            _tapThrowStart = null;

            var scene = document.getElementById('ar-scene');
            if (!scene || !scene.camera) return;

            _isThrowing = true;
            setTimeout(function () { _isThrowing = false; }, 500);

            var camera  = scene.camera;
            var camQuat = camera.quaternion.clone();

            // カメラ前方ベクトル（正面）
            var forward = new THREE.Vector3(0, 0, -1).applyQuaternion(camQuat).normalize();

            // カメラ右方向・上方向
            var right = new THREE.Vector3(1, 0, 0).applyQuaternion(camQuat).normalize();
            var up    = new THREE.Vector3(0, 1, 0).applyQuaternion(camQuat).normalize();

            // スワイプ量を画面サイズで正規化してオフセット化する（最大 ±0.4 rad 程度）
            var swipeX =  dx / window.innerWidth  * 0.8; // 左右
            var swipeY = -dy / window.innerHeight * 0.8; // 上下（Y軸反転）

            // スワイプが小さい場合は前方へ（ほぼタップ）
            var dist = Math.sqrt(dx * dx + dy * dy);
            var dir;
            if (dist < 20) {
                dir = forward.clone();
            } else {
                dir = forward.clone()
                    .addScaledVector(right, swipeX)
                    .addScaledVector(up,    swipeY)
                    .normalize();
            }

            // 長押し時間で速度を変化（0.1s 以下: 15, 長押し: 最大 25）
            var holdMs = Date.now() - _tapStartTime;
            var speed  = Math.min(15 + holdMs / 100, 25);

            var autoGetStampId = getActiveVisibleStampId();
            throwPokeballInDirection(dir, speed, autoGetStampId);
        }, { passive: true });

        // PC マウスクリックでも投げられるようにする（タップと同じ動き）
        (function () {
            var _mouseDown = null;
            var _mouseDownTime = 0;
            document.addEventListener('mousedown', function (e) {
                var el = document.elementFromPoint(e.clientX, e.clientY);
                if (isUIButton(el)) { _mouseDown = null; return; }
                _mouseDown     = { x: e.clientX, y: e.clientY };
                _mouseDownTime = Date.now();
            });
            document.addEventListener('mouseup', function (e) {
                if (!_mouseDown || _isThrowing) { _mouseDown = null; return; }
                var dx = e.clientX - _mouseDown.x;
                var dy = e.clientY - _mouseDown.y;
                _mouseDown = null;

                var scene = document.getElementById('ar-scene');
                if (!scene || !scene.camera) return;

                _isThrowing = true;
                setTimeout(function () { _isThrowing = false; }, 500);

                var camQuat = scene.camera.quaternion.clone();
                var forward = new THREE.Vector3(0, 0, -1).applyQuaternion(camQuat).normalize();
                var right   = new THREE.Vector3(1, 0, 0).applyQuaternion(camQuat).normalize();
                var up      = new THREE.Vector3(0, 1, 0).applyQuaternion(camQuat).normalize();

                var dist = Math.sqrt(dx * dx + dy * dy);
                var dir;
                if (dist < 10) {
                    dir = forward.clone();
                } else {
                    var swipeX =  dx / window.innerWidth  * 0.8;
                    var swipeY = -dy / window.innerHeight * 0.8;
                    dir = forward.clone()
                        .addScaledVector(right, swipeX)
                        .addScaledVector(up,    swipeY)
                        .normalize();
                }
                var holdMs = Date.now() - _mouseDownTime;
                var speed  = Math.min(15 + holdMs / 100, 25);
                var autoGetStampId = getActiveVisibleStampId();
                throwPokeballInDirection(dir, speed, autoGetStampId);
            });
        })();

        // showHitEffect: ヒット時の視覚エフェクト（スタンプ帳の更新など）
        function showHitEffect(pokeball, hitbox) {
            // パーティクルを出す（すでに showNormalParticles 等が collectStamp 内で呼ばれるため軽量版のみ）
            try {
                var icons = ['💥', '✨', '⭐'];
                for (var i = 0; i < 5; i++) {
                    (function (idx) {
                        setTimeout(function () {
                            createParticle(icons[Math.floor(Math.random() * icons.length)], false);
                        }, idx * 40);
                    })(i);
                }
            } catch (e) {}
        }
// ========== maker00 ギャラリー機能 ==========
// ========== maker00 ギャラリー機能 ==========
        // marker-00 が見つかったとき、
        //   - Model_00 を (0,0,0) に固定表示（anime03ループ + gallery-hitbox）
        //   - 捕獲済み選択モデル最大4体を GALLERY_POSITIONS に配置（anime03ループ + gallery-hitbox）
        //   - ボールヒット時: anime02一度再生 → anime03ループに戻る（スタンプ取得なし）

        (function () {
            var GALLERY_POSITIONS = [
                { x: 0, y: 0, z:  0.75 },
                { x: 0, y: 0, z: -0.75 },
                { x:  0.75, y: 0, z: 0 },
                { x: -0.75, y: 0, z: 0 }
            ];
            var DEBOUNCE_MS      = 300;
            var LOAD_INTERVAL_MS = 500;

            var galleryCache  = {};   // { stampId: entityElement }
            var galleryMixers = [];
            var markerVisible = false;
            var debounceTimer = null;
            var loadQueue     = [];
            var loadingActive = false;

            // --- Model_00 管理変数 ---
            var model00Entity = null;
            var model00Mixer  = null;
            var model00Action01 = null;
            var model00Action02 = null;

            var marker00 = document.getElementById('marker-00');
            if (!marker00) return;

            // --- Model_00 初期化 ---
            model00Entity = document.getElementById('model-00');
            if (model00Entity) {
                model00Entity.addEventListener('model-loaded', function () {
                    var model = model00Entity.getObject3D('mesh');
                    if (!model || !model.animations || model.animations.length === 0) return;

                    model.traverse(function (node) {
                        if (node.isMesh) node.frustumCulled = false;
                    });

                    model00Mixer = new THREE.AnimationMixer(model);
                    galleryMixers.push(model00Mixer);
                    window.galleryMixers = galleryMixers;

                    model00Entity._galleryMixer   = model00Mixer;

                    var clip01 = THREE.AnimationClip.findByName(model.animations, 'anime03') || model.animations[0];
                    var clip02 = THREE.AnimationClip.findByName(model.animations, 'anime02')
                               || (model.animations.length > 1 ? model.animations[1] : model.animations[0]);

                    model00Action01 = model00Mixer.clipAction(clip01);
                    model00Action01.setLoop(THREE.LoopRepeat, Infinity);
                    model00Action01.stop();

                    model00Action02 = model00Mixer.clipAction(clip02);
                    model00Action02.setLoop(THREE.LoopOnce, 1);
                    model00Action02.clampWhenFinished = true;
                    model00Action02.stop();

                    model00Entity._galleryAction01 = model00Action01;
                    model00Entity._galleryAction02 = model00Action02;

                    // gallery-hitbox を付与
                    model00Entity.setAttribute('gallery-hitbox', 'width: 1.0; height: 2.0; depth: 1.0');

                    // マーカーが既に見えていれば即再生
                    if (markerVisible) {
                        model00Action01.reset();
                        model00Action01.play();
                    }
                });

                model00Entity.addEventListener('model-unloaded', function () {
                    if (model00Mixer) {
                        var idx = galleryMixers.indexOf(model00Mixer);
                        if (idx !== -1) galleryMixers.splice(idx, 1);
                        window.galleryMixers = galleryMixers;
                    }
                    // gallery-hitbox を外す
                    try { model00Entity.removeAttribute('gallery-hitbox'); } catch (e) {}
                    model00Mixer    = null;
                    model00Action01 = null;
                    model00Action02 = null;
                });
            }

            // --- markerFound（デバウンス付き） ---
            marker00.addEventListener('markerFound', function () {
                markerVisible = true;
                if (window.startGalleryMixerLoop) window.startGalleryMixerLoop();
                if (model00Entity) model00Entity.setAttribute('visible', 'true');
                if (debounceTimer) clearTimeout(debounceTimer);
                debounceTimer = setTimeout(onMarkerConfirmed, DEBOUNCE_MS);
            });

            // --- markerLost（モデル非表示のみ、破棄しない） ---
            marker00.addEventListener('markerLost', function () {
                markerVisible = false;
                if (debounceTimer) { clearTimeout(debounceTimer); debounceTimer = null; }
                loadQueue     = [];
                loadingActive = false;
                hideGallery();
                if (window.stopGalleryMixerLoop) window.stopGalleryMixerLoop();
            });

            // --- デバウンス後に呼ばれる本処理 ---
            function onMarkerConfirmed() {
                debounceTimer = null;
                if (!markerVisible) return;

                var ids = (typeof getGallerySelection === 'function') ? getGallerySelection() : [];

                // キャッシュ済みで選択外のモデルは非表示
                Object.keys(galleryCache).forEach(function (key) {
                    if (ids.indexOf(key) === -1) {
                        try { galleryCache[key].setAttribute('visible', 'false'); } catch (e) {}
                    }
                });

                // キャッシュ済みエンティティを再表示（位置を GALLERY_POSITIONS で更新）
                var newIds = [];
                ids.forEach(function (stampId, index) {
                    var pos = GALLERY_POSITIONS[index];
                    if (!pos) return;
                    if (galleryCache[stampId]) {
                        var entity = galleryCache[stampId];
                        entity.setAttribute('position', pos.x + ' ' + pos.y + ' ' + pos.z);
                        entity.setAttribute('visible', 'true');
                    } else {
                        newIds.push({ stampId: stampId, posIndex: index });
                    }
                });

                // 新規モデルは逐次ロード
                if (newIds.length > 0) {
                    loadQueue = newIds.slice();
                    if (!loadingActive) loadNextModel();
                }
            }

            // --- 逐次ロード: 1体ずつ LOAD_INTERVAL_MS 間隔で生成 ---
            function loadNextModel() {
                if (loadQueue.length === 0 || !markerVisible) {
                    loadingActive = false;
                    return;
                }
                loadingActive = true;

                var item    = loadQueue.shift();
                var stampId = item.stampId;
                var pos     = GALLERY_POSITIONS[item.posIndex];
                if (!pos) { setTimeout(loadNextModel, 50); return; }

                // 二重チェック
                if (galleryCache[stampId]) {
                    galleryCache[stampId].setAttribute('visible', 'true');
                    setTimeout(loadNextModel, 50);
                    return;
                }

                var modelPath = STAMPS[stampId] ? STAMPS[stampId].model : null;
                if (!modelPath) { setTimeout(loadNextModel, 50); return; }

                var modelUrl = 'http://localhost/cg/' + modelPath;
                var entity   = document.createElement('a-entity');
                entity.setAttribute('position', pos.x + ' ' + pos.y + ' ' + pos.z);
                entity.setAttribute('scale', '0.48 0.48 0.48');
                entity.setAttribute('rotation', '0 0 0');
                entity.setAttribute('visible', markerVisible ? 'true' : 'false');

                entity.addEventListener('model-loaded', function () {
                    var model = entity.getObject3D('mesh');
                    if (!model || !model.animations || model.animations.length === 0) return;

                    model.traverse(function (node) {
                        if (node.isMesh) node.frustumCulled = false;
                    });

                    var mixer = new THREE.AnimationMixer(model);
                    entity._galleryMixer = mixer;
                    galleryMixers.push(mixer);
                    window.galleryMixers = galleryMixers;

                    var clip01 = THREE.AnimationClip.findByName(model.animations, 'anime03') || model.animations[0];
                    var clip02 = THREE.AnimationClip.findByName(model.animations, 'anime02')
                               || (model.animations.length > 1 ? model.animations[1] : model.animations[0]);

                    var action01 = mixer.clipAction(clip01);
                    action01.setLoop(THREE.LoopRepeat, Infinity);
                    action01.play();

                    var action02 = mixer.clipAction(clip02);
                    action02.setLoop(THREE.LoopOnce, 1);
                    action02.clampWhenFinished = true;
                    action02.stop();

                    entity._galleryAction01 = action01;
                    entity._galleryAction02 = action02;

                    // gallery-hitbox を付与
                    entity.setAttribute('gallery-hitbox', 'width: 1.0; height: 2.0; depth: 1.0');
                });

                entity.setAttribute('gltf-model', modelUrl);
                marker00.appendChild(entity);
                galleryCache[stampId] = entity;

                setTimeout(loadNextModel, LOAD_INTERVAL_MS);
            }

            // --- 全ギャラリーエンティティを非表示（破棄しない） ---
            function hideGallery() {
                Object.keys(galleryCache).forEach(function (key) {
                    try { galleryCache[key].setAttribute('visible', 'false'); } catch (e) {}
                });
                if (model00Entity) model00Entity.setAttribute('visible', 'false');
            }

            // --- ギャラリーヒット時アニメーション ---
            // pokeball-throwable の handleGalleryHit から呼ばれる
            window.playGalleryHitAnimation = function (entity) {
                if (!entity) return;
                var action01 = entity._galleryAction01;
                var action02 = entity._galleryAction02;
                var mixer    = entity._galleryMixer;
                if (!mixer || !action02) return;

                // anime03停止 → anime02再生
                if (action01) action01.stop();
                try {
                    action02.reset();
                    action02.setLoop(THREE.LoopOnce, 1);
                    action02.clampWhenFinished = true;
                    action02.play();

                    var resolved = false;
                    var onFinished = function (ev) {
                        if (ev && ev.action === action02) {
                            try { if (mixer) mixer.removeEventListener('finished', onFinished); } catch (e) {}
                            if (!resolved) {
                                resolved = true;
                                // anime03に戻す
                                try {
                                    action02.stop();
                                    if (action01) { action01.reset(); action01.play(); }
                                } catch (e) {}
                            }
                        }
                    };
                    mixer.addEventListener('finished', onFinished);

                    // タイムアウト保険
                    var clipDuration = 1.0;
                    try {
                        if (action02._clip && action02._clip.duration) clipDuration = action02._clip.duration;
                    } catch (e) {}
                    setTimeout(function () {
                        if (!resolved) {
                            resolved = true;
                            try { if (mixer) mixer.removeEventListener('finished', onFinished); } catch (e) {}
                            try {
                                action02.stop();
                                if (action01) { action01.reset(); action01.play(); }
                            } catch (e) {}
                        }
                    }, (clipDuration * 1000) + 120);
                } catch (e) {
                    // フォールバック: anime03再生
                    try { if (action01) { action01.reset(); action01.play(); } } catch (e2) {}
                }
            };

            // --- 外部からギャラリー再描画を呼べるように公開 ---
            window.refreshGallery = onMarkerConfirmed;

            // --- AnimationMixer更新はjs-init.blade.phpのupdateGalleryMixersに一本化 ---
        })();
// ========== 写真・動画撮影 ==========
// ========== 写真・動画撮影 ==========

        var capturedImageData  = null;
        var isRecording        = false;
        var mediaRecorder      = null;
        var recordedChunks     = [];
        var recordingStartTime = 0;
        (function () {
            var cameraButton      = document.getElementById('camera-button');
            var videoButton       = document.getElementById('video-button');
            // MediaRecorder feature detection: 未実装環境（iOS 14.2以前等）では動画ボタンを非表示にする
            if (typeof MediaRecorder === 'undefined' && videoButton) {
                videoButton.style.display = 'none';
            }
            var photoPreview      = document.getElementById('photo-preview');
            var previewImage      = document.getElementById('preview-image');
            var downloadButton    = document.getElementById('download-button');
            var closeButton       = document.getElementById('close-button');
            var flash             = document.getElementById('flash');

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
