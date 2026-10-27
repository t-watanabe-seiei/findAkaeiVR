/**
 * ARstampRally202610 専用 Service Worker（オフライン対応）
 * ------------------------------------------------------------------
 * 対象: /stamp202610 ページと 202610 が使う静的アセットのみ。
 * 分離原則: キャッシュキーは "ar202610-" 接頭辞。他キャンペーン
 *           （202605 / 202609 など）や public/service-worker.js とは干渉しない。
 *
 * 戦略:
 *   - install  : 必須セット（ページ・オフラインページ・共有JS/CSS・マーカー21枚・
 *                ロード中モデル・ボール・効果音）を事前キャッシュ
 *   - navigation (GET) : network-first → キャッシュ → オフラインページ
 *   - 静的アセット     : cache-first（js/css/img/build は背景で更新、cg/202610 は更新しない）
 *   - API（POST 等）   : 介入しない（常にネットワーク・キャッシュしない）
 *
 * デバッグ: ページ URL に ?sw=0 を付けると head.blade.php 側で登録をスキップする。
 */
'use strict';

var VERSION = 'v1';
var CACHE_PREFIX = 'ar202610-';
var CACHE_HTML   = CACHE_PREFIX + 'html-'   + VERSION;
var CACHE_CORE   = CACHE_PREFIX + 'core-'   + VERSION;
var CACHE_ASSETS = CACHE_PREFIX + 'assets-' + VERSION;

// アプリのベースパス（'/' または '/find/'）。SW 自身の URL から導出する。
var BASE = self.location.pathname.replace(/[^/]*$/, '') || '/';

var PAGE_PATH    = BASE + 'stamp202610';
var OFFLINE_PATH = BASE + 'stamp202610/offline';

/** install 時に必ず取得する必須セット（FR-20） */
var CORE_URLS = [
    PAGE_PATH,
    OFFLINE_PATH,
    BASE + 'css/stampRally.css',
    BASE + 'js/ar-engine.min.js',
    BASE + 'js/ar-tracking.min.js',
    BASE + 'js/geo-location.min.js',
    BASE + 'js/loaders/GLTFLoader.js',
    BASE + 'js/loaders/OBJLoader.js',
    BASE + 'js/objects/ball.obj',
    BASE + 'js/objects/ball.mtl',
    BASE + 'js/sounds/loading.ogg',
    BASE + 'img/howToOperate.png',
    BASE + 'cg/202610/Model_00.glb'
];

// マーカー 21 枚（pattern-maker00..20.patt）
for (var i = 0; i <= 20; i++) {
    var nn = (i < 10 ? '0' : '') + i;
    CORE_URLS.push(BASE + 'cg/202610/pattern-maker' + nn + '.patt');
}

/** 軽量静的アセット（cache-first + 背景更新） */
function isLightAsset(pathname) {
    return pathname.indexOf(BASE + 'js/')   === 0
        || pathname.indexOf(BASE + 'css/')  === 0
        || pathname.indexOf(BASE + 'img/')  === 0
        || pathname.indexOf(BASE + 'build/') === 0;
}

/** 重量アセット（cache-first のみ・背景更新しない: GLB / .patt / .obj） */
function isHeavyAsset(pathname) {
    return pathname.indexOf(BASE + 'cg/202610/') === 0;
}

self.addEventListener('install', function (event) {
    event.waitUntil((async function () {
        var core = await caches.open(CACHE_CORE);
        var html = await caches.open(CACHE_HTML);
        await Promise.all(CORE_URLS.map(function (url) {
            // 個別失敗（404 等）で install 全体を失敗させない
            return fetch(new Request(url, { cache: 'reload' })).then(function (res) {
                if (!res || !res.ok) return;
                return core.put(url, res.clone()).then(function () {
                    // ページ類は navigation フォールバック用キャッシュにも保存する
                    if (url === PAGE_PATH || url === OFFLINE_PATH) return html.put(url, res);
                });
            }).catch(function (err) {
                console.warn('[AR202610-SW] precache failed:', url, err);
            });
        }));
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', function (event) {
    event.waitUntil((async function () {
        var keys = await caches.keys();
        await Promise.all(keys.filter(function (k) {
            // 旧バージョン（ar202610-*-v1 以前）のみ削除
            return k.indexOf(CACHE_PREFIX) === 0 && k !== CACHE_HTML && k !== CACHE_CORE && k !== CACHE_ASSETS;
        }).map(function (k) { return caches.delete(k); }));
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', function (event) {
    var req = event.request;

    // POST 等の API（record-scan / check-prize / exchange-prize）は常にネットワーク
    if (req.method !== 'GET') return;

    var url;
    try { url = new URL(req.url); } catch (e) { return; }
    if (url.origin !== self.location.origin) return;

    // ページ遷移: network-first → キャッシュ → オフラインページ
    if (req.mode === 'navigate') {
        event.respondWith(handleNavigation(req));
        return;
    }

    // CSRF トークン取得は常にネットワーク（no-store）
    if (url.pathname === BASE + 'stamp202610/csrf') return;

    if (isLightAsset(url.pathname)) {
        event.respondWith(handleAsset(req, true));
        return;
    }
    if (isHeavyAsset(url.pathname)) {
        event.respondWith(handleAsset(req, false));
        return;
    }
});

/** ページ（navigation）: network-first → キャッシュ → オフラインページ */
async function handleNavigation(req) {
    try {
        var res = await fetch(req);
        if (res && res.ok) {
            var cache = await caches.open(CACHE_HTML);
            cache.put(req, res.clone()).catch(function () {});
        }
        return res;
    } catch (err) {
        var htmlCache = await caches.open(CACHE_HTML);
        var cached = await htmlCache.match(req, { ignoreSearch: true });
        if (cached) return cached;
        var offline = await htmlCache.match(OFFLINE_PATH);
        if (!offline) {
            // install 時の precache（CACHE_CORE）も念のため参照
            var coreCache = await caches.open(CACHE_CORE);
            offline = await coreCache.match(OFFLINE_PATH);
        }
        if (offline) return offline;
        return new Response('offline', {
            status: 504,
            statusText: 'Offline',
            headers: { 'Content-Type': 'text/plain; charset=utf-8' }
        });
    }
}

/**
 * 静的アセット: cache-first
 * @param {boolean} revalidate  true なら背景で再取得して更新（軽量アセットのみ）
 */
async function handleAsset(req, revalidate) {
    var cache = await caches.open(CACHE_ASSETS);
    var cached = await cache.match(req, { ignoreSearch: true });

    if (cached) {
        if (revalidate) {
            fetch(new Request(req, { cache: 'reload' })).then(function (fresh) {
                if (fresh && fresh.ok) cache.put(req, fresh.clone()).catch(function () {});
            }).catch(function () {});
        }
        return cached;
    }

    try {
        var fresh = await fetch(req);
        if (fresh && fresh.ok) cache.put(req, fresh.clone()).catch(function () {});
        return fresh;
    } catch (err) {
        // オフラインで未キャッシュのアセットは取得不可（AR 本体は継続可能）
        return new Response('', { status: 504, statusText: 'Offline' });
    }
}

/** オフラインページ「キャッシュ削除」ボタンからの要求（FR-29） */
self.addEventListener('message', function (event) {
    var data = event.data || {};
    if (data.type !== 'CLEAR_CACHES') return;
    event.waitUntil((async function () {
        var keys = await caches.keys();
        await Promise.all(keys.filter(function (k) {
            return k.indexOf(CACHE_PREFIX) === 0;
        }).map(function (k) { return caches.delete(k); }));
    })());
});