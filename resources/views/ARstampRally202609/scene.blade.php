@php
    // AR.js 射影パラメータの初期値（暫定値・16:9）。
    // 動画読み込み完了後、js-init.blade.php の syncArjsToRealSize() が
    // source=動画実寸 / display=実画面 に縦横問わず同期するため、
    // UAによる解像度出し分けは行わない（低解像度 ideal 制約による
    // Android HAL のデジタルクロップ回避）。
    $_srcW = 1280;
    $_srcH = 720;
    $_dispW = 1280;
    $_dispH = 720;
@endphp
<script>
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
</script>

<a-scene
    id="ar-scene"
    embedded
    arjs="sourceWidth: {{ $_srcW }}; sourceHeight: {{ $_srcH }}; displayWidth: {{ $_dispW }}; displayHeight: {{ $_dispH }}; trackingMethod: best; sourceType: webcam; debugUIEnabled: false; detectionMode: mono; maxDetectionRate: 30;"
    vr-mode-ui="enabled: false"
    renderer="logarithmicDepthBuffer: true; colorManagement: true; sortObjects: true; physicallyCorrectLights: false; antialias: true;"
    loading-screen="dotsColor: white; backgroundColor: black;">

    <a-assets timeout="10000"></a-assets>

    <!-- ライト -->
    <a-light type="ambient" color="#ffffff" intensity="1.0"></a-light>
    <a-light type="directional" color="#ffffff" intensity="0.6" position="1 2 0"></a-light>

    <!-- カメラ: look-controls 無効化（AndroidでのDeviceOrientationEvent誤介入を防止） -->
    <a-entity camera look-controls="enabled: false"></a-entity>

    <!-- maker00: ギャラリー専用マーカー（Model_00固定 + 選択4体をjs-gallery.blade.phpで動的生成） -->
    <a-marker
        type="pattern"
        url="{{ asset('cg/202609/pattern-maker00.patt') }}"
        id="marker-00"
        smooth="true"
        smoothCount="10"
        smoothTolerance="0.01"
        smoothThreshold="5">
        <!-- <a-cylinder color="#ffffff" height="2" radius="0.05" position="0.65 1 0"></a-cylinder>
        <a-sphere color="#ffea00" radius="0.1" position="0.65 2 0"></a-sphere> -->

        <a-entity
            id="model-00"
            lazy-model="src: {{ asset('cg/202609/Model_00.glb') }}"
            position="0 0 0"
            scale="0.6 0.6 0.6"
            rotation="0 0 0"
            visible="false">
        </a-entity>

    </a-marker>

    <!-- maker01 ～ maker20: 捕獲対象マーカー -->
    @for ($i = 1; $i <= 20; $i++)
    @php $id = str_pad($i, 2, '0', STR_PAD_LEFT); @endphp
    <a-marker
        type="pattern"
        url="{{ asset('cg/202609/pattern-maker' . $id . '.patt') }}"
        id="marker-{{ $id }}"
        smooth="true"
        smoothCount="10"
        smoothTolerance="0.01"
        smoothThreshold="5">
        <a-entity
            id="model-{{ $id }}"
            lazy-model="src: {{ asset('cg/202609/Model_' . $id . '.glb') }}"
            position="0 0 0.5"
            scale="1.1 1.1 1.1"
            rotation="-90 0 0"
            visible="false"
            click-animation="clip: anime01"
            hitbox="stampId: model_{{ $id }}; width: 1.6; height: 3.2; depth: 1.6">
        </a-entity>
    </a-marker>
    @endfor

</a-scene>
