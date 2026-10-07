<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'query.sesi' => \App\Http\Middleware\QueryDiSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Form biasa dikirim setelah session habis (CSRF tidak cocok) → login ulang, bukan halaman 419.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getPrevious() instanceof TokenMismatchException && ! $request->expectsJson() && ! $request->is('api/*')) {
                return redirect()->route('sesi.habis');
            }
        });
    })->create();
