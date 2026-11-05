<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin.auth' => \App\Http\Middleware\AdminAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 202611 API は fetch() で JSON を読む。HTML エラーページで返すとクライアント側で
        // エラー種別を分岐できず「確認コードを送信できませんでした。」に化ける（FR-61 / FR-63）。

        // レートリミット超過（429）を JSON で返す（Retry-After を waitSeconds として渡す）。
        // Exceptions::throttle() は「例外のレポート回数」を抑える API なので 429 の応答には使わない。
        $exceptions->render(function (ThrottleRequestsException $e, $request) {
            if (!$request->is('stamp202611/*')) {
                return null;
            }

            $headers = $e->getHeaders();
            $wait = isset($headers['Retry-After']) ? (int) $headers['Retry-After'] : 60;

            return response()->json([
                'success' => false,
                'error' => 'too_many',
                'waitSeconds' => max(1, $wait),
                'message' => '送信回数が増えすぎました。少し待ってからもう一度お試しください',
            ], 429);
        });

        // SQLite の "database is locked" を JSON の 503 に変換する（HTML の 500 を避ける）。
        // 原因はセッション書き込み・レートリミットの cache 書き込み・応募行書き込みの競合で、
        // config/database.php の WAL + busy_timeout で解消する（design.md §5.9）。
        $exceptions->render(function (QueryException $e, $request) {
            if (!$request->is('stamp202611/*')) {
                return null;
            }

            if (!str_contains($e->getMessage(), 'database is locked')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'error' => 'server_busy',
                'message' => 'サーバーが混み合っています。10 秒ほどおいてもう一度お試しください',
            ], 503);
        });
    })->create();
