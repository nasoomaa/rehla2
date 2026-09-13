<?php

declare(strict_types=1);

namespace Rehla\Travelers\Contracts;

use Rehla\Travelers\Data\TravelerSnapshot;

interface TravelerSnapshotReader
{
    public function getOwned(string $accountId, string $travelerId): TravelerSnapshot;
}
