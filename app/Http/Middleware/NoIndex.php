<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NoIndex
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        $contentType = (string) $response->headers->get('Content-Type', '');
        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return $response;
        }

        $isHtml = str_contains($contentType, 'text/html')
            || str_starts_with(ltrim($content), '<!DOCTYPE')
            || str_starts_with(ltrim($content), '<html');

        if (! $isHtml || preg_match('/name=["\']robots["\']/i', $content) === 1) {
            return $response;
        }

        $updated = preg_replace(
            '/<head\b[^>]*>/i',
            '$0'."\n".'        <meta name="robots" content="noindex, nofollow">',
            $content,
            1,
            $count,
        );

        if (is_string($updated) && $count > 0) {
            $response->setContent($updated);
        }

        return $response;
    }
}
