const CACHE_NAME = 'my-cache';  // キャッシュの名前を設定
const urlsToCache = [
    '/find',  // キャッシュするルートURL
    '/find/cg/R0010049_st.MP4',  // キャッシュする動画ファイルのURL
    '/find/cg/R0010011_st.MP4'  // 新しく追加する動画ファイルのURL
    
];

// Service Workerのインストールイベント
self.addEventListener('install', function(event) {
    // インストール時にキャッシュを開く
    event.waitUntil(
        caches.open(CACHE_NAME)
        .then(function(cache) {
            console.log('Opened cache');  // キャッシュが開かれたことをログに記録

            // 事前にキャッシュするURLリストをキャッシュに追加
            return cache.addAll(urlsToCache);
        })
    );
});

// フェッチイベント（ネットワークリクエスト）の処理
self.addEventListener('fetch', function(event) {
    // 'chrome-extension'スキームのリクエストを無視
    if (event.request.url.startsWith('chrome-extension://')) {
        console.error('Service Worker does not support chrome-extension scheme:', event.request.url);
        return;
    }

    event.respondWith(
        // キャッシュ内にリクエストに対応するレスポンスがあるか確認
        caches.match(event.request).then(function(response) {
            // キャッシュにあればそれを返す
            if (response) {
                return response;
            }
            // キャッシュにない場合、ネットワークからリクエストを行う
            return fetch(event.request).then(function(response) {
                // ネットワークからのレスポンスをキャッシュに保存
                return caches.open(CACHE_NAME).then(function(cache) {
                    cache.put(event.request, response.clone());
                    return response;
                });
            });
        })
    );
});
