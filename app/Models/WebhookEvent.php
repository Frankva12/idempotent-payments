<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $provider_event_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property string $outcome
 */
#[Fillable(['provider_event_id', 'type', 'payload', 'outcome'])]
class WebhookEvent extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
