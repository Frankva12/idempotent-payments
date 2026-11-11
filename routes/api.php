<?php

use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\ProviderWebhookController;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\VerifyProviderSignature;
use Illuminate\Support\Facades\Route;

Route::post('/payouts', [PayoutController::class, 'store'])
    ->middleware(EnsureIdempotency::class)
    ->name('payouts.store');

Route::get('/payouts/{payout}', [PayoutController::class, 'show'])
    ->whereUuid('payout')
    ->name('payouts.show');

Route::post('/webhooks/payout-provider', ProviderWebhookController::class)
    ->middleware(VerifyProviderSignature::class)
    ->name('webhooks.payout-provider');
