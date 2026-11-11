<?php

namespace App\Payouts;

enum ProviderOutcome: string
{
    case Succeeded = 'succeeded';
    case Rejected = 'rejected';
    // The provider may or may not have moved the money: never treat this as a failure.
    case Unknown = 'unknown';
    // Only returned by lookups: the provider has no record of the transfer.
    case NotFound = 'not_found';
}
