<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Ferret\Exceptions\InvalidNeonConfigurationException;
use Cline\Ferret\Loaders\NeonLoader;

describe('NeonLoader', function (): void {
    beforeEach(function (): void {
        $this->loader = new NeonLoader();
    });

    describe('extensions', function (): void {
        test('returns neon extension', function (): void {
            expect($this->loader->extensions())->toBe(['neon']);
        });
    });

    describe('load', function (): void {
        test('loads valid neon file', function (): void {
            $result = $this->loader->load(fixturesPath('config.neon'));

            expect($result)->toBeArray()
                ->and($result['database']['host'])->toBe('localhost')
                ->and($result['database']['port'])->toBe(5_432)
                ->and($result['debug'])->toBeTrue();
        });

        test('returns empty array for empty file', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/empty.neon';
            file_put_contents($file, '');

            try {
                $result = $this->loader->load($file);
                expect($result)->toBe([]);
            } finally {
                removeDir($tempDir);
            }
        });

        test('throws exception for invalid neon', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/invalid.neon';
            file_put_contents($file, "key: value\n  invalid: indentation");

            try {
                $this->loader->load($file);
            } catch (InvalidNeonConfigurationException $invalidNeonConfigurationException) {
                expect($invalidNeonConfigurationException->getMessage())->toContain('Failed to parse NEON');

                return;
            } finally {
                removeDir($tempDir);
            }

            $this->fail('Expected InvalidNeonConfigurationException was not thrown');
        });

        test('throws exception for non-existent file', function (): void {
            $this->loader->load('/nonexistent/file.neon');
        })->throws(InvalidNeonConfigurationException::class);
    });

    describe('encode', function (): void {
        test('encodes array to neon string', function (): void {
            $data = ['key' => 'value', 'nested' => ['a' => 1]];
            $encoded = $this->loader->encode($data);

            expect($encoded)->toBeString()
                ->and($encoded)->toContain('key: value');
        });
    });
});
