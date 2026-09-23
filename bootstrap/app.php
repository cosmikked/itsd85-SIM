<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // so guests are not automatically redirected to a login route 
        $middleware->redirectGuestsTo(fn () => null); 
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // custom error message structure used in all API error responses 
        $error = fn (string $message, int $status, array $errors = []) => response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);

        $exceptions->render(function (ValidationException $e, Request $request) use ($error) {
            if ($request->is('api/*')) {
                return $error('Validation failed.', 422, $e->errors());
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($error) {
            if ($request->is('api/*')) {
                return $error('Unauthenticated.', 401);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($error) {
            if ($request->is('api/*')) {
                return $error('Resource not found.', 404);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) use ($error) {
            if ($request->is('api/*')) {
                return $error($e->getMessage() ?: 'Request failed.', $e->getStatusCode());
            }
        });
    })->create();
