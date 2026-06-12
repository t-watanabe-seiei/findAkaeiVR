<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>seieiVR Halloween4</title>
    <script src="{{ asset('js/aframe.min.js') }}"></script>
    <script src="{{ asset('js/aframe-particle-system-component.min.js') }}"></script>
    <script src="{{ asset('js/aframe-extras.min.js') }}"></script>
    <script src="{{ asset('js/axios.min.js') }}"></script>

    @include('shooting3Dhalloween4._components')
</head>

<body>
    @include('shooting3Dhalloween4._scene')
</body>

</html>
