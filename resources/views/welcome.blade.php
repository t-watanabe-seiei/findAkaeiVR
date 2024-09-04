<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Livewire</title>
    @livewireStyles
    @script
    <script src="https://aframe.io/releases/1.4.2/aframe.min.js"></script>
    <script>
      console.log("@@@");
    </script>
    @script
</head>
<body>
<h1>Hello Livewire</h1>
<livewire:counter>

  <a-scene>
    <a-box position="-1 0.5 -3" rotation="0 45 0" color="#4CC3D9"></a-box>
    <a-text position="0 1.25 -5" value="Hello, World!" color="#EF2D5E"></a-text>
    <a-sky color="#ECECEC"></a-sky>
  </a-scene>

    @livewireScripts
</body>
</html>
