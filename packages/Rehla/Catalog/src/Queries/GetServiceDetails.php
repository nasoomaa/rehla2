<?php

declare(strict_types=1);

namespace Rehla\Catalog\Queries;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Rehla\Catalog\Data\ServiceMediaData;
use Rehla\Catalog\Data\ServiceRequirementData;
use Rehla\Catalog\Data\ServiceSnapshot;
use Rehla\Catalog\Enums\ServiceStatus;
use Rehla\Catalog\Exceptions\ServiceNotFound;
use Rehla\Core\Identifiers\OpaqueId;
use UnexpectedValueException;

final class GetServiceDetails
{
    public function getById(string $serviceId): ServiceSnapshot
    {
        OpaqueId::fromString($serviceId);

        return $this->snapshot(DB::table('services')->where('id', $serviceId)->first());
    }

    public function getPublishedBySlug(string $slug): ServiceSnapshot
    {
        return $this->snapshot(DB::table('services')
            ->where('slug', $slug)
            ->where('status', ServiceStatus::Active->value)
            ->first());
    }

    public function snapshot(?object $service): ServiceSnapshot
    {
        if ($service === null) {
            throw new ServiceNotFound;
        }
        $serviceId = $this->string($service, 'id');
        $requirements = array_values(DB::table('service_requirements')->where('service_id', $serviceId)->orderBy('sort_order')->get()
            ->map(fn (object $row): ServiceRequirementData => new ServiceRequirementData(
                ['en' => $this->string($row, 'text_en'), 'ar' => $this->string($row, 'text_ar')],
                $this->integer($row, 'sort_order'),
            ))->all());
        $media = array_values(DB::table('service_media')->where('service_id', $serviceId)->orderBy('sort_order')->get()
            ->map(fn (object $row): ServiceMediaData => new ServiceMediaData(
                $this->string($row, 'document_id'),
                ['en' => $this->string($row, 'alt_en'), 'ar' => $this->string($row, 'alt_ar')],
                $this->integer($row, 'sort_order'),
            ))->all());

        return new ServiceSnapshot(
            $serviceId,
            $this->string($service, 'slug'),
            ['en' => $this->string($service, 'name_en'), 'ar' => $this->string($service, 'name_ar')],
            [
                'short' => ['en' => $this->string($service, 'short_description_en'), 'ar' => $this->string($service, 'short_description_ar')],
                'detailed' => ['en' => $this->string($service, 'detailed_description_en'), 'ar' => $this->string($service, 'detailed_description_ar')],
            ],
            ['en' => $this->string($service, 'expected_duration_en'), 'ar' => $this->string($service, 'expected_duration_ar')],
            ['en' => $this->string($service, 'notes_en'), 'ar' => $this->string($service, 'notes_ar')],
            $requirements,
            $media,
        );
    }

    private function string(object $row, string $key): string
    {
        $value = get_object_vars($row)[$key] ?? null;
        if (! is_string($value)) {
            throw new UnexpectedValueException((string) Lang::get('rehla-catalog::messages.validation.row_string'));
        }

        return $value;
    }

    private function integer(object $row, string $key): int
    {
        $value = get_object_vars($row)[$key] ?? null;
        if (! is_int($value)) {
            throw new UnexpectedValueException((string) Lang::get('rehla-catalog::messages.validation.row_integer'));
        }

        return $value;
    }
}
