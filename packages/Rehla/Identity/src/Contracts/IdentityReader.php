<?php

declare(strict_types=1);

namespace Rehla\Identity\Contracts;

use Rehla\Identity\Data\UserData;

interface IdentityReader
{
    public function findCustomer(string $accountId): ?UserData;
}
