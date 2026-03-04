<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Ferret\Facades\Ferret;
use Cline\Ferret\Searcher;

describe('Ferret Facade', function (): void {
    describe('explorer', function (): void {
        test('returns a searcher instance', function (): void {
            $searcher = Ferret::explorer('myapp');

            expect($searcher)->toBeInstanceOf(Searcher::class);
        });
    });

    describe('load', function (): void {
        test('loads configuration file', function (): void {
            $result = Ferret::load(fixturesPath('config.json'), 'myconfig');

            expect($result->config['database']['host'])->toBe('localhost');
        });
    });

    describe('get and set', function (): void {
        test('sets and gets configuration values', function (): void {
            Ferret::load(fixturesPath('config.json'), 'test');

            Ferret::set('test', 'custom.key', 'custom-value');

            expect(Ferret::get('test', 'custom.key'))->toBe('custom-value');
        });
    });

    describe('has and forget', function (): void {
        test('checks and removes configuration keys', function (): void {
            Ferret::load(fixturesPath('config.json'), 'test');

            expect(Ferret::has('test', 'database.host'))->toBeTrue();

            Ferret::forget('test', 'database.host');

            expect(Ferret::has('test', 'database.host'))->toBeFalse();
        });
    });

    describe('cache management', function (): void {
        test('clears cache', function (): void {
            Ferret::load(fixturesPath('config.json'), 'cached');

            expect(Ferret::isLoaded('cached'))->toBeTrue();

            Ferret::clearCache('cached');

            expect(Ferret::isLoaded('cached'))->toBeFalse();
        });
    });

    describe('loadedModules', function (): void {
        test('returns loaded module names', function (): void {
            // Clear any previous state
            Ferret::clearCache();

            Ferret::load(fixturesPath('config.json'), 'module1');
            Ferret::load(fixturesPath('config.yaml'), 'module2');

            expect(Ferret::loadedModules())->toContain('module1')
                ->and(Ferret::loadedModules())->toContain('module2');
        });
    });
});
