<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <title>seieiVR</title>
    <script src="https://aframe.io/releases/1.2.0/aframe.min.js"></script>
    <script src="{{ asset('js/aframe-particle-system-component.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/gh/c-frame/aframe-extras@7.2.0/dist/aframe-extras.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/n5ro/aframe-physics-system@v4.2.2/dist/aframe-physics-system.min.js"></script>
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>

    <script>  
        let PassSec;   // 秒数カウント用変数
        let mytext;
        let PassageID;

        // 繰り返し処理の中身
        function showPassage() {
            PassSec = PassSec + 0.01;   // カウントアップ
            PassSec = Math.round(PassSec * 100); // 100をかけて→「3.12300000000000004」を四捨五入→「312」
            PassSec = PassSec / 100;             // 「312」を100で割る
            
        }

        // 繰り返し処理の中止
        function stopShowing() {
            clearInterval( PassageID );   // タイマーのクリア
            // console.log(PassSec);
        }

        // ボールを撃つコンポーネント
        AFRAME.registerComponent('shoot', {
            init: function () {
                this.shoot = this.shoot.bind(this);
                this.onKeyDown = this.onKeyDown.bind(this);
                
                // スペースキーのイベントリスナーを追加
                window.addEventListener('keydown', this.onKeyDown);
                
                // VRコントローラーのイベントリスナーを追加
                const leftController = document.getElementById('leftController');
                const rightController = document.getElementById('rightController');
                if (leftController) leftController.addEventListener('triggerdown', this.shoot);
                if (rightController) rightController.addEventListener('triggerdown', this.shoot);
            },
            
            onKeyDown: function (event) {
                // スペースキーが押された場合
                if (event.code === 'Space') {
                    event.preventDefault(); // デフォルトのスペースキー動作を防ぐ
                    this.shoot(event);
                    console.log('space key pressed');
                }
            },
            
            shoot: function (event) {
                console.log('Shoot function called, event type:', event.type);
                
                const sceneEl = this.el.sceneEl;
                const camera = this.el;
                
                // ボールエンティティを作成
                const ball = document.createElement('a-sphere');
                ball.setAttribute('radius', 0.1);
                ball.setAttribute('color', 'red');
                ball.setAttribute('dynamic-body', 'shape: sphere; mass: 1;');
                ball.setAttribute('material', 'color: red; metalness: 0.1; roughness: 0.8;');
                
                // 位置と方向を計算
                const position = new THREE.Vector3();
                const direction = new THREE.Vector3();
                
                if (event.type === 'triggerdown') {
                    // VRコントローラーからの発射
                    event.target.object3D.getWorldPosition(position);
                    event.target.object3D.getWorldDirection(direction);
                    console.log('Shooting from VR controller');
                } else {
                    // スペースキーからの発射（カメラの向いている方向）
                    camera.object3D.getWorldPosition(position);
                    camera.object3D.getWorldDirection(direction);
                    console.log('Shooting from camera, position:', position, 'direction:', direction);
                }
                
                // カメラの少し前にボールを配置
                const startPos = position.clone().add(direction.clone().multiplyScalar(-0.5));
                ball.setAttribute('position', `${startPos.x} ${startPos.y} ${startPos.z}`);
                sceneEl.appendChild(ball);
                
                console.log('Ball created at position:', startPos);
                
                // 物理エンジンを使わずに、シンプルなアニメーションで実装
                const targetPos = startPos.clone().add(direction.clone().multiplyScalar(-20));
                ball.setAttribute('animation', {
                    property: 'position',
                    to: `${targetPos.x} ${targetPos.y} ${targetPos.z}`,
                    dur: 2000,
                    easing: 'linear'
                });
                
                console.log('Ball animation started to:', targetPos);
                
                // リアルタイム衝突検出（アニメーション中に連続チェック）
                let animationFrameId;
                let hasHit = false;
                
                const checkCollision = () => {
                    if (hasHit || !ball.parentNode) {
                        return; // 既に当たったか削除されている場合は終了
                    }
                    
                    // エイの位置を取得
                    const akaeiGroup = document.getElementById('akaeiGroup');
                    if (akaeiGroup) {
                        const akaeiPos = akaeiGroup.getAttribute('position');
                        const ballCurrentPos = ball.getAttribute('position');
                        
                        // リアルタイムでボールとエイの距離を計算
                        const distance = new THREE.Vector3(
                            ballCurrentPos.x - akaeiPos.x,
                            ballCurrentPos.y - akaeiPos.y,
                            ballCurrentPos.z - akaeiPos.z
                        ).length();
                        
                        console.log('Current distance to target:', distance.toFixed(2));
                        
                        if (distance < 5.1) { // 1.1m以内なら当たり判定（より厳しく）
                            hasHit = true;
                            console.log('Ball hit akaei during animation!');
                            const hitBoxComponent = akaeiGroup.querySelector('[hit-box]');
                            if (hitBoxComponent) {
                                hitBoxComponent.emit('click');
                            }
                            
                            // ボールを即座に削除
                            if (ball.parentNode) {
                                ball.parentNode.removeChild(ball);
                                console.log('Ball removed after hit');
                            }
                            return;
                        }
                    }
                    
                    // 次のフレームでも衝突チェックを継続
                    animationFrameId = requestAnimationFrame(checkCollision);
                };
                
                // 衝突チェック開始
                animationFrameId = requestAnimationFrame(checkCollision);
                
                // アニメーション完了時の処理（当たらなかった場合）
                setTimeout(() => {
                    if (animationFrameId) {
                        cancelAnimationFrame(animationFrameId);
                    }
                    
                    if (!hasHit && ball.parentNode) {
                        console.log('Ball missed target');
                        ball.parentNode.removeChild(ball);
                        console.log('Ball removed after timeout');
                    }
                }, 2000);
            }
        });
        
        AFRAME.registerComponent('hit-box', {
            init: function () {
                // akaeiModel_01のエンティティを取得
                const model = document.getElementById('target3DModel');
                let hitFlag = false;
                let hitCount = 0;
                let mySky;
                let model1;
                let model2;
                let myRank1;
                let myRank2;
                let myRank3;
                let myRank4;
                let myRank5;
                let myRankElement;
                
                const video = document.getElementById("video");
                const videosphere = document.getElementById("videosphere");



                this.el.addEventListener('click', () => {
                    // console.log(hitFlag);
                    // console.log(model.getAttribute('position'));
                    
                    if(!hitFlag){       //hitFlag がfalseの時の処理
                        hitCount++;
                        hitFlag = true;

                        // クリックされたら、モデル０１を非表示にして、モデル０２に切り替え
                        model.removeAttribute('gltf-model');
                        model.setAttribute('gltf-model', '#akaeiModel_02');

                        // hitFlag デフォルトは false クリックされたら、2.3秒後にrePaintModel()を実行し、モデルを01に戻して、hitFlagもfalseに戻す
                        setTimeout(rePaintModel, 2300, "該当modelを削除し、別の場所に再描画します", hitCount);
                    }

                });

                
                const playMovie = function (msg1, movieID) {
                    // akaei　非表示
                    model2 = document.getElementById('target3DModel');
                    model2.setAttribute("visible","false");

                    //　背景a-skyを非表示
                    mySky = document.getElementById('aSky');
                    mySky.setAttribute("visible","false");

                    //Particle非表示
                    document.getElementById("particle").setAttribute("visible", "false");

                    //　my_text , ranking1~5 を非表示
                    document.getElementById("my_text").setAttribute("value", "");
                    document.getElementById("ranking1").setAttribute("value", "");
                    document.getElementById("ranking2").setAttribute("value", "");
                    document.getElementById("ranking3").setAttribute("value", "");
                    document.getElementById("ranking4").setAttribute("value", "");
                    document.getElementById("ranking5").setAttribute("value", "");

                    //movie再生
                    videosphere.setAttribute("visible","true");
                    video.play();

                    setTimeout(stopMovie, 10000, "movieを停止します", 1);
                };

                const stopMovie = function (msg1, movieID) {
                    // akaei　表示
                    model2 = document.getElementById('target3DModel');
                    model2.setAttribute("visible","true");

                    //　背景a-skyを表示
                    mySky = document.getElementById('aSky');
                    mySky.setAttribute("visible","true");

                    //　ranking1~5 を非表示
                    document.getElementById("ranking1").setAttribute("value", "");
                    document.getElementById("ranking2").setAttribute("value", "");
                    document.getElementById("ranking3").setAttribute("value", "");
                    document.getElementById("ranking4").setAttribute("value", "");
                    document.getElementById("ranking5").setAttribute("value", "");
                    
                    document.getElementById("ranking1").setAttribute("Color","#ffffff");
                    document.getElementById("ranking2").setAttribute("Color","#ffffff");
                    document.getElementById("ranking3").setAttribute("Color","#ffffff");
                    document.getElementById("ranking4").setAttribute("Color","#ffffff");
                    document.getElementById("ranking5").setAttribute("Color","#ffffff");

                    //movie stop
                    videosphere.setAttribute("visible","false");
                    video.pause();

                    //クリックできるように
                    hitFlag = false;

                    document.getElementById("my_text").setAttribute("value", "Please look for Akaei.");

                    //akaeiGroupの一を変更 position_1 <a-entity id="akaeiGroup" position="-1.0 0.6 1.0" rotation="0 90 0" scale="0.9 0.9 0.9">
                    model1 = document.getElementById('akaeiGroup');
                    model1.setAttribute('position', '-2 -0.6 1');
                    model1.setAttribute('rotation', '0 120 0');
                    model1.setAttribute('scale', "1.4 1.4 1.4");

                    model2 = document.getElementById('target3DModel');
                    model2.removeAttribute('gltf-model');
                    model2.setAttribute('gltf-model', '#akaeiModel_01');

                };


                const rePaintModel = async function (msg1, hitCount) {
                    console.log(msg1);
                    console.log(hitCount);

                    switch (hitCount % 4) {
                        case 0:
                            // //movie
                            // videosphere.setAttribute("visible","true");
                            // video.play();


                            //　背景を変更 a-sky
                            mySky = document.getElementById('aSky');
                            // mySky.setAttribute("visible","false");
                            mySky.setAttribute('src', '#sky01');

                            //akaeiGroupの一を変更 position_1 <a-entity id="akaeiGroup" position="-1.0 0.6 1.0" rotation="0 90 0" scale="0.9 0.9 0.9">
                            model1 = document.getElementById('akaeiGroup');
                            model1.setAttribute('position', '-2 0.5 1');
                            model1.setAttribute('rotation', '0 120 0');
                            model1.setAttribute('scale', "2.1 2.1 2.1");

                            model2 = document.getElementById('target3DModel');
                            model2.removeAttribute('gltf-model');
                            model2.setAttribute('gltf-model', '#akaeiModel_03');
                            
                            mytext = document.getElementById("my_text");
                            mytext.setAttribute("value", "Look for the stingray again");

                            //タイマーSTOP
                            stopShowing();

                            //タイマーの時間を表示
                            console.log(PassSec);

                            //fetch de POST 自分の記録をPOST
                            await fetch("{{ env('MIX_ASSET_URL') }}" + '/api/Scores', {  
                                method: "POST",
                                headers: {
                                    "Content-Type": "application/json"
                                },
                                body: JSON.stringify({userid: '2',time: PassSec,}
                                )
                            });

                            //fetch de get 自分の記録も含めて TOP 5 表示
                            fetch("{{ env('MIX_ASSET_URL') }}" + '/api/Scores')            
                            .then((response) => response.json())
                            .then((datas) => { 
                                datas.forEach((data,index) => {
                                    console.log(index + "st : " + data.userid + " -> " + data.time);
                                    myRankElement = document.getElementById("ranking" + (index + 1));
                                    myRankElement.setAttribute("value" , (index+1) + "st : " + data.time);
                                    if(data.time == PassSec){
                                        myRankElement.setAttribute("Color","#faa7de");
                                    }
                                });
                            });

                            //
                            document.getElementById("my_text").setAttribute("value", "Congratulations. Your clear time is " + PassSec + " seconds. Please watch the introductory video of club.");
                            //Particle表示
                            document.getElementById("particle").setAttribute("visible", "true");

                            
                            // //fetch de PUT
                            // fetch("{{ env('MIX_ASSET_URL') }}" + '/api/Scores/1', {  
                            //     method: "PUT",
                            //     headers: {
                            //         "Content-Type": "application/json"
                            //     },
                            //     body: JSON.stringify({userid: '20180001',time: '88.88',}
                            //     )
                            // });

                            hitFlag = true;
                            
                            //15秒後に動画再生
                            setTimeout(playMovie, 15000, "movieを再生します", 1);

                            break;

                        case 1:
                            //タイマースタート
                            PassSec = 0;
                            PassageID = setInterval('showPassage()',10);   // タイマーをセット(0.01s間隔)

                            //　背景を変更 a-sky
                            mySky = document.getElementById('aSky');
                            mySky.setAttribute('src', '#sky02');
                            mySky = document.getElementById('aSky');
                            

                            //akaeiGroupの一を変更 position_2
                            model1 = document.getElementById('akaeiGroup');
                            // model1.setAttribute('position', '42 -1.6 3');
                            model1.setAttribute('position', '10 -0.88 -1.9');
                            model1.setAttribute('rotation', '0 -90 0');
                            model1.setAttribute('scale', "2.7 2.7 2.7");

                            model2 = document.getElementById('target3DModel');
                            model2.removeAttribute('gltf-model');
                            model2.setAttribute('gltf-model', '#akaeiModel_01');
                            hitFlag = false;
                            mytext = document.getElementById("my_text");
                            mytext.setAttribute("value", "");
                            break;

                        case 2:
                            //　背景を変更 a-sky
                            mySky = document.getElementById('aSky');
                            mySky.setAttribute('src', '#sky03');

                            //akaeiGroupの一を変更 position_3
                            model1 = document.getElementById('akaeiGroup');
                            model1.setAttribute('position', '-1.325 1.0 4.00');
                            model1.setAttribute('rotation', '0 150 0');
                            model1.setAttribute('scale', "1.4 1.4 1.4");

                            model2 = document.getElementById('target3DModel');
                            model2.removeAttribute('gltf-model');
                            model2.setAttribute('gltf-model', '#akaeiModel_01');
                            hitFlag = false;
                            break;

                        case 3:
                            //　背景を変更 a-sky
                            document.getElementById('aSky').setAttribute('src', '#sky04');

                            //akaeiGroupの一を変更 position_4
                            model1 = document.getElementById('akaeiGroup');
                            model1.setAttribute('position', '6.0 0 0.13');
                            model1.setAttribute('rotation', '0 -120 0');
                            model1.setAttribute('scale', "2.1 2.1 2.1");

                            model2 = document.getElementById('target3DModel');
                            model2.removeAttribute('gltf-model');
                            model2.setAttribute('gltf-model', '#akaeiModel_01');
                            hitFlag = false;
                            break;
                        
                        case 4:
                            //　背景を変更 a-sky
                            document.getElementById('aSky').setAttribute('src', '#sky05');

                            //akaeiGroupの一を変更 position_5
                            model1 = document.getElementById('akaeiGroup');
                            model1.setAttribute('position', '-0.5 0 -0.5');
                            model1.setAttribute('rotation', '0 0 0');
                            model1.setAttribute('scale', "1 1 1");

                            model2 = document.getElementById('target3DModel');
                            model2.removeAttribute('gltf-model');
                            model2.setAttribute('gltf-model', '#akaeiModel_01');
                            hitFlag = false;
                            break;

                        case 5:
                            //　背景を変更 a-sky
                            document.getElementById('aSky').setAttribute('src', '#sky06');

                            //akaeiGroupの一を変更 position_6
                            model1 = document.getElementById('akaeiGroup');
                            model1.setAttribute('position', '-4.5 0.9 4.6');
                            model1.setAttribute('rotation', '0 130 0');
                            model1.setAttribute('scale', "1.9 1.9 1.9");

                            model2 = document.getElementById('target3DModel');
                            model2.removeAttribute('gltf-model');
                            model2.setAttribute('gltf-model', '#akaeiModel_01');
                            hitFlag = false;
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
    <a-scene physics="gravity: -9.8">
        <a-assets>
            <!-- <a-asset-item id="akaeiModel_01" src={{ asset('cg/praying3.glb') }}></a-asset-item>
            <a-asset-item id="akaeiModel_02" src={{ asset('cg/TrunToRunning3.glb') }}></a-asset-item>
            <a-asset-item id="akaeiModel_03" src={{ asset('cg/SillyDancing.glb') }}></a-asset-item> -->
            <a-asset-item id="akaeiModel_01" src={{ asset('cg/akaei_oldMan_idle.glb') }}></a-asset-item>
            <a-asset-item id="akaeiModel_02" src={{ asset('cg/akaei_TrunToRunning.glb') }}></a-asset-item>
            <a-asset-item id="akaeiModel_03" src={{ asset('cg/akaei_HouseDancing.glb') }}></a-asset-item>
            <!-- <img id="sky01" src={{ asset('cg/R0010034.JPG') }} crossorigin="anonymous" >  
            <img id="sky02" src={{ asset('cg/R0010035.JPG') }} crossorigin="anonymous" >
            <img id="sky03" src={{ asset('cg/R0010036.JPG') }} crossorigin="anonymous" >
            <img id="sky04" src={{ asset('cg/R0010041.JPG') }} crossorigin="anonymous" >  
            <img id="sky05" src={{ asset('cg/R0010056.JPG') }} crossorigin="anonymous" >
            <img id="sky06" src={{ asset('cg/R0010064.JPG') }} crossorigin="anonymous" >
            <video id="video" src="{{ asset('cg/R0010008_st_001.MP4') }}" -->
            <img id="sky01" src={{ asset('cg/R0010181.JPG') }} crossorigin="anonymous" >  
            <img id="sky02" src={{ asset('cg/R0010186.JPG') }} crossorigin="anonymous" >
            <img id="sky03" src={{ asset('cg/R0010191.JPG') }} crossorigin="anonymous" >
            <img id="sky04" src={{ asset('cg/R0010198.JPG') }} crossorigin="anonymous" >  
            <img id="sky05" src={{ asset('cg/R0010131.JPG') }} crossorigin="anonymous" >
            <img id="sky06" src={{ asset('cg/R0010143.JPG') }} crossorigin="anonymous" >
            <video id="video" src="{{ asset('cg/R0010199_st.MP4') }}"
            preload="auto" loop="false" webkit-playsinline playsinline crossorigin="anonymous"></video>
            

        </a-assets>

        <!-- マウスカーソル -->
        <a-entity id="mouseCursor" cursor="rayOrigin: mouse" raycaster="objects: .raycastable"></a-entity>

        <!-- Controller -->
        <a-entity id="leftController" laser-controls="hand: left" raycaster="objects: .collidable; far: 5" vr-controller></a-entity>
        <a-entity id="rightController" laser-controls="hand: right" raycaster="objects: .collidable; far: 5" vr-controller></a-entity>

        <!-- クリックしたいentityグループ position_1-->
        <a-entity id="akaeiGroup" static-body position="-2 -0.6 1" rotation="0 120 0" scale="1.4 1.4 1.4">
            <!-- 3Dモデル -->
            <a-entity id="target3DModel" class="collidable" gltf-model="#akaeiModel_01" scale="1 1 1" rotation="0 0 0" animation-mixer>

                <!-- 当たり判定オブジェクト -->
                <a-entity position="0 -0.05 0" hit-box id="hit-boxed">
                    <a-entity id="hit-box-cylinder" class="raycastable collidable" geometry="primitive:cylinder"
                        material="color:blue; opacity: 0.3" scale="0.14 0.3 0.14" position="0 0.21 0"></a-entity>
                </a-entity>
            </a-entity>
        </a-entity>

        <!-- 360度画像を表示 -->
        <a-sky id="aSky" src="#sky01"></a-sky> <!--最初は正門の写真-->

        <!-- 実際にVRで表示されるモデル内側に指定リソースを表示する球体オブジェクト 　この球体に３６０videoを投影-->
        <a-videosphere id="videosphere" src='#video' visible="false"></a-videosphere>

        <!-- Particle -->
        <a-entity id="particle" visible="false" position="0 3 0" particle-system="preset:star; color: #f216b0,#f24535"></a-entity>
        

        <a-camera id="my_camera" shoot>
            <!-- <a-cursor></a-cursor> -->
            <input type="button" value="start" onClick="OnStartButtonClick();">
            <a-text id="my_text" value="Please look for Akaei." position="0 -0.1 -2" scale="0.4 0.4 0.4" align="center" color="#ffffff"></a-text>
            <a-text id="ranking1" value="" position="-1 1.25 -3" color="#ffffff" scale="0.7 0.7 0.7" ></a-text>
            <a-text id="ranking2" value="" position="-1 1.00 -3" color="#ffffff" scale="0.7 0.7 0.7" ></a-text>
            <a-text id="ranking3" value="" position="-1 0.75 -3" color="#ffffff" scale="0.7 0.7 0.7" ></a-text>
            <a-text id="ranking4" value="" position="-1 0.50 -3" color="#ffffff" scale="0.7 0.7 0.7" ></a-text>
            <a-text id="ranking5" value="" position="-1 0.25 -3" color="#ffffff" scale="0.7 0.7 0.7" ></a-text>
            
        </a-camera>
    </a-scene>
</body>

</html>