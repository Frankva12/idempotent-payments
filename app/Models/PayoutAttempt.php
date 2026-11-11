<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $payout_id
 * @property AttemptStatus $status
 * @property string|null $provider_transfer_id
 * @property string|null $detail
 * @property-read Payout $payout
 */
#[Fillable(['status', 'provider_transfer_id', 'detail'])]
class PayoutAttempt extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Payout, $this>
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }
}
