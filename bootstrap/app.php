<?php

use App\Http\Middleware\CanonicalPublicUrl;
use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\NoIndex;
use App\Http\Middleware\RequireMfa;
use App\Http\Middleware\RequireModuleActionGrant;
use App\Http\Middleware\RequirePendingMfaEnrollment;
use App\Http\Middleware\RequireSyntheticAdministrator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(CanonicalPublicUrl::class);
        $middleware->append(NoIndex::class);
        $middleware->append(ContentSecurityPolicy::class);
        $middleware->redirectGuestsTo(fn (Request $request) => route('login.create'));
        $middleware->alias([
            'mfa' => RequireMfa::class,
            'synthetic.admin' => RequireSyntheticAdministrator::class,
            'pending.mfa' => RequirePendingMfaEnrollment::class,
            'grant' => RequireModuleActionGrant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
