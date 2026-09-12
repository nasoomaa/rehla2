<?php

declare(strict_types=1);

namespace Rehla\Audit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rehla\Audit\Providers\AuditServiceProvider;

final class PackageBootTest extends TestCase
{
    public function test_package_provider_exists(): void
    {
        self::assertTrue(class_exists(AuditServiceProvider::class));
    }
}
