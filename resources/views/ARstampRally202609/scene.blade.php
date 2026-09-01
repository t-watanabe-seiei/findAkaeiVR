@php
    $_arjsIsAndroid = stripos(request()->header('User-Agent', ''), 'android') !== false;
    $_srcW = $_arjsIsAndroid ? 640 : 1280;
    $_srcH = $_arjsIsAndroid ? 480 : 720;
    $_dispW = $_arjsIsAndroid ? 640 : 1280;
    $_dispH = $_arjsIsAndroid ? 480 : 720;
@endphp
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
        url="{{ asset('cg/202606/pattern-maker00.patt') }}"
        id="marker-00"
        smooth="true"
        smoothCount="10"
        smoothTolerance="0.01"
        smoothThreshold="5">
        <!-- <a-cylinder color="#ffffff" height="2" radius="0.05" position="0.65 1 0"></a-cylinder>
        <a-sphere color="#ffea00" radius="0.1" position="0.65 2 0"></a-sphere> -->

        <a-entity
            id="model-00"
            lazy-model="src: {{ asset('cg/202606/Model_00.glb') }}"
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
        url="{{ asset('cg/202606/pattern-maker' . $id . '.patt') }}"
        id="marker-{{ $id }}"
        smooth="true"
        smoothCount="10"
        smoothTolerance="0.01"
        smoothThreshold="5">
        <a-entity
            id="model-{{ $id }}"
            lazy-model="src: {{ asset('cg/202606/Model_' . $id . '.glb') }}"
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
<script>
// Samsung Galaxy 縦向き対策:
// A-Frame の DOMContentLoaded 初期化より先に同期実行し、
// arjs の sourceWidth/sourceHeight を縦型寸法（480x640）に上書きする。
// 非 Samsung 端末・横向き使用時にはスキップするため既存端末への影響なし。
(function () {
    var ua = navigator.userAgent;
    if (!/android/i.test(ua)) return;
    if (!/samsung|SM-[A-Z]/i.test(ua)) return;
    if (window.innerWidth >= window.innerHeight) return; // 横向きはスキップ
    var scene = document.getElementById('ar-scene');
    if (!scene) return;
    scene.setAttribute('arjs',
        'sourceWidth: 480; sourceHeight: 640; displayWidth: 480; displayHeight: 640;' +
        ' trackingMethod: best; sourceType: webcam; debugUIEnabled: false;' +
        ' detectionMode: mono; maxDetectionRate: 30;'
    );
    console.log('[AR202609] Samsung portrait: arjs set to 480x640.');
}());
</script>
