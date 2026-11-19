<?php

use App\Services\AccountRequestLifecycle;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('account-requests:expire', function (AccountRequestLifecycle $lifecycle): void {
    $expired = $lifecycle->expirePending();

    $this->info("Expired {$expired} account requests.");
})->purpose('Expire old synthetic account requests and remove their personal data')->hourly();
