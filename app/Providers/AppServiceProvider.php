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

        // ARstampRally202611 専用のレートリミッター。
        // セッションキーは 202611 固有の ar_fingerprint_202611（202610 と分離・AC-3）。
        $key202611 = fn (Request $request) => $request->session()->get('ar_fingerprint_202611') ?: $request->ip();

        RateLimiter::for('stamp202611_scan', fn (Request $request) => Limit::perMinute(120)->by($key202611($request)));
        RateLimiter::for('stamp202611_check', fn (Request $request) => Limit::perMinute(120)->by($key202611($request)));
        RateLimiter::for('stamp202611_apply', fn (Request $request) => Limit::perHour(10)->by($key202611($request)));
        // 確認コード送信（FR-61）: 1 端末（fingerprint）あたり 1 時間 N 回まで。
        // 打ち間違いでコードが届かなかった場合は「アドレスを直して送り直す」操作で複数回使うため、
        // 3 回だと正常な再試行が 429 で止まる（既定 6 回・AR202611_EMAIL_CODE_LIMIT_PER_HOUR で調整）。
        // 総当たりは 1 コードあたりの入力試行上限（max_attempts）で止まるため、送信回数の上限は緩めてもよい。
        $emailCodeLimit = max(1, (int) config('ar202611.email_verification.code_send_limit_per_hour', 6));

        RateLimiter::for('stamp202611_email_code', fn (Request $request) => Limit::perHour($emailCodeLimit)->by($key202611($request)));
        // 応募後のメールアドレス変更（FR-63）: 変更用コード送信は 1 時間同じ上限、変更確定は 1 時間 10 回まで
        RateLimiter::for('stamp202611_email_change_code', fn (Request $request) => Limit::perHour($emailCodeLimit)->by($key202611($request)));
        RateLimiter::for('stamp202611_email_change', fn (Request $request) => Limit::perHour(10)->by($key202611($request)));
    }
}
