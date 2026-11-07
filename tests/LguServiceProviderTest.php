<?php

declare(strict_types=1);

namespace Lgu\GovSuite\Tests;

use Lgu\GovSuite\LguServiceProvider;
use PHPUnit\Framework\TestCase;

class LguServiceProviderTest extends TestCase
{
    public function test_service_provider_class_exists(): void
    {
        $this->assertTrue(class_exists(LguServiceProvider::class));
    }
}
