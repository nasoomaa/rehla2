<?php

declare(strict_types=1);

namespace Rehla\Identity\Actions;

use Illuminate\Support\Facades\DB;
use Rehla\Core\Time\Clock;
use Rehla\Identity\Contracts\AuthorizesActor;
use Rehla\Identity\Data\ActorData;
use Rehla\Identity\Data\ResourceRef;
use Rehla\Identity\Enums\AbilityName;
use Rehla\Identity\Enums\AccountStatus;

final readonly class AuthorizeActor implements AuthorizesActor
{
    public function __construct(private Clock $clock) {}

    public function allows(ActorData $actor, AbilityName $ability, ?ResourceRef $resource = null): bool
    {
        if ($actor->type !== 'staff') {
            return false;
        }

        if ($ability->requiresRecentMfa()
            && ($actor->mfaConfirmedAt === null
                || $actor->mfaConfirmedAt->isBefore($this->clock->now()->subHours(4)))) {
            return false;
        }

        $query = DB::table('users')
            ->join('staff_profiles', 'staff_profiles.user_id', '=', 'users.id')
            ->join('user_role', 'user_role.user_id', '=', 'users.id')
            ->join('role_ability', 'role_ability.role_id', '=', 'user_role.role_id')
            ->join('abilities', 'abilities.id', '=', 'role_ability.ability_id')
            ->where('users.id', $actor->id)
            ->where('users.status', AccountStatus::Active->value)
            ->where('staff_profiles.is_active', true)
            ->where('abilities.name', $ability->value);

        if ($ability->requiresRecentMfa()) {
            $query->whereNotNull('staff_profiles.mfa_confirmed_at')
                ->where('staff_profiles.mfa_confirmed_at', '>=', $this->clock->now()->subHours(4));
        }

        return $query->exists();
    }
}
