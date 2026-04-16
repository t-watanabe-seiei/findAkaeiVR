        // ========== IndexedDB / Cookie / UUID ヘルパー ==========

        var UserIdDB202605 = {
            dbName:    'ARStampRallyDB202605',
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
                }).catch(function () {});
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

        var CookieHelper202605 = {
            set: function (name, value, days) {
                days = days || 365;
                var exp = new Date();
                exp.setTime(exp.getTime() + days * 24 * 60 * 60 * 1000);
                document.cookie = name + '=' + value + ';expires=' + exp.toUTCString() + ';path=/;SameSite=Strict';
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

        function generateUUID202605() {
            return 'uid_' + 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = Math.random() * 16 | 0;
                var v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }

        function getUserId202605() {
            var storageKey = 'ar-user-id-202605';
            var cookieName = 'ar_user_id_202605';
            var userId = localStorage.getItem(storageKey) || CookieHelper202605.get(cookieName);
            if (userId) {
                localStorage.setItem(storageKey, userId);
                CookieHelper202605.set(cookieName, userId);
                UserIdDB202605.saveUserId(userId);
                return Promise.resolve(userId);
            }
            return UserIdDB202605.getUserId().then(function (uid) {
                if (uid) {
                    localStorage.setItem(storageKey, uid);
                    CookieHelper202605.set(cookieName, uid);
                    return uid;
                }
                var newId = generateUUID202605();
                localStorage.setItem(storageKey, newId);
                CookieHelper202605.set(cookieName, newId);
                UserIdDB202605.saveUserId(newId);
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
                isIOS:       /iPad|iPhone|iPod/.test(navigator.userAgent),
                isAndroid:   /Android/.test(navigator.userAgent)
            };
        }

        function generateFingerprint() {
            return getUserId202605().then(function (userId) {
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

        // ========== マーカースキャン記録 ==========

        function recordMarkerDetection(markerId, markerName) {
            var today    = new Date().toISOString().split('T')[0];
            var cacheKey = 'marker-scan-cache-202605-' + markerId + '-' + today;
            if (localStorage.getItem(cacheKey)) return;
            recordMarkerScan(markerId, markerName, 'marker_scan').then(function () {
                localStorage.setItem(cacheKey, JSON.stringify({ scanned: true, timestamp: new Date().toISOString() }));
            }).catch(function () {});
        }

        function recordMarkerScan(markerId, markerName, captureType) {
            captureType = captureType || 'ball_hit';
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (!csrfMeta) return Promise.resolve();
            var csrfToken = csrfMeta.content;
            return generateFingerprint().then(function (fingerprint) {
                return fetch('{{ url("/api/record-marker-scan") }}', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body:    JSON.stringify({
                        markerId: markerId, markerName: markerName,
                        fingerprint: fingerprint, deviceInfo: collectDeviceInfo(),
                        captureType: captureType, scannedAt: new Date().toISOString()
                    })
                }).then(function (r) { return r.json(); }).catch(function () {});
            });
        }

        // ========== 景品交換機能 (閾値: 6個) ==========

        var PRIZE_EXCHANGE_THRESHOLD = 6;

        function checkPrizeExchangeStatus() {
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (!csrfMeta) return Promise.resolve({ hasExchanged: false });
            var csrfToken = csrfMeta.content;
            return generateFingerprint().then(function (fp) {
                return fetch('{{ url("/api/check-prize-exchange") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body:   JSON.stringify({ fingerprint: fp })
                }).then(function (r) { return r.json(); }).catch(function () { return { hasExchanged: false }; });
            });
        }

        function exchangePrize() {
            var stamps = getCollectedStamps();
            var count  = Object.keys(stamps).length;

            // 閾値未満は交換不可
            if (count < PRIZE_EXCHANGE_THRESHOLD) {
                alert('隠れている動物を' + PRIZE_EXCHANGE_THRESHOLD + '匹以上捕まえると、景品と交換できるよ！');
                return;
            }

            var deviceInfo = collectDeviceInfo();
            var csrfMeta   = document.querySelector('meta[name="csrf-token"]');
            if (!csrfMeta) { alert('エラー：ページをリロードしてください'); return; }
            var csrfToken = csrfMeta.content;

            checkPrizeExchangeStatus().then(function (serverStatus) {
                if (serverStatus.isRedeemed) { showRedeemedPrizeInfo(serverStatus.prizeCode, serverStatus.exchangedAt); return; }
                if (serverStatus.hasExchanged && serverStatus.prizeCode) { showPrizeCode(serverStatus.prizeCode); return; }

                return generateFingerprint().then(function (fp) {
                    return fetch('{{ url("/api/exchange-prize") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body:   JSON.stringify({ fingerprint: fp, deviceInfo: deviceInfo, stamps: stamps })
                    }).then(function (r) { return r.json(); }).then(function (data) {
                        if (data.success) {
                            localStorage.setItem('ar-prize-exchanged-202605', 'true');
                            localStorage.setItem('ar-prize-code-202605', data.prizeCode);
                            updatePrizeButton();
                            showPrizeCode(data.prizeCode);
                        } else {
                            alert(data.message || '景品交換に失敗しました');
                        }
                    });
                });
            }).catch(function () { alert('通信エラーが発生しました'); });
        }

        function showPrizeCode(code) {
            var modal = document.createElement('div');
            modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.9);display:flex;justify-content:center;align-items:center;z-index:10005;';
            modal.innerHTML = '<div style="background:white;padding:30px;border-radius:15px;text-align:center;max-width:90%;">'
                + '<h2 style="color:#4CAF50;margin:0 0 20px 0;">🎉 景品交換完了！ 🎉</h2>'
                + '<p style="font-size:16px;margin-bottom:20px;">以下のコードを受付でお見せください</p>'
                + '<div style="background:#f5f5f5;padding:20px;border-radius:10px;margin-bottom:20px;">'
                + '<div style="font-size:32px;font-weight:bold;color:#333;letter-spacing:3px;">' + code + '</div></div>'
                + '<button onclick="this.parentElement.parentElement.remove()" style="padding:12px 30px;background:#4CAF50;color:white;border:none;border-radius:8px;font-size:16px;cursor:pointer;">閉じる</button>'
                + '</div>';
            document.body.appendChild(modal);
        }

        function showRedeemedPrizeInfo(code, exchangedAt) {
            var dateTimeStr = '';
            if (exchangedAt) {
                var d   = new Date(exchangedAt);
                dateTimeStr = d.getFullYear() + '年' + String(d.getMonth() + 1).padStart(2, '0') + '月' + String(d.getDate()).padStart(2, '0') + '日 '
                            + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
            }
            var modal = document.createElement('div');
            modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.9);display:flex;justify-content:center;align-items:center;z-index:10005;';
            modal.innerHTML = '<div style="background:white;padding:30px;border-radius:15px;text-align:center;max-width:90%;">'
                + '<h2 style="color:#999;margin:0 0 20px 0;">✅ すでに景品と交換済みです</h2>'
                + '<div style="background:#f5f5f5;padding:20px;border-radius:10px;margin-bottom:20px;">'
                + '<div style="font-size:14px;color:#666;margin-bottom:10px;">景品コード</div>'
                + '<div style="font-size:28px;font-weight:bold;color:#333;letter-spacing:3px;margin-bottom:15px;">' + code + '</div>'
                + (dateTimeStr ? '<div style="font-size:14px;color:#666;border-top:1px solid #ddd;padding-top:10px;">交換日時: ' + dateTimeStr + '</div>' : '')
                + '</div>'
                + '<button onclick="this.parentElement.parentElement.remove()" style="padding:12px 30px;background:#999;color:white;border:none;border-radius:8px;font-size:16px;cursor:pointer;">閉じる</button>'
                + '</div>';
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
                    button.textContent = PRIZE_EXCHANGE_THRESHOLD + '匹以上で交換可能 (' + count + '/' + PRIZE_EXCHANGE_THRESHOLD + ')';
                } else {
                    button.disabled = false;
                    button.style.backgroundColor = '#4CAF50';
                    button.textContent = '景品と交換する';
                }
            }).catch(function () {
                button.disabled = !enough;
                button.style.backgroundColor = enough ? '#4CAF50' : '#ccc';
                button.textContent = enough ? '景品と交換する' : PRIZE_EXCHANGE_THRESHOLD + '匹以上で交換可能 (' + count + '/' + PRIZE_EXCHANGE_THRESHOLD + ')';
            });
        }
