<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ARstampRally202609 専用のレートリミッター。
        // セッション上のフィンガープリント（recordScan で保持）でキー付けし、
        // 未設定の場合はIPにフォールバックする。
        $key = fn (Request $request) => $request->session()->get('ar_fingerprint') ?: $request->ip();

        RateLimiter::for('stamp202609_scan', fn (Request $request) => Limit::perMinute(120)->by($key($request)));
        RateLimiter::for('stamp202609_check', fn (Request $request) => Limit::perMinute(120)->by($key($request)));
        RateLimiter::for('stamp202609_redeem', fn (Request $request) => Limit::perHour(10)->by($key($request)));

        // ARstampRally202610 専用のレートリミッター。
        RateLimiter::for('stamp202610_scan', fn (Request $request) => Limit::perMinute(120)->by($key($request)));
        RateLimiter::for('stamp202610_check', fn (Request $request) => Limit::perMinute(120)->by($key($request)));
        RateLimiter::for('stamp202610_redeem', fn (Request $request) => Limit::perHour(10)->by($key($request)));
    }
}
