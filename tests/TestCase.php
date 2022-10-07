<?php

declare(strict_types=1);

namespace ParcelTrap\USPS\Tests;

use ParcelTrap\ParcelTrapServiceProvider;
use ParcelTrap\USPS\USPSServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ParcelTrapServiceProvider::class, USPSServiceProvider::class];
    }
}
