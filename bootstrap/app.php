<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Modules\Shared\Exceptions\ApiException;
use App\Modules\Shared\Exceptions\ApiExceptionRenderer;
use App\Modules\Shared\Http\Middleware\ForceJsonResponse;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [ForceJsonResponse::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(fn (\Throwable $exception, Request $request) =>
            app(ApiExceptionRenderer::class)->render($exception, $request));
        $exceptions->dontReport([ApiException::class, JWTException::class]);
        $exceptions->dontFlash(['password', 'password_confirmation', 'card_number', 'cvv', 'payment_details']);
    })->create();
