<?php

declare(strict_types=1);

namespace Rehla\Forms\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Forms\Contracts\PublishedFormReader;
use Rehla\Forms\Data\FormFieldData;
use Rehla\Forms\Data\PublishedFormData;
use Rehla\Forms\Exceptions\FormNotFound;
use Rehla\Forms\Exceptions\FormSchemaIntegrityFailed;
use stdClass;

final class GetPublishedForm implements PublishedFormReader
{
    public function forService(string $serviceId): PublishedFormData
    {
        OpaqueId::fromString($serviceId);
        $row = DB::table('form_drafts')
            ->join('form_versions', 'form_versions.id', '=', 'form_drafts.current_version_id')
            ->where('form_drafts.service_id', $serviceId)
            ->select('form_versions.*')
            ->first();
        if ($row === null) {
            throw new FormNotFound;
        }

        return $this->hydrate($row);
    }

    public function hydrate(object $row): PublishedFormData
    {
        $values = get_object_vars($row);
        $encodedSchema = $this->string($values, 'schema');
        $schema = json_decode($encodedSchema, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($schema) || ! is_array($schema['fields'] ?? null)) {
            throw new FormSchemaIntegrityFailed;
        }
        $checksumSource = json_decode($encodedSchema, false, flags: JSON_THROW_ON_ERROR);
        $checksum = hash('sha256', json_encode($this->canonicalize($checksumSource), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if (! hash_equals($this->string($values, 'checksum'), $checksum)) {
            throw new FormSchemaIntegrityFailed;
        }
        $fields = array_values(array_map(
            static fn (mixed $field): FormFieldData => is_array($field)
                ? FormFieldData::fromArray($field)
                : throw new FormSchemaIntegrityFailed,
            $schema['fields'],
        ));

        return new PublishedFormData(
            $this->string($values, 'id'), $this->string($values, 'service_id'),
            $this->integer($values, 'version'), $fields, $checksum,
            CarbonImmutable::parse($this->string($values, 'published_at')),
        );
    }

    private function canonicalize(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $properties = get_object_vars($value);
            ksort($properties);
            $canonical = new stdClass;
            foreach ($properties as $key => $item) {
                $canonical->{$key} = $this->canonicalize($item);
            }

            return $canonical;
        }
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private function string(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (! is_string($value)) {
            throw new FormSchemaIntegrityFailed;
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private function integer(array $values, string $key): int
    {
        $value = $values[$key] ?? null;
        if (! is_int($value)) {
            throw new FormSchemaIntegrityFailed;
        }

        return $value;
    }
}
