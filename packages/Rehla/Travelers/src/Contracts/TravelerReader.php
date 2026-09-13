<?php

declare(strict_types=1);

namespace Rehla\Travelers\Contracts;

use Rehla\Travelers\Data\TravelerSnapshot;

interface TravelerReader
{
    /** @return list<TravelerSnapshot> */
    public function listOwned(string $accountId, int $page, int $perPage): array;
}
