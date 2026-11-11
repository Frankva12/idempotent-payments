<?php

namespace App\Jobs;

use App\Models\Payout;
use App\Payouts\PayoutProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPayout implements ShouldQueue
{
    use Queueable;

    // Retries are safe: the processor never sends a payout that already left the pending state.
    public int $tries = 3;

    public function __construct(public readonly string $payoutId) {}

    public function handle(PayoutProcessor $processor): void
    {
        $payout = Payout::find($this->payoutId);

        if ($payout !== null) {
            $processor->process($payout);
        }
    }
}
