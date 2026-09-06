<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Issue #25: 404 / 403 / 500 系のエラーを、Blade の既定エラービューではなく
        // デザイントークンに沿った Inertia ページ(resources/js/Pages/Error.vue)で描画する。
        // デバッグモード有効時(ローカル開発)は Laravel 標準のデバッグ画面を優先するため
        // 何もしない。
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if (app()->hasDebugModeEnabled() || $request->is('api/*') || $request->expectsJson()) {
                return $response;
            }

            if (in_array($response->getStatusCode(), [403, 404, 419, 500, 503], strict: true)) {
                return Inertia::render('Error', ['status' => $response->getStatusCode()])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            }

            return $response;
        });
    })->create();
