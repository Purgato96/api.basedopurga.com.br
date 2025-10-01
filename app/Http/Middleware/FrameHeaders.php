<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class FrameHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Remove X-Frame-Options se existir
        $response->headers->remove('X-Frame-Options');

        // Permite iframe apenas de app.interacti.io
        $response->headers->set(
            'Content-Security-Policy',
            "frame-ancestors https://app.interacti.io"
        );

        return $response;
    }
}
