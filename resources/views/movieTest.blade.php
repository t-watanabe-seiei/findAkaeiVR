<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <title>A-Frame Particle System Component Example</title>
    <meta name="description" content="Hello, World!">
    <script src="https://aframe.io/releases/1.4.2/aframe.min.js"></script> 
    <script src="https://cdn.jsdelivr.net/gh/c-frame/aframe-extras@7.2.0/dist/aframe-extras.min.js"></script>
    <!-- <script src="https://aframe.io/releases/0.8.2/aframe.min.js"></script> -->
    <!-- <script src="https://aframe.io/releases/1.2.0/aframe.min.js"></script> -->
    <!-- <script src="https://unpkg.com/aframe-particle-system-component@1.0.x/dist/aframe-particle-system-component.min.js"></script> -->
    
    <!-- <script src="https://unpkg.com/aframe-particle-system-component@1.0.x/dist/aframe-particle-system-component.min.js"></script> -->
    
    <!-- <script src="{{ asset('js/aframe-particle-system-component.min.js') }}"></script> -->
    <script>
      AFRAME.registerComponent('hit-box', {
        init: function () {
          const video = document.getElementById("video");
          const videosphere = document.getElementById("videosphere");

          this.el.addEventListener('click', () => {
              console.log("hit");
              videosphere.setAttribute("visible","true");
              video.play();
          });
        }
      });
    </script>
 
  </head>
  <body>
    <a-scene>
      <a-assets timeout="9000"> <!--  タイムアウト時間　9秒  -->
          <a-asset-item id="akaeiModel_01" src="{{ asset('cg/akaei_oldMan_idle.glb') }}"></a-asset-item>
          
          <video id="video" src="{{ asset('cg/R0010008_st_001.MP4') }}"
          preload="auto" loop="false" webkit-playsinline playsinline crossorigin="anonymous"></video>

      </a-assets>



        <!-- マウスカーソル -->
        <a-entity id="mouseCursor" cursor="rayOrigin: mouse" raycaster="objects: .raycastable"></a-entity>

        <!-- クリックしたいentityグループ position_1-->
        <a-entity id="akaeiGroup" position="-2 0.5 1" rotation="0 120 0" scale="1.4 1.4 1.4">
            <!-- 3Dモデル -->
            <a-entity id="target3DModel" class="collidable" gltf-model="#akaeiModel_01" scale="1 1 1" rotation="0 0 0" animation-mixer sphere data-color="red" >
                <!-- 当たり判定オブジェクト -->
                <a-entity position="0 -0.05 0" hit-box id="hit-boxed">
                    <a-entity id="hit-box-cylinder" class="raycastable collidable" geometry="primitive:cylinder"
                        material="color:red; opacity: 0.2" scale="0.14 0.3 0.14" position="0 0.21 0"></a-entity>
                </a-entity>
            </a-entity>
        </a-entity>


      <!-- Particle system uses 'default' preset, setting custom colors. -->
      <!-- <a-entity position="0 2.25 -15" particle-system="preset:star; color: #f216b0,#f24535"></a-entity> -->
      <!-- <a-entity position="0 2.25 -15" particle-system="preset: dust; particleCount: 10000"></a-entity> -->
      <!-- <a-entity position="0 2.25 -15" particle-system="preset: snow; particleCount: 5000"></a-entity> -->
      <!-- <a-entity position="0 2.25 -15" particle-system="preset: rain"></a-entity> -->

      <a-sky color="#000000"></a-sky>

      <!-- 実際にVRで表示されるモデル内側に指定リソースを表示する球体オブジェクト 　この球体に３６０videoを投影-->
      <a-videosphere id="videosphere" src='#video' visible="false"></a-videosphere>
    </a-scene>
  </body>
</html>