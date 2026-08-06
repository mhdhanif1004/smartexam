<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectLocalhost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests()) {
            return $next($request);
        }

        if ($request->getHost() === 'localhost') {
            $url = str_replace('://localhost', '://127.0.0.1', $request->fullUrl());

            return redirect()->away($url, 301);
        }

        return $next($request);
    }
}
