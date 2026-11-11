<?php

use App\Http\Controllers\Api\PayoutController;
use Illuminate\Support\Facades\Route;

Route::post('/payouts', [PayoutController::class, 'store'])->name('payouts.store');
Route::get('/payouts/{payout}', [PayoutController::class, 'show'])->name('payouts.show');
