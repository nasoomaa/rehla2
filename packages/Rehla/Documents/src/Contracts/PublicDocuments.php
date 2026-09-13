<?php

declare(strict_types=1);

namespace Rehla\Documents\Contracts;

use Rehla\Documents\Data\DocumentReference;
use Rehla\Documents\Enums\DocumentPurpose;

interface PublicDocuments
{
    /**
     * @param  list<string>  $documentIds
     * @param  list<DocumentPurpose>  $requiredPurposes
     * @return list<DocumentReference>
     */
    public function assertCleanPublic(array $documentIds, string $ownerId, array $requiredPurposes): array;
}
