{{-- ========== テストデータリセット（FR-65 / design.md §5.11）========== --}}
{{-- サーバー側 `php artisan stamp202611:reset` で marker_scans / --}}
{{-- stamp202611_voucher_applications を消した日と同じ日に AR202611_RESET_EPOCH を 1 増やす。--}}
{{-- 端末に保存された世代と不一致のときだけ発火する（冪等・毎回消えることはない）。--}}
{{-- head.blade.php の Service Worker 登録より前＝body の 202611 JS より前に読み込むこと。--}}
{{-- 202610 以前（ar-*-202610 など）には一切触れない。--}}
<script>
(function () {
    var EPOCH     = {{ (int) config('ar202611.reset_epoch', 1) }};
    var EPOCH_KEY = 'ar-reset-epoch-202611';
    var IDB_NAME  = 'ARStampRallyDB202611';
    var UID_COOKIE = 'ar_user_id_202611';

    var stored = null;
    try { stored = localStorage.getItem(EPOCH_KEY); } catch (e) { return; } // 私密モード等で読めなければ何もしない
    if (stored !== null && String(stored) === String(EPOCH)) return;        // 冪等: 同一世代では再実行しない

    // (1) 202611 系の localStorage キーを消す（逆順ループで削除しながら走査する）。
    //     'ar-' で始まり '202611' を含むキーだけ対象。将来追加されるキーも自動的に吸収される。
    var removed = [];
    try {
        for (var i = localStorage.length - 1; i >= 0; i--) {
            var k = localStorage.key(i);
            if (!k || k === EPOCH_KEY) continue;
            if (k.indexOf('ar-') !== 0 || k.indexOf('202611') === -1) continue;
            localStorage.removeItem(k);
            removed.push(k);
        }
    } catch (e) {
        console.warn('[AR202611] reset localStorage failed', e);
    }

    // (2) fingerprint の元になる userId を Cookie と IndexedDB からも消す（完全な新規端末にする）。
    try {
        document.cookie = UID_COOKIE + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
        document.cookie = UID_COOKIE + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT';
    } catch (e) { /* Cookie 無効環境では localStorage / IndexedDB 側で代償される */ }
    try { indexedDB.deleteDatabase(IDB_NAME); } catch (e) { console.warn('[AR202611] deleteDatabase failed', e); }

    // (3) Service Worker キャッシュ（旧 VERSION の html / core / assets）を削除する。
    //     SW 側の 'CLEAR_CACHES' ハンドラを再利用する（sw-stamp202611.js）。
    if ('serviceWorker' in navigator) {
        try {
            navigator.serviceWorker.getRegistrations().then(function (regs) {
                (regs || []).forEach(function (reg) {
                    var worker = reg.active || reg.installing || reg.waiting;
                    if (worker && worker.postMessage) worker.postMessage({ type: 'CLEAR_CACHES' });
                });
            }).catch(function () { /* 未登録・取得失敗時は無視 */ });
        } catch (e) { /* getRegistrations 非対応環境では無視 */ }
    }

    // (4) 新しい世代を記録する。この後 SW は VERSION 変更で自動的に再 install されるため
    //     location.reload() は不要（head で全 202611 JS より先に走るので同一読み込みでクリーン）。
    try { localStorage.setItem(EPOCH_KEY, String(EPOCH)); } catch (e) { /* 書込不可なら次回に持ち越し */ }

    console.log('[AR202611] client state reset -> epoch', EPOCH, '/ keys removed:', removed.length);
})();
</script>