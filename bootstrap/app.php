<?php

use App\Http\Middleware\CorsMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        \App\Providers\EventServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\SubadminPermissionMiddleware::class,
            'auth' => \App\Http\Middleware\Authenticate::class,
            'force.json' => \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        $middleware->use([
            CorsMiddleware::class,
        ]);

        $middleware->api([
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, $request) {
            // Treat API requests as JSON (either Accept header or URI)
            if ($request->expectsJson() || $request->is('api/*') || str_starts_with($request->path(), 'api')) {

                // Validation errors (422)
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $e->errors(),
                    ], 422);
                }

                // Authentication errors (401)
                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json(['message' => 'Unauthenticated.'], 401);
                }

                // Authorization (403)
                if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    return response()->json(['message' => $e->getMessage() ?: 'Forbidden'], 403);
                }

                // Not found (404)
                if (
                    $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
                    || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
                ) {
                    return response()->json(['message' => 'Not Found'], 404);
                }

                // Http exceptions with status code
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                    $status = $e->getStatusCode();
                    return response()->json(['message' => $e->getMessage() ?: 'Error'], $status);
                }

                // Fallback: generic server error. Include message in debug mode only.
                $status = 500;
                $message = config('app.debug') ? $e->getMessage() : 'Server Error';
                return response()->json(['message' => $message], $status);
            }

            // return null to let the default rendering continue for non-API requests
            return null;
        });
    })
    ->create();
