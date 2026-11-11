<?php

namespace App\Payouts;

final readonly class ProviderResult
{
    private function __construct(
        public ProviderOutcome $outcome,
        public ?string $transferId = null,
        public ?string $reason = null,
    ) {}

    public static function succeeded(string $transferId): self
    {
        return new self(ProviderOutcome::Succeeded, transferId: $transferId);
    }

    public static function rejected(string $reason, ?string $transferId = null): self
    {
        return new self(ProviderOutcome::Rejected, transferId: $transferId, reason: $reason);
    }

    public static function unknown(string $reason, ?string $transferId = null): self
    {
        return new self(ProviderOutcome::Unknown, transferId: $transferId, reason: $reason);
    }

    public static function notFound(): self
    {
        return new self(ProviderOutcome::NotFound, reason: 'The provider has no record of this transfer.');
    }
}
