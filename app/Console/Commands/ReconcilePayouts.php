<?php

namespace App\Console\Commands;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Payouts\Exceptions\InvalidPayoutTransition;
use App\Payouts\PayoutProcessor;
use App\Payouts\PayoutProvider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('payouts:reconcile {--stale-after=120 : Seconds a payout must sit untouched before it is checked}')]
#[Description('Ask the provider how unresolved payouts ended and settle them')]
class ReconcilePayouts extends Command
{
    public function handle(PayoutProvider $provider, PayoutProcessor $processor): int
    {
        $cutoff = now()->subSeconds((int) $this->option('stale-after'));
        $tally = ['resolved' => 0, 'still_unknown' => 0, 'conflicts' => 0];

        // In progress for too long means the worker died mid-call; the provider may still have the transfer.
        Payout::query()
            ->whereIn('status', [PayoutStatus::PendingVerification, PayoutStatus::InProgress])
            ->where('updated_at', '<', $cutoff)
            ->with(['attempts' => fn ($query) => $query->latest()])
            ->lazyById()
            ->each(function (Payout $payout) use ($provider, $processor, &$tally): void {
                $attempt = $payout->attempts->first();

                if ($attempt === null) {
                    return;
                }

                $result = $provider->lookup($attempt->id);

                try {
                    $processor->recordResult($attempt, $result);
                } catch (InvalidPayoutTransition $exception) {
                    $tally['conflicts']++;
                    Log::warning('Reconciliation contradicts the payout status', [
                        'payout_id' => $payout->id,
                        'attempt_id' => $attempt->id,
                        'error' => $exception->getMessage(),
                    ]);

                    return;
                }

                $settled = ! in_array($payout->fresh()?->status, [PayoutStatus::PendingVerification, PayoutStatus::InProgress], true);
                $tally[$settled ? 'resolved' : 'still_unknown']++;
            });

        $this->table(['resolved', 'still unknown', 'conflicts'], [array_values($tally)]);

        return self::SUCCESS;
    }
}
