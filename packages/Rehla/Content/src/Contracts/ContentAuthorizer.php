<?php

declare(strict_types=1);

namespace Rehla\Content\Contracts;

interface ContentAuthorizer
{
    public function assertCanManage(string $actorId): void;
}
