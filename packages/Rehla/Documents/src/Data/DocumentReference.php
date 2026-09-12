<?php

declare(strict_types=1);

namespace Rehla\Documents\Data;

use Rehla\Documents\Enums\DocumentPurpose;
use Rehla\Documents\Enums\DocumentStatus;

final readonly class DocumentReference
{
    public function __construct(
        public string $id,
        public DocumentPurpose $purpose,
        public DocumentStatus $status,
        public string $originalName,
        public ?string $detectedMime,
        public int $sizeBytes,
        public string $sha256,
    ) {}
}
