const CACHE_NAME = 'my-cache';
const urlsToCache = [
    '/find',
    '/find/cg/akaei_oldMan_idle.glb'
];

self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME)
        .then(function(cache) {
            console.log('Opened cache');

            return cache.addAll(urlsToCache);
        })
    );
});

self.addEventListener('fetch', function(event) {
    event.respondWith(
        caches.match(event.request)
        .then(function(response) {
            if (response) {
                return response;  // キャッシュされたリソースを返す
            }
            return fetch(event.request);  // ネットワークから取得
        })
    );
});
