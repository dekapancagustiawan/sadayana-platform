<?php

use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // Header keamanan dasar untuk semua respons (web & API).
        $middleware->append(SecurityHeaders::class);

        // Apply the visitor's session-selected language (id/en) on every
        // web request — see App\Http\Middleware\SetLocale.
        $middleware->web(append: [
            SetLocale::class,
        ]);

        // Otorisasi route memakai middleware bawaan `can:<permission>` (lewat
        // Gate, sehingga Admin tetap superuser via Gate::before).
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render authentication exceptions as JSON for API routes
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated. Please provide a valid API token.',
                ], 401);
            }

            // Permintaan web dari pengunjung yang belum masuk TIDAK dialihkan
            // diam-diam ke form login: pengalihan itu memberi tahu penebak URL
            // bahwa halamannya benar-benar ada. Yang muncul adalah halaman 401
            // yang sama dengan halaman error lain, tanpa tautan ke form login.
            //
            // Tujuan semula tetap disimpan supaya staf yang sesinya habis di
            // halaman dalam tetap kembali ke sana setelah login lewat alamat
            // login (ADMIN_LOGIN_PATH).
            if ($request->isMethod('GET') && ! $request->routeIs('login')) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return response()->view('errors.401', [], 401);
        });

        // Render authorization exceptions as JSON for API routes
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden. You do not have access to this resource.',
                ], 403);
            }
        });

        // Render validation exceptions as JSON for API routes
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });
    })->create();
