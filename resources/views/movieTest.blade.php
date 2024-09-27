<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <title>A-Frame Particle System Component Example</title>
    <meta name="description" content="Hello, World!">
    <script src="https://aframe.io/releases/1.2.0/aframe.min.js"></script> 
    <!-- <script src="https://aframe.io/releases/0.8.2/aframe.min.js"></script> -->
    <!-- <script src="https://aframe.io/releases/1.2.0/aframe.min.js"></script> -->
    <!-- <script src="https://unpkg.com/aframe-particle-system-component@1.0.x/dist/aframe-particle-system-component.min.js"></script> -->
    
    <!-- <script src="https://unpkg.com/aframe-particle-system-component@1.0.x/dist/aframe-particle-system-component.min.js"></script> -->
    
    <script src="{{ asset('js/aframe-particle-system-component.min.js') }}"></script>
 
  </head>
  <body>
    <a-scene>
      <!-- Particle system uses 'default' preset, setting custom colors. -->
      <a-entity position="0 2.25 -15" particle-system="preset:star; color: #f216b0,#f24535"></a-entity>
      <!-- <a-entity position="0 2.25 -15" particle-system="preset: dust; particleCount: 10000"></a-entity> -->
      <!-- <a-entity position="0 2.25 -15" particle-system="preset: snow; particleCount: 5000"></a-entity> -->
      <!-- <a-entity position="0 2.25 -15" particle-system="preset: rain"></a-entity> -->
      <a-sky color="#000000"></a-sky>
    </a-scene>
  </body>
</html>