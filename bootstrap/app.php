<?php

use Illuminate\Foundation\Application;
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
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // 400 Bad Request — errores de validación (formato contrato OpenAPI)
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $firstMessage = collect($e->errors())->flatten()->first();
                return response()->json([
                    'timestamp' => now()->toIso8601String(),
                    'status'    => 400,
                    'error'     => 'Bad Request',
                    'message'   => $firstMessage ?? 'Datos inválidos o incompletos',
                    'path'      => '/' . $request->path(),
                ], 400);
            }
        });

        // 401 Unauthenticated (sin token)
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'timestamp' => now()->toIso8601String(),
                    'status'    => 401,
                    'error'     => 'Unauthorized',
                    'message'   => 'Token JWT inválido o expirado',
                    'path'      => '/' . $request->path(),
                ], 401);
            }
        });

    })->create();
