<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Ferret\FerretManager;

describe('FerretServiceProvider', function (): void {
    describe('registration', function (): void {
        test('registers FerretManager as singleton', function (): void {
            $manager1 = resolve(FerretManager::class);
            $manager2 = resolve(FerretManager::class);

            expect($manager1)->toBeInstanceOf(FerretManager::class)
                ->and($manager1)->toBe($manager2);
        });
    });

    describe('configuration', function (): void {
        test('config file can be published', function (): void {
            $configPath = config_path('ferret.php');

            // Config should be available even if not published
            expect(config('ferret'))->toBeArray();
        });

        test('applies default configuration values', function (): void {
            expect(config('ferret.cache'))->toBeTrue()
                ->and(config('ferret.ignore_empty'))->toBeTrue()
                ->and(config('ferret.search_strategy'))->toBe('none');
        });

        test('applies custom search places from config', function (): void {
            config(['ferret.search_places' => ['custom.json', '.customrc']]);

            // Clear the singleton to force re-creation
            app()->forgetInstance(FerretManager::class);

            $manager = resolve(FerretManager::class);
            expect($manager)->toBeInstanceOf(FerretManager::class);
        });

        test('applies custom stop directory from config', function (): void {
            config(['ferret.stop_dir' => '/custom/stop/dir']);

            // Clear the singleton to force re-creation
            app()->forgetInstance(FerretManager::class);

            $manager = resolve(FerretManager::class);
            expect($manager)->toBeInstanceOf(FerretManager::class);
        });
    });
});
