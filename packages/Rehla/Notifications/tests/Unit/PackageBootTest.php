<?php

declare(strict_types=1);

namespace Rehla\Notifications\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rehla\Notifications\Providers\NotificationsServiceProvider;

final class PackageBootTest extends TestCase
{
    public function test_package_provider_exists(): void
    {
        self::assertTrue(class_exists(NotificationsServiceProvider::class));
    }
}
