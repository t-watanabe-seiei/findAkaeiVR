<!DOCTYPE html>
<html lang="ja">
@include('ARstampRally202609.head')
<body>
@include('ARstampRally202609.ui')
@include('ARstampRally202609.scene')
@include('ARstampRally202609.aframe-components')
<script>
// ========== スタンプ・LocalStorage管理 ==========
@include('ARstampRally202609.js-stamps')
// ========== 景品交換・UUID管理 ==========
@include('ARstampRally202609.js-prize')
// ========== ポケボール投擲（タップ投げ） ==========
@include('ARstampRally202609.js-throw')
// ========== maker00 ギャラリー機能 ==========
@include('ARstampRally202609.js-gallery')
// ========== 写真・動画撮影 ==========
@include('ARstampRally202609.js-camera')
</script>
@include('ARstampRally202609.js-init')
</body>
</html>
