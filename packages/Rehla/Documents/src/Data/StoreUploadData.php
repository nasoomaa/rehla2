<?php

declare(strict_types=1);

namespace Rehla\Documents\Data;

use InvalidArgumentException;
use Rehla\Core\Identifiers\OpaqueId;

final readonly class StoreUploadData
{
    public string $originalName;

    public function __construct(
        public string $uploadSessionId,
        public string $ownerId,
        string $originalName,
        public string $declaredMime,
        public string $contents,
    ) {
        OpaqueId::fromString($uploadSessionId);
        OpaqueId::fromString($ownerId);

        $originalName = trim($originalName);
        if ($originalName === ''
            || basename($originalName) !== $originalName
            || preg_match('/[\x00-\x1F\x7F]/', $originalName) === 1
            || mb_strlen($originalName) > 255) {
            throw new InvalidArgumentException;
        }

        $this->originalName = $originalName;
    }
}
