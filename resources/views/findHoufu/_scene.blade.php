<a-scene
    renderer="antialias: true; colorManagement: true; sortObjects: true; physicallyCorrectLights: true"
    vr-mode-ui="enabled: true"
    auto-enter-vr>

    <a-assets timeout="15000">
        <a-asset-item id="model_bucchi" src="{{ asset('cg/202609/model00_bucchi.glb') }}"></a-asset-item>
        <audio id="bgm_s1" src="{{ asset('cg/sound_bgm11.mp3') }}" preload="auto" loop crossorigin="anonymous"></audio>
        <audio id="bgm_s2" src="{{ asset('cg/sound_bgm12.mp3') }}" preload="auto" loop crossorigin="anonymous"></audio>
        <audio id="bgm_s3" src="{{ asset('cg/sound_bgm13.mp3') }}" preload="auto" loop crossorigin="anonymous"></audio>
        <audio id="bgm_s4" src="{{ asset('cg/sound_bgm14.mp3') }}" preload="auto" loop crossorigin="anonymous"></audio>
        <audio id="sound_appear" src="{{ asset('cg/sound_animal_appear.mp3') }}" preload="auto" crossorigin="anonymous"></audio>
        <audio id="sound_hit" src="{{ asset('cg/sound_animal_die.mp3') }}" preload="auto" crossorigin="anonymous"></audio>
        <img id="sky01" src="{{ asset('cg/R0010095.JPG') }}" crossorigin="anonymous">
        <img id="sky02" src="{{ asset('cg/R0010109.JPG') }}" crossorigin="anonymous">
        <img id="sky03" src="{{ asset('cg/R0010111.JPG') }}" crossorigin="anonymous">
        <img id="sky04" src="{{ asset('cg/R0010114.JPG') }}" crossorigin="anonymous">
        <img id="sky05" src="{{ asset('cg/R0010131.JPG') }}" crossorigin="anonymous">
        <img id="sky06" src="{{ asset('cg/R0010143.JPG') }}" crossorigin="anonymous">
    </a-assets>

    <a-entity light="type: ambient; color: #DDD; intensity: 1.2"></a-entity>
    <a-entity light="type: directional; color: #FFF; intensity: 1.5" position="1 2 1"></a-entity>
    <a-entity light="type: directional; color: #FFF; intensity: 0.8" position="-1 1 -1"></a-entity>

    <a-entity id="mouseCursor" cursor="rayOrigin: mouse" raycaster="objects: .clickable, .collidable"></a-entity>
    <a-entity id="leftController"
              laser-controls="hand: left"
              raycaster="objects: .collidable, .clickable; far: 5; showLine: false"
              vr-controller></a-entity>
    <a-entity id="rightController"
              laser-controls="hand: right; model: false"
              raycaster="objects: .collidable, .clickable; far: 5; showLine: true"
              vr-controller>
        <a-entity id="controllerGunModel"
                  gltf-model="{{ asset('cg/gun_01.glb') }}"
                  position="0 -0.05 -0.1"
                  rotation="0 -90 0"
                  scale="0.2 0.2 0.2"></a-entity>
    </a-entity>

    <a-entity id="startMenu" position="0 1.6 -3" start-menu>
        <a-plane position="0 0 0" width="3.5" height="3.0" color="#000000" opacity="0.75" material="transparent: true" class="clickable"></a-plane>
        <a-text value="seieiVR FIND BUCCHI" position="0 1.1 0.01" align="center" color="#FFFFFF" width="3.5" font="roboto" shader="msdf"></a-text>
        <a-text value="Explore the tourist spots of Houfu City!" position="0 0.7 0.01" align="center" color="#00FFFF" width="3" font="roboto" shader="msdf"></a-text>
        <a-text value="Trigger / Click : Shoot" position="0 0.2 0.01" align="center" color="#AAAAAA" width="2.5" font="roboto" shader="msdf"></a-text>
        <a-text value="Hit Bucchi as fast as you can!" position="0 -0.1 0.01" align="center" color="#FFD700" width="3" font="roboto" shader="msdf"></a-text>
        <a-plane id="startButton" position="0 -0.9 0.01" width="2.4" height="0.62" color="#00CCFF" opacity="0.92" material="transparent: true" class="clickable"></a-plane>
        <a-text value="START" position="0 -0.9 0.02" align="center" color="#000000" width="3.5" font="roboto" shader="msdf" baseline="center"></a-text>
    </a-entity>

    <a-entity id="timerDisplay" position="0 2.0 -3" visible="false">
        <a-text id="timerText" value="TIME: 0s" position="-1.2 0 0" align="center" color="#FFFF00" width="3" font="roboto" shader="msdf"></a-text>
        <a-text id="scoreText" value="SCORE: 0" position="0 0 0" align="center" color="#00FF00" width="3" font="roboto" shader="msdf"></a-text>
        <a-text id="comboText" value="" position="1.2 0 0" align="center" color="#FF6600" width="3" font="roboto" shader="msdf"></a-text>
    </a-entity>

    <a-entity id="resultMenu" position="0 1.6 -3" visible="false">
        <a-plane position="0 0 0" width="6" height="5.2" color="#000000" opacity="0.88" material="transparent: true"></a-plane>
        <a-text id="resultTitle" value="GAME COMPLETE" position="0 2.0 0.01" align="center" color="#FFD700" width="4" font="roboto" shader="msdf"></a-text>
        <a-text id="resultScore" value="SCORE: 0" position="0 1.5 0.01" align="center" color="#FFFFFF" width="4" font="roboto" shader="msdf"></a-text>
        <a-text id="resultCombo" value="MAX COMBO: 0" position="0 1.1 0.01" align="center" color="#FF6600" width="3" font="roboto" shader="msdf"></a-text>
        <a-text id="resultHits" value="HITS: 0" position="0 0.7 0.01" align="center" color="#00FF00" width="3" font="roboto" shader="msdf"></a-text>
        <a-text id="resultMessage" value="Please visit the scenic spots of Houfu City! Bucchi might be hiding somewhere..." position="0 0.15 0.01" align="center" color="#00FFFF" width="5" font="roboto" shader="msdf"></a-text>
        <a-entity id="rankingDisplay" position="0 -1.0 0.01"></a-entity>
    </a-entity>

    <a-sky id="aSky" src="#sky01"></a-sky>

    <a-entity id="particle-celebration" visible="false" position="0 2 -3">
        <a-entity particle-system="preset: default; color: #FFD700,#FFA500,#FFFF00; particleCount: 15; size: 0.3; maxAge: 3; velocityValue: 0 5 0; velocitySpread: 5 2 5; accelerationValue: 0 -1 0; blending: 1"></a-entity>
        <a-entity particle-system="preset: default; color: #FFFFFF,#FFD700; particleCount: 10; size: 0.15; maxAge: 2.5; velocityValue: 0 3 0; velocitySpread: 4 3 4; accelerationValue: 0 -0.5 0; blending: 1" position="0 0.5 0"></a-entity>
    </a-entity>

    <a-camera id="my_camera" position="0 1.6 0" look-controls shoot>
        <a-entity id="fadeOverlay" visible="false">
            <a-plane width="200" height="200" position="0 0 -0.5" color="#000000" opacity="0" material="transparent: true; depthTest: false; side: double"></a-plane>
        </a-entity>
    </a-camera>

</a-scene>