<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\PreventBackHistoryCache;
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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        $middleware->web(append: [
            PreventBackHistoryCache::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Laravel mengubah TokenMismatchException menjadi HttpException 419
        // ("Page Expired") melalui prepareException SEBELUM render callback
        // dijalankan, sehingga kita mendeteksi 419 di level ini.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || ! $e->getPrevious() instanceof TokenMismatchException) {
                return null;
            }

            if ($request->expectsJson() || $request->is('csrf-token')) {
                $request->session()->regenerateToken();

                return response()->json([
                    'message' => 'Sesi Anda telah kadaluarsa, silakan muat ulang halaman dan login kembali.',
                    'csrf_token' => csrf_token(),
                    'login_url' => route('login'),
                ], 419);
            }

            return redirect()->route('login')
                ->with('error', 'Sesi Anda telah kadaluarsa, silakan login kembali.');
        });
    })->create();
