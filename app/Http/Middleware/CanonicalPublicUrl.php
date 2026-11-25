<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rewrites the forwarded host to the public APP_URL host when the request
 * arrives at the origin or through the Worker. A foreign X-Forwarded-Host
 * is dropped. The prefix header is dropped when the path already contains
 * it, so signatures are not doubled.
 */
class CanonicalPublicUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        $public = parse_url((string) config('app.url'));
        $publicHost = strtolower((string) ($public['host'] ?? ''));
        $originHost = strtolower((string) config('app.origin_host'));
        $prefix = '/'.trim((string) config('app.path_prefix', 'novologin'), '/');

        $forwardedHost = strtolower(trim(explode(',', (string) $request->headers->get('X-Forwarded-Host', ''))[0]));
        $hostHeader = strtolower((string) preg_replace('/:\d+$/', '', (string) $request->headers->get('Host', '')));

        $recognized = $publicHost !== '' && (
            $hostHeader === $originHost
            || $hostHeader === $publicHost
            || $forwardedHost === $publicHost
        );

        if ($recognized) {
            $request->headers->set('X-Forwarded-Host', $publicHost);
            $request->headers->set('X-Forwarded-Proto', (string) ($public['scheme'] ?? 'https'));
            if (! empty($public['port'])) {
                $request->headers->set('X-Forwarded-Port', (string) $public['port']);
            }
        } elseif ($forwardedHost !== '') {
            $request->headers->remove('X-Forwarded-Host');
        }

        $forwardedPrefix = '/'.trim((string) $request->headers->get('X-Forwarded-Prefix', ''), '/');
        $path = '/'.ltrim((string) (parse_url((string) $request->server->get('REQUEST_URI', '/'), PHP_URL_PATH) ?? '/'), '/');
        if ($prefix !== '/' && $forwardedPrefix === $prefix && ($path === $prefix || str_starts_with($path, $prefix.'/'))) {
            $request->headers->remove('X-Forwarded-Prefix');
        }

        return $next($request);
    }
}
