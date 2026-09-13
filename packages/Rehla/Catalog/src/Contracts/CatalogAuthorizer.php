<?php

declare(strict_types=1);

namespace Rehla\Catalog\Contracts;

interface CatalogAuthorizer
{
    public function assertCanManage(string $actorId): void;
}
