<?php

declare(strict_types=1);

namespace Rehla\Catalog\Contracts;

use Rehla\Catalog\Data\ServiceSnapshot;

interface ServiceCatalog
{
    /** @return list<ServiceSnapshot> */
    public function listPublished(int $page, int $perPage): array;

    public function getPublishedBySlug(string $slug): ServiceSnapshot;

    public function getById(string $serviceId): ServiceSnapshot;
}
