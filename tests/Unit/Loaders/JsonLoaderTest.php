<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Ferret\Exceptions\ConfigurationEncodingFailedException;
use Cline\Ferret\Exceptions\InvalidJsonConfigurationException;
use Cline\Ferret\Loaders\JsonLoader;

describe('JsonLoader', function (): void {
    beforeEach(function (): void {
        $this->loader = new JsonLoader();
    });

    describe('extensions', function (): void {
        test('returns json extension', function (): void {
            expect($this->loader->extensions())->toBe(['json']);
        });
    });

    describe('load', function (): void {
        test('loads valid json file', function (): void {
            $result = $this->loader->load(fixturesPath('config.json'));

            expect($result)->toBeArray()
                ->and($result['database']['host'])->toBe('localhost')
                ->and($result['database']['port'])->toBe(5_432)
                ->and($result['debug'])->toBeTrue();
        });

        test('returns empty array for empty file', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/empty.json';
            file_put_contents($file, '');

            try {
                $result = $this->loader->load($file);
                expect($result)->toBe([]);
            } finally {
                removeDir($tempDir);
            }
        });

        test('throws exception for invalid json', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/invalid.json';
            file_put_contents($file, '{invalid json}');

            try {
                $this->loader->load($file);
            } catch (InvalidJsonConfigurationException $invalidJsonConfigurationException) {
                expect($invalidJsonConfigurationException->getMessage())->toContain('Failed to parse JSON');

                return;
            } finally {
                removeDir($tempDir);
            }

            $this->fail('Expected InvalidJsonConfigurationException was not thrown');
        });

        test('throws exception for non-existent file', function (): void {
            $this->loader->load('/nonexistent/file.json');
        })->throws(InvalidJsonConfigurationException::class);
    });

    describe('encode', function (): void {
        test('encodes array to json string', function (): void {
            $data = ['key' => 'value', 'nested' => ['a' => 1]];
            $encoded = $this->loader->encode($data);

            expect($encoded)->toBeString()
                ->and(json_decode($encoded, true))->toBe($data);
        });

        test('produces pretty-printed output', function (): void {
            $data = ['key' => 'value'];
            $encoded = $this->loader->encode($data);

            expect($encoded)->toContain("\n");
        });

        test('throws exception for unencodable data', function (): void {
            // Create a resource that can't be encoded
            $resource = fopen('php://memory', 'rb');
            $data = ['resource' => $resource];

            try {
                $this->loader->encode($data);
            } catch (ConfigurationEncodingFailedException $configurationEncodingFailedException) {
                expect($configurationEncodingFailedException->getMessage())->toContain('Failed to encode');

                return;
            } finally {
                fclose($resource);
            }

            $this->fail('Expected ConfigurationEncodingFailedException was not thrown');
        });
    });
});
