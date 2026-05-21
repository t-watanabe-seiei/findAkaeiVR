<!DOCTYPE html>
<html lang="ja">
@include('ARstampRally202606.head')
<body>
@include('ARstampRally202606.ui')
@include('ARstampRally202606.scene')
@include('ARstampRally202606.aframe-components')
<script>
// ========== スタンプ・LocalStorage管理 ==========
@include('ARstampRally202606.js-stamps')
// ========== 景品交換・UUID管理 ==========
@include('ARstampRally202606.js-prize')
// ========== ポケボール投擲（タップ投げ） ==========
@include('ARstampRally202606.js-throw')
// ========== maker00 ギャラリー機能 ==========
@include('ARstampRally202606.js-gallery')
// ========== 写真・動画撮影 ==========
@include('ARstampRally202606.js-camera')
</script>
@include('ARstampRally202606.js-init')
</body>
</html>
