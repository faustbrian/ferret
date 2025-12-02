<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret;

use Cline\Ferret\Console\ConfigConvertCommand;
use Cline\Ferret\Console\ConfigDecryptCommand;
use Cline\Ferret\Console\ConfigEncryptCommand;
use Cline\Ferret\Enums\SearchStrategy;
use Illuminate\Contracts\Config\Repository;
use Override;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

use function is_array;
use function is_string;

/**
 * Laravel service provider for the Ferretonfig package.
 *
 * Registers the FerretManager singleton and configuration. Integrates
 * Ferretonfig with Laravel's service container for seamless dependency
 * injection throughout the application.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FerretServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package.
     *
     * @param Package $package The package instance to configure
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('ferret')
            ->hasConfigFile()
            ->hasCommands([
                ConfigEncryptCommand::class,
                ConfigDecryptCommand::class,
                ConfigConvertCommand::class,
            ]);
    }

    /**
     * Register Ferretonfig services in the container.
     */
    #[Override()]
    public function registeringPackage(): void
    {
        $this->app->singleton(FerretManager::class, function (): FerretManager {
            $config = $this->app->make(Repository::class);

            $searchPlaces = $config->get('ferret.search_places', []);
            $searchStrategy = $config->get('ferret.search_strategy', 'none');
            $stopDir = $config->get('ferret.stop_dir');
            $cache = $config->get('ferret.cache', true);
            $ignoreEmpty = $config->get('ferret.ignore_empty', true);

            $options = [
                'cache' => (bool) $cache,
                'ignoreEmpty' => (bool) $ignoreEmpty,
            ];

            if (is_array($searchPlaces) && $searchPlaces !== []) {
                /** @var array<string> $searchPlaces */
                $options['searchPlaces'] = $searchPlaces;
            }

            if (is_string($searchStrategy)) {
                $options['searchStrategy'] = SearchStrategy::tryFrom($searchStrategy) ?? SearchStrategy::None;
            }

            if (is_string($stopDir)) {
                $options['stopDir'] = $stopDir;
            }

            return new FerretManager($options);
        });
    }
}
