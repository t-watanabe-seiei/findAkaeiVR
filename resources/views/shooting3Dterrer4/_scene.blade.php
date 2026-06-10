<a-scene
    renderer="antialias: true; colorManagement: true; sortObjects: true; physicallyCorrectLights: true; exposure: 1; toneMapping: ACESFilmic"
    vr-mode-ui="enabled: true"
    auto-enter-vr>

    <!-- ─ アセット ─ -->
    <a-assets>
        <!-- ステージ1 ゾンビモデル -->
        <a-asset-item id="model_s1_01" src="{{ asset('cg/20260613/01_optimized.glb') }}"></a-asset-item>
        <a-asset-item id="model_s1_02" src="{{ asset('cg/20260613/02_optimized.glb') }}"></a-asset-item>
        <a-asset-item id="model_s1_03" src="{{ asset('cg/20260613/03_optimized.glb') }}"></a-asset-item>
        <a-asset-item id="model_s1_04" src="{{ asset('cg/20260613/04_optimized.glb') }}"></a-asset-item>
        <a-asset-item id="model_s1_05" src="{{ asset('cg/20260613/05_optimized.glb') }}"></a-asset-item>
        <a-asset-item id="model_boss_s1" src="{{ asset('cg/zombie_morishige4.glb') }}"></a-asset-item>

        <!-- ステージ2 ゾンビモデル -->
        <a-asset-item id="model_s2_01" src="{{ asset('cg/20260613/06_optimized.glb') }}"></a-asset-item>
        <a-asset-item id="model_s2_02" src="{{ asset('cg/20260613/07_optimized.glb') }}"></a-asset-item>
        <a-asset-item id="model_s2_03" src="{{ asset('cg/20260613/08_optimized.glb') }}"></a-asset-item>
        <a-asset-item id="model_s2_04" src="{{ asset('cg/20260613/09_optimized.glb') }}"></a-asset-item>
        <a-asset-item id="model_s2_05" src="{{ asset('cg/20260613/10_optimized.glb') }}"></a-asset-item>
        <a-asset-item id="model_boss_s2" src="{{ asset('cg/zombie_fujii.glb') }}"></a-asset-item>

        <!-- サウンド -->
        <audio id="sound_hit"          src="{{ asset('cg/sound_hit02.mp3') }}"          preload="auto" crossorigin="anonymous"></audio>
        <audio id="sound_bgm_s1"       src="{{ asset('cg/sound_bgm08.mp3') }}"          preload="auto" crossorigin="anonymous"></audio>
        <audio id="sound_bgm_s2"       src="{{ asset('cg/sound_bgm06.mp3') }}"          preload="auto" crossorigin="anonymous"></audio>
        <audio id="sound_alert"        src="{{ asset('cg/sound_alert.mp3') }}"           preload="auto" loop crossorigin="anonymous"></audio>
        <audio id="sound_zombie_appear" src="{{ asset('cg/sound_zombie_appear.mp3') }}" preload="auto" crossorigin="anonymous"></audio>
        <audio id="sound_zombie_die"   src="{{ asset('cg/sound_zombie_die.mp3') }}"      preload="auto" crossorigin="anonymous"></audio>

        <!-- 背景画像 -->
        <img id="sky_s1" src="{{ asset('cg/R0010143a.JPG') }}" crossorigin="anonymous">
        <img id="sky_s2" src="{{ asset('cg/R0010131a.JPG') }}" crossorigin="anonymous">
        <img id="pokeball_icon_05" src="{{ asset('cg/pokeball_icon05.png') }}" crossorigin="anonymous">
        <img id="pokeball_icon_06" src="{{ asset('cg/pokeball_icon06.png') }}" crossorigin="anonymous">
    </a-assets>

    <!-- ─ ライティング ─ -->
    <a-entity light="type: ambient; color: #DDD; intensity: 1.2"></a-entity>
    <a-entity light="type: directional; color: #FFF; intensity: 1.5" position="1 2 1"></a-entity>
    <a-entity light="type: directional; color: #FFF; intensity: 0.8" position="-1 1 -1"></a-entity>
    <a-entity light="type: directional; color: #FFF; intensity: 0.6" position="0 1 2"></a-entity>

    <!-- ─ カーソル・コントローラー ─ -->
    <a-entity id="mouseCursor" cursor="rayOrigin: mouse" raycaster="objects: .clickable, .collidable"></a-entity>
    <a-entity id="leftController"
              laser-controls="hand: left"
              raycaster="objects: .collidable, .clickable; far: 5; showLine: false"
              vr-controller></a-entity>
    <a-entity id="rightController"
              laser-controls="hand: right; model: false"
              raycaster="objects: .collidable, .clickable; far: 5"
              vr-controller>
        <a-entity id="controllerGunModel"
                  gltf-model="{{ asset('cg/gun_01.glb') }}"
                  position="0 -0.05 -0.1"
                  rotation="0 -90 0"
                  scale="0.2 0.2 0.2"></a-entity>
    </a-entity>

    <!-- ─ スタートメニュー ─ -->
    <a-entity id="startMenu" position="0 1.6 -3" start-menu>
        <a-plane position="0 0 0" width="3.2" height="2.6"
                 color="#000000" opacity="0.7"
                 material="transparent: true"
                 class="clickable"></a-plane>
        <a-text value="seieiVR SHOOTING GAME"
                position="0 1.0 0.01" align="center"
                color="#FFFFFF" width="3"
                font="roboto" shader="msdf"></a-text>
        <a-text value="Stage 1"
                position="0 0.65 0.01" align="center"
                color="#00FFFF" width="3"
                font="roboto" shader="msdf"></a-text>
        <!-- 武器切替エリア（クリックで切り替え）-->
        <a-plane id="weaponDisplay"
                 position="0 0.15 0.01" width="2.8" height="0.48"
                 color="#222244" opacity="0.85"
                 material="transparent: true"
                 class="clickable"></a-plane>
        <a-text id="weaponText"
                value="WEAPON: Gun 1"
                position="0 0.15 0.02" align="center"
                color="#FFFF00" width="3"
                font="roboto" shader="msdf"></a-text>
        <a-text value="Grip / A / B : Switch Weapon"
                position="0 -0.22 0.01" align="center"
                color="#AAAAAA" width="2.5"
                font="roboto" shader="msdf"></a-text>
        <!-- START ボタン -->
        <a-plane id="startButton"
                 position="0 -0.8 0.01" width="2.4" height="0.62"
                 color="#00CCFF" opacity="0.92"
                 material="transparent: true"
                 class="clickable"></a-plane>
        <a-text value="START"
                position="0 -0.8 0.02" align="center"
                color="#000000" width="3.5"
                font="roboto" shader="msdf" baseline="center"></a-text>
    </a-entity>

    <!-- ─ タイマー・スコア表示 ─ -->
    <a-entity id="timerDisplay" position="0 2.0 -3" visible="false">
                <a-text id="timerText"    value="TIME: 100s"  position="-0.9 0 0" align="center" color="#FFFF00" width="4" font="roboto" shader="msdf"></a-text>
        <a-text id="currentScore" value="SCORE: 0.0"  position=" 0.9 0 0" align="center" color="#00FF00" width="4" font="roboto" shader="msdf"></a-text>
    </a-entity>

    <!-- デバッグ表示（通常非表示）-->
    <a-entity id="debugDisplay" position="0 2.75 -3" visible="false">
        <a-text id="debugText" value="DEBUG: Ready" align="center" color="#FF00FF" width="2" font="roboto" shader="msdf"></a-text>
    </a-entity>

    <!-- ─ リザルト画面 Stage 1 ─ -->
    <a-entity id="resultMenu_s1" position="0 1.6 -3" visible="false" result-menu>
        <a-plane position="0 0 0" width="6" height="5.2"
                 color="#000000" opacity="0.85"
                 material="transparent: true"></a-plane>
        <a-text id="resultStageTitle_s1"
                value="STAGE 1 CLEAR"
                position="0 2.0 0.01" align="center"
                color="#00FF00" width="4"
                font="roboto" shader="msdf"></a-text>
        <a-text id="resultScore_s1"
                value="SCORE: 0.0"
                position="0 1.5 0.01" align="center"
                color="#FFD700" width="4"
                font="roboto" shader="msdf"></a-text>
        <a-text id="maxComboText_s1"
                value="MAX COMBO: 0"
                position="0 1.1 0.01" align="center"
                color="#FF6600" width="3"
                font="roboto" shader="msdf"></a-text>
        <a-text id="resultComment_s1"
                value="KEEP PRACTICING!"
                position="0 0.7 0.01" align="center"
                color="#FFFFFF" width="3"
                font="roboto" shader="msdf"></a-text>
        <a-entity id="rankingDisplay_s1" position="0 -0.4 0.01"></a-entity>
        <!-- Next Stage ボタン -->
        <a-plane id="nextStageButton"
                 position="0 -2.1 0.01" width="3.5" height="0.65"
                 color="#00CC00" opacity="0.92"
                 material="transparent: true"
                 class="clickable"></a-plane>
        <a-text value="&#9654; Next Stage"
                position="0 -2.1 0.02" align="center"
                color="#FFFFFF" width="4"
                font="roboto" shader="msdf" baseline="center"></a-text>
    </a-entity>

    <!-- ─ リザルト画面 Stage 2 ─ -->
    <a-entity id="resultMenu_s2" position="0 1.6 -3" visible="false">
        <a-plane position="0 0 0" width="6" height="5.2"
                 color="#000000" opacity="0.85"
                 material="transparent: true"></a-plane>
        <a-text id="resultStageTitle_s2"
                value="GAME OVER"
                position="0 2.0 0.01" align="center"
                color="#FF0000" width="4"
                font="roboto" shader="msdf"></a-text>
        <a-text id="resultScore_s2"
                value="SCORE: 0.0"
                position="0 1.5 0.01" align="center"
                color="#FFD700" width="4"
                font="roboto" shader="msdf"></a-text>
        <a-text id="maxComboText_s2"
                value="MAX COMBO: 0"
                position="0 1.1 0.01" align="center"
                color="#FF6600" width="3"
                font="roboto" shader="msdf"></a-text>
        <a-text id="resultComment_s2"
                value="KEEP PRACTICING!"
                position="0 0.7 0.01" align="center"
                color="#FFFFFF" width="3"
                font="roboto" shader="msdf"></a-text>
        <a-entity id="rankingDisplay_s2" position="0 -0.4 0.01"></a-entity>
        <!-- Game Over ボタン -->
        <a-plane id="gameOverButton"
                 position="0 -2.1 0.01" width="3.5" height="0.65"
                 color="#CC0000" opacity="0.92"
                 material="transparent: true"
                 class="clickable collidable"></a-plane>
        <a-text value="&#10005; Game Over"
                position="0 -2.1 0.02" align="center"
                color="#FFFFFF" width="4"
                font="roboto" shader="msdf" baseline="center"></a-text>
    </a-entity>

    <!-- ─ タブが閉じられなかった場合のメッセージ ─ -->
    <a-entity id="closeMessage" position="0 1.6 -3" visible="false">
        <a-plane position="0 0 0" width="5" height="1.5"
                 color="#000000" opacity="0.9"
                 material="transparent: true"></a-plane>
        <a-text value="このタブを閉じてください"
                position="0 0 0.01" align="center"
                color="#FFFFFF" width="4"
                font="roboto" shader="msdf"></a-text>
    </a-entity>

    <!-- ─ 背景 ─ -->
    <a-sky id="aSky" src="#sky_s1"></a-sky>

    <!-- ─ パーティクルエフェクト ─ -->
    <a-entity id="particle-normal" visible="false" position="0 3 0"
              particle-system="preset: default; color: #FFFFFF; particleCount: 3; size: 0.1; maxAge: 1.0; velocityValue: 1 1 1; velocitySpread: 2 2 2; accelerationValue: 0 -2 0"></a-entity>
    <a-entity id="particle-tier1" visible="false" position="0 3 0"
              particle-system="preset: default; color: #00FFFF; particleCount: 5; size: 0.1; maxAge: 1.5; velocityValue: 2 2 2; velocitySpread: 3 3 3; accelerationValue: 0 -2 0"></a-entity>
    <a-entity id="particle-tier2" visible="false" position="0 3 0"
              particle-system="preset: default; color: #FF6600; particleCount: 8; size: 0.15; maxAge: 1.5; velocityValue: 2 2 2; velocitySpread: 3 3 3; accelerationValue: 0 -2 0"></a-entity>
    <a-entity id="particle-tier3" visible="false" position="0 3 0"
              particle-system="preset: default; color: #FF00FF; particleCount: 10; size: 0.2; maxAge: 1.5; velocityValue: 2 2 2; velocitySpread: 3 3 3; accelerationValue: 0 -2 0"></a-entity>
    <a-entity id="particle-celebration" visible="false" position="0 2 -3">
        <a-entity particle-system="preset: default; color: #FFD700,#FFA500,#FFFF00; particleCount: 15; size: 0.3; maxAge: 3; velocityValue: 0 5 0; velocitySpread: 5 2 5; accelerationValue: 0 -1 0; blending: 1"></a-entity>
        <a-entity particle-system="preset: default; color: #FFFFFF,#FFD700; particleCount: 10; size: 0.15; maxAge: 2.5; velocityValue: 0 3 0; velocitySpread: 4 3 4; accelerationValue: 0 -0.5 0; blending: 1" position="0 0.5 0"></a-entity>
    </a-entity>

    <!-- ─ カメラ ─ -->
    <a-camera id="my_camera" shoot>
                <a-entity id="ammoHud" position="0.62 -0.3 -1.1" scale="0.38 0.38 0.38">
                        <a-plane position="-0.56 0 0" width="1.15" height="0.7" color="#000000" opacity="0.45" material="transparent: true"></a-plane>

                        <a-image src="#pokeball_icon_05" position="-0.73 0.2 0.01" width="0.25" height="0.25"></a-image>
                        <a-text id="ammoTextGun1" value="30" position="-0.3 0.2 0.01" align="left" color="#FFFFFF" width="2.1" font="roboto" shader="msdf"></a-text>
                        <a-text id="ammoPopupGun1" value="" position="-0.1 0.2 0.02" align="left" color="#7CFF7C" width="1.8" font="roboto" shader="msdf" visible="false"></a-text>

                        <a-image src="#pokeball_icon_06" position="-0.73 -0.2 0.01" width="0.25" height="0.25"></a-image>
                        <a-text id="ammoTextGun2" value="30" position="-0.3 -0.2 0.01" align="left" color="#FFFFFF" width="2.1" font="roboto" shader="msdf"></a-text>
                        <a-text id="ammoPopupGun2" value="" position="-0.1 -0.2 0.02" align="left" color="#7CFF7C" width="1.8" font="roboto" shader="msdf" visible="false"></a-text>
                </a-entity>

        <a-entity id="fadeOverlay" visible="false">
            <a-plane width="200" height="200" position="0 0 -0.5"
                     color="#000000" opacity="0"
                     material="transparent: true; depthTest: false; side: double"></a-plane>
        </a-entity>
    </a-camera>

</a-scene>
