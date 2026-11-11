<?php

use App\Enums\PayoutStatus;
use App\Models\Payout;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withHeader('Idempotency-Key', (string) Str::uuid());
});

it('creates a pending payout with the amount in cents', function () {
    $response = $this->postJson('/api/payouts', [
        'wallet_id' => 'wal_123',
        'amount' => 1050,
        'currency' => 'USD',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.amount', 1050)
        ->assertJsonPath('data.status', PayoutStatus::Pending->value);

    expect(Payout::sole())
        ->wallet_id->toBe('wal_123')
        ->amount->toBe(1050)
        ->status->toBe(PayoutStatus::Pending);
});

it('rejects invalid amounts', function (mixed $amount) {
    $this->postJson('/api/payouts', [
        'wallet_id' => 'wal_123',
        'amount' => $amount,
        'currency' => 'USD',
    ])->assertUnprocessable()->assertJsonValidationErrors('amount');

    expect(Payout::count())->toBe(0);
})->with([
    'negative' => -100,
    'zero' => 0,
    'decimal' => 10.5,
    'text' => 'ten',
    'above the limit' => 1_000_001,
]);

it('rejects unsupported currencies', function () {
    $this->postJson('/api/payouts', [
        'wallet_id' => 'wal_123',
        'amount' => 1000,
        'currency' => 'EUR',
    ])->assertUnprocessable()->assertJsonValidationErrors('currency');
});

it('shows a payout', function () {
    $payout = Payout::factory()->create();

    $this->getJson("/api/payouts/{$payout->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $payout->id);
});
