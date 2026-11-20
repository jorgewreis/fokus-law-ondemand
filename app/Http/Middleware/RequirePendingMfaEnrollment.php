<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePendingMfaEnrollment
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->session()->get('mfa_setup.user_id');
        $expiresAt = $request->session()->get('mfa_setup.expires_at');

        abort_unless(is_numeric($userId) && is_numeric($expiresAt) && (int) $expiresAt > now()->timestamp, 403);

        $user = User::query()->find($userId);
        abort_unless($user !== null && $user->mfa_enabled_at === null, 403);

        $request->attributes->set('pending_mfa_user', $user);

        return $next($request);
    }
}
