<?php

declare(strict_types=1);

namespace Rehla\Documents\Enums;

enum DocumentStatus: string
{
    case PendingScan = 'pending_scan';
    case Quarantined = 'quarantined';
    case Clean = 'clean';
    case Rejected = 'rejected';
    case Attached = 'attached';
    case CleanupClaimed = 'cleanup_claimed';
    case Purged = 'purged';
}
