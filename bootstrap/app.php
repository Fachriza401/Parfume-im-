<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);

        $middleware->throttleApi('60,1');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Never leak internals: log full detail, return a safe message.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // Expected client-side outcomes are not server errors: reporting them
            // floods the log and makes real problems hard to spot.
            if (! $e instanceof BusinessRuleException
                && ! $e instanceof ValidationException
                && ! $e instanceof AuthenticationException
                && ! $e instanceof AuthorizationException
                && ! $e instanceof ModelNotFoundException
                && ! $e instanceof NotFoundHttpException) {
                report($e);
            }

            return match (true) {
                $e instanceof BusinessRuleException => ApiResponse::error(
                    $e->getMessage(),
                    $e->getStatusCode(),
                    $e->getErrors() + ['code' => $e->getErrorCode()]
                ),

                $e instanceof ValidationException => ApiResponse::error(
                    'Data yang dikirim tidak valid.',
                    422,
                    $e->errors()
                ),

                $e instanceof AuthenticationException => ApiResponse::error(
                    'Autentikasi diperlukan. Silakan login terlebih dahulu.',
                    401
                ),

                $e instanceof AuthorizationException => ApiResponse::error(
                    $e->getMessage() !== '' ? $e->getMessage() : 'Akses ditolak.',
                    403
                ),

                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => ApiResponse::error(
                    'Data yang diminta tidak ditemukan.',
                    404
                ),

                $e instanceof HttpExceptionInterface => ApiResponse::error(
                    $e->getMessage() !== '' ? $e->getMessage() : 'Permintaan tidak valid.',
                    $e->getStatusCode()
                ),

                default => ApiResponse::error(
                    config('app.debug')
                        ? $e->getMessage()
                        : 'Terjadi kesalahan pada server. Silakan coba lagi.',
                    500
                ),
            };
        });
    })->create();
