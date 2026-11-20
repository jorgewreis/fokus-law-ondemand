<?php

use App\Http\Controllers\AccountActivationController;
use App\Http\Controllers\AccountAdminController;
use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\AccountRecoveryController;
use App\Http\Controllers\AccountRequestController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompetencyAdminController;
use App\Http\Controllers\InstitutionalContactController;
use App\Http\Controllers\MfaEnrollmentController;
use App\Http\Controllers\MfaRecoveryAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/contatos', InstitutionalContactController::class)
    ->name('institutional-contacts.show');

Route::get('/account-requests/create', [AccountRequestController::class, 'create'])
    ->name('account-requests.create');
Route::post('/account-requests', [AccountRequestController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('account-requests.store');
Route::get('/account-requests/{accountRequest}/confirm', [AccountRequestController::class, 'showConfirmation'])
    ->name('account-requests.confirm.show');
Route::post('/account-requests/{accountRequest}/confirm', [AccountRequestController::class, 'confirm'])
    ->middleware('throttle:10,1')
    ->name('account-requests.confirm');

Route::get('/login', [AuthController::class, 'create'])->name('login.create');
Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('pending.mfa')->group(function (): void {
    Route::get('/mfa/enroll', [MfaEnrollmentController::class, 'create'])->name('mfa-enrollment.create');
    Route::post('/mfa/enroll', [MfaEnrollmentController::class, 'store'])->middleware('throttle:5,1')->name('mfa-enrollment.store');
});

Route::get('/account-activation', [AccountActivationController::class, 'show'])->name('account-activation.show');
Route::post('/account-activation', [AccountActivationController::class, 'store'])->middleware('throttle:5,1')->name('account-activation.store');

Route::get('/account-recovery', [AccountRecoveryController::class, 'create'])->name('account-recovery.create');
Route::post('/account-recovery', [AccountRecoveryController::class, 'store'])->middleware('throttle:5,1')->name('account-recovery.store');

Route::get('/password/forgot', [AccountPasswordController::class, 'requestForm'])->name('account-password.request');
Route::post('/password/forgot', [AccountPasswordController::class, 'sendLink'])->middleware('throttle:3,1')->name('account-password.email');
Route::get('/password/reset', [AccountPasswordController::class, 'resetForm'])->name('password.reset');
Route::post('/password/reset', [AccountPasswordController::class, 'update'])->middleware('throttle:5,1')->name('account-password.update');

Route::prefix('/admin/accounts')->name('account-admin.')->middleware(['auth', 'mfa', 'synthetic.admin'])->group(function (): void {
    Route::get('/', [AccountAdminController::class, 'index'])->name('index');
    Route::post('/requests/{accountRequest}/approve', [AccountAdminController::class, 'approve'])->name('approve');
    Route::post('/requests/{accountRequest}/reject', [AccountAdminController::class, 'reject'])->name('reject');
    Route::post('/users/{user}/grants', [AccountAdminController::class, 'grants'])->name('grants');
    Route::post('/users/{user}/invite', [AccountAdminController::class, 'reissue'])->name('reissue');
});

Route::post('/admin/accounts/mfa-recovery/{recoveryRequest}/approve', [MfaRecoveryAdminController::class, 'approve'])
    ->middleware(['auth', 'mfa', 'synthetic.admin'])
    ->name('mfa-recovery.approve');

Route::prefix('/admin/competencies')->name('competency-admin.')->middleware(['auth', 'mfa', 'synthetic.admin'])->group(function (): void {
    Route::get('/', [CompetencyAdminController::class, 'index'])->name('index');
    Route::post('/catalog', [CompetencyAdminController::class, 'import'])->name('import');
    Route::post('/settings', [CompetencyAdminController::class, 'settings'])->name('settings');
    Route::post('/users/{user}/grants', [CompetencyAdminController::class, 'grant'])->name('grant');
});
