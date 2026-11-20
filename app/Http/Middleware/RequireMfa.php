<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $verifiedAt = $request->session()->get('auth.mfa_verified_at');

        abort_unless(
            $user !== null
                && $user->is_active
                && $user->mfa_enabled_at !== null
                && is_numeric($verifiedAt)
                && (int) $verifiedAt >= now()->subHours(12)->timestamp,
            403,
        );

        return $next($request);
    }
}
