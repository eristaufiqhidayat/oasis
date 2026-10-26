<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureSalesman;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [HandleInertiaRequests::class]);
        $middleware->alias([
            'password.changed' => EnsurePasswordChanged::class,
            'salesman' => EnsureSalesman::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/login');
        $middleware->redirectUsersTo('/home');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        // Aturan bisnis di web: kembali ke form dengan pesan galat (dan error per field bila ada).
        $exceptions->render(function (BusinessRuleException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['error' => ['code' => $e->errorCode, 'message' => $e->getMessage(), 'field' => $e->field]], 422);
            }

            return back()->withInput()->with('galat', $e->getMessage())
                ->withErrors($e->field ? [$e->field => $e->getMessage()] : []);
        });

        // API HP: semua error berbentuk {"error": {"code", "message", "fields"?}}.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $error = fn (string $code, string $message, int $status, array $extra = []) => response()->json(['error' => ['code' => $code, 'message' => $message] + $extra], $status);

            return match (true) {
                $e instanceof AuthenticationException => $error('unauthenticated', 'Sesi habis. Silakan login ulang.', 401),
                $e instanceof ValidationException => $error('validation_failed', $e->validator->errors()->first(), 422, ['fields' => $e->errors()]),
                $e instanceof HttpExceptionInterface => $error(match ($e->getStatusCode()) {
                    403 => 'forbidden', 404 => 'not_found', 429 => 'too_many_requests', default => 'http_error',
                }, match ($e->getStatusCode()) {
                    403 => 'Akun ini tidak boleh memakai fitur tersebut.', 404 => 'Data tidak ditemukan.', 429 => 'Terlalu banyak permintaan. Coba lagi sebentar.', default => 'Permintaan tidak bisa diproses.',
                }, $e->getStatusCode()),
                config('app.debug') => null,
                default => $error('server_error', 'Terjadi gangguan di server. Data tetap tersimpan di HP dan akan dikirim ulang.', 500),
            };
        });
    })->create();
