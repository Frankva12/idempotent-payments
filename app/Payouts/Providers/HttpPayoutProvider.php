<?php

namespace App\Payouts\Providers;

use App\Models\Payout;
use App\Payouts\PayoutProvider;
use App\Payouts\ProviderResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class HttpPayoutProvider implements PayoutProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeoutSeconds,
    ) {}

    public function send(string $reference, Payout $payout): ProviderResult
    {
        try {
            $response = $this->client()->post('/transfers', [
                'reference' => $reference,
                'wallet_id' => $payout->wallet_id,
                'amount' => $payout->amount,
                'currency' => $payout->currency,
            ]);
        } catch (ConnectionException $exception) {
            return ProviderResult::unknown('No response from the provider: '.$exception->getMessage());
        }

        // The provider already has a transfer with this reference: only a lookup can say how it ended.
        if ($response->status() === 409) {
            return ProviderResult::unknown('The provider reports a duplicate reference.');
        }

        if ($response->serverError()) {
            return ProviderResult::unknown("The provider returned {$response->status()}.", $response->json('id'));
        }

        if ($response->clientError()) {
            return ProviderResult::rejected($this->errorMessage($response));
        }

        return $this->fromTransfer($response);
    }

    public function lookup(string $reference): ProviderResult
    {
        try {
            $response = $this->client()->get('/transfers', ['reference' => $reference]);
        } catch (ConnectionException $exception) {
            return ProviderResult::unknown('No response from the provider: '.$exception->getMessage());
        }

        if ($response->notFound()) {
            return ProviderResult::notFound();
        }

        if ($response->failed()) {
            return ProviderResult::unknown("The provider returned {$response->status()}.");
        }

        return $this->fromTransfer($response);
    }

    private function fromTransfer(Response $response): ProviderResult
    {
        $transferId = $response->json('id');

        return match ($response->json('status')) {
            'succeeded' => is_string($transferId)
                ? ProviderResult::succeeded($transferId)
                : ProviderResult::unknown('The provider reported success without a transfer id.'),
            'failed' => ProviderResult::rejected($this->errorMessage($response), $transferId),
            default => ProviderResult::unknown('The transfer is still processing.', $transferId),
        };
    }

    private function errorMessage(Response $response): string
    {
        $message = $response->json('error.message') ?? $response->json('failure_reason');

        return is_string($message) ? $message : "The provider returned {$response->status()}.";
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->timeout($this->timeoutSeconds);
    }
}
