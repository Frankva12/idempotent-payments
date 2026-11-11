<?php

use App\Http\Controllers\Api\PayoutController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

Route::post('/payouts', [PayoutController::class, 'store'])
    ->middleware(EnsureIdempotency::class)
    ->name('payouts.store');

Route::get('/payouts/{payout}', [PayoutController::class, 'show'])->name('payouts.show');
