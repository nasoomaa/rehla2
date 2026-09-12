<?php

declare(strict_types=1);

namespace Rehla\Documents\Contracts;

use Rehla\Documents\Data\ScanResult;

interface DocumentScanner
{
    public function scan(string $contents): ScanResult;
}
