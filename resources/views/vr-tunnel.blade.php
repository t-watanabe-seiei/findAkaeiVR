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
        #vr-start-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            cursor: pointer;
        }
        #vr-start-overlay.hidden {
            display: none;
        }
        #vr-start-overlay p {
            color: white;
            font-size: 24px;
            font-family: sans-serif;
        }
    </style>
</head>
<body>
    <div id="vr-start-overlay">
        <p>クリックしてVR体験を開始</p>
    </div>
    
    <!-- 重要: vr-mode-ui設定がPicoブラウザでの自動VRモード起動に不可欠 -->
    <a-scene vr-mode-ui="enabled: true" auto-enter-vr>
        <!-- 360度画像 -->
        <!-- <a-sky src="/cg/R0010034.JPG" rotation="0 -90 0"></a-sky> -->
        <!-- <a-sky src="{{ asset('cg/R0010034.JPG') }}"></a-sky> -->
        <!-- <a-sky src="{{ asset('cg/IMG_20260204_172656_00_153.jpg') }}"></a-sky> -->
        <a-sky src="{{ asset('cg/IMG_20260204_172656_00_153(1).jpg') }}"></a-sky>
        
        
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
