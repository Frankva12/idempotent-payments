<?php

use App\Enums\AttemptStatus;
use App\Enums\PayoutStatus;
use App\Jobs\ProcessPayout;
use App\Models\Payout;
use App\Models\PayoutAttempt;
use App\Payouts\Exceptions\InvalidPayoutTransition;
use App\Payouts\PayoutProcessor;
use App\Payouts\ProviderResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.payout_provider.driver', 'http');
    config()->set('services.payout_provider.url', 'https://provider.test');
});

function processPayout(Payout $payout): Payout
{
    app(PayoutProcessor::class)->process($payout);

    return $payout->fresh();
}

it('completes the payout and sends the attempt id as the reference', function () {
    Http::fake(['*' => Http::response(['id' => 'tr_1', 'status' => 'succeeded'])]);

    $payout = processPayout(Payout::factory()->create());
    $attempt = $payout->attempts()->sole();

    expect($payout->status)->toBe(PayoutStatus::Completed)
        ->and($payout->provider_reference)->toBe('tr_1')
        ->and($attempt->status)->toBe(AttemptStatus::Succeeded);

    Http::assertSent(fn (Request $request) => $request['reference'] === $attempt->id);
});

it('fails the payout when the provider rejects it', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'Wallet is closed']], 422)]);

    $payout = processPayout(Payout::factory()->create());

    expect($payout->status)->toBe(PayoutStatus::Failed)
        ->and($payout->failure_reason)->toBe('Wallet is closed');
});

it('records the attempt before calling the provider', function () {
    $payout = Payout::factory()->create();

    Http::fake(function () use ($payout) {
        // Seen from inside the provider call: the intent is already on record.
        expect($payout->fresh()->status)->toBe(PayoutStatus::InProgress)
            ->and(PayoutAttempt::where('status', AttemptStatus::InProgress)->count())->toBe(1);

        return Http::response(['id' => 'tr_1', 'status' => 'succeeded']);
    });

    processPayout($payout);
});

it('parks the payout in pending verification when the provider times out', function () {
    $calls = 0;
    Http::fake(function () use (&$calls) {
        $calls++;
        throw new ConnectionException('Operation timed out');
    });

    $payout = processPayout(Payout::factory()->create());

    expect($payout->status)->toBe(PayoutStatus::PendingVerification)
        ->and($payout->failure_reason)->toBeNull()
        ->and($payout->attempts()->sole()->status)->toBe(AttemptStatus::Unknown);

    // A retry must not send the money again: only reconciliation can settle it.
    processPayout($payout);
    expect($calls)->toBe(1);
});

it('parks the payout in pending verification on a 5xx', function () {
    Http::fake(['*' => Http::response([], 503)]);

    expect(processPayout(Payout::factory()->create())->status)->toBe(PayoutStatus::PendingVerification);
});

it('calls the provider once even if the job runs twice', function () {
    $calls = 0;
    Http::fake(function () use (&$calls) {
        $calls++;

        return Http::response(['id' => 'tr_1', 'status' => 'succeeded']);
    });

    $payout = Payout::factory()->create();

    (new ProcessPayout($payout->id))->handle(app(PayoutProcessor::class));
    (new ProcessPayout($payout->id))->handle(app(PayoutProcessor::class));

    expect($calls)->toBe(1)
        ->and($payout->attempts()->count())->toBe(1);
});

it('processes a payout end to end through the API', function () {
    Http::fake(['*' => Http::response(['id' => 'tr_1', 'status' => 'succeeded'])]);

    $id = $this->withHeader('Idempotency-Key', 'key-1')
        ->postJson('/api/payouts', ['wallet_id' => 'wal_1', 'amount' => 1000, 'currency' => 'USD'])
        ->assertAccepted()
        ->json('data.id');

    $this->getJson("/api/payouts/{$id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.provider_reference', 'tr_1');
});

it('ignores a verdict that was already applied', function () {
    Http::fake(['*' => Http::response(['id' => 'tr_1', 'status' => 'succeeded'])]);
    $payout = processPayout(Payout::factory()->create());

    app(PayoutProcessor::class)->recordResult($payout->attempts()->sole(), ProviderResult::succeeded('tr_1'));

    expect($payout->fresh()->status)->toBe(PayoutStatus::Completed);
});

it('refuses a verdict that contradicts a final status', function () {
    Http::fake(['*' => Http::response(['id' => 'tr_1', 'status' => 'succeeded'])]);
    $payout = processPayout(Payout::factory()->create());

    app(PayoutProcessor::class)->recordResult($payout->attempts()->sole(), ProviderResult::rejected('Late failure'));
})->throws(InvalidPayoutTransition::class);
