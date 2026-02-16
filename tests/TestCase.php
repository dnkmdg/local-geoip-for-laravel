<?php

declare(strict_types=1);

namespace Dnkmdg\LocalGeoIp\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Dnkmdg\LocalGeoIp\LocalGeoIpServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LocalGeoIpServiceProvider::class,
        ];
    }
}
