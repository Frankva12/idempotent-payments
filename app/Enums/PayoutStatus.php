<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case PendingVerification = 'pending_verification';
}
