<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;

Route::get('/welcome', function () {
    return view('welcome0');
});

Route::get('/vr', function () {
    return view('findakaei');
});

Route::get('/train', function () {
    return view('train');
});

Route::match(['get', 'head'], '/cute', function () {
    return view('shooting3DModel2');
})->name('home.index');

Route::match(['get', 'head'], '/cute3', function () {
    return view('shooting3DModel3');
})->name('home.index');

Route::match(['get', 'head'], '/stamp', function () {
    return view('ARstampRally');
})->name('stamp.index');

Route::match(['get', 'head'], '/stamp202603', function () {
    return view('ARstampRally202603');
})->name('stamp.index');

Route::match(['get', 'head'], '/stamp202605', function () {
    return view('ARstampRally202605');
})->name('stamp202605.index');

Route::match(['get', 'head'], '/number', function () {
    return view('ARstampNumber');
})->name('stamp.index');

Route::match(['get', 'head'], '/animal', function () {
    return view('shooting3Danimal');
})->name('animal.index');

Route::match(['get', 'head'], '/animal3', function () {
    return view('shooting3Danimal3');
})->name('animal.index');

Route::match(['get', 'head'], '/terrer', function () {
    return view('shooting3Dterrer2');
})->name('terrer.index');

Route::match(['get', 'head'], '/terrer3', function () {
    return view('shooting3Dterrer3');
})->name('terrer.index');

Route::match(['get', 'head'], '/insect', function () {
    return view('shooting3DInsect');
})->name('insect.index');

Route::match(['get', 'head'], '/t-watanabe', function () {
    return view('ARmeishi');
})->name('insect.index');

Route::get('/minion', function () {
    return view('findminion');
});

Route::get('/movie', function () {
    return view('movieTest');
});

Route::get('/vr-tunnel', function () {
    return view('vr-tunnel');
})->name('vr.tunnel');

Route::get('/vr-center-dark', function () {
    return view('vr-center-dark');
})->name('vr.center.dark');

// Auth::routes(); // Commented out - laravel/ui not installed

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

// 管理画面ルート
Route::prefix('admin')->group(function () {
    // ログイン関連（認証不要）
    Route::get('/login', [AdminController::class, 'showLogin'])->name('admin.login');
    Route::post('/login', [AdminController::class, 'login'])->name('admin.login.post');
    
    // 認証が必要なルート
    Route::middleware('admin.auth')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
        Route::get('/logout', [AdminController::class, 'logout'])->name('admin.logout');
        Route::post('/prizes/{id}/redeem', [AdminController::class, 'redeemPrize'])->name('admin.prizes.redeem');
        Route::get('/exchanges', [AdminController::class, 'allExchanges'])->name('admin.exchanges');
        Route::get('/scans', [AdminController::class, 'allScans'])->name('admin.scans');
        // CSV エクスポート（dataset: exchanges|redeemed|scans|daily|markerStats, start/end optional)
        Route::get('/export', [AdminController::class, 'exportCsv'])->name('admin.export');
        // ARstampRally202603用のダッシュボード（パンダマーカーの統計）
        Route::get('/dashboard202603', [AdminController::class, 'dashboard202603'])->name('admin.dashboard202603');
        // ARstampRally202605用のダッシュボード
        Route::get('/dashboard202605', [AdminController::class, 'dashboard202605'])->name('admin.dashboard202605');
    });
});
