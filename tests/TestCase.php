<?php

declare(strict_types=1);
namespace Lgu\GovSuite\Tests;

use Lgu\GovSuite\LguServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LguServiceProvider::class,
        ];
    }
}
