
    // P2-10: AR_FORCE_LOWRES 時に antialias を無効化（モバイル GPU 負荷軽減）
    if (window.AR_FORCE_LOWRES) {
        document.addEventListener('beforeentitycomposition', function(e) {
            var el = e.target;
            if (el && el.id === 'ar-scene') {
                var r = el.getAttribute('renderer') || '';
                if (r.indexOf('antialias: true') !== -1) {
                    el.setAttribute('renderer', r.replace('antialias: true', 'antialias: false'));
                }
            }
        }, { once: true });
    }
