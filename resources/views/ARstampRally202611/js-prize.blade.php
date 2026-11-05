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

        // 抽選の参考値行（検出種数・同一ブラウザ合算・注意文）は検証モードのページだけに表示する（FR-59）。
        // fingerprint へ test_prefix を前置する判定（isTestMode）と同じ単一ソースを使い、
        // 「test_ で計測される端末」と「参考値が見える端末」が食い違わないようにする
        const VOUCHER_DIAGNOSTICS_202611 = isTestMode();

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
                // Accept を付けると 429 / 500 が HTML ではなく JSON で返る（エラー種別を分岐できる）
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': meta ? meta.content : '' },
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
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfMeta.content },
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
        // code はメールで届いた確認コード（FR-61）。確認無効の設定時は空文字でよい
        function applyVoucher202611(email, name, code) {
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
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfMeta.content },
                        body: JSON.stringify({
                            fingerprint: fp,
                            deviceInfo: collectDeviceInfo(),
                            email: email,
                            name: name,
                            code: code || ''
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
                            return applyVoucher202611(email, name, code);
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

        // ========== スタンプ帳内のガラポン・抽選応募 UI（FR-40〜FR-44 / FR-52 / FR-60）==========
        // 3点目: 当選確率の倍率はバッジ要素で強調表示する（weight の正本は常に check-status の応答・FR-42）
        var VOUCHER_WEIGHT_INFO202611 = {
            3: { label: '当選確率 3倍！', cls: 'voucher-weight-badge--x3', detail: '20種すべて収集' },
            2: { label: '当選確率 2倍！', cls: 'voucher-weight-badge--x2', detail: '15種以上収集' }
        };

        function voucherWeightInfo202611(weight) {
            return VOUCHER_WEIGHT_INFO202611[parseInt(weight, 10) || 1] || null;
        }

        // スタンプ帳内に表示する倍率バッジ（1倍は控えめなグレー）
        function voucherWeightBadge202611(weight) {
            var info = voucherWeightInfo202611(weight);
            var el   = document.createElement('div');
            if (!info) {
                el.className = 'voucher-weight-badge voucher-weight-badge--base';
                el.textContent = '当選確率 1倍（' + VOUCHER_THRESHOLD + '種以上で応募）';
                return el;
            }
            el.className = 'voucher-weight-badge ' + info.cls;
            el.textContent = info.label + '　' + info.detail;
            return el;
        }

        // 応募完了トーストの HTML（文言は固定値のみ・外部入力は一切挿入しない）
        function voucherToastHTML202611(weight) {
            var info = voucherWeightInfo202611(weight);
            var html = '市内共通商品券の抽選会に応募しました。';
            if (info) html += '<div class="voucher-weight-badge ' + info.cls + '">' + info.label + '</div>';
            html += '<div class="voucher-toast-note">ガラポン会場本部スタッフにこの画面をお見せください。</div>';
            return html;
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

        // HTML 版トースト（倍率バッジを表示する・挿入する HTML は voucherToastHTML202611 の固定文言のみ）
        function showVoucherToastHTML202611(html) {
            var t = document.createElement('div');
            t.innerHTML = html;
            t.style.cssText = 'position:fixed;left:50%;transform:translateX(-50%);bottom:24px;z-index:10010;'
                + 'background:rgba(0,0,0,.9);color:#fff;padding:14px 18px;border-radius:12px;'
                + 'font-size:15px;line-height:1.6;max-width:92%;text-align:center;';
            document.body.appendChild(t);
            setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 6000);
        }

        // ========== ガラポン押下前の確認ダイアログ（FR-62）==========
        // ボタン押下では引き済みにならない。#gacha-confirm-dialog の「はい」を押したときだけ
        // drawGacha202611()（LocalStorage へ記録）を実行する。「いいえ」は記録を残さない。
        var _gachaPendingTier202611 = null;

        function openGachaConfirm202611(tier) {
            var overlay = document.getElementById('gacha-confirm-overlay');
            var dialog  = document.getElementById('gacha-confirm-dialog');
            var message = document.getElementById('gacha-confirm-message');

            _gachaPendingTier202611 = tier;
            if (message) message.textContent = tier + '種ガラポンを引き済みにしますか？';
            if (overlay) overlay.style.display = 'block';
            if (dialog)  dialog.style.display  = 'block';
        }

        function closeGachaConfirm202611() {
            var overlay = document.getElementById('gacha-confirm-overlay');
            var dialog  = document.getElementById('gacha-confirm-dialog');

            _gachaPendingTier202611 = null;
            if (overlay) overlay.style.display = 'none';
            if (dialog)  dialog.style.display  = 'none';
        }

        // 引き済み確定（FR-39 の回数管理はそのまま・サーバー送信しない）
        function confirmGachaDraw202611(tier) {
            var res = drawGacha202611(tier);

            if (res.error === 'already_drawn') showVoucherToast202611('この段階のガラポンはすでに引いています。');
            else if (res.error === 'not_reached') showVoucherToast202611(tier + '種類集めてから引けます。');
            else if (res.error === 'all_drawn') showVoucherToast202611('ガラポンはすべて引きました。');
            else showVoucherToast202611('ガラポン会場本部スタッフにこの画面をお見せください。');

            renderGacha202611();
        }

        // ダイアログのはい / いいえ / オーバーレイ押下（1 回だけ結線する）
        function initGachaConfirm202611() {
            var yes     = document.getElementById('gacha-confirm-yes');
            var no      = document.getElementById('gacha-confirm-no');
            var overlay = document.getElementById('gacha-confirm-overlay');

            if (yes) {
                yes.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var tier = _gachaPendingTier202611;
                    closeGachaConfirm202611();
                    if (tier !== null) confirmGachaDraw202611(tier);
                });
            }
            if (no) {
                no.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeGachaConfirm202611();
                    showVoucherToast202611('ガラポンは引いていません。');
                });
            }
            if (overlay) {
                overlay.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeGachaConfirm202611();
                });
            }
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

                // 1点目: ボタンは「N種ガラポン」のみ。未到達には「（あとM種）」を併記（FR-39 / FR-60）
                if (draws.indexOf(tier) !== -1) {
                    b.disabled = true;
                    b.textContent = tier + '種ガラポン ✅ 引き済み';
                } else if (reached.indexOf(tier) === -1) {
                    b.disabled = true;
                    b.textContent = tier + '種ガラポン（あと' + Math.max(0, tier - count) + '種）';
                } else {
                    b.disabled = false;
                    b.textContent = tier + '種ガラポン';
                }

                b.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    // FR-62: 押下時は必ず確認ダイアログを経由する（この時点では引き済みにならない）
                    var draws = getGachaDraws202611();
                    if (draws.indexOf(tier) !== -1) {
                        showVoucherToast202611('この段階のガラポンはすでに引いています。');
                        renderGacha202611();
                        return;
                    }
                    if (reachableTiers202611().indexOf(tier) === -1) {
                        showVoucherToast202611(tier + '種類集めてから引けます。');
                        return;
                    }
                    openGachaConfirm202611(tier);
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

            // 正本はこの端末の「収集種数」（サーバー算出・capture_type='ball_hit'・FR-56 / FR-58）。
            // 合算値（検出込み）とマーカー検出種数は参考値としてのみ表示し、
            // その参考値行は検証モード（?test=1）のページだけに表示する（FR-59）。
            var voucher  = (_voucherStatus && _voucherStatus.voucher) ? _voucherStatus.voucher : null;
            var deviceCount   = _voucherStatus ? parseInt(_voucherStatus.deviceStampCount, 10) : NaN;
            if (isNaN(deviceCount) && voucher) deviceCount = parseInt(voucher.deviceStampCount, 10);
            if (isNaN(deviceCount)) deviceCount = null;
            var detectedCount = _voucherStatus ? parseInt(_voucherStatus.deviceDetectedCount, 10) : NaN;
            if (isNaN(detectedCount)) detectedCount = null;
            var aggregateCount = _voucherStatus ? parseInt(_voucherStatus.stampCount, 10) : NaN;
            if (isNaN(aggregateCount)) aggregateCount = null;
            var applied  = voucher ? (voucher.applied === true) : isVoucherAppliedLocal202611();
            var weight   = voucher ? (parseInt(voucher.weight, 10) || 0) : 0;
            var deadline = formatDeadline202611();

            var lines = [];
            lines.push('この端末の収集: '
                + (deviceCount === null ? '読み込み中…' : deviceCount + ' 種')
                + '（抽選の基準・この端末 ' + collectedCount202611() + ' 種）');
            if (VOUCHER_DIAGNOSTICS_202611 && detectedCount !== null && detectedCount > 0) {
                lines.push('この端末が見つけたマーカー: ' + detectedCount + ' 種（抽選には含めません）');
            }
            if (VOUCHER_DIAGNOSTICS_202611 && aggregateCount !== null && deviceCount !== null && aggregateCount > deviceCount) {
                lines.push('同一ブラウザの合算: ' + aggregateCount + ' 種（参考）');
            }
            lines.push('応募期限: ' + deadline);
            if (applied) {
                // 倍率は直後に続くバッジ要素で強調表示する（FR-60）
                lines.push('市内共通商品券の抽選会に応募しました。');
            } else if (deviceCount !== null && deviceCount >= VOUCHER_THRESHOLD) {
                lines.push('抽選応募が可能です（1端末で' + VOUCHER_THRESHOLD + '種以上収集）');
            }

            statusEl.innerHTML = '';
            var weightBadge = applied ? voucherWeightBadge202611(weight) : null;
            lines.forEach(function (text) {
                var p = document.createElement('p');
                p.textContent = text;
                statusEl.appendChild(p);
                if (weightBadge && text === '市内共通商品券の抽選会に応募しました。') {
                    statusEl.appendChild(weightBadge);
                    weightBadge = null;
                }
            });

            // ボタンの表示可否（応募済み / 未送信あり / 期限経過後 / 応答なし は非表示）
            // 応募可否の判定は収集種数（deviceStampCount）のみで行う（FR-58）
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
            } else if (deviceCount === null) {
                showButton = false;
            } else if (deviceCount < VOUCHER_THRESHOLD) {
                disabled = true;
                button.textContent = '抽選応募には' + VOUCHER_THRESHOLD + '種の収集が必要（現在' + deviceCount + '種）';
                if (VOUCHER_DIAGNOSTICS_202611 && detectedCount !== null && detectedCount >= VOUCHER_THRESHOLD) {
                    var hint = document.createElement('p');
                    hint.textContent = 'マーカーを ' + detectedCount + ' 種見つけていますが、抽選にはボールを当てて収集した種数だけ使えます。';
                    statusEl.appendChild(hint);
                }
            } else {
                button.textContent = '商品券抽選に応募する';
            }

            button.style.display = showButton ? 'block' : 'none';
            button.disabled = disabled;

            if (form && !showButton) form.style.display = 'none';
            updateVoucherAppliedInfo202611(applied, voucher);
        }


        // 応募後の表示（FR-63）: 応募したメールアドレス（マスク済み）・変更ボタン・抽選案内
        function updateVoucherAppliedInfo202611(applied, voucher) {
            var appliedInfo = document.getElementById('voucher-applied-info');
            var appliedMail = document.getElementById('voucher-applied-email');
            var changeBtn   = document.getElementById('voucher-change-email');
            var changeForm  = document.getElementById('voucher-change-form');
            var lotteryNote = document.getElementById('voucher-lottery-note');

            // 抽選案内は応募後だけに表示する
            if (lotteryNote) lotteryNote.style.display = applied ? 'block' : 'none';

            if (!appliedInfo) return;

            if (!applied) {
                appliedInfo.style.display = 'none';
                // 変更フォームは応募済み行に対してだけ開くため、応募が無い状態では閉じる
                if (changeForm) {
                    changeForm.style.display = 'none';
                    _voucherChangeOpen202611 = false;
                }
                return;
            }

            var shown = (voucher && voucher.email) ? String(voucher.email) : '';
            if (appliedMail) appliedMail.textContent = shown || '（取得できませんでした。ページを再読み込みしてください）';
            appliedInfo.style.display = 'block';

            if (changeBtn) {
                // 変更期限（email_change_deadline）以降は変更を受け付けない
                var canChange = !isEmailChangeDeadlinePassed202611();
                changeBtn.style.display = canChange ? 'inline-block' : 'none';
                changeBtn.disabled = !canChange;
            }

            if (changeForm && changeForm.style.display === 'block' && !_voucherChangeOpen202611) {
                changeForm.style.display = 'none';
            }
        }

        // ========== 抽選応募のメールアドレス確認（FR-61 / design §5.7）==========
        var _requestingVoucherEmailCode202611 = false;
        var _voucherEmailConfirmed202611 = false;   // 送信先アドレスを利用者が目視確認済みか
        var _voucherSentEmail202611 = '';           // 確認コードを送信したメールアドレス（正規化後）

        // 全角英数記号（U+FF01〜U+FF5E）を半角へ、全角/半角スペースを除去する（打ち間違いの自動補正）
        function normalizeEmailInput202611(value) {
            return String(value || '')
                .replace(/\u3000/g, '')
                .replace(/[\uFF01-\uFF5E]/g, function (ch) {
                    return String.fromCharCode(ch.charCodeAt(0) - 0xFEE0);
                })
                .replace(/\s+/g, '')
                .trim()
                .toLowerCase();
        }

        function hasFullWidth202611(value) { return /[\u3000\uFF01-\uFF5E]/.test(String(value || '')); }
        function hasSpace202611(value) { return /\s/.test(String(value || '')); }
        function isValidEmail202611(value) { return /^[a-z0-9._%+-]+@[a-z0-9-]+(\.[a-z0-9-]+)+$/.test(String(value || '')); }

        // サーバーのエラー種別を利用者向けの文言に変換する
        function voucherCodeErrorText202611(res) {
            if (res.error === 'not_eligible') return res.message || '抽選応募の条件を満たしていません。';
            if (res.error === 'already_applied') return 'すでに応募済みです。1 メールアドレスにつき 1 口です。';
            if (res.error === 'deadline_passed') return '応募期限を過ぎたため応募できません。';
            // 応募後のメールアドレス変更（FR-63）
            if (res.error === 'not_applied') return '応募が見つかりません。ページを再読み込みしてください。';
            if (res.error === 'same_email') return '現在登録済みのメールアドレスです。別のアドレスを入力してください。';
            if (res.error === 'email_in_use') return 'そのメールアドレスはすでに別の応募で使われています。';
            if (res.error === 'change_deadline_passed') return '抽選の準備のため、メールアドレスは変更できません。';
            if (res.error === 'invalid_email') return res.message || 'メールアドレスのドメインが見つかりません。入力をご確認ください。';
            if (res.error === 'too_soon') {
                var wait = res.waitSeconds ? Math.ceil(res.waitSeconds) : 30;
                return '同じアドレスへ ' + wait + ' 秒以内に再送信しました。アドレスを直した場合はそのままもう一度押してください。';
            }
            // 1 時間あたりの送信回数上限（throttle）。打ち間違いの再試行が続いたときに出る
            if (res.error === 'too_many') {
                var limitWait = res.waitSeconds ? Math.ceil(res.waitSeconds / 60) : 60;
                return 'この端末からの送信回数が増えすぎました。' + limitWait + ' 分ほど置いてからもう一度お試しください。';
            }
            // サーバー側の一時的な混雑（SQLite のロックなど・503）
            if (res.error === 'server_busy') return res.message || 'サーバーが混み合っています。10 秒ほどおいてもう一度お試しください。';
            if (res.error === 'mail_failed') return '確認メールを送信できませんでした。時間をおいてもう一度お試しください。';
            if (res.error === 'offline') return '確認コードの送信には通信が必要です。';
            if (res.error === 'csrf') return 'セッションが切れました。ページを再読み込みしてください。';
            if (res.error === 'network') return '通信に失敗しました。時間をおいてもう一度お試しください。';
            // 想定外のステータス（HTML が返った等）は種別をログに出す（原因特定のため）
            console.warn('[AR202611] voucher code error', res.error, res.status, res.message);
            return res.message || '確認コードを送信できませんでした。時間をおいてもう一度お試しください。';
        }

        // 確認コード送信（POST /stamp202611/voucher-email-code・CSRF 陳腐化は 1 回だけ再試行・FR-26）
        function requestVoucherEmailCode202611(email) {
            if (_requestingVoucherEmailCode202611) return Promise.resolve({ success: false, error: 'busy' });
            if (!navigator.onLine) return Promise.resolve({ success: false, error: 'offline' });

            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (!csrfMeta) return Promise.resolve({ success: false, error: 'csrf' });

            _requestingVoucherEmailCode202611 = true;

            return Promise.resolve(typeof refreshCsrfToken === 'function' ? refreshCsrfToken() : false)
                .then(function () { return generateFingerprint(); })
                .then(function (fp) {
                    return fetch('{{ url("/stamp202611/voucher-email-code") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfMeta.content },
                        body: JSON.stringify({ fingerprint: fp, email: email })
                    }).then(function (r) {
                        return r.json().catch(function () { return {}; }).then(function (d) {
                            return { status: r.status, data: d || {} };
                        });
                    });
                })
                .then(function (res) {
                    var data = res.data;

                    if (res.status === 200 && data.success) {
                        return {
                            success: true,
                            verificationRequired: data.verification_required !== false,
                            email: data.email || '',
                            ttlMinutes: parseInt(data.ttlMinutes, 10) || 15,
                            resendInterval: parseInt(data.resendInterval, 10) || 30
                        };
                    }

                    if (res.status === 419) {
                        return refreshCsrfToken().then(function (ok) {
                            if (!ok) return { success: false, error: 'csrf' };
                            _requestingVoucherEmailCode202611 = false;
                            return requestVoucherEmailCode202611(email);
                        });
                    }

                    return {
                        success: false,
                        error: data.error || ('HTTP ' + res.status),
                        message: data.message || '',
                        waitSeconds: data.waitSeconds
                    };
                })
                .catch(function (err) {
                    console.warn('[AR202611] requestEmailCode error', err);
                    return { success: false, error: 'network' };
                })
                .then(function (out) {
                    _requestingVoucherEmailCode202611 = false;
                    return out;
                });
        }

        // 応募フォームの 2 ステップ送信（メール送信 → 確認コード → 応募・FR-41 / FR-44 / FR-61）
        function initVoucherUI202611() {
            var button = document.getElementById('voucher-apply-button');
            var form   = document.getElementById('voucher-form');
            if (!button || !form) return;

            var stepEmail   = document.getElementById('voucher-step-email');
            var stepCode    = document.getElementById('voucher-step-code');
            var submit      = document.getElementById('voucher-submit');
            var cancel      = document.getElementById('voucher-cancel');
            var sendBtn     = document.getElementById('voucher-send-code');
            var resendBtn   = document.getElementById('voucher-resend-code');
            var editBtn     = document.getElementById('voucher-edit-email');
            var errEl       = document.getElementById('voucher-error');
            var emailEl     = document.getElementById('voucher-email');
            var nameEl      = document.getElementById('voucher-name');
            var codeEl      = document.getElementById('voucher-code');
            var confirmBox  = document.getElementById('voucher-email-confirm');
            var confirmMail = document.getElementById('voucher-email-confirm-target');
            var codeMail    = document.getElementById('voucher-code-email');
            var codeTtl     = document.getElementById('voucher-code-ttl');

            function showError(text) { if (errEl) errEl.textContent = text; }
            function clearError()   { if (errEl) errEl.textContent = ''; }

            function showStep(which) {
                if (stepEmail) stepEmail.style.display = (which === 'code') ? 'none' : 'block';
                if (stepCode)  stepCode.style.display  = (which === 'code') ? 'block' : 'none';
            }

            // 送信先確認（2 押し込み方式）を未確認へ戻す
            function resetEmailConfirm() {
                _voucherEmailConfirmed202611 = false;
                if (confirmBox) confirmBox.style.display = 'none';
                if (sendBtn) sendBtn.textContent = '確認コードを送信';
            }

            function closeForm() {
                form.style.display = 'none';
                clearError();
                resetEmailConfirm();
                showStep('email');
            }

            function currentEmail() {
                return normalizeEmailInput202611(emailEl ? emailEl.value : '');
            }

            // 応募送信（確認無効の設定時は code を空のまま送る）
            function submitApplication(code) {
                var email = _voucherSentEmail202611 || currentEmail();
                var name  = nameEl ? nameEl.value.trim() : '';

                if (submit) {
                    submit.disabled = true;
                    submit.textContent = '応募中…';
                }

                applyVoucher202611(email, name, code).then(function (res) {
                    if (submit) {
                        submit.disabled = false;
                        submit.textContent = '応募する';
                    }

                    if (res.success) {
                        _voucherSentEmail202611 = '';
                        closeForm();
                        var st = voucherStatus202611();
                        var weight = (st && st.voucher) ? (parseInt(st.voucher.weight, 10) || 1) : 1;
                        showVoucherToastHTML202611(voucherToastHTML202611(weight));
                        updateVoucherUI202611();
                        return;
                    }

                    // 確認コード関連のエラーは入力欄を空にして再入力を促す
                    if (res.error === 'code_invalid' || res.error === 'code_required') {
                        var left = (res.remainingAttempts === undefined || res.remainingAttempts === null)
                            ? '' : '（残り ' + res.remainingAttempts + ' 回）';
                        showError((res.message || '確認コードが一致しません。') + left);
                        if (codeEl) { codeEl.value = ''; codeEl.focus(); }
                        return;
                    }
                    if (res.error === 'code_expired') {
                        showError('確認コードが無効または期限切れです。「コードを再送信」から新しいコードを取得してください。');
                        if (codeEl) { codeEl.value = ''; codeEl.focus(); }
                        return;
                    }

                    var msg = '応募に失敗しました。時間をおいてもう一度お試しください。';
                    if (res.error === 'already_applied') msg = 'すでに応募済みです。1 メールアドレスにつき 1 口です。';
                    else if (res.error === 'not_eligible') msg = res.message || '抽選応募の条件を満たしていません。';
                    else if (res.error === 'deadline_passed') msg = '応募期限を過ぎたため応募できません。';
                    else if (res.error === 'offline') msg = '抽選応募には通信が必要です。';
                    else if (res.error === 'csrf') msg = 'セッションが切れました。ページを再読み込みしてください。';
                    else if (res.message) msg = res.message;

                    showError(msg);
                    updateVoucherUI202611();
                });
            }

            // 入力中のアドレスが変わったら送信先確認をやり直す（確認済みアドレスと違う値のため）
            if (emailEl) {
                emailEl.addEventListener('input', function () {
                    resetEmailConfirm();
                    clearError();
                });
            }

            button.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (!navigator.onLine) {
                    showVoucherToast202611('抽選応募には通信が必要です。オンラインになってからもう一度お試しください。');
                    return;
                }
                form.style.display = 'block';
                clearError();
                resetEmailConfirm();
                showStep('email');
                if (emailEl) emailEl.focus();
            });

            if (cancel) {
                cancel.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeForm();
                });
            }

            // ステップ1: 全角の自動補正 → 送信先の再確認 → 確認コード送信
            if (sendBtn) {
                sendBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    var raw   = emailEl ? emailEl.value : '';
                    var email = normalizeEmailInput202611(raw);
                    var name  = nameEl ? nameEl.value.trim() : '';

                    if (!email) { showError('メールアドレスを入力してください。'); return; }

                    if (hasFullWidth202611(raw) || hasSpace202611(raw)) {
                        showError('メールアドレスを半角に直しました: ' + email);
                        if (emailEl) emailEl.value = email;
                    }

                    if (!isValidEmail202611(email)) {
                        resetEmailConfirm();
                        showError('メールアドレスの形が正しくありません（例: example@example.com）。');
                        return;
                    }
                    if (!name) { showError('お名前を入力してください。'); return; }

                    // 1 回目の押下: 送信先を表示して確認を求める（別アドレスに送らないための確認）
                    if (!_voucherEmailConfirmed202611) {
                        _voucherEmailConfirmed202611 = true;
                        if (confirmMail) confirmMail.textContent = email;
                        if (confirmBox) confirmBox.style.display = 'block';
                        sendBtn.textContent = 'このアドレスに送信';
                        clearError();
                        return;
                    }

                    sendBtn.disabled = true;
                    sendBtn.textContent = '送信中…';

                    requestVoucherEmailCode202611(email).then(function (res) {
                        sendBtn.disabled = false;
                        sendBtn.textContent = '確認コードを送信';

                        if (!res.success) {
                            showError(voucherCodeErrorText202611(res));
                            updateVoucherUI202611();
                            return;
                        }

                        _voucherSentEmail202611 = email;
                        resetEmailConfirm();

                        if (!res.verificationRequired) {
                            submitApplication('');   // 確認無効（開発環境）はそのまま応募する
                            return;
                        }

                        showStep('code');
                        clearError();
                        showVoucherToast202611('確認コードを ' + res.email + ' に送信しました（' + res.ttlMinutes + ' 分以内）。');
                        if (codeMail) codeMail.textContent = res.email;   // サーバーが返すマスク済みアドレス
                        if (codeTtl) codeTtl.textContent = String(res.ttlMinutes);
                        if (codeEl) { codeEl.value = ''; codeEl.focus(); }
                    });
                });
            }

            // ステップ2: 確認コード付きで応募
            if (submit) {
                submit.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    submitApplication(codeEl ? codeEl.value.trim() : '');
                });
            }

            // コードを再送信（送信済みアドレス宛・送信先確認は済んでいる）
            if (resendBtn) {
                resendBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    resendBtn.disabled = true;
                    resendBtn.textContent = '送信中…';

                    requestVoucherEmailCode202611(_voucherSentEmail202611).then(function (res) {
                        resendBtn.disabled = false;
                        resendBtn.textContent = 'コードを再送信';

                        if (!res.success) {
                            showError(voucherCodeErrorText202611(res));
                            return;
                        }

                        clearError();
                        showVoucherToast202611('新しい確認コードを送信しました（' + res.ttlMinutes + ' 分以内）。');
                        if (codeMail) codeMail.textContent = res.email;
                        if (codeTtl) codeTtl.textContent = String(res.ttlMinutes);
                        if (codeEl) { codeEl.value = ''; codeEl.focus(); }
                    });
                });
            }

            // メールアドレスを変更（送信済みコードは使わない）
            if (editBtn) {
                editBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    _voucherSentEmail202611 = '';
                    resetEmailConfirm();
                    showStep('email');
                    clearError();
                    if (emailEl) emailEl.focus();
                });
            }
        }

        // ========== 応募後のメールアドレス変更（FR-63 / design §5.8）==========
        // 応募済み行のメールアドレスは、新しいアドレスへ 6 桁コードを送信して
        // 入力してもらってからだけ書き換わる（応募用のコードとは別キー・使い回ししない）。
        var _voucherChangeConfirmed202611 = false; // 送信先アドレスを利用者が目視確認済みか
        var _voucherChangeSentEmail202611 = '';    // 変更用コードを送信した新しいアドレス（正規化後）
        var _voucherChangeOpen202611        = false; // 変更フォームを開いているか
        var _requestingVoucherChange202611  = false;

        // 変更期限（check-status の emailChangeDeadline）。応答が無ければ期限前として扱う
        function isEmailChangeDeadlinePassed202611() {
            var iso = (_voucherStatus && _voucherStatus.emailChangeDeadline) ? _voucherStatus.emailChangeDeadline : '';
            if (!iso) return false;
            var d = new Date(iso);
            if (isNaN(d.getTime())) return false;
            return Date.now() > d.getTime();
        }

        // 変更用コードの送信（POST /stamp202611/voucher-email-change-code・CSRF 陳腐化は 1 回だけ再試行）
        function requestVoucherEmailChangeCode202611(email) {
            if (_requestingVoucherChange202611) return Promise.resolve({ success: false, error: 'busy' });
            if (!navigator.onLine) return Promise.resolve({ success: false, error: 'offline' });

            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (!csrfMeta) return Promise.resolve({ success: false, error: 'csrf' });

            _requestingVoucherChange202611 = true;

            return Promise.resolve(typeof refreshCsrfToken === 'function' ? refreshCsrfToken() : false)
                .then(function () { return generateFingerprint(); })
                .then(function (fp) {
                    return fetch('{{ url("/stamp202611/voucher-email-change-code") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfMeta.content },
                        body: JSON.stringify({ fingerprint: fp, email: email })
                    }).then(function (r) {
                        return r.json().catch(function () { return {}; }).then(function (d) {
                            return { status: r.status, data: d || {} };
                        });
                    });
                })
                .then(function (res) {
                    var data = res.data;

                    if (res.status === 200 && data.success) {
                        return {
                            success: true,
                            verificationRequired: data.verification_required !== false,
                            email: data.email || '',
                            ttlMinutes: parseInt(data.ttlMinutes, 10) || 15,
                            resendInterval: parseInt(data.resendInterval, 10) || 30
                        };
                    }

                    if (res.status === 419) {
                        return refreshCsrfToken().then(function (ok) {
                            if (!ok) return { success: false, error: 'csrf' };
                            _requestingVoucherChange202611 = false;
                            return requestVoucherEmailChangeCode202611(email);
                        });
                    }

                    return {
                        success: false,
                        error: data.error || ('HTTP ' + res.status),
                        message: data.message || '',
                        waitSeconds: data.waitSeconds
                    };
                })
                .catch(function (err) {
                    console.warn('[AR202611] requestEmailChangeCode error', err);
                    return { success: false, error: 'network' };
                })
                .then(function (out) {
                    _requestingVoucherChange202611 = false;
                    return out;
                });
        }

        // 変更の確定（POST /stamp202611/voucher-email-change）。成功後は check-status を引き直して表示を同期する
        function changeVoucherEmail202611(email, code) {
            if (_applyingVoucher) return Promise.resolve({ success: false, error: 'busy' });
            if (!navigator.onLine) return Promise.resolve({ success: false, error: 'offline' });

            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (!csrfMeta) return Promise.resolve({ success: false, error: 'csrf' });

            _applyingVoucher = true;

            return Promise.resolve(typeof refreshCsrfToken === 'function' ? refreshCsrfToken() : false)
                .then(function () { return generateFingerprint(); })
                .then(function (fp) {
                    return fetch('{{ url("/stamp202611/voucher-email-change") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfMeta.content },
                        body: JSON.stringify({ fingerprint: fp, email: email, code: code || '' })
                    }).then(function (r) {
                        return r.json().catch(function () { return {}; }).then(function (d) {
                            return { status: r.status, data: d || {} };
                        });
                    });
                })
                .then(function (res) {
                    var data = res.data;

                    if (res.status === 200 && data.success) {
                        return checkStatus202611().then(function () {
                            return { success: true, email: data.email || '' };
                        });
                    }

                    if (res.status === 419) {
                        return refreshCsrfToken().then(function (ok) {
                            if (!ok) return { success: false, error: 'csrf' };
                            _applyingVoucher = false;
                            return changeVoucherEmail202611(email, code);
                        });
                    }

                    return {
                        success: false,
                        error: data.error || ('HTTP ' + res.status),
                        message: data.message || '',
                        remainingAttempts: data.remainingAttempts
                    };
                })
                .catch(function (err) {
                    console.warn('[AR202611] changeVoucherEmail error', err);
                    return { success: false, error: 'network' };
                })
                .then(function (out) {
                    _applyingVoucher = false;
                    return out;
                });
        }

        // 変更フォームの 2 ステップ送信（新アドレス入力 → 送信先確認 → コード入力 → 変更完了）
        function initVoucherEmailChange202611() {
            var openBtn  = document.getElementById('voucher-change-email');
            var form     = document.getElementById('voucher-change-form');
            if (!openBtn || !form) return;

            var stepEmail   = document.getElementById('voucher-change-step-email');
            var stepCode    = document.getElementById('voucher-change-step-code');
            var emailEl     = document.getElementById('voucher-change-email-input');
            var codeEl      = document.getElementById('voucher-change-code-input');
            var sendBtn     = document.getElementById('voucher-change-send-code');
            var resendBtn   = document.getElementById('voucher-change-resend-code');
            var submit      = document.getElementById('voucher-change-submit');
            var cancel      = document.getElementById('voucher-change-cancel');
            var errEl       = document.getElementById('voucher-change-error');
            var confirmBox  = document.getElementById('voucher-change-email-confirm');
            var confirmMail = document.getElementById('voucher-change-email-confirm-target');
            var codeMail    = document.getElementById('voucher-change-code-email');
            var codeTtl     = document.getElementById('voucher-change-code-ttl');

            function showError(text) { if (errEl) errEl.textContent = text; }
            function clearError()   { if (errEl) errEl.textContent = ''; }

            function showStep(which) {
                if (stepEmail) stepEmail.style.display = (which === 'code') ? 'none' : 'block';
                if (stepCode)  stepCode.style.display  = (which === 'code') ? 'block' : 'none';
            }

            function resetConfirm() {
                _voucherChangeConfirmed202611 = false;
                if (confirmBox) confirmBox.style.display = 'none';
                if (sendBtn) sendBtn.textContent = '確認コードを送信';
            }

            function openForm() {
                _voucherChangeOpen202611 = true;
                _voucherChangeSentEmail202611 = '';
                form.style.display = 'block';
                clearError();
                resetConfirm();
                showStep('email');
                if (codeEl) codeEl.value = '';
                if (emailEl) emailEl.focus();
            }

            function closeForm() {
                _voucherChangeOpen202611 = false;
                _voucherChangeSentEmail202611 = '';
                form.style.display = 'none';
                clearError();
                resetConfirm();
                showStep('email');
                if (codeEl) codeEl.value = '';
            }

            function currentEmail() {
                return normalizeEmailInput202611(emailEl ? emailEl.value : '');
            }

            // 変更確定（確認無効の設定時は code を空のまま送る）
            function applyChange(code) {
                var email = _voucherChangeSentEmail202611 || currentEmail();

                if (!email) { showError('新しいメールアドレスを入力してください。'); return; }

                if (submit) {
                    submit.disabled = true;
                    submit.textContent = '変更中…';
                }

                changeVoucherEmail202611(email, code).then(function (res) {
                    if (submit) {
                        submit.disabled = false;
                        submit.textContent = '変更完了';
                    }

                    if (res.success) {
                        closeForm();
                        if (emailEl) emailEl.value = '';
                        showVoucherToast202611('メールアドレスを変更しました。');
                        updateVoucherUI202611();
                        return;
                    }

                    // 確認コード関連のエラーは入力欄を空にして再入力を促す
                    if (res.error === 'code_invalid' || res.error === 'code_required') {
                        var left = (res.remainingAttempts === undefined || res.remainingAttempts === null)
                            ? '' : '（残り ' + res.remainingAttempts + ' 回）';
                        showError((res.message || '確認コードが一致しません。') + left);
                        if (codeEl) { codeEl.value = ''; codeEl.focus(); }
                        return;
                    }
                    if (res.error === 'code_expired') {
                        showError('確認コードが無効または期限切れです。「コードを再送信」から新しいコードを取得してください。');
                        if (codeEl) { codeEl.value = ''; codeEl.focus(); }
                        return;
                    }

                    showError(voucherCodeErrorText202611(res));
                    updateVoucherUI202611();
                });
            }

            openBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (!navigator.onLine) {
                    showVoucherToast202611('メールアドレスの変更には通信が必要です。');
                    return;
                }
                if (isEmailChangeDeadlinePassed202611()) {
                    showVoucherToast202611('抽選の準備のため、メールアドレスは変更できません。');
                    return;
                }
                openForm();
            });

            if (cancel) {
                cancel.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeForm();
                });
            }

            // 入力中のアドレスが変わったら送信先確認をやり直す（送信済みアドレスと違う値のため）
            if (emailEl) {
                emailEl.addEventListener('input', function () {
                    resetConfirm();
                    _voucherChangeSentEmail202611 = '';
                    clearError();
                });
            }

            // ステップ1: 全角の自動補正 → 送信先の再確認 → 変更用コード送信
            if (sendBtn) {
                sendBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    var raw   = emailEl ? emailEl.value : '';
                    var email = normalizeEmailInput202611(raw);

                    if (!email) { showError('新しいメールアドレスを入力してください。'); return; }

                    if (hasFullWidth202611(raw) || hasSpace202611(raw)) {
                        showError('メールアドレスを半角に直しました: ' + email);
                        if (emailEl) emailEl.value = email;
                    }

                    if (!isValidEmail202611(email)) {
                        resetConfirm();
                        showError('メールアドレスの形が正しくありません（例: example@example.com）。');
                        return;
                    }

                    // 1 回目の押下: 送信先を表示して確認を求める（別アドレスに送らないための確認）
                    if (!_voucherChangeConfirmed202611) {
                        _voucherChangeConfirmed202611 = true;
                        if (confirmMail) confirmMail.textContent = email;
                        if (confirmBox) confirmBox.style.display = 'block';
                        sendBtn.textContent = 'このアドレスに送信';
                        clearError();
                        return;
                    }

                    sendBtn.disabled = true;
                    sendBtn.textContent = '送信中…';

                    requestVoucherEmailChangeCode202611(email).then(function (res) {
                        sendBtn.disabled = false;
                        sendBtn.textContent = '確認コードを送信';

                        if (!res.success) {
                            showError(voucherCodeErrorText202611(res));
                            updateVoucherUI202611();
                            return;
                        }

                        _voucherChangeSentEmail202611 = email;
                        resetConfirm();

                        if (!res.verificationRequired) {
                            applyChange('');   // 確認無効（開発環境）はそのまま変更する
                            return;
                        }

                        showStep('code');
                        clearError();
                        showVoucherToast202611('確認コードを ' + res.email + ' に送信しました（' + res.ttlMinutes + ' 分以内）。');
                        if (codeMail) codeMail.textContent = res.email;
                        if (codeTtl) codeTtl.textContent = String(res.ttlMinutes);
                        if (codeEl) { codeEl.value = ''; codeEl.focus(); }
                    });
                });
            }

            // ステップ2: 確認コード付きで変更確定
            if (submit) {
                submit.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    if (!_voucherChangeSentEmail202611) {
                        showError('まず「確認コードを送信」を押してください。');
                        return;
                    }

                    applyChange(codeEl ? codeEl.value.trim() : '');
                });
            }

            // コードを再送信（送信済みアドレス宛）
            if (resendBtn) {
                resendBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    if (!_voucherChangeSentEmail202611) {
                        showError('まず「確認コードを送信」を押してください。');
                        return;
                    }

                    resendBtn.disabled = true;
                    resendBtn.textContent = '送信中…';

                    requestVoucherEmailChangeCode202611(_voucherChangeSentEmail202611).then(function (res) {
                        resendBtn.disabled = false;
                        resendBtn.textContent = 'コードを再送信';

                        if (!res.success) {
                            showError(voucherCodeErrorText202611(res));
                            return;
                        }

                        clearError();
                        showVoucherToast202611('新しい確認コードを送信しました（' + res.ttlMinutes + ' 分以内）。');
                        if (codeMail) codeMail.textContent = res.email;
                        if (codeTtl) codeTtl.textContent = String(res.ttlMinutes);
                        if (codeEl) { codeEl.value = ''; codeEl.focus(); }
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
        window.requestVoucherEmailCode202611 = requestVoucherEmailCode202611;
        window.normalizeEmailInput202611     = normalizeEmailInput202611;
        window.voucherStatus202611           = voucherStatus202611;
        window.renderGacha202611             = renderGacha202611;
        window.initGachaConfirm202611        = initGachaConfirm202611;
        window.openGachaConfirm202611        = openGachaConfirm202611;
        window.confirmGachaDraw202611        = confirmGachaDraw202611;
        window.updateVoucherAppliedInfo202611   = updateVoucherAppliedInfo202611;
        window.initVoucherEmailChange202611     = initVoucherEmailChange202611;
        window.requestVoucherEmailChangeCode202611 = requestVoucherEmailChangeCode202611;
        window.changeVoucherEmail202611         = changeVoucherEmail202611;
        window.isEmailChangeDeadlinePassed202611 = isEmailChangeDeadlinePassed202611;
        window.updateVoucherUI202611         = updateVoucherUI202611;
        window.initVoucherUI202611           = initVoucherUI202611;
        window.refreshStampBookRewards202611 = refreshStampBookRewards202611;

