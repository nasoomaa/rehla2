<?php

declare(strict_types=1);

namespace Rehla\Identity\Contracts;

interface RegistrationNotificationRecorder
{
    public function recordWelcome(string $accountId, string $locale, string $correlationId): void;
}
