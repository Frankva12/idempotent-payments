<?php

use App\Models\IdempotencyKey;
use App\Models\Payout;

function payoutPayload(array $overrides = []): array
{
    return [
        'wallet_id' => 'wal_123',
        'amount' => 2500,
        'currency' => 'USD',
        ...$overrides,
    ];
}

it('requires an idempotency key', function () {
    $this->postJson('/api/payouts', payoutPayload())
        ->assertStatus(400);

    expect(Payout::count())->toBe(0);
});

it('creates a single payout when the same request is sent twice', function () {
    $first = $this->withHeader('Idempotency-Key', 'key-1')
        ->postJson('/api/payouts', payoutPayload());

    $second = $this->withHeader('Idempotency-Key', 'key-1')
        ->postJson('/api/payouts', payoutPayload());

    $first->assertCreated();
    $second->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect(Payout::count())->toBe(1);
});

it('treats the same payload with keys in a different order as the same request', function () {
    $this->withHeader('Idempotency-Key', 'key-1')
        ->postJson('/api/payouts', ['wallet_id' => 'wal_1', 'amount' => 100, 'currency' => 'USD'])
        ->assertCreated();

    $this->withHeader('Idempotency-Key', 'key-1')
        ->postJson('/api/payouts', ['currency' => 'USD', 'amount' => 100, 'wallet_id' => 'wal_1'])
        ->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true');

    expect(Payout::count())->toBe(1);
});

it('rejects a reused key with a different payload', function () {
    $this->withHeader('Idempotency-Key', 'key-1')
        ->postJson('/api/payouts', payoutPayload(['amount' => 2500]))
        ->assertCreated();

    $this->withHeader('Idempotency-Key', 'key-1')
        ->postJson('/api/payouts', payoutPayload(['amount' => 9900]))
        ->assertUnprocessable();

    expect(Payout::sole()->amount)->toBe(2500);
});

it('creates separate payouts for different keys', function () {
    $this->withHeader('Idempotency-Key', 'key-1')->postJson('/api/payouts', payoutPayload())->assertCreated();
    $this->withHeader('Idempotency-Key', 'key-2')->postJson('/api/payouts', payoutPayload())->assertCreated();

    expect(Payout::count())->toBe(2);
});

it('replays validation errors too, so a retry cannot change the outcome', function () {
    $this->withHeader('Idempotency-Key', 'key-1')
        ->postJson('/api/payouts', payoutPayload(['amount' => -1]))
        ->assertUnprocessable();

    $this->withHeader('Idempotency-Key', 'key-1')
        ->postJson('/api/payouts', payoutPayload(['amount' => -1]))
        ->assertUnprocessable()
        ->assertHeader('Idempotent-Replayed', 'true');
});

it('prunes keys older than the retention window', function () {
    $old = IdempotencyKey::create(['key' => 'old', 'request_hash' => str_repeat('a', 64)]);
    $old->forceFill(['created_at' => now()->subHours(IdempotencyKey::RETENTION_HOURS + 1)])->save();
    IdempotencyKey::create(['key' => 'fresh', 'request_hash' => str_repeat('b', 64)]);

    $this->artisan('model:prune', ['--model' => IdempotencyKey::class])->assertSuccessful();

    expect(IdempotencyKey::pluck('key')->all())->toBe(['fresh']);
});
