<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Ferret\Exceptions\InvalidTomlConfigurationException;
use Cline\Ferret\Loaders\TomlLoader;

describe('TomlLoader', function (): void {
    beforeEach(function (): void {
        $this->loader = new TomlLoader();
    });

    describe('extensions', function (): void {
        test('returns toml extension', function (): void {
            expect($this->loader->extensions())->toBe(['toml']);
        });
    });

    describe('load', function (): void {
        test('loads valid toml file', function (): void {
            $result = $this->loader->load(fixturesPath('config.toml'));

            expect($result)->toBeArray()
                ->and($result['database']['host'])->toBe('localhost')
                ->and($result['database']['port'])->toBe(5_432)
                ->and($result['debug'])->toBeTrue();
        });

        test('throws exception for invalid toml', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/invalid.toml';
            file_put_contents($file, '[invalid toml');

            try {
                $this->loader->load($file);
            } catch (InvalidTomlConfigurationException $invalidTomlConfigurationException) {
                expect($invalidTomlConfigurationException->getMessage())->toContain('Failed to parse TOML');

                return;
            } finally {
                removeDir($tempDir);
            }

            $this->fail('Expected InvalidTomlConfigurationException was not thrown');
        });

        test('throws exception for non-existent file', function (): void {
            $this->loader->load('/nonexistent/file.toml');
        })->throws(InvalidTomlConfigurationException::class);
    });

    describe('encode', function (): void {
        test('encodes array to toml string', function (): void {
            $data = ['key' => 'value', 'number' => 42];
            $encoded = $this->loader->encode($data);

            expect($encoded)->toBeString()
                ->and($encoded)->toContain('key')
                ->and($encoded)->toContain('value')
                ->and($encoded)->toContain('42');
        });

        test('encodes nested arrays as tables', function (): void {
            $data = ['database' => ['host' => 'localhost', 'port' => 5_432]];
            $encoded = $this->loader->encode($data);

            expect($encoded)->toContain('[database]')
                ->and($encoded)->toContain('host')
                ->and($encoded)->toContain('localhost');
        });
    });
});
