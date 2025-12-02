<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Ferret\FerretManager;

describe('Environment Variable Interpolation', function (): void {
    beforeEach(function (): void {
        $this->manager = new FerretManager();
    });

    describe('interpolate()', function (): void {
        test('interpolates simple environment variable', function (): void {
            putenv('TEST_VAR=hello');

            $result = $this->manager->interpolate('${TEST_VAR}');

            expect($result)->toBe('hello');

            putenv('TEST_VAR');
        });

        test('interpolates variable with default when unset', function (): void {
            putenv('UNSET_VAR'); // Ensure unset

            $result = $this->manager->interpolate('${UNSET_VAR:-default_value}');

            expect($result)->toBe('default_value');
        });

        test('ignores default when variable is set', function (): void {
            putenv('SET_VAR=actual');

            $result = $this->manager->interpolate('${SET_VAR:-default}');

            expect($result)->toBe('actual');

            putenv('SET_VAR');
        });

        test('interpolates multiple variables in string', function (): void {
            putenv('VAR_A=foo');
            putenv('VAR_B=bar');

            $result = $this->manager->interpolate('${VAR_A}-${VAR_B}');

            expect($result)->toBe('foo-bar');

            putenv('VAR_A');
            putenv('VAR_B');
        });

        test('interpolates nested array values', function (): void {
            putenv('DB_HOST=localhost');
            putenv('DB_PORT=5432');

            $result = $this->manager->interpolate([
                'database' => [
                    'host' => '${DB_HOST}',
                    'port' => '${DB_PORT}',
                ],
            ]);

            expect($result)->toBe([
                'database' => [
                    'host' => 'localhost',
                    'port' => '5432',
                ],
            ]);

            putenv('DB_HOST');
            putenv('DB_PORT');
        });

        test('returns non-string values unchanged', function (): void {
            expect($this->manager->interpolate(42))->toBe(42);
            expect($this->manager->interpolate(3.14))->toBe(3.14);
            expect($this->manager->interpolate(true))->toBe(true);
            expect($this->manager->interpolate(null))->toBe(null);
        });

        test('returns string without variables unchanged', function (): void {
            $result = $this->manager->interpolate('no variables here');

            expect($result)->toBe('no variables here');
        });

        test('handles empty default value', function (): void {
            putenv('EMPTY_DEFAULT');

            $result = $this->manager->interpolate('prefix${EMPTY_DEFAULT:-}suffix');

            expect($result)->toBe('prefixsuffix');
        });

        test('handles variable names with underscores and numbers', function (): void {
            putenv('MY_VAR_123=test');

            $result = $this->manager->interpolate('${MY_VAR_123}');

            expect($result)->toBe('test');

            putenv('MY_VAR_123');
        });
    });

    describe('getInterpolated()', function (): void {
        test('returns interpolated value from loaded config', function (): void {
            putenv('API_URL=https://api.example.com');

            $tempFile = sys_get_temp_dir().'/interp-test-'.uniqid().'.json';
            file_put_contents($tempFile, json_encode([
                'api' => [
                    'url' => '${API_URL}',
                    'timeout' => 30,
                ],
            ]));

            $this->manager->load($tempFile, 'test-module');

            $result = $this->manager->getInterpolated('test-module', 'api.url');

            expect($result)->toBe('https://api.example.com');

            putenv('API_URL');
            unlink($tempFile);
        });

        test('returns entire config interpolated when no key specified', function (): void {
            putenv('HOST=localhost');
            putenv('PORT=8080');

            $tempFile = sys_get_temp_dir().'/interp-full-'.uniqid().'.json';
            file_put_contents($tempFile, json_encode([
                'server' => [
                    'host' => '${HOST}',
                    'port' => '${PORT}',
                ],
            ]));

            $this->manager->load($tempFile, 'full-module');

            $result = $this->manager->getInterpolated('full-module');

            expect($result)->toBe([
                'server' => [
                    'host' => 'localhost',
                    'port' => '8080',
                ],
            ]);

            putenv('HOST');
            putenv('PORT');
            unlink($tempFile);
        });
    });
});
