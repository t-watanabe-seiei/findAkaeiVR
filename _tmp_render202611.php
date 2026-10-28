<?php
// 一時的な検証スクリプト: /stamp202611 の描画 HTML と JS 構文を確認する（完了後に削除）
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/stamp202611', 'GET');
$response = $kernel->handle($request);

$html = $response->getContent();
$status = $response->getStatusCode();
file_put_contents(__DIR__ . '/_render202611.html', $html);

// <script>（src なし）を抽出して JS 構文チェック用のファイルへ
preg_match_all('/<script(?![^>]*\bsrc=)[^>]*>(.*?)<\/script>/s', $html, $m);
foreach ($m[1] as $i => $js) {
    file_put_contents(__DIR__ . "/_render202611_script{$i}.js", $js);
}

echo 'status=' . $status . PHP_EOL;
echo 'html_bytes=' . strlen($html) . PHP_EOL;
echo 'script_blocks=' . count($m[1]) . PHP_EOL;
foreach (['pending-scans', 'gacha-buttons', 'voucher-apply-button', 'voucher-email', 'var VOUCHER_THRESHOLD   = 10;', 'var CANDY_TIERS         = [5,10,15,20];', "APPLY_DEADLINE_ISO  = '2026-11-16 23:59:59'"] as $needle) {
    echo (strpos($html, $needle) !== false ? 'OK  ' : 'MISS') . ' ' . $needle . PHP_EOL;
}
foreach (['exchange-prize-button', 'updatePrizeButton', 'exchangePrize(', 'PRIZE_EXCHANGE_THRESHOLD', 'checkPrizeExchangeStatus'] as $gone) {
    echo (strpos($html, $gone) === false ? 'CLEAN ' : 'LEFT  ') . $gone . PHP_EOL;
}
