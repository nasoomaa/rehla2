<?php

declare(strict_types=1);

namespace Rehla\Reporting\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rehla\Reporting\Providers\ReportingServiceProvider;

final class PackageBootTest extends TestCase
{
    public function test_package_provider_exists(): void
    {
        self::assertTrue(class_exists(ReportingServiceProvider::class));
    }
}
