<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <title>hit-box test</title>
    <script src="https://aframe.io/releases/1.4.1/aframe.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/c-frame/aframe-extras@7.2.0/dist/aframe-extras.min.js"></script>
    <script>
  
        let PassSec;   // 秒数カウント用変数
        let mytext;
        let PassageID;

        // 繰り返し処理の中身
        function showPassage() {
            PassSec = PassSec + 0.1;   // カウントアップ
            PassSec = Math.round(PassSec * 10); // 10をかけて→「3.0000000000000004」を四捨五入→「3」
            PassSec = PassSec / 10;             // 「3」を10で割る
            
            mytext = document.getElementById("my_text");
            mytext.setAttribute("value", PassSec.toFixed(1));
        }

        // 繰り返し処理の中止
        function stopShowing() {
            clearInterval( PassageID );   // タイマーのクリア
            console.log(PassSec);
        }
        
        AFRAME.registerComponent('hit-box', {
            init: function () {
                // akaeiModel_01のエンティティを取得
                const model = document.getElementById('target3DModel');
                let hitFlag = false;
                let hitCount = 0;
                let mySky;
                let model1;
                let model2;

                //タイマースタート
                PassSec = 0;
                PassageID = setInterval('showPassage()',100);   // タイマーをセット(0.1s間隔)

                this.el.addEventListener('click', () => {
                    // console.log('clicked!');
                    // console.log(model.getAttribute('position'));
                    // クリックされたら、モデルを非表示にする
                    model.removeAttribute('gltf-model');
                    model.setAttribute('gltf-model', '#akaeiModel_02');
                    hitCount++;
                    setTimeout(rePaintModel, 2300, "該当modelを削除し、別の場所に再描画します", hitCount);
                });

                const rePaintModel = function (msg1, hitCount) {
                    console.log(msg1);
                    console.log(hitCount);

                    switch (hitCount % 3) {
                        case 0:
                            //　背景を変更 a-sky
                            mySky = document.getElementById('aSky');
                            mySky.setAttribute('src', '#sky01');

                            //akaeiGroupの一を変更
                            model1 = document.getElementById('akaeiGroup');
                            // console.log(model1.getAttribute('position'));
                            model1.setAttribute('position', '-0.3 1.3 0.2');
                            model1.setAttribute('rotation', '0 90 0');
                            model1.setAttribute('scale', "0.4 0.4 0.4")

                            model2 = document.getElementById('target3DModel');
                            model2.removeAttribute('gltf-model');
                            model2.setAttribute('gltf-model', '#akaeiModel_01');
                            hitFlag = true;
                            mytext = document.getElementById("my_text");
                            mytext.setAttribute("value", "Look for the stingray again");

                            //タイマーSTOP
                            stopShowing();
                            break;

                        case 1:
                            //　背景を変更 a-sky
                            mySky = document.getElementById('aSky');
                            mySky.setAttribute('src', '#sky02');

                            //akaeiGroupの一を変更
                            model1 = document.getElementById('akaeiGroup');
                            // console.log(model1.getAttribute('position'));
                            model1.setAttribute('position', '32 -1.6 3');
                            model1.setAttribute('rotation', '0 -90 0');
                            model1.setAttribute('scale', "10 10 10")

                            model2 = document.getElementById('target3DModel');
                            model2.removeAttribute('gltf-model');
                            model2.setAttribute('gltf-model', '#akaeiModel_01');
                            hitFlag = true;
                            mytext = document.getElementById("my_text");
                            mytext.setAttribute("value", "Look for the stingray again");
                            break;

                        case 2:
                            //　背景を変更 a-sky
                            mySky = document.getElementById('aSky');
                            mySky.setAttribute('src', '#sky03');

                            //akaeiGroupの一を変更
                            model1 = document.getElementById('akaeiGroup');
                            // console.log(model1.getAttribute('position'));
                            model1.setAttribute('position', '-7 1 0.3');
                            model1.setAttribute('rotation', '0 90 0');
                            model1.setAttribute('scale', "0.6 0.6 0.6")

                            model2 = document.getElementById('target3DModel');
                            model2.removeAttribute('gltf-model');
                            model2.setAttribute('gltf-model', '#akaeiModel_01');
                            hitFlag = true;
                            mytext = document.getElementById("my_text");
                            mytext.setAttribute("value", "Look for the stingray again");
                            break;

                        default:
                            break;
                    }
                };
            }
        });




        // Controller
        AFRAME.registerComponent("vr-controller", {
            dependencies: ["raycaster"],// Important
            init: function () {
                // console.log("vr-controller");
                // const text = document.getElementById("my_text");
                // text.setAttribute("value", "Controller is ready!?");

                // this.el.addEventListener("gripdown", function(e) {
                //     text.setAttribute("value", "GripDown!!");
                // });
            }
        });


    </script>
</head>

<body>
    <a-scene>
        <a-assets>
            <a-asset-item id="akaeiModel_01" src={{ asset('cg/akaei_oldMan_idle.glb') }}></a-asset-item>
            <a-asset-item id="akaeiModel_02" src={{ asset('cg/akaei_TrunToRunning.glb') }}></a-asset-item>
            <img id="sky01" src={{ asset('cg/R0010034.JPG') }} >  
            <img id="sky02" src={{ asset('cg/R0010035.JPG') }} >
            <img id="sky03" src={{ asset('cg/R0010036.JPG') }} >
        </a-assets>

        <!-- マウスカーソル -->
        <a-entity id="mouseCursor" cursor="rayOrigin: mouse" raycaster="objects: .raycastable"></a-entity>

        <!-- Controller -->
        <a-entity laser-controls="hand: left" raycaster="objects: .collidable; far: 50" vr-controller></a-entity>
        <a-entity laser-controls="hand: right" raycaster="objects: .collidable; far: 50" vr-controller></a-entity>

        <!-- クリックしたいentityグループ -->
        <a-entity id="akaeiGroup" position="-0.3 1.3 0.2" rotation="0 90 0" scale="0.4 0.4 0.4">
            <!-- 3Dモデル -->
            <a-entity id="target3DModel" class="collidable" gltf-model="#akaeiModel_01" scale="1 1 1" rotation="0 0 0" animation-mixer>

                <!-- 当たり判定オブジェクト -->
                <a-entity position="0 -0.05 0" hit-box>
                    <a-entity class="raycastable collidable" geometry="primitive:cylinder"
                        material="color:red; opacity: 0.0" scale="0.1 0.2 0.18" position="0 0.2 0" visible="true"></a-entity>
                </a-entity>
            </a-entity>
        </a-entity>

        <!-- 360度画像を表示 -->
        <a-sky id="aSky" src="#sky01"></a-sky> <!--最初は正門の写真-->

        <a-camera id="my_camera">
            <a-cursor></a-cursor>
            <input type="button" value="start" onClick="OnStartButtonClick();">
            <a-text id="my_text" value="Look for the stingray　**26**" position="0 -0.1 -2" scale="0.4 0.4 0.4" align="center" color="#ffffff"></a-text>
        </a-camera>
    </a-scene>
</body>

</html>