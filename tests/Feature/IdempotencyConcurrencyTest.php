<?php

use App\Models\IdempotencyKey;
use App\Models\Payout;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('answers 409 while another request holds the same key', function () {
    $payload = ['wallet_id' => 'wal_1', 'amount' => 1000, 'currency' => 'USD'];

    // Hold the key the way an in-flight request would, then fire the duplicate.
    $this->withHeader('Idempotency-Key', 'key-1')->postJson('/api/payouts', $payload)->assertCreated();
    IdempotencyKey::where('key', 'key-1')->update(['response_status' => null, 'response_body' => null]);

    $this->withHeader('Idempotency-Key', 'key-1')
        ->postJson('/api/payouts', $payload)
        ->assertConflict();

    expect(Payout::count())->toBe(1);
});

it('lets the database reject a second claim of the same key', function () {
    IdempotencyKey::create(['key' => 'key-1', 'request_hash' => str_repeat('a', 64)]);

    expect(fn () => DB::transaction(
        fn () => IdempotencyKey::create(['key' => 'key-1', 'request_hash' => str_repeat('a', 64)])
    ))->toThrow(UniqueConstraintViolationException::class);
});

it('claims a key with a single atomic insert', function () {
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        if (str_contains($query->sql, 'idempotency_keys')) {
            $queries[] = $query->sql;
        }
    });

    $this->withHeader('Idempotency-Key', 'key-1')
        ->postJson('/api/payouts', ['wallet_id' => 'wal_1', 'amount' => 1000, 'currency' => 'USD'])
        ->assertCreated();

    expect($queries[0])->toStartWith('insert')->toContain('on conflict do nothing');
});
