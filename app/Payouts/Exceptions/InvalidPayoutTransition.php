<?php

namespace App\Payouts\Exceptions;

use App\Enums\PayoutStatus;
use RuntimeException;

class InvalidPayoutTransition extends RuntimeException
{
    public static function between(PayoutStatus $from, PayoutStatus $to): self
    {
        return new self("A payout cannot move from {$from->value} to {$to->value}.");
    }
}
