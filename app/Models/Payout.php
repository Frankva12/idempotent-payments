<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use App\Payouts\Exceptions\InvalidPayoutTransition;
use Database\Factories\PayoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $wallet_id
 * @property int $amount
 * @property string $currency
 * @property PayoutStatus $status
 * @property string|null $provider_reference
 * @property string|null $failure_reason
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PayoutAttempt> $attempts
 */
#[Fillable(['wallet_id', 'amount', 'currency', 'status', 'provider_reference', 'failure_reason'])]
class Payout extends Model
{
    /** @use HasFactory<PayoutFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PayoutStatus::class,
        ];
    }

    /**
     * @return HasMany<PayoutAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(PayoutAttempt::class);
    }

    public function transitionTo(PayoutStatus $next): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw InvalidPayoutTransition::between($this->status, $next);
        }

        $this->status = $next;
    }
}
