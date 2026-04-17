@php
    $_arjsIsAndroid = stripos(request()->header('User-Agent', ''), 'android') !== false;
    $_srcW = $_arjsIsAndroid ? 640 : 1280;
    $_srcH = $_arjsIsAndroid ? 480 : 720;
@endphp
<a-scene
    id="ar-scene"
    embedded
    arjs="sourceWidth: {{ $_srcW }}; sourceHeight: {{ $_srcH }}; displayWidth: 1280; displayHeight: 720; trackingMethod: best; sourceType: webcam; debugUIEnabled: false; detectionMode: mono; maxDetectionRate: 30;"
    vr-mode-ui="enabled: false"
    renderer="logarithmicDepthBuffer: true; colorManagement: true; sortObjects: true; physicallyCorrectLights: true; antialias: true;"
    loading-screen="dotsColor: white; backgroundColor: black;">

    <a-assets timeout="10000"></a-assets>

    <!-- ライト -->
    <a-light type="ambient" color="#ffffff" intensity="0.8"></a-light>
    <a-light type="directional" color="#ffffff" intensity="0.6" position="1 2 0"></a-light>

    <!-- カメラ -->
    <a-entity camera></a-entity>

    <!-- maker00: ギャラリー専用マーカー（モデルなし・js-gallery.blade.php で動的生成） -->
    <a-marker
        type="pattern"
        url="{{ asset('cg/202605/pattern-maker00.patt') }}"
        id="marker-00"
        smooth="true"
        smoothCount="10"
        smoothTolerance="0.01"
        smoothThreshold="5">
        <a-cylinder color="#ffffff" height="3" radius="0.05" position="0.68 1 0"></a-cylinder>
         <a-sphere color="#ffea00" radius="0.1" position="0.68 2.5 0"></a-sphere>

        <a-cylinder color="#ffffff" height="3" radius="0.05" position="0.68 1 1"></a-cylinder>
         <a-sphere color="#ffea00" radius="0.1" position="0.68 2.5 1"></a-sphere>

    </a-marker>

    <!-- maker01 ～ maker10: 捕獲対象マーカー -->
    @for ($i = 1; $i <= 10; $i++)
    @php $id = str_pad($i, 2, '0', STR_PAD_LEFT); @endphp
    <a-marker
        type="pattern"
        url="{{ asset('cg/202605/pattern-maker' . $id . '.patt') }}"
        id="marker-{{ $id }}"
        smooth="true"
        smoothCount="10"
        smoothTolerance="0.01"
        smoothThreshold="5">
        <a-entity
            id="model-{{ $id }}"
            lazy-model="src: {{ asset('cg/202605/Model_' . $id . '.glb') }}"
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
