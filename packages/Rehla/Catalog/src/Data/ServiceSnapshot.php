<?php

declare(strict_types=1);

namespace Rehla\Catalog\Data;

final readonly class ServiceSnapshot
{
    /**
     * @param  array{en: string, ar: string}  $name
     * @param  array{short: array{en: string, ar: string}, detailed: array{en: string, ar: string}}  $descriptions
     * @param  array{en: string, ar: string}  $expectedDuration
     * @param  array{en: string, ar: string}  $notes
     * @param  list<ServiceRequirementData>  $requirements
     * @param  list<ServiceMediaData>  $media
     */
    public function __construct(
        public string $id,
        public string $slug,
        public array $name,
        public array $descriptions,
        public array $expectedDuration,
        public array $notes,
        public array $requirements,
        public array $media,
    ) {}
}
