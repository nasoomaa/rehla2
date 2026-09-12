<?php

declare(strict_types=1);

namespace Rehla\Identity\Data;

use Rehla\Identity\Enums\AccountStatus;

final readonly class UserData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public AccountStatus $status,
        public string $preferredLocale,
        public ActorData $actor,
    ) {}
}
