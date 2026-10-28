<!DOCTYPE html>
<html lang="ja">
@include('ARstampRally202611.head')
<body>
@include('ARstampRally202611.ui')
@include('ARstampRally202611.scene')
@include('ARstampRally202611.aframe-components')
<script>
// ========== スタンプ・LocalStorage管理 ==========
@include('ARstampRally202611.js-stamps')
// ========== 景品交換・UUID管理 ==========
@include('ARstampRally202611.js-prize')
// ========== ポケボール投擲（タップ投げ） ==========
@include('ARstampRally202611.js-throw')
// ========== maker00 ギャラリー機能 ==========
@include('ARstampRally202611.js-gallery')
// ========== 写真・動画撮影 ==========
@include('ARstampRally202611.js-camera')
</script>
@include('ARstampRally202611.js-init')
</body>
</html>
