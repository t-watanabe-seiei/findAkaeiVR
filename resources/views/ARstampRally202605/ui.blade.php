    <!-- ローディング -->
    <div class="arjs-loader" id="arjs-loader">
        <div>
            <p>AR を起動中...</p>
            <p style="font-size:0.8em; opacity:0.7;">Loading AR...</p>
        </div>
    </div>

    <!-- 左上ボタン列: スタンプ帳・ガイド・動画・写真 -->
    <div id="top-left-buttons">
        <button id="stamp-book-button" type="button" title="コレクションを見る" aria-label="コレクション">
            🎯
            <span class="badge" id="stamp-badge" style="display:none;">0</span>
        </button>
        <button id="guide-button" type="button" title="遊び方を見る" aria-label="遊び方">❓</button>
        <button id="video-button" type="button" title="動画を撮る" aria-label="動画撮影">📹</button>
        <button id="camera-button" type="button" title="写真を撮る" aria-label="写真撮影">📷</button>
    </div>

    <!-- スタンプ帳モーダル -->
    <div id="stamp-book-modal" role="dialog" aria-labelledby="stamp-book-title" aria-modal="true">
        <div id="stamp-book-content">
            <h2 id="stamp-book-title">🎯 コレクション 🎯</h2>
            <div class="progress">
                <span id="collected-count">0</span> / <span id="total-slots">10</span> 種類
            </div>
            <div id="complete-message-container"></div>
            <div class="stamps-grid" id="stamps-grid">
                <!-- スタンプアイテムはJavaScriptで動的生成 -->
            </div>
            <div class="button-row">
                <button id="close-stamp-book" type="button">閉じる</button>
                <button id="exchange-prize-button" type="button">景品と交換する</button>
                <button id="clear-stamps" type="button">動物たちを逃がす</button>
            </div>
        </div>
    </div>

    <!-- 操作説明モーダル -->
    <div id="guide-modal" aria-hidden="true" role="dialog" aria-labelledby="guide-title" aria-modal="true">
        <div id="guide-content">
            <button id="close-guide-top" class="close-guide-x" type="button" aria-label="Close">×</button>
            <div id="stamp-rally-note" class="guide-note" aria-hidden="false" style="margin-bottom:10px;"></div>
            <div class="guide-header">
                <h2 id="guide-title">How to play</h2>
                <div class="lang-switch" id="guide-lang-switch" role="tablist" aria-label="言語切替">
                    <button id="lang-jp" class="lang-btn" aria-pressed="false">日本語</button>
                    <button id="lang-en" class="lang-btn active" aria-pressed="true">English</button>
                </div>
            </div>
            <div class="guide-steps">
                <div class="step main">
                    <img class="howto-main" src="{{ asset('img/howToOperate.png') }}" alt="操作ガイド" />
                </div>
                <div class="step" id="guide-step-find"></div>
                <div class="step" id="guide-step-zoom"></div>
                <div class="step" id="guide-step-photo"></div>
                <div class="step" id="guide-step-throw"></div>
                <div class="step" id="guide-step-prize"></div>
                <div class="step" id="guide-step-others"></div>
                <div class="step" id="guide-step-hints"></div>
            </div>
            <div class="guide-close-row">
                <button id="close-guide" type="button">close</button>
            </div>
        </div>
    </div>

    <!-- 捕獲済みメッセージ -->
    <div id="captured-message" class="captured-message" aria-live="polite">
        <h2>🎉 捕まえました！ 🎉</h2>
        <div class="animal-name" id="captured-animal-name"></div>
        <p style="margin-top:15px;font-size:14px;color:#ccc;">
            コレクションの「動物たちを逃がす」ボタンで<br>全てリセットできます
        </p>
    </div>

    <!-- 確認ダイアログ（スタンプ削除など） -->
    <div id="confirm-overlay" aria-hidden="true"></div>
    <div id="confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="confirm-message">
        <div class="confirm-message" id="confirm-message"></div>
        <div class="confirm-buttons">
            <button class="confirm-yes" id="confirm-yes" type="button">はい</button>
            <button class="confirm-no" id="confirm-no" type="button">いいえ</button>
        </div>
    </div>

    <!-- カメラ権限エラーUI -->
    <div id="camera-error" style="display:none; position:fixed; left:0; right:0; top:0; bottom:0; background:rgba(0,0,0,0.75); color:#fff; z-index:9999; flex-direction:column; align-items:center; justify-content:center;">
        <div style="max-width:420px; text-align:center; padding:20px;">
            <h2 style="margin-top:0;">カメラが起動できません</h2>
            <p>カメラの許可が拒否されているか、端末がカメラを初期化できませんでした。<br>カメラの許可を確認し、もう一度お試しください。</p>
            <div style="margin-top:12px;">
                <button id="retry-camera" style="padding:10px 16px;font-size:16px;border-radius:6px;background:#0078D4;color:#fff;border:none;margin-right:8px;">再試行</button>
                <button id="retry-camera-lowres" style="padding:10px 16px;font-size:16px;border-radius:6px;background:#ff8c00;color:#fff;border:none;display:none;">低解像度で再試行</button>
            </div>
        </div>
    </div>

    <!-- カメラヘルプモーダル -->
    <div id="camera-help-modal" style="display:none;" aria-hidden="true">
        <div class="camera-help-content">
            <h3 id="camera-help-title">カメラの許可が必要です</h3>
            <div id="camera-help-body"></div>
            <div class="camera-help-actions">
                <button id="camera-help-ok" type="button" style="padding:8px 16px;background:#4CAF50;color:#fff;border:none;border-radius:6px;cursor:pointer;">OK</button>
                <button id="camera-help-close" type="button" style="padding:8px 16px;background:#999;color:#fff;border:none;border-radius:6px;cursor:pointer;">閉じる</button>
            </div>
        </div>
    </div>

    <!-- フラッシュ（写真撮影エフェクト） -->
    <div id="flash" aria-hidden="true"></div>

    <!-- 写真プレビュー -->
    <div id="photo-preview" style="display:none;" role="dialog" aria-modal="true" aria-label="写真プレビュー">
        <img id="preview-image" src="" alt="撮影した写真">
        <div class="buttons">
            <button id="download-button" type="button">ダウンロード</button>
            <button id="close-button" type="button">閉じる</button>
        </div>
    </div>
