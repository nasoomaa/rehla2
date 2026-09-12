<?php

declare(strict_types=1);

namespace Rehla\Identity\Queries;

use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Identity\Contracts\IdentityReader;
use Rehla\Identity\Data\ActorData;
use Rehla\Identity\Data\UserData;
use Rehla\Identity\Enums\AccountStatus;
use Rehla\Identity\Models\User;

final readonly class FindCustomer implements IdentityReader
{
    public function findCustomer(string $accountId): ?UserData
    {
        OpaqueId::fromString($accountId);

        $user = User::query()
            ->whereKey($accountId)
            ->whereDoesntHave('staffProfile')
            ->first();

        return $user === null ? null : $this->toData($user);
    }

    private function toData(User $user): UserData
    {
        return new UserData(
            id: (string) $user->getKey(),
            name: (string) $user->getAttribute('name'),
            email: (string) $user->getAttribute('email'),
            status: $user->getAttribute('status') instanceof AccountStatus
                ? $user->getAttribute('status')
                : AccountStatus::from((string) $user->getAttribute('status')),
            preferredLocale: (string) $user->getAttribute('preferred_locale'),
            actor: new ActorData((string) $user->getKey(), 'customer'),
        );
    }
}
