<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        // セッションに管理者ログイン情報があるかチェック
        if (!session('admin_authenticated')) {
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
