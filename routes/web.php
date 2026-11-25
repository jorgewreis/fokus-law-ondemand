<?php

use App\Http\Controllers\AccountActivationController;
use App\Http\Controllers\AccountAdminController;
use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\AccountRecoveryController;
use App\Http\Controllers\AccountRequestController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompetencyAdminController;
use App\Http\Controllers\CourtProcessController;
use App\Http\Controllers\InstitutionalContactController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MfaEnrollmentController;
use App\Http\Controllers\MfaRecoveryAdminController;
use App\Http\Controllers\PersonAccountLinkAdminController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\ProcessSecrecyAdminController;
use App\Http\Controllers\SecrecyAdminController;
use App\Http\Controllers\UnitAdminController;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

$register = function (bool $shouldName): void {
    $name = function ($route, string $routeName) use ($shouldName) {
        if ($shouldName) {
            $route->name($routeName);
        }

        return $route;
    };

    $name(Route::get('/', LandingController::class), 'home');

    if (! $shouldName) {
        Route::get('/up', function () {
            try {
                Event::dispatch(new DiagnosingHealth);
            } catch (Throwable $exception) {
                report($exception);

                return response('unhealthy', 500);
            }

            return response('ok', 200);
        });
    }

    $name(Route::get('/robots.txt', function () {
        return response("User-agent: *\nDisallow: /\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }), 'robots');

    $name(Route::get('/contatos', InstitutionalContactController::class), 'institutional-contacts.show');

    $name(Route::get('/account-requests/create', [AccountRequestController::class, 'create']), 'account-requests.create');
    $name(Route::post('/account-requests', [AccountRequestController::class, 'store'])->middleware('throttle:5,1'), 'account-requests.store');
    $name(Route::get('/account-requests/{accountRequest}/confirm', [AccountRequestController::class, 'showConfirmation']), 'account-requests.confirm.show');
    $name(Route::post('/account-requests/{accountRequest}/confirm', [AccountRequestController::class, 'confirm'])->middleware('throttle:10,1'), 'account-requests.confirm');

    $name(Route::get('/login', [AuthController::class, 'create']), 'login.create');
    $name(Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1'), 'login.store');
    $name(Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth'), 'logout');

    Route::middleware('pending.mfa')->group(function () use ($name): void {
        $name(Route::get('/mfa/enroll', [MfaEnrollmentController::class, 'create']), 'mfa-enrollment.create');
        $name(Route::post('/mfa/enroll', [MfaEnrollmentController::class, 'store'])->middleware('throttle:5,1'), 'mfa-enrollment.store');
    });

    $name(Route::get('/account-activation', [AccountActivationController::class, 'show']), 'account-activation.show');
    $name(Route::post('/account-activation', [AccountActivationController::class, 'store'])->middleware('throttle:5,1'), 'account-activation.store');

    $name(Route::get('/account-recovery', [AccountRecoveryController::class, 'create']), 'account-recovery.create');
    $name(Route::post('/account-recovery', [AccountRecoveryController::class, 'store'])->middleware('throttle:5,1'), 'account-recovery.store');

    $name(Route::get('/password/forgot', [AccountPasswordController::class, 'requestForm']), 'account-password.request');
    $name(Route::post('/password/forgot', [AccountPasswordController::class, 'sendLink'])->middleware('throttle:3,1'), 'account-password.email');
    $name(Route::get('/password/reset', [AccountPasswordController::class, 'resetForm']), 'password.reset');
    $name(Route::post('/password/reset', [AccountPasswordController::class, 'update'])->middleware('throttle:5,1'), 'account-password.update');

    Route::prefix('/people')->name($shouldName ? 'people.' : '')->middleware(['auth', 'mfa'])->group(function () use ($shouldName): void {
        $route = function ($definition, string $routeName) use ($shouldName) {
            return $shouldName ? $definition->name($routeName) : $definition;
        };

        $route(Route::post('/address/lookup', [PersonController::class, 'lookupAddress'])->middleware('throttle:10,1'), 'address.lookup');
        $route(Route::get('/me/contacts/edit', [PersonController::class, 'editOwnContacts']), 'own-contacts.edit');
        $route(Route::put('/me/contacts', [PersonController::class, 'updateOwnContacts']), 'own-contacts.update');
        $route(Route::post('/me/contacts/address/lookup', [PersonController::class, 'lookupOwnAddress'])->middleware('throttle:10,1'), 'own-contacts.address.lookup');
        $route(Route::post('/{personId}/address/lookup', [PersonController::class, 'lookupAddressForPerson'])->whereNumber('personId')->middleware('throttle:10,1'), 'person.address.lookup');
        $route(Route::get('/', [PersonController::class, 'index']), 'index');
        $route(Route::post('/search', [PersonController::class, 'search']), 'search');
        $route(Route::get('/create', [PersonController::class, 'create']), 'create');
        $route(Route::post('/', [PersonController::class, 'store']), 'store');
        $route(Route::get('/{personId}/edit', [PersonController::class, 'edit'])->whereNumber('personId'), 'edit');
        $route(Route::get('/{personId}', [PersonController::class, 'show'])->whereNumber('personId'), 'show');
        $route(Route::put('/{personId}', [PersonController::class, 'update'])->whereNumber('personId'), 'update');
        $route(Route::delete('/{personId}', [PersonController::class, 'destroy'])->whereNumber('personId'), 'destroy');
    });

    Route::prefix('/processes')->name($shouldName ? 'processes.' : '')->middleware(['auth', 'mfa'])->group(function () use ($shouldName): void {
        $route = function ($definition, string $routeName) use ($shouldName) {
            return $shouldName ? $definition->name($routeName) : $definition;
        };

        $route(Route::get('/create', [CourtProcessController::class, 'create'])->middleware('grant:processes,create'), 'create');
        $route(Route::post('/', [CourtProcessController::class, 'store'])->middleware('grant:processes,create'), 'store');
        $route(Route::put('/{processId}/status', [CourtProcessController::class, 'updateStatus'])->whereNumber('processId'), 'status.update');
        $route(Route::get('/{processId}', [CourtProcessController::class, 'show'])->whereNumber('processId'), 'show');
    });

    Route::prefix('/admin/accounts')->name($shouldName ? 'account-admin.' : '')->middleware(['auth', 'mfa', 'synthetic.admin'])->group(function () use ($shouldName): void {
        $route = function ($definition, string $routeName) use ($shouldName) {
            return $shouldName ? $definition->name($routeName) : $definition;
        };

        $route(Route::get('/', [AccountAdminController::class, 'index']), 'index');
        $route(Route::post('/requests/{accountRequest}/approve', [AccountAdminController::class, 'approve']), 'approve');
        $route(Route::post('/requests/{accountRequest}/reject', [AccountAdminController::class, 'reject']), 'reject');
        $route(Route::post('/users/{user}/grants', [AccountAdminController::class, 'grants']), 'grants');
        $route(Route::post('/users/{user}/invite', [AccountAdminController::class, 'reissue']), 'reissue');
    });

    $name(Route::post('/admin/accounts/mfa-recovery/{recoveryRequest}/approve', [MfaRecoveryAdminController::class, 'approve'])->middleware(['auth', 'mfa', 'synthetic.admin']), 'mfa-recovery.approve');

    Route::prefix('/admin/competencies')->name($shouldName ? 'competency-admin.' : '')->middleware(['auth', 'mfa', 'synthetic.admin'])->group(function () use ($shouldName): void {
        $route = function ($definition, string $routeName) use ($shouldName) {
            return $shouldName ? $definition->name($routeName) : $definition;
        };

        $route(Route::get('/', [CompetencyAdminController::class, 'index']), 'index');
        $route(Route::post('/catalog', [CompetencyAdminController::class, 'import']), 'import');
        $route(Route::post('/settings', [CompetencyAdminController::class, 'settings']), 'settings');
        $route(Route::post('/users/{user}/grants', [CompetencyAdminController::class, 'grant']), 'grant');
    });

    Route::prefix('/admin/secrecy')->name($shouldName ? 'secrecy-admin.' : '')->middleware(['auth', 'mfa', 'synthetic.admin'])->group(function () use ($shouldName): void {
        $route = function ($definition, string $routeName) use ($shouldName) {
            return $shouldName ? $definition->name($routeName) : $definition;
        };

        $route(Route::get('/', [SecrecyAdminController::class, 'index']), 'index');
        $route(Route::post('/users/{user}/grants', [SecrecyAdminController::class, 'grant']), 'grant');
    });

    Route::prefix('/admin/process-secrecy')->name($shouldName ? 'process-secrecy-admin.' : '')->middleware(['auth', 'mfa', 'synthetic.admin'])->group(function () use ($shouldName): void {
        $route = function ($definition, string $routeName) use ($shouldName) {
            return $shouldName ? $definition->name($routeName) : $definition;
        };

        $route(Route::get('/', [ProcessSecrecyAdminController::class, 'index']), 'index');
        $route(Route::put('/{processId}', [ProcessSecrecyAdminController::class, 'update'])->whereNumber('processId'), 'update');
    });

    Route::prefix('/admin/person-account-links')->name($shouldName ? 'person-account-links.' : '')->middleware(['auth', 'mfa', 'synthetic.admin'])->group(function () use ($shouldName): void {
        $route = function ($definition, string $routeName) use ($shouldName) {
            return $shouldName ? $definition->name($routeName) : $definition;
        };

        $route(Route::get('/', [PersonAccountLinkAdminController::class, 'index']), 'index');
        $route(Route::post('/', [PersonAccountLinkAdminController::class, 'store']), 'store');
    });

    Route::prefix('/admin/units')->name($shouldName ? 'unit-admin.' : '')->middleware(['auth', 'mfa', 'synthetic.admin'])->group(function () use ($shouldName): void {
        $route = function ($definition, string $routeName) use ($shouldName) {
            return $shouldName ? $definition->name($routeName) : $definition;
        };

        $route(Route::get('/', [UnitAdminController::class, 'index']), 'index');
        $route(Route::post('/settings', [UnitAdminController::class, 'configure']), 'configure');
        $route(Route::post('/users/{user}/grants', [UnitAdminController::class, 'grant']), 'grant');
    });
};

$register(true);

Route::prefix('novologin')->group(function () use ($register): void {
    $register(false);
});
