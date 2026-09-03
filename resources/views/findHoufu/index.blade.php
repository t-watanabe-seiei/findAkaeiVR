<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>seieiVR FIND BUCCHI</title>
    <script src="{{ asset('js/aframe.min.js') }}"></script>
    <script src="{{ asset('js/aframe-particle-system-component.min.js') }}"></script>
    <script src="{{ asset('js/aframe-extras.min.js') }}"></script>

    @include('findHoufu._components')
</head>

<body>
    @include('findHoufu._scene')
</body>

</html>