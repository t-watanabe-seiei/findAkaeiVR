const CACHE_NAME = 'my-cache';
const urlsToCache = [
    '/find',
    '/find/cg/R0010049_st.MP4'
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

// self.addEventListener('fetch', function(event) {
//     event.respondWith(
//         caches.match(event.request)
//         .then(function(response) {
//             if (response) {
//                 return response;  // キャッシュされたリソースを返す
//             }
//             return fetch(event.request);  // ネットワークから取得
//         })
//     );
// });


self.addEventListener('fetch', function(event) {
    event.respondWith(
        caches.match(event.request).then(function(response) {
            return response || fetch(event.request).then(function(response) {
                return caches.open(CACHE_NAME).then(function(cache) {
                    cache.put(event.request, response.clone());
                    return response;
                });
            });
        })
    );
});
