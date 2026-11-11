<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayoutAttempt;
use App\Models\WebhookEvent;
use App\Payouts\Exceptions\InvalidPayoutTransition;
use App\Payouts\PayoutProcessor;
use App\Payouts\ProviderResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProviderWebhookController extends Controller
{
    public function __invoke(Request $request, PayoutProcessor $processor): JsonResponse
    {
        $event = $request->validate([
            'id' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'data.reference' => ['required', 'string'],
            'data.transfer_id' => ['nullable', 'string'],
            'data.failure_reason' => ['nullable', 'string'],
        ]);

        // One transaction: if applying the event fails, the event is not marked as seen
        // and the provider's next delivery gets another chance.
        $outcome = DB::transaction(function () use ($event, $request, $processor): string {
            $inserted = WebhookEvent::query()->insertOrIgnore([
                'provider_event_id' => $event['id'],
                'type' => $event['type'],
                'payload' => json_encode($request->all(), JSON_THROW_ON_ERROR),
                'outcome' => 'received',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                return 'duplicate';
            }

            $outcome = $this->apply($event, $processor);
            WebhookEvent::where('provider_event_id', $event['id'])->update(['outcome' => $outcome]);

            return $outcome;
        });

        // Always 200 for a verified event: a non-2xx only makes the provider redeliver it.
        return response()->json(['status' => $outcome]);
    }

    /**
     * @param  array{id: string, type: string, data: array{reference: string, transfer_id?: string|null, failure_reason?: string|null}}  $event
     */
    private function apply(array $event, PayoutProcessor $processor): string
    {
        $reference = $event['data']['reference'];
        // Postgres rejects a malformed uuid with an error instead of simply finding nothing.
        $attempt = Str::isUuid($reference) ? PayoutAttempt::find($reference) : null;
        $transferId = $event['data']['transfer_id'] ?? null;

        $result = match ($event['type']) {
            'transfer.succeeded' => $transferId !== null ? ProviderResult::succeeded($transferId) : null,
            'transfer.failed' => ProviderResult::rejected($event['data']['failure_reason'] ?? 'Rejected by the provider.', $transferId),
            default => null,
        };

        if ($attempt === null || $result === null) {
            return 'ignored';
        }

        try {
            $processor->recordResult($attempt, $result);
        } catch (InvalidPayoutTransition $exception) {
            Log::warning('Webhook contradicts the payout status', [
                'event_id' => $event['id'],
                'attempt_id' => $attempt->id,
                'error' => $exception->getMessage(),
            ]);

            return 'conflict';
        }

        return 'processed';
    }
}
