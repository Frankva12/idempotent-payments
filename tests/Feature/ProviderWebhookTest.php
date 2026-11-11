<?php

use App\Enums\AttemptStatus;
use App\Enums\PayoutStatus;
use App\Http\Middleware\VerifyProviderSignature;
use App\Models\Payout;
use App\Models\PayoutAttempt;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config()->set('services.payout_provider.webhook_secret', 'whsec_test');

    $this->payout = Payout::factory()->status(PayoutStatus::PendingVerification)->create();
    $this->attempt = $this->payout->attempts()->create(['status' => AttemptStatus::Unknown]);
});

function sendWebhook(array $event, ?string $secret = 'whsec_test', ?int $timestamp = null)
{
    $body = json_encode($event);
    $timestamp = (string) ($timestamp ?? now()->timestamp);

    return test()->call('POST', '/api/webhooks/payout-provider', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_PROVIDER_TIMESTAMP' => $timestamp,
        'HTTP_X_PROVIDER_SIGNATURE' => VerifyProviderSignature::sign($timestamp, $body, (string) $secret),
    ], content: $body);
}

function succeededEvent(PayoutAttempt $attempt, string $eventId = 'evt_1'): array
{
    return [
        'id' => $eventId,
        'type' => 'transfer.succeeded',
        'data' => ['reference' => $attempt->id, 'transfer_id' => 'tr_1'],
    ];
}

it('settles a payout awaiting verification', function () {
    sendWebhook(succeededEvent($this->attempt))
        ->assertOk()
        ->assertJsonPath('status', 'processed');

    expect($this->payout->fresh())
        ->status->toBe(PayoutStatus::Completed)
        ->provider_reference->toBe('tr_1');
});

it('fails a payout the provider rejected', function () {
    sendWebhook([
        'id' => 'evt_1',
        'type' => 'transfer.failed',
        'data' => ['reference' => $this->attempt->id, 'failure_reason' => 'Wallet is closed'],
    ])->assertOk();

    expect($this->payout->fresh())
        ->status->toBe(PayoutStatus::Failed)
        ->failure_reason->toBe('Wallet is closed');
});

it('ignores a redelivered event', function () {
    sendWebhook(succeededEvent($this->attempt))->assertJsonPath('status', 'processed');
    sendWebhook(succeededEvent($this->attempt))->assertOk()->assertJsonPath('status', 'duplicate');

    expect(WebhookEvent::count())->toBe(1);
});

it('logs and keeps the status when an event contradicts it', function () {
    Log::spy();
    sendWebhook(succeededEvent($this->attempt, 'evt_1'));

    sendWebhook([
        'id' => 'evt_2',
        'type' => 'transfer.failed',
        'data' => ['reference' => $this->attempt->id],
    ])->assertOk()->assertJsonPath('status', 'conflict');

    expect($this->payout->fresh()->status)->toBe(PayoutStatus::Completed);
    Log::shouldHaveReceived('warning')->once();
});

it('ignores events for unknown references or types', function (array $event) {
    sendWebhook($event)->assertOk()->assertJsonPath('status', 'ignored');
})->with([
    'unknown reference' => [['id' => 'evt_1', 'type' => 'transfer.succeeded', 'data' => ['reference' => 'nope', 'transfer_id' => 'tr_1']]],
    'unknown type' => [['id' => 'evt_1', 'type' => 'balance.updated', 'data' => ['reference' => 'nope']]],
]);

it('rejects an invalid signature', function () {
    sendWebhook(succeededEvent($this->attempt), secret: 'wrong-secret')->assertUnauthorized();

    expect($this->payout->fresh()->status)->toBe(PayoutStatus::PendingVerification);
});

it('rejects a replayed request with an old timestamp', function () {
    $tooOld = now()->subSeconds(VerifyProviderSignature::TOLERANCE_SECONDS + 1)->timestamp;

    sendWebhook(succeededEvent($this->attempt), timestamp: $tooOld)->assertUnauthorized();
});

it('rejects everything when no secret is configured', function () {
    config()->set('services.payout_provider.webhook_secret', '');

    sendWebhook(succeededEvent($this->attempt), secret: '')->assertUnauthorized();
});
