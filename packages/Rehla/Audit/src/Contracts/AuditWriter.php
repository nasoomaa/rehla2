<?php

declare(strict_types=1);

namespace Rehla\Audit\Contracts;

use Rehla\Audit\Data\AppendAuditData;

interface AuditWriter
{
    public function append(AppendAuditData $data): string;
}
