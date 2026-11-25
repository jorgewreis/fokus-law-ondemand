<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scripts stay on this origin. Lenis and GSAP are bundled by Vite, so the
 * policy does not allow a CDN or unsafe-eval. style-src keeps unsafe-inline
 * because the existing account pages embed their CSS in the document.
 */
class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'",
            "font-src 'self'",
            "img-src 'self' data:",
            "connect-src 'self'",
        ]));

        return $response;
    }
}
