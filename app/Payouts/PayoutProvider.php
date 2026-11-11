<?php

namespace App\Payouts;

use App\Models\Payout;

interface PayoutProvider
{
    /**
     * Sends the money. $reference must be unique per attempt and is never reused.
     */
    public function send(string $reference, Payout $payout): ProviderResult;

    /**
     * Asks the provider what happened to a transfer previously sent with $reference.
     */
    public function lookup(string $reference): ProviderResult;
}
