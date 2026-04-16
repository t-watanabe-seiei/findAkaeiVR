<!DOCTYPE html>
<html lang="ja">
@include('ARstampRally202605.head')
<body>
@include('ARstampRally202605.ui')
@include('ARstampRally202605.scene')
@include('ARstampRally202605.aframe-components')
<script>
// ========== スタンプ・LocalStorage管理 ==========
@include('ARstampRally202605.js-stamps')
// ========== 景品交換・UUID管理 ==========
@include('ARstampRally202605.js-prize')
// ========== ポケボール投擲（タップ投げ） ==========
@include('ARstampRally202605.js-throw')
// ========== maker00 ギャラリー機能 ==========
@include('ARstampRally202605.js-gallery')
// ========== 写真・動画撮影 ==========
@include('ARstampRally202605.js-camera')
</script>
@include('ARstampRally202605.js-init')
</body>
</html>
