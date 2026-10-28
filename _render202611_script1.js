
    // ========== Service Worker 登録（202611 専用・FR-19）==========
    // 他キャンペーン（202605 / 202609 など）とは共有しない。
    // デバッグ: URL に ?sw=0 を付けると登録をスキップする（FR-29）。
    (function () {
        if (!('serviceWorker' in navigator)) return;
        try {
            if (new URL(location.href).searchParams.get('sw') === '0') {
                console.log('[AR202611] Service Worker skipped (?sw=0)');
                return;
            }
        } catch (e) { /* URL パース失敗時は登録続行 */ }

        window.addEventListener('load', function () {
            var swUrl;
            try {
                swUrl = new URL('http://localhost/sw-stamp202611.js', location.href);
            } catch (e) { return; }
            // scope は SW と同じディレクトリ（'/' または '/find/'）
            var scope = swUrl.pathname.replace(/[^/]*$/, '') || '/';
            navigator.serviceWorker.register(swUrl.href, { scope: scope })
                .then(function (reg) {
                    console.log('[AR202611] SW registered:', reg.scope);
                })
                .catch(function (err) {
                    console.warn('[AR202611] SW registration failed:', err);
                });
        });
    })();
    