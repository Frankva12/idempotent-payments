<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case PendingVerification = 'pending_verification';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::InProgress],
            self::InProgress => [self::Completed, self::Failed, self::PendingVerification],
            self::PendingVerification => [self::Completed, self::Failed],
            self::Completed, self::Failed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
