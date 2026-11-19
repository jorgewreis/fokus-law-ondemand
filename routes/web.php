<?php

use App\Http\Controllers\AccountRequestController;
use App\Http\Controllers\InstitutionalContactController;
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
