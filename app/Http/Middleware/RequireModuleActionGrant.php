<?php

namespace App\Http\Middleware;

use App\Services\AccountAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireModuleActionGrant
{
    public function handle(Request $request, Closure $next, string $module, string $action): Response
    {
        $user = $request->user();
        $verifiedAt = $request->session()->get('auth.mfa_verified_at');

        abort_unless(
            $user !== null
                && is_numeric($verifiedAt)
                && (int) $verifiedAt >= now()->subHours(12)->timestamp
                && app(AccountAccess::class)->allows($user, $module, $action),
            403,
        );

        return $next($request);
    }
}
