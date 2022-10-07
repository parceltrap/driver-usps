<?php

declare(strict_types=1);

namespace ParcelTrap\USPS;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use ParcelTrap\Contracts\Factory;
use ParcelTrap\ParcelTrap;

class USPSServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /** @var ParcelTrap $factory */
        $factory = $this->app->make(Factory::class);

        $factory->extend(USPS::IDENTIFIER, function () {
            /** @var Repository $config */
            $config = $this->app->make(Repository::class);

            return new USPS(
                /** @phpstan-ignore-next-line */
                apiKey: (string) $config->get('parceltrap.drivers.usps.api_key'),
                /** @phpstan-ignore-next-line */
                sourceId: (string) $config->get('parceltrap.drivers.usps.source_id'),
            );
        });
    }
}
