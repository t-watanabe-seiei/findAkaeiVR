<!DOCTYPE html>
<html lang="ja">
@include('ARstampRally202610.head')
<body>
@include('ARstampRally202610.ui')
@include('ARstampRally202610.scene')
@include('ARstampRally202610.aframe-components')
<script>
// ========== スタンプ・LocalStorage管理 ==========
@include('ARstampRally202610.js-stamps')
// ========== 景品交換・UUID管理 ==========
@include('ARstampRally202610.js-prize')
// ========== ポケボール投擲（タップ投げ） ==========
@include('ARstampRally202610.js-throw')
// ========== maker00 ギャラリー機能 ==========
@include('ARstampRally202610.js-gallery')
// ========== 写真・動画撮影 ==========
@include('ARstampRally202610.js-camera')
</script>
@include('ARstampRally202610.js-init')
</body>
</html>
