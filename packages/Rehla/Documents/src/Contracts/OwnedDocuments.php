<?php

declare(strict_types=1);

namespace Rehla\Documents\Contracts;

use Rehla\Documents\Data\DocumentReference;
use Rehla\Documents\Enums\DocumentPurpose;

interface OwnedDocuments
{
    /**
     * @param  list<string>  $documentIds
     * @param  list<DocumentPurpose>  $requiredPurposes
     * @return list<DocumentReference>
     */
    public function assertCleanOwned(array $documentIds, string $ownerId, array $requiredPurposes): array;
}
