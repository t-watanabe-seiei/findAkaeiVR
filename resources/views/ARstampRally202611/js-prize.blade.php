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

        // 検証モード（FR-54）: `?test=1` を付けた URL で読み込んだ端末だけ fingerprint に
        // 接頭辞を付ける。サーバー側の集計・業者提出 CSV は通常これを除外する
        const TEST_FP_PARAM  = @json(config('ar202611.test_param'));
        const TEST_FP_PREFIX = @json(config('ar202611.test_prefix'));

        function isTestMode() {
            try {
                return new URLSearchParams(window.location.search).get(TEST_FP_PARAM) === '1';
            } catch (e) {
                return false;      // URLSearchParams 非対応では検証モード無効
            }
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
                // 検証モードでは test_ を接頭する（同一端末でも本計測とは別アイデンティティ）
                return (isTestMode() ? TEST_FP_PREFIX : '') + 'fp_' + Math.abs(hash).toString(36);
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
            return fetch('{{ url("/stamp202611/csrf") }}', {
                method: 'GET', credentials: 'same-origin', cache: 'no-store'
            }).then(function (r) { return r.ok ? r.json() : null; })
              .then(function (d) {
                  if (d && d.token) { meta.setAttribute('content', d.token); return true; }
                  return false;
              }).catch(function () { return false; });
        }

        function postRecordScan(payload) {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return fetch('{{ url("/stamp202611/record-scan") }}', {
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
        var VOUCHER_THRESHOLD   = {{ (int) config('ar202611.voucher_threshold') }};
        var CANDY_TIERS         = @json(config('ar202611.candy_tiers'));
        var APPLY_DEADLINE_ISO  = '{{ config('ar202611.apply_deadline') }}';
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
                return fetch('{{ url("/stamp202611/check-status") }}', {
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
                    return fetch('{{ url("/stamp202611/apply-voucher") }}', {
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

