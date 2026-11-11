<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * @property int $id
 * @property string $key
 * @property string $request_hash
 * @property int|null $response_status
 * @property string|null $response_body
 */
#[Fillable(['key', 'request_hash', 'response_status', 'response_body'])]
class IdempotencyKey extends Model
{
    use Prunable;

    public const RETENTION_HOURS = 24;

    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
        ];
    }

    public function hasResponse(): bool
    {
        return $this->response_status !== null;
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subHours(self::RETENTION_HOURS));
    }
}
