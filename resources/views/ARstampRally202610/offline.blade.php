{{-- resources/views/ARstampRally202610/offline.blade.php --}}
{{-- オフライン専用ページ（FR-23）: 外部アセット（CSS/JS/画像）に依存しない自己完結 --}}
{{-- Service Worker が navigation 失敗時の最終フォールバックとして返す --}}
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>オフライン | 隠しキャラを探せ！ARスタンプラリー2026</title>
    <style>
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { margin: 0; padding: 0; min-height: 100%; }
        body {
            font-family: "Segoe UI", "Hiragino Sans", "Noto Sans JP", sans-serif;
            background: linear-gradient(160deg, #eaf4ff 0%, #f7f9fb 60%);
            color: #222; padding: 20px 16px 40px;
        }
        .off-card {
            max-width: 520px; margin: 0 auto; background: #fff; border-radius: 14px;
            padding: 20px 18px; box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        .off-badge {
            display: inline-block; background: #f44336; color: #fff; font-size: 12px;
            font-weight: bold; padding: 4px 10px; border-radius: 999px; margin-bottom: 10px;
        }
        .off-card h1 { font-size: 20px; margin: 0 0 10px; color: #1a5fb4; }
        .off-card p { font-size: 14px; line-height: 1.6; color: #444; margin: 0 0 12px; }
        .off-stats { display: flex; gap: 10px; margin: 14px 0 18px; }
        .off-stat {
            flex: 1; background: #f5f7fa; border-radius: 10px; padding: 12px 10px; text-align: center;
        }
        .off-stat .num { font-size: 24px; font-weight: bold; color: #2e7d32; }
        .off-stat .lbl { font-size: 11px; color: #666; margin-top: 4px; }
        .off-actions { display: flex; flex-direction: column; gap: 10px; }
        .off-actions a, .off-actions button {
            display: block; width: 100%; padding: 13px; border: none; border-radius: 10px;
            font-size: 15px; font-weight: bold; text-align: center; text-decoration: none;
            cursor: pointer; color: #fff; background: #4CAF50;
        }
        .off-actions .sub { background: #1a5fb4; }
        .off-actions .ghost { background: #999; font-size: 13px; }
        .off-note { margin-top: 16px; font-size: 12px; color: #777; }
    </style>
</head>
<body>
    <div class="off-card">
        <span class="off-badge">オフライン</span>
        <h1>隠しキャラを探せ！ARスタンプラリー2026</h1>
        <p>いま通信できません。キャッシュ済みのデータで <strong>AR スタンプラリーは続けられます</strong>。<br>
           集めたスタンプはこの端末に保存され、通信が戻ったときに自動で送信されます。</p>

        <div class="off-stats">
            <div class="off-stat">
                <div class="num" id="off-stamp-count">-</div>
                <div class="lbl">集めた種類</div>
            </div>
            <div class="off-stat">
                <div class="num" id="off-queue-count">-</div>
                <div class="lbl">未送信のスキャン</div>
            </div>
        </div>

        <div class="off-actions">
            <a href="{{ url('/stamp202610') }}">AR を続ける（キャッシュ）</a>
            <a href="{{ url('/stamp202610') }}" class="sub">読み込みをやり直す</a>
            <button type="button" id="off-clear">端末内のキャッシュを削除</button>
        </div>

        <p class="off-note" id="off-note">通信状態: 確認中…</p>
    </div>

    <script>
        (function () {
            var STAMP_KEY = 'ar-stamp-rally-202610-r2';
            var QUEUE_KEY = 'ar-scan-queue-202610-r2';

            function stampCount() {
                try {
                    var raw = localStorage.getItem(STAMP_KEY);
                    if (!raw) return 0;
                    var obj = JSON.parse(raw);
                    return Object.keys(obj || {}).length;
                } catch (e) { return 0; }
            }
            function queueCount() {
                try {
                    var raw = localStorage.getItem(QUEUE_KEY);
                    if (!raw) return 0;
                    var arr = JSON.parse(raw);
                    return Array.isArray(arr) ? arr.length : 0;
                } catch (e) { return 0; }
            }

            document.getElementById('off-stamp-count').textContent = stampCount();
            document.getElementById('off-queue-count').textContent = queueCount();

            var note = document.getElementById('off-note');
            function updateNote() {
                note.textContent = navigator.onLine
                    ? '通信状態: オンライン（このページを開き直すと通常表示に戻ります）'
                    : '通信状態: オフライン';
            }
            updateNote();
            window.addEventListener('online', updateNote);
            window.addEventListener('offline', updateNote);

            document.getElementById('off-clear').addEventListener('click', function () {
                if (!confirm('この端末に保存したキャッシュを削除しますか？\n（集めたスタンプの記録は残ります）')) return;
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.getRegistrations().then(function (regs) {
                        (regs || []).forEach(function (reg) {
                            if (reg.active && reg.active.postMessage) {
                                reg.active.postMessage({ type: 'CLEAR_CACHES' });
                            }
                            reg.unregister();
                        });
                        location.href = '{{ url("/stamp202610") }}';
                    });
                } else {
                    location.href = '{{ url("/stamp202610") }}';
                }
            });
        })();
    </script>
</body>
</html>