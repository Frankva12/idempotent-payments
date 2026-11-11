<?php

namespace App\Payouts\Providers;

use App\Models\Payout;
use App\Payouts\PayoutProvider;
use App\Payouts\ProviderResult;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;

/**
 * Local stand-in for a real provider. Like a payment sandbox, the amount picks the scenario:
 * cents ending in 13 time out after the money moved, cents ending in 99 are rejected.
 */
class SimulatedPayoutProvider implements PayoutProvider
{
    public function __construct(private readonly Repository $cache) {}

    public function send(string $reference, Payout $payout): ProviderResult
    {
        $cents = $payout->amount % 100;

        if ($cents === 99) {
            $this->remember($reference, 'failed', null);

            return ProviderResult::rejected('Simulated rejection: wallet is closed.');
        }

        $transferId = 'tr_'.Str::lower(Str::random(16));
        $this->remember($reference, 'succeeded', $transferId);

        if ($cents === 13) {
            return ProviderResult::unknown('Simulated timeout: the money moved, but the response was lost.');
        }

        return ProviderResult::succeeded($transferId);
    }

    public function lookup(string $reference): ProviderResult
    {
        /** @var array{status: string, id: string|null}|null $transfer */
        $transfer = $this->cache->get($this->cacheKey($reference));

        return match ($transfer['status'] ?? null) {
            'succeeded' => ProviderResult::succeeded((string) $transfer['id']),
            'failed' => ProviderResult::rejected('Simulated rejection: wallet is closed.'),
            default => ProviderResult::notFound(),
        };
    }

    private function remember(string $reference, string $status, ?string $transferId): void
    {
        $this->cache->put($this->cacheKey($reference), ['status' => $status, 'id' => $transferId], now()->addDay());
    }

    private function cacheKey(string $reference): string
    {
        return "simulated-provider:transfer:{$reference}";
    }
}
