<?php

declare(strict_types=1);

namespace Rehla\Identity\Queries;

use Illuminate\Support\Facades\Auth;
use Rehla\Identity\Contracts\IdentityReader;
use Rehla\Identity\Data\UserData;
use Rehla\Identity\Models\User;

final readonly class GetCurrentUser
{
    public function __construct(private IdentityReader $reader) {}

    public function handle(): ?UserData
    {
        $user = Auth::guard('web')->user();

        return $user instanceof User
            ? $this->reader->findCustomer((string) $user->getAuthIdentifier())
            : null;
    }
}
