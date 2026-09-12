<?php

declare(strict_types=1);

namespace Rehla\Identity\Contracts;

interface RegistrationWalletInitializer
{
    public function initialize(string $accountId): void;
}
