<?php

use App\Models\Payout;
use App\Payouts\ProviderOutcome;
use App\Payouts\Providers\SimulatedPayoutProvider;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->provider = new SimulatedPayoutProvider(Cache::store('array'));
});

it('picks the scenario from the cents', function (int $amount, ProviderOutcome $sent, ProviderOutcome $lookedUp) {
    $payout = Payout::factory()->make(['amount' => $amount]);

    expect($this->provider->send('ref-1', $payout)->outcome)->toBe($sent)
        ->and($this->provider->lookup('ref-1')->outcome)->toBe($lookedUp);
})->with([
    'success' => [1000, ProviderOutcome::Succeeded, ProviderOutcome::Succeeded],
    'timeout after the money moved' => [1013, ProviderOutcome::Unknown, ProviderOutcome::Succeeded],
    'rejection' => [1099, ProviderOutcome::Rejected, ProviderOutcome::Rejected],
]);

it('does not know transfers it never received', function () {
    expect($this->provider->lookup('missing')->outcome)->toBe(ProviderOutcome::NotFound);
});
