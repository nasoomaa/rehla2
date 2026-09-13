<?php

declare(strict_types=1);

namespace Rehla\Catalog\Data;

use Illuminate\Support\Facades\Lang;
use InvalidArgumentException;

final readonly class ServiceData
{
    /**
     * @param  array{en: string, ar: string}  $name
     * @param  array{en: string, ar: string}  $shortDescription
     * @param  array{en: string, ar: string}  $detailedDescription
     * @param  array{en: string, ar: string}  $expectedDuration
     * @param  array{en: string, ar: string}  $notes
     * @param  list<ServiceRequirementData>  $requirements
     * @param  list<ServiceMediaData>  $media
     */
    public function __construct(
        public string $slug,
        public array $name,
        public array $shortDescription,
        public array $detailedDescription,
        public array $expectedDuration,
        public array $notes,
        public int $priceMinor,
        public array $requirements,
        public array $media,
        public int $sortOrder,
    ) {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1
            || mb_strlen($slug) > 120
            || $priceMinor < 1
            || $sortOrder < 0) {
            throw new InvalidArgumentException((string) Lang::get('rehla-catalog::messages.validation.slug_price_sort'));
        }

        foreach ([$name, $shortDescription, $detailedDescription, $expectedDuration] as $localized) {
            if (trim($localized['en']) === '' || trim($localized['ar']) === '') {
                throw new InvalidArgumentException((string) Lang::get('rehla-catalog::messages.validation.bilingual_content'));
            }
        }

        if (mb_strlen(trim($name['en'])) > 100 || mb_strlen(trim($name['ar'])) > 100) {
            throw new InvalidArgumentException((string) Lang::get('rehla-catalog::messages.validation.name_length'));
        }

        if ($requirements === [] || $media === []) {
            throw new InvalidArgumentException((string) Lang::get('rehla-catalog::messages.validation.requirements_media'));
        }

        $this->assertUniqueOrder($requirements);
        $this->assertUniqueOrder($media);
    }

    /** @param list<ServiceRequirementData>|list<ServiceMediaData> $items */
    private function assertUniqueOrder(array $items): void
    {
        $orders = array_map(static fn (ServiceRequirementData|ServiceMediaData $item): int => $item->sortOrder, $items);
        if (count($orders) !== count(array_unique($orders))) {
            throw new InvalidArgumentException((string) Lang::get('rehla-catalog::messages.validation.unique_sort'));
        }
    }
}
