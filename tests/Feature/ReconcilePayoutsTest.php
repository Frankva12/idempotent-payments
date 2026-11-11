<?php

use App\Enums\AttemptStatus;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.payout_provider.driver', 'http');
    config()->set('services.payout_provider.url', 'https://provider.test');
});

function unresolvedPayout(PayoutStatus $status, int $minutesAgo = 10): Payout
{
    $payout = Payout::factory()->status($status)->create();
    $payout->attempts()->create(['status' => AttemptStatus::Unknown]);
    Payout::whereKey($payout->id)->update(['updated_at' => now()->subMinutes($minutesAgo)]);

    return $payout;
}

it('settles payouts according to the provider lookup', function (int $status, array $body, PayoutStatus $expected) {
    Http::fake(['*' => Http::response($body, $status)]);
    $payout = unresolvedPayout(PayoutStatus::PendingVerification);

    $this->artisan('payouts:reconcile')->assertSuccessful();

    expect($payout->fresh()->status)->toBe($expected);
})->with([
    'money arrived' => [200, ['id' => 'tr_1', 'status' => 'succeeded'], PayoutStatus::Completed],
    'rejected' => [200, ['id' => 'tr_1', 'status' => 'failed'], PayoutStatus::Failed],
    'never reached the provider' => [404, [], PayoutStatus::Failed],
    'provider still down' => [503, [], PayoutStatus::PendingVerification],
]);

it('looks the transfer up by the attempt id', function () {
    Http::fake(['*' => Http::response(['id' => 'tr_1', 'status' => 'succeeded'])]);
    $payout = unresolvedPayout(PayoutStatus::PendingVerification);

    $this->artisan('payouts:reconcile')->assertSuccessful();

    Http::assertSent(fn ($request) => $request['reference'] === $payout->attempts()->sole()->id);
});

it('recovers a payout whose worker died mid-call', function () {
    Http::fake(['*' => Http::response(['id' => 'tr_1', 'status' => 'succeeded'])]);
    $payout = unresolvedPayout(PayoutStatus::InProgress);

    $this->artisan('payouts:reconcile')->assertSuccessful();

    expect($payout->fresh()->status)->toBe(PayoutStatus::Completed);
});

it('leaves recent payouts alone, since the call may still be in flight', function () {
    Http::fake();
    $payout = unresolvedPayout(PayoutStatus::InProgress, minutesAgo: 0);

    $this->artisan('payouts:reconcile')->assertSuccessful();

    expect($payout->fresh()->status)->toBe(PayoutStatus::InProgress);
    Http::assertNothingSent();
});

it('never touches settled payouts', function () {
    Http::fake();
    Payout::factory()->status(PayoutStatus::Completed)->create();
    Payout::factory()->status(PayoutStatus::Failed)->create();

    $this->artisan('payouts:reconcile')->assertSuccessful();

    Http::assertNothingSent();
});
