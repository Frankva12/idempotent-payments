<?php

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Payouts\Exceptions\InvalidPayoutTransition;

it('allows only the transitions of the state machine', function (PayoutStatus $from, PayoutStatus $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    'pending → in progress' => [PayoutStatus::Pending, PayoutStatus::InProgress, true],
    'pending → completed' => [PayoutStatus::Pending, PayoutStatus::Completed, false],
    'in progress → completed' => [PayoutStatus::InProgress, PayoutStatus::Completed, true],
    'in progress → failed' => [PayoutStatus::InProgress, PayoutStatus::Failed, true],
    'in progress → pending verification' => [PayoutStatus::InProgress, PayoutStatus::PendingVerification, true],
    'in progress → pending' => [PayoutStatus::InProgress, PayoutStatus::Pending, false],
    'pending verification → completed' => [PayoutStatus::PendingVerification, PayoutStatus::Completed, true],
    'pending verification → failed' => [PayoutStatus::PendingVerification, PayoutStatus::Failed, true],
    'pending verification → pending' => [PayoutStatus::PendingVerification, PayoutStatus::Pending, false],
    'completed → failed' => [PayoutStatus::Completed, PayoutStatus::Failed, false],
    'failed → pending' => [PayoutStatus::Failed, PayoutStatus::Pending, false],
]);

it('marks completed and failed as final', function () {
    expect(PayoutStatus::Completed->isFinal())->toBeTrue()
        ->and(PayoutStatus::Failed->isFinal())->toBeTrue()
        ->and(PayoutStatus::PendingVerification->isFinal())->toBeFalse();
});

it('throws on a forbidden transition', function () {
    $payout = Payout::factory()->make(['status' => PayoutStatus::Completed]);

    $payout->transitionTo(PayoutStatus::Pending);
})->throws(InvalidPayoutTransition::class);
