<?php

declare(strict_types=1);

namespace Rehla\Catalog\Data;

use Illuminate\Support\Facades\Lang;
use InvalidArgumentException;

final readonly class ServiceRequirementData
{
    /** @param array{en: string, ar: string} $text */
    public function __construct(public array $text, public int $sortOrder)
    {
        if (trim($text['en']) === '' || trim($text['ar']) === '' || $sortOrder < 1) {
            throw new InvalidArgumentException((string) Lang::get('rehla-catalog::messages.validation.requirement'));
        }
    }
}
