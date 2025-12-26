<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Ferret\Exceptions\InvalidYamlConfigurationException;
use Cline\Ferret\Loaders\YamlLoader;

describe('YamlLoader', function (): void {
    beforeEach(function (): void {
        $this->loader = new YamlLoader();
    });

    describe('extensions', function (): void {
        test('returns yaml and yml extensions', function (): void {
            expect($this->loader->extensions())->toBe(['yaml', 'yml']);
        });
    });

    describe('load', function (): void {
        test('loads valid yaml file', function (): void {
            $result = $this->loader->load(fixturesPath('config.yaml'));

            expect($result)->toBeArray()
                ->and($result['database']['host'])->toBe('localhost')
                ->and($result['database']['port'])->toBe(5_432)
                ->and($result['debug'])->toBeTrue();
        });

        test('returns empty array for empty file', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/empty.yaml';
            file_put_contents($file, '');

            try {
                $result = $this->loader->load($file);
                expect($result)->toBe([]);
            } finally {
                removeDir($tempDir);
            }
        });

        test('throws exception for invalid yaml', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/invalid.yaml';
            file_put_contents($file, "key: value\n  invalid: indentation");

            try {
                $this->loader->load($file);
            } catch (InvalidYamlConfigurationException $invalidYamlConfigurationException) {
                expect($invalidYamlConfigurationException->getMessage())->toContain('Failed to parse YAML');

                return;
            } finally {
                removeDir($tempDir);
            }

            $this->fail('Expected InvalidYamlConfigurationException was not thrown');
        });
    });

    describe('encode', function (): void {
        test('encodes array to yaml string', function (): void {
            $data = ['key' => 'value', 'nested' => ['a' => 1]];
            $encoded = $this->loader->encode($data);

            expect($encoded)->toBeString()
                ->and($encoded)->toContain('key: value');
        });
    });

    describe('load edge cases', function (): void {
        test('throws exception for non-existent file', function (): void {
            $this->loader->load('/nonexistent/file.yaml');
        })->throws(InvalidYamlConfigurationException::class);
    });
});
