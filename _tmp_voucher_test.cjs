// 一時的な検証スクリプト: js-prize.blade.php のガラポン / 抽選応募ロジックを Node で検証（完了後に削除）
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const lines = fs.readFileSync('_render202611_script4.js', 'utf8').split('\n');
const start = lines.findIndex((l) => l.includes('市内共通商品券の抽選応募（FR-41'));
assert.ok(start > 0, 'voucher block not found');
const src = lines.slice(start, lines.findIndex((l) => l.includes('window.refreshStampBookRewards202611 =')) + 1).join('\n');

// ---------- DOM / Storage スタブ ----------
function makeEl(id) {
    const el = {
        id: id,
        style: {},
        textContent: '',
        disabled: false,
        children: [],
        appendChild: function (c) { this.children.push(c); },
        addEventListener: function (type, fn) { (this._handlers || (this._handlers = {}))[type] = fn; },
        focus: function () {},
    };
    Object.defineProperty(el, 'innerHTML', {
        get: function () { return this._html || ''; },
        set: function (v) { this._html = v; if (v === '') this.children = []; },
    });
    return el;
}

const ids = ['pending-scans', 'voucher-status', 'voucher-apply-button', 'voucher-form',
    'gacha-buttons', 'gacha-note', 'voucher-submit', 'voucher-cancel', 'voucher-error',
    'voucher-email', 'voucher-name'];
const els = {};
ids.forEach(function (id) { els[id] = makeEl(id); });

const store = new Map();
const localStorage = {
    getItem: function (k) { return store.has(k) ? store.get(k) : null; },
    setItem: function (k, v) { store.set(k, String(v)); },
    removeItem: function (k) { store.delete(k); },
};

// この端末は 12 種収集済み（LocalStorage 上）
const stamps = {};
for (let i = 1; i <= 12; i++) {
    stamps['model_' + String(i).padStart(2, '0')] = { collectedAt: '2026-11-10T12:00:00Z', name: 'n' + i };
}
localStorage.setItem('ar-stamp-rally-202611', JSON.stringify(stamps));

const sandbox = {
    localStorage: localStorage,
    navigator: { onLine: true, userAgent: 'node-test', language: 'ja-JP', platform: 'test' },
    document: {
        getElementById: function (id) { return els[id] || null; },
        querySelector: function () { return { content: 'csrf-test', setAttribute: function () {} }; },
        createElement: function (tag) { return makeEl(tag); },
        body: { appendChild: function () {} },
        cookie: '',
    },
    console: console,
    fetch: function () { return Promise.resolve({ ok: true, status: 200, json: function () { return Promise.resolve({ success: true }); } }); },
    // js-prize / js-stamps 側の依存（このテストではスタブで代替）
    getCollectedStamps: function () { return JSON.parse(localStorage.getItem('ar-stamp-rally-202611') || '{}'); },
    generateFingerprint: function () { return Promise.resolve('fp-test'); },
    collectDeviceInfo: function () { return { isIOS: false }; },
    flushScanQueue: function () { return Promise.resolve(0); },
    refreshCsrfToken: function () { return Promise.resolve(true); },
    pendingScanCount: function () { return 0; },
};
sandbox.window = sandbox;
vm.createContext(sandbox);
vm.runInContext(src, sandbox, { filename: 'js-prize-voucher.js' });

function texts(id) {
    return els[id].children.map(function (c) { return c.textContent; }).join(' | ');
}

let pass = 0;
function ok(label, cond) {
    assert.ok(cond, label);
    pass++;
    console.log('OK   ' + label);
}

// ---------- 1. ガラポン（FR-37〜FR-40 / AC-9）----------
sandbox.renderGacha202611();
ok('ガラポン: 5 / 10 段階は有効、15 / 20 は無効（12種収集）',
    els['gacha-buttons'].children.length === 4
    && els['gacha-buttons'].children[0].disabled === false
    && els['gacha-buttons'].children[1].disabled === false
    && els['gacha-buttons'].children[2].disabled === true
    && els['gacha-buttons'].children[3].disabled === true);

const d5 = sandbox.drawGacha202611(5);
ok('ガラポン: 5種段階を1回引ける', d5.success === true && d5.draws.length === 1);
ok('ガラポン: 同一段階の再押下は不可', sandbox.drawGacha202611(5).error === 'already_drawn');
ok('ガラポン: 未到達段階（15種）は引けない', sandbox.drawGacha202611(15).error === 'not_reached');
ok('ガラポン: 10種段階を引ける', sandbox.drawGacha202611(10).success === true);
ok('ガラポン: 引抽済み段階が localStorage に保存',
    JSON.stringify(JSON.parse(localStorage.getItem('ar-gacha-draws-202611'))) === '[5,10]');

// 4 回上限（15 / 20 種に到達した状態）
for (let i = 13; i <= 20; i++) stamps['model_' + String(i).padStart(2, '0')] = { collectedAt: 'x', name: 'n' + i };
localStorage.setItem('ar-stamp-rally-202611', JSON.stringify(stamps));
ok('ガラポン: 15 / 20 到達後は有効化', sandbox.drawGacha202611(15).success === true && sandbox.drawGacha202611(20).success === true);
ok('ガラポン: 4 回目以降は all_drawn', sandbox.drawGacha202611(5).error === 'already_drawn');
sandbox.renderGacha202611();
ok('ガラポン: 引き済み表示（全ボタン無効・案内）',
    els['gacha-buttons'].children.every(function (b) { return b.disabled === true; })
    && els['gacha-note'].textContent.indexOf('すべて引きました') !== -1);

// ---------- 2. 未送信キューがある間は応募ボタン非表示（FR-52 / AC-15）----------
sandbox.pendingScanCount = function () { return 3; };
sandbox._voucherStatus = { stampCount: 20, applyDeadline: '2026-11-16T23:59:59+09:00', voucher: { applied: false, weight: 0 } };
sandbox.updateVoucherUI202611();
ok('未送信 3 件: 応募ボタン非表示', els['voucher-apply-button'].style.display === 'none');
ok('未送信 3 件: #pending-scans 表示', els['pending-scans'].style.display === 'block'
    && els['pending-scans'].textContent.indexOf('3 件') !== -1);

// ---------- 3. 応募ボタンの正本はサーバー応答（design §8.6）----------
sandbox.pendingScanCount = function () { return 0; };
sandbox._voucherStatus = null;
sandbox.updateVoucherUI202611();
ok('check-status 応答なし: 応募ボタン非表示', els['voucher-apply-button'].style.display === 'none');
ok('check-status 応答なし: 「読み込み中…」表示', texts('voucher-status').indexOf('読み込み中') !== -1);

// LocalStorage 上は 20 種でも、サーバーが 9 種なら無効表示（422 not_eligible の不整合回避）
sandbox._voucherStatus = { stampCount: 9, applyDeadline: '2026-11-16T23:59:59+09:00', voucher: { applied: false, weight: 0 } };
sandbox.updateVoucherUI202611();
ok('サーバー 9 種: 応募ボタンは無効', els['voucher-apply-button'].style.display === 'block'
    && els['voucher-apply-button'].disabled === true
    && els['voucher-apply-button'].textContent.indexOf('10種以上必要（現在9種）') !== -1);

sandbox._voucherStatus = { stampCount: 12, applyDeadline: '2026-11-16T23:59:59+09:00', voucher: { applied: false, weight: 0 } };
sandbox.updateVoucherUI202611();
ok('サーバー 12 種: 応募ボタン有効', els['voucher-apply-button'].style.display === 'block'
    && els['voucher-apply-button'].disabled === false
    && els['voucher-apply-button'].textContent === '商品券抽選に応募する');

// ---------- 4. 応募済み表示と倍率の文言（FR-42 / FR-43 / AC-13）----------
sandbox._voucherStatus = { stampCount: 12, applyDeadline: '2026-11-16T23:59:59+09:00', voucher: { applied: true, weight: 1 } };
sandbox.updateVoucherUI202611();
ok('応募済み weight1: 倍率なしの文言・ボタン非表示',
    texts('voucher-status').indexOf('市内共通商品券の抽選会に応募しました。') !== -1
    && els['voucher-apply-button'].style.display === 'none');

sandbox._voucherStatus = { stampCount: 16, applyDeadline: '2026-11-16T23:59:59+09:00', voucher: { applied: true, weight: 2 } };
sandbox.updateVoucherUI202611();
ok('応募済み weight2: 「15個以上集めたので当選確率2倍」',
    texts('voucher-status').indexOf('15個以上集めたので当選確率2倍です。') !== -1);

sandbox._voucherStatus = { stampCount: 20, applyDeadline: '2026-11-16T23:59:59+09:00', voucher: { applied: true, weight: 3 } };
sandbox.updateVoucherUI202611();
ok('応募済み weight3: 「20個すべて集めたので当選確率3倍」',
    texts('voucher-status').indexOf('20個すべて集めたので当選確率3倍です。') !== -1);

// ---------- 5. 応募期限経過後（FR-50）----------
sandbox._voucherStatus = { stampCount: 20, applyDeadline: '2026-10-01T00:00:00+09:00', voucher: { applied: false, weight: 0 } };
sandbox.updateVoucherUI202611();
ok('応募期限経過後: 応募ボタン非表示＋期限超過の案内',
    els['voucher-apply-button'].style.display === 'none'
    && texts('voucher-status').indexOf('応募期限') !== -1
    && texts('voucher-status').indexOf('応募できません') !== -1);

// ---------- 6. 応募送信（422 / 200 の扱い）----------
sandbox.fetch = function () {
    return Promise.resolve({
        ok: false, status: 422,
        json: function () { return Promise.resolve({ success: false, error: 'already_applied', message: 'すでに応募済みです' }); },
    });
};
sandbox.applyVoucher202611('dup@example.com', 'テスト 太郎').then(function (res) {
    ok('applyVoucher202611: 422 already_applied を error として返す',
        res.success === false && res.error === 'already_applied');

    sandbox.fetch = function () {
        return Promise.resolve({
            ok: true, status: 200,
            json: function () { return Promise.resolve({ success: true, applied: true, weight: 2, stampCount: 16 }); },
        });
    };
    return sandbox.applyVoucher202611('ok@example.com', 'テスト 太郎');
}).then(function (res) {
    ok('applyVoucher202611: 200 → success / weight 返却', res.success === true && res.weight === 2);
    ok('応募成功: ar-voucher-applied-202611 はフラグのみ保存（weight は保存しない・design §9）',
        localStorage.getItem('ar-voucher-applied-202611') === 'true');

    // check-status の応答で応募済み表示を復旧する（キャッシュ削除後も正本はサーバー・design §9）
    localStorage.removeItem('ar-voucher-applied-202611');
    sandbox.fetch = function () {
        return Promise.resolve({ ok: true, status: 200, json: function () {
            return Promise.resolve({
                success: true, stampCount: 20, voucherThreshold: 10,
                applyDeadline: '2026-11-16T23:59:59+09:00',
                voucher: { applied: true, weight: 3, stampCount: 20, appliedAt: '2026-11-16T10:00:00+09:00', updated: false },
            });
        } });
    };
    return sandbox.checkStatus202611();
}).then(function (st) {
    ok('checkStatus202611: 応答で応募済み表示を復旧（weight 3 の文言）',
        st && st.voucher.weight === 3 && texts('voucher-status').indexOf('20個すべて集めたので当選確率3倍です。') !== -1);
    ok('checkStatus202611: 応募済みフラグのみキャッシュ（weight / stampCount は保存しない）',
        localStorage.getItem('ar-voucher-applied-202611') === 'true');
    console.log('ALL PASS: ' + pass + ' checks');
}).catch(function (err) {
    console.error('FAIL', err);
    process.exit(1);
});

