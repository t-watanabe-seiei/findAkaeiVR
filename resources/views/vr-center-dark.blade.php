<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="VR中心暗転体験アプリ">
    <title>VR 中心暗転体験</title>
    
    <!-- A-Frame CDN (1.4.0) -->
    <script src="https://aframe.io/releases/1.4.0/aframe.min.js"></script>
    
    <!-- カスタムコンポーネント -->
    <script src="{{ asset('js/vr-center-dark/center-dark.js') }}"></script>
    
    <style>
        body {
            margin: 0;
            overflow: hidden;
        }
        
        /* VRモード開始オーバーレイ */
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
            font-family: Arial, sans-serif;
        }
        
        #vr-start-overlay.hidden {
            display: none;
        }
        
        #vr-start-message {
            text-align: center;
            color: white;
        }
        
        #vr-start-message h1 {
            font-size: 2em;
            margin-bottom: 0.5em;
        }
        
        #vr-start-message p {
            font-size: 1.2em;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <!-- VRモード開始オーバーレイ（クリックで開始） -->
    <div id="vr-start-overlay">
        <div id="vr-start-message">
            <h1>VR 中心暗転体験</h1>
            <p>画面をクリックしてVRモードを開始</p>
        </div>
    </div>
    
    <a-scene 
        vr-mode-ui="enabled: true"
        auto-enter-vr>
        <!-- 360度画像 -->
        <!-- <a-sky src="{{ asset('cg/R0010034.JPG') }}"></a-sky> -->
        <!-- <a-sky src="{{ asset('cg/IMG_20260204_172548_00_151.jpg') }}"></a-sky> -->
        <a-sky src="{{ asset('cg/IMG_20260204_172754_00_154(1).jpg') }}"></a-sky>
        
        <!-- カメラリグ -->
        <a-entity id="camera-rig">
            <a-camera>
                <!-- 中心暗転オーバーレイ -->
                <a-entity center-dark-overlay></a-entity>
            </a-camera>
        </a-entity>
    </a-scene>
</body>
</html>
