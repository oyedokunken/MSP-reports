<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecureHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Enforce TLS / HSTS
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');
        // XSS Protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        return $response;
    }
}
