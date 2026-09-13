<?php

declare(strict_types=1);

namespace Rehla\Catalog\Queries;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use InvalidArgumentException;
use Rehla\Catalog\Contracts\ServiceCatalog;
use Rehla\Catalog\Data\ServiceSnapshot;
use Rehla\Catalog\Enums\ServiceStatus;

final readonly class ListPublishedServices implements ServiceCatalog
{
    public function __construct(private GetServiceDetails $details) {}

    public function listPublished(int $page, int $perPage): array
    {
        if ($page < 1 || $perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException((string) Lang::get('rehla-catalog::messages.validation.pagination'));
        }

        return array_values(DB::table('services')->where('status', ServiceStatus::Active->value)
            ->orderBy('sort_order')->orderBy('id')
            ->offset(($page - 1) * $perPage)->limit($perPage)->get()
            ->map(fn (object $service): ServiceSnapshot => $this->details->snapshot($service))->all());
    }

    public function getPublishedBySlug(string $slug): ServiceSnapshot
    {
        return $this->details->getPublishedBySlug($slug);
    }

    public function getById(string $serviceId): ServiceSnapshot
    {
        return $this->details->getById($serviceId);
    }
}
