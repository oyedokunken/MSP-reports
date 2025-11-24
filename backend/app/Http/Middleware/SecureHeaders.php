<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecureHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // HSTS - Force HTTPS for 1 year including subdomains
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        
        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // XSS Protection (legacy but still useful for older browsers)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Content Security Policy - Strict policy for enhanced security
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'", // Required for Vite/React
            "style-src 'self' 'unsafe-inline'", // Required for styled components
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self' " . config('app.frontend_url', 'http://localhost:5173'),
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'"
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        // Referrer Policy - Don't leak referrer information
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy - Disable unnecessary browser features
        $permissions = implode(', ', [
            'camera=()',
            'microphone=()',
            'geolocation=()',
            'payment=()',
            'usb=()',
            'magnetometer=()',
            'gyroscope=()',
            'accelerometer=()'
        ]);
        $response->headers->set('Permissions-Policy', $permissions);

        // Remove server identification headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
