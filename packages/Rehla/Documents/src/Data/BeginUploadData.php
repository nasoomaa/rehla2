<?php

declare(strict_types=1);

namespace Rehla\Documents\Data;

use Rehla\Core\Identifiers\OpaqueId;
use Rehla\Documents\Enums\DocumentPurpose;

final readonly class BeginUploadData
{
    public function __construct(
        public string $ownerId,
        public DocumentPurpose $purpose,
    ) {
        OpaqueId::fromString($ownerId);
    }
}
