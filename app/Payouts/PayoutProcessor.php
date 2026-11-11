<?php

namespace App\Payouts;

use App\Enums\AttemptStatus;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\PayoutAttempt;
use App\Payouts\Exceptions\InvalidPayoutTransition;
use Illuminate\Support\Facades\DB;

class PayoutProcessor
{
    public function __construct(private readonly PayoutProvider $provider) {}

    /**
     * Safe to call any number of times: only a pending payout ever reaches the provider.
     */
    public function process(Payout $payout): void
    {
        $attempt = $this->startAttempt($payout);

        if ($attempt === null) {
            return;
        }

        // Deliberately outside any transaction: a rollback cannot un-send money,
        // and row locks would otherwise be held for the whole network round trip.
        $result = $this->provider->send($attempt->id, $attempt->payout);

        $this->recordResult($attempt, $result);
    }

    /**
     * Applies a verdict from the provider (send response, lookup or webhook).
     * Repeating a verdict is a no-op; contradicting a final one throws.
     *
     * @throws InvalidPayoutTransition
     */
    public function recordResult(PayoutAttempt $attempt, ProviderResult $result): void
    {
        DB::transaction(function () use ($attempt, $result): void {
            $payout = Payout::whereKey($attempt->payout_id)->lockForUpdate()->firstOrFail();
            $attempt = PayoutAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            $target = match ($result->outcome) {
                ProviderOutcome::Succeeded => PayoutStatus::Completed,
                ProviderOutcome::Rejected, ProviderOutcome::NotFound => PayoutStatus::Failed,
                ProviderOutcome::Unknown => PayoutStatus::PendingVerification,
            };

            $alreadyApplied = $payout->status === $target;
            $nothingNewLearned = $result->outcome === ProviderOutcome::Unknown && $payout->status->isFinal();

            if ($alreadyApplied || $nothingNewLearned) {
                return;
            }

            $payout->transitionTo($target);

            if ($target === PayoutStatus::Completed) {
                $payout->provider_reference = $result->transferId;
            }

            if ($target === PayoutStatus::Failed) {
                $payout->failure_reason = $result->reason;
            }

            $payout->save();

            $attempt->update([
                'status' => match ($target) {
                    PayoutStatus::Completed => AttemptStatus::Succeeded,
                    PayoutStatus::Failed => AttemptStatus::Failed,
                    default => AttemptStatus::Unknown,
                },
                'provider_transfer_id' => $result->transferId ?? $attempt->provider_transfer_id,
                'detail' => $result->reason,
            ]);
        });
    }

    /**
     * Commits the intent before any money moves, so a crash or rollback later
     * can never erase the fact that the provider was called.
     */
    private function startAttempt(Payout $payout): ?PayoutAttempt
    {
        return DB::transaction(function () use ($payout): ?PayoutAttempt {
            $locked = Payout::whereKey($payout->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== PayoutStatus::Pending) {
                return null;
            }

            $locked->transitionTo(PayoutStatus::InProgress);
            $locked->save();

            $attempt = $locked->attempts()->create(['status' => AttemptStatus::InProgress]);
            $attempt->setRelation('payout', $locked);

            return $attempt;
        });
    }
}
