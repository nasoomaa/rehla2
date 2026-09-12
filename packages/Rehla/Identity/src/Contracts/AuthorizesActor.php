<?php

declare(strict_types=1);

namespace Rehla\Identity\Contracts;

use Rehla\Identity\Data\ActorData;
use Rehla\Identity\Data\ResourceRef;
use Rehla\Identity\Enums\AbilityName;

interface AuthorizesActor
{
    public function allows(ActorData $actor, AbilityName $ability, ?ResourceRef $resource = null): bool;
}
