<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="VR視野狭窄体験アプリ">
    <title>VR 視野狭窄体験</title>
    
    <!-- A-Frame CDN (1.4.0) -->
    <script src="https://aframe.io/releases/1.4.0/aframe.min.js"></script>
    
    <!-- カスタムコンポーネント -->
    <!-- <script src="/js/vr-tunnel/tunnel-vision.js"></script> -->
    <script src="{{ asset('js/vr-tunnel/tunnel-vision.js') }}"></script>
    
    <style>
        body {
            margin: 0;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <a-scene>
        <!-- 360度画像 -->
        <a-sky src="/cg/R0010034.JPG" rotation="0 -90 0"></a-sky>
        
        <!-- カメラリグ -->
        <a-entity id="camera-rig">
            <a-camera>
                <!-- 視野狭窄オーバーレイ -->
                <a-entity tunnel-vision-overlay></a-entity>
            </a-camera>
        </a-entity>
    </a-scene>
</body>
</html>
