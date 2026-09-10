        // ========== IndexedDB / Cookie / UUID ヘルパー ==========

        var UserIdDB202610 = {
            dbName:    'ARStampRallyDB202610',
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
                }).catch(function (err) { console.warn('[AR202610] saveUserId error', err); });
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

        var CookieHelper202610 = {
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

        function generateUUID202610() {
            if (typeof crypto !== 'undefined' && crypto.randomUUID) {
                return 'uid_' + crypto.randomUUID();
            }
            return 'uid_' + 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = Math.random() * 16 | 0;
                var v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }

        function getUserId202610() {
            var storageKey = 'ar-user-id-202610';
            var cookieName = 'ar_user_id_202610';
            var userId = localStorage.getItem(storageKey) || CookieHelper202610.get(cookieName);
            if (userId) {
                localStorage.setItem(storageKey, userId);
                CookieHelper202610.set(cookieName, userId);
                UserIdDB202610.saveUserId(userId);
                return Promise.resolve(userId);
            }
            return UserIdDB202610.getUserId().then(function (uid) {
                if (uid) {
                    localStorage.setItem(storageKey, uid);
                    CookieHelper202610.set(cookieName, uid);
                    return uid;
                }
                var newId = generateUUID202610();
                localStorage.setItem(storageKey, newId);
                CookieHelper202610.set(cookieName, newId);
                UserIdDB202610.saveUserId(newId);
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
            return getUserId202610().then(function (userId) {
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
        // キー形式: marker-scan-cache-202610-{markerId}-{YYYY-MM-DD}
        // 初回ロード時に「今日より古い」日付のキーのみを削除
        function cleanupOldMarkerScanCache() {
            var prefix = 'marker-scan-cache-202610-';
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
                console.warn('[AR202610] cleanupOldMarkerScanCache error', e);
            }
        }

        // ========== マーカースキャン記録 ==========

        function recordMarkerDetection(markerId, markerName) {
            var today    = new Date().toISOString().split('T')[0];
            var cacheKey = 'marker-scan-cache-202610-' + markerId + '-' + today;
            if (localStorage.getItem(cacheKey)) return;
            recordMarkerScan(markerId, markerName, 'marker_scan').then(function () {
                localStorage.setItem(cacheKey, JSON.stringify({ scanned: true, timestamp: new Date().toISOString() }));
            }).catch(function (err) { console.warn('[AR202610] recordMarkerDetection error', err); });
        }

        function recordMarkerScan(markerId, markerName, captureType) {
            captureType = captureType || 'ball_hit';
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (!csrfMeta) return Promise.resolve();
            var csrfToken = csrfMeta.content;
            return generateFingerprint().then(function (fingerprint) {
                return fetch('{{ url("/stamp202610/record-scan") }}', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body:    JSON.stringify({
                        markerId: markerId, markerName: markerName,
                        fingerprint: fingerprint, deviceInfo: collectDeviceInfo(),
                        captureType: captureType, scannedAt: new Date().toISOString()
                    })
                }).then(function (r) {
                    if (!r.ok) { console.warn('[AR202610] recordMarkerScan HTTP ' + r.status); return null; }
                    return r.json();
                }).catch(function (err) { console.warn('[AR202610] recordMarkerScan error', err); });
            });
        }

        // ========== 景品交換機能 (閾値: 10個) ==========

        var PRIZE_EXCHANGE_THRESHOLD = 10;

        function checkPrizeExchangeStatus() {
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (!csrfMeta) return Promise.resolve({ hasExchanged: false });
            var csrfToken = csrfMeta.content;
            return generateFingerprint().then(function (fp) {
                return fetch('{{ url("/stamp202610/check-prize") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body:   JSON.stringify({ fingerprint: fp })
                }).then(function (r) {
                    if (!r.ok) { console.warn('[AR202610] checkPrize HTTP ' + r.status); return { hasExchanged: false }; }
                    return r.json();
                }).catch(function (err) { console.warn('[AR202610] checkPrize error', err); return { hasExchanged: false }; });
            });
        }

        // 二重送信防止: 交換処理実行中は再実行しないガード
        var _exchanging = false;

        function exchangePrize() {
            if (_exchanging) return;
            var stamps = getCollectedStamps();
            var count  = Object.keys(stamps).length;
            // base64スクリーンショットを除外して送付（DB容量・ネットワーク帯域の節約）
            var stampArr = Object.keys(stamps).map(function (sid) {
                return { stampId: sid, collectedAt: stamps[sid].collectedAt || '', name: stamps[sid].name || '' };
            });

            // 閾値未満は交換不可
            if (count < PRIZE_EXCHANGE_THRESHOLD) {
                alert('隠れているキャラクターを' + PRIZE_EXCHANGE_THRESHOLD + '体以上捕まえると、景品と交換できるよ！');
                return;
            }

            var deviceInfo = collectDeviceInfo();
            var csrfMeta   = document.querySelector('meta[name="csrf-token"]');
            if (!csrfMeta) { alert('エラー：ページをリロードしてください'); return; }
            var csrfToken = csrfMeta.content;

            _exchanging = true;
            checkPrizeExchangeStatus().then(function (serverStatus) {
                if (serverStatus.isRedeemed) { _exchanging = false; showPrizeModal({ title:'✅ すでに景品と交換済みです', titleColor:'#999', code:serverStatus.prizeCode, codeFontSize:'28px', label:'景品コード', dateTimeStr:_formatExchangeDateTime(serverStatus.exchangedAt), buttonBg:'#999' }); return; }
                if (serverStatus.hasExchanged && serverStatus.prizeCode) { _exchanging = false; showPrizeModal({ title:'🎉 景品交換完了！ 🎉', titleColor:'#4CAF50', subtitle:'以下のコードを受付でお見せください', code:serverStatus.prizeCode, codeFontSize:'32px', buttonBg:'#4CAF50' }); return; }

                return generateFingerprint().then(function (fp) {
                    return fetch('{{ url("/stamp202610/exchange-prize") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body:   JSON.stringify({ fingerprint: fp, deviceInfo: deviceInfo, stamps: stampArr })
                    }).then(function (r) { return r.json(); }).then(function (data) {
                        if (data.success) {
                            localStorage.setItem('ar-prize-exchanged-202610', 'true');
                            localStorage.setItem('ar-prize-code-202610', data.prizeCode);
                            updatePrizeButton();
                            showPrizeModal({ title:'🎉 景品交換完了！ 🎉', titleColor:'#4CAF50', subtitle:'以下のコードを受付でお見せください', code:data.prizeCode, codeFontSize:'32px', buttonBg:'#4CAF50' });
                        } else {
                            alert(data.message || '景品交換に失敗しました');
                        }
                        _exchanging = false;
                    });
                });
            }).catch(function (err) { console.warn('[AR202610] exchangePrize error', err); alert('通信エラーが発生しました'); _exchanging = false; });
        }

        function _formatExchangeDateTime(exchangedAt) {
            if (!exchangedAt) return '';
            var d = new Date(exchangedAt);
            return d.getFullYear() + '年' + String(d.getMonth() + 1).padStart(2, '0') + '月'
                + String(d.getDate()).padStart(2, '0') + '日 '
                + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        }

        function showPrizeModal(config) {
            var modal = document.createElement('div');
            modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.9);display:flex;justify-content:center;align-items:center;z-index:10005;';
            var inner = document.createElement('div');
            inner.style.cssText = 'background:white;padding:30px;border-radius:15px;text-align:center;max-width:90%;';
            var h2 = document.createElement('h2');
            h2.style.cssText = 'margin:0 0 20px 0;color:' + (config.titleColor || '#4CAF50') + ';';
            h2.textContent = config.title;
            inner.appendChild(h2);
            if (config.subtitle) {
                var p = document.createElement('p');
                p.style.cssText = 'font-size:16px;margin-bottom:20px;';
                p.textContent = config.subtitle;
                inner.appendChild(p);
            }
            var codeBox = document.createElement('div');
            codeBox.style.cssText = 'background:#f5f5f5;padding:20px;border-radius:10px;margin-bottom:20px;';
            if (config.label) {
                var labelDiv = document.createElement('div');
                labelDiv.style.cssText = 'font-size:14px;color:#666;margin-bottom:10px;';
                labelDiv.textContent = config.label;
                codeBox.appendChild(labelDiv);
            }
            var codeDiv = document.createElement('div');
            codeDiv.style.cssText = 'font-size:' + (config.codeFontSize || '32px') + ';font-weight:bold;color:#333;letter-spacing:3px;';
            if (config.dateTimeStr) codeDiv.style.cssText += 'margin-bottom:15px;';
            codeDiv.textContent = config.code;
            codeBox.appendChild(codeDiv);
            if (config.dateTimeStr) {
                var dtDiv = document.createElement('div');
                dtDiv.style.cssText = 'font-size:14px;color:#666;border-top:1px solid #ddd;padding-top:10px;';
                dtDiv.textContent = '交換日時: ' + config.dateTimeStr;
                codeBox.appendChild(dtDiv);
            }
            inner.appendChild(codeBox);
            var btn = document.createElement('button');
            btn.style.cssText = 'padding:12px 30px;background:' + (config.buttonBg || '#4CAF50') + ';color:white;border:none;border-radius:8px;font-size:16px;cursor:pointer;';
            btn.textContent = '閉じる';
            btn.addEventListener('click', function() { modal.remove(); });
            inner.appendChild(btn);
            modal.appendChild(inner);
            document.body.appendChild(modal);
        }

        function updatePrizeButton() {
            var button = document.getElementById('exchange-prize-button');
            if (!button) return;
            var stamps = getCollectedStamps();
            var count  = Object.keys(stamps).length;
            var enough = count >= PRIZE_EXCHANGE_THRESHOLD;

            checkPrizeExchangeStatus().then(function (status) {
                if (status.isRedeemed) {
                    button.disabled = true;
                    button.style.backgroundColor = '#999';
                    button.textContent = '使用済み';
                    return;
                }
                if (status.hasExchanged && status.prizeCode) {
                    button.disabled = false;
                    button.style.backgroundColor = '#4CAF50';
                    button.textContent = '景品コードを表示';
                    return;
                }
                if (!enough) {
                    button.disabled = true;
                    button.style.backgroundColor = '#ccc';
                    button.textContent = PRIZE_EXCHANGE_THRESHOLD + '体以上で交換可能 (' + count + '/' + PRIZE_EXCHANGE_THRESHOLD + ')';
                } else {
                    button.disabled = false;
                    button.style.backgroundColor = '#4CAF50';
                    button.textContent = '景品と交換する';
                }
            }).catch(function () {
                button.disabled = !enough;
                button.style.backgroundColor = enough ? '#4CAF50' : '#ccc';
                button.textContent = enough ? '景品と交換する' : PRIZE_EXCHANGE_THRESHOLD + '体以上で交換可能 (' + count + '/' + PRIZE_EXCHANGE_THRESHOLD + ')';
            });
        }
