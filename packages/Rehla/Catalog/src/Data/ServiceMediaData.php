<?php

declare(strict_types=1);

namespace Rehla\Catalog\Data;

use Illuminate\Support\Facades\Lang;
use InvalidArgumentException;
use Rehla\Core\Identifiers\OpaqueId;

final readonly class ServiceMediaData
{
    /** @param array{en: string, ar: string} $alt */
    public function __construct(public string $documentId, public array $alt, public int $sortOrder)
    {
        OpaqueId::fromString($documentId);
        if (trim($alt['en']) === '' || trim($alt['ar']) === '' || $sortOrder < 1) {
            throw new InvalidArgumentException((string) Lang::get('rehla-catalog::messages.validation.media'));
        }
    }
}
