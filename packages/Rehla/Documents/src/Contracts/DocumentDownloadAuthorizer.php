<?php

declare(strict_types=1);

namespace Rehla\Documents\Contracts;

use Rehla\Identity\Data\ActorData;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface DocumentDownloadAuthorizer
{
    public function authorize(string $documentId, ActorData $actor): StreamedResponse;
}
