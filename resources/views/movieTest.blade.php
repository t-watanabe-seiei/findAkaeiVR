<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta charset="utf-8">
    <title></title>
    <meta name="description" content="">
    <meta name="author" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="stylesheet" href="">
    <link rel="shortcut icon" href="">

    <!-- CDN経由でA-Frameを読み込み -->
    <script src="https://aframe.io/releases/1.0.3/aframe.min.js"></script>
</head>

<body>
    <!-- 描画するシーン -->
    <a-scene>

        <!-- A-Frameで利用するアセット -->
        <a-assets>
            <video id="video" src="{{ asset('cg/R0010008_st_001.MP4') }}"
            preload="auto" muted loop="false" webkit-playsinline playsinline crossorigin="anonymous"></video>
            <img id="img_play" src="{{ asset('img/img_play.png') }}" crossorigin="anonymous"></img>
            <img id="img_pause" src="{{ asset('img/img_pause.png') }}" crossorigin="anonymous"></img>
        </a-assets>
        <!-- 実際にVRで表示されるモデル内側に指定リソースを表示する球体オブジェクト -->
        <a-videosphere id="videosphere" src='#video' visible="false"></a-videosphere>
 
        <a-image id="my_btn01" src="#img_pause" position="0 1.2 -3" scale="0.3 0.3 0.3"></a-image>
        
    </a-scene>
</body>

<!-- 以下はVR開始ボタンで動画を再生するためのJavascript -->
<script>
    var scene = document.querySelector("a-scene");
    var video = document.getElementById("video");
    
    if (scene.hasLoaded) {
        run();
    } else {
        scene.addEventListener("loaded", playvideo);
    }

    function playvideo() {
        scene.querySelector(".a-enter-vr-button").addEventListener("click", function (e) {
            var videosphere = document.getElementById("videosphere");
            videosphere.setAttribute("visible","true");

            video.play();
        }, false);
    }

    // window.onload = (e)=>{
    //     const video01 = document.getElementById("video");
    //     const btn01 = document.getElementById("my_btn01");
    //     // const videosphere = document.getElementById("videosphere");

    //     btn01.addEventListener("click", (e)=>{
    //         console.log("@@@@");
    //         // if(video01.paused){
    //         //     video01.play();
    //         //     e.target.setAttribute("src", "#img_play");
    //         // }else{
    //         //     video01.pause();
    //         //     e.target.setAttribute("src", "#img_pause");
    //         // }
    //     });
    // }
        
</script>

</html>