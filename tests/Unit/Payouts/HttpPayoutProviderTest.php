<?php

use App\Models\Payout;
use App\Payouts\ProviderOutcome;
use App\Payouts\Providers\HttpPayoutProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->provider = new HttpPayoutProvider('https://provider.test', 'secret-key', 5);
    $this->payout = Payout::factory()->make(['wallet_id' => 'wal_1', 'amount' => 1500]);
});

it('sends the attempt reference and returns success', function () {
    Http::fake(['provider.test/transfers' => Http::response(['id' => 'tr_1', 'status' => 'succeeded'])]);

    $result = $this->provider->send('ref-1', $this->payout);

    expect($result->outcome)->toBe(ProviderOutcome::Succeeded)
        ->and($result->transferId)->toBe('tr_1');

    Http::assertSent(fn (Request $request) => $request['reference'] === 'ref-1'
        && $request['amount'] === 1500
        && $request->hasHeader('Authorization', 'Bearer secret-key'));
});

it('maps a 4xx to a rejection with the provider message', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'Wallet is closed']], 422)]);

    $result = $this->provider->send('ref-1', $this->payout);

    expect($result->outcome)->toBe(ProviderOutcome::Rejected)
        ->and($result->reason)->toBe('Wallet is closed');
});

it('treats a timeout as unknown, not as a failure', function () {
    Http::fake(fn () => throw new ConnectionException('Operation timed out'));

    expect($this->provider->send('ref-1', $this->payout)->outcome)->toBe(ProviderOutcome::Unknown);
});

it('treats a 5xx as unknown and keeps the transfer id when present', function () {
    Http::fake(['*' => Http::response(['id' => 'tr_9'], 502)]);

    $result = $this->provider->send('ref-1', $this->payout);

    expect($result->outcome)->toBe(ProviderOutcome::Unknown)
        ->and($result->transferId)->toBe('tr_9');
});

it('treats a duplicate reference as unknown', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'Duplicate reference']], 409)]);

    expect($this->provider->send('ref-1', $this->payout)->outcome)->toBe(ProviderOutcome::Unknown);
});

it('treats a transfer still processing as unknown', function () {
    Http::fake(['*' => Http::response(['id' => 'tr_1', 'status' => 'processing'])]);

    expect($this->provider->send('ref-1', $this->payout)->outcome)->toBe(ProviderOutcome::Unknown);
});

it('looks up a transfer by reference', function (int $status, array $body, ProviderOutcome $expected) {
    Http::fake(['provider.test/transfers*' => Http::response($body, $status)]);

    expect($this->provider->lookup('ref-1')->outcome)->toBe($expected);

    Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request['reference'] === 'ref-1');
})->with([
    'succeeded' => [200, ['id' => 'tr_1', 'status' => 'succeeded'], ProviderOutcome::Succeeded],
    'failed' => [200, ['id' => 'tr_1', 'status' => 'failed', 'failure_reason' => 'Closed'], ProviderOutcome::Rejected],
    'never received' => [404, [], ProviderOutcome::NotFound],
    'provider down' => [503, [], ProviderOutcome::Unknown],
]);
