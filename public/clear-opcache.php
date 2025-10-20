<?php
// OPcache クリアスクリプト
// 使用後は必ず削除してください！

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache has been reset successfully!\n";
} else {
    echo "OPcache is not enabled.\n";
}

if (function_exists('opcache_invalidate')) {
    $baseDir = dirname(__DIR__);
    $files = [
        $baseDir . '/bootstrap/app.php',
        $baseDir . '/bootstrap/cache/routes-v7.php',
        $baseDir . '/bootstrap/cache/config.php',
        $baseDir . '/routes/web.php',
        $baseDir . '/routes/api.php',
    ];
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            opcache_invalidate($file, true);
            echo "Invalidated: $file\n";
        }
    }
}

echo "\nNow delete this file for security!\n";
echo "rm " . __FILE__ . "\n";
