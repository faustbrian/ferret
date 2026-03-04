<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Ferret\Exceptions\LoaderException;
use Cline\Ferret\Loaders\IniLoader;

describe('IniLoader', function (): void {
    beforeEach(function (): void {
        $this->loader = new IniLoader();
    });

    describe('extensions', function (): void {
        test('returns ini extension', function (): void {
            expect($this->loader->extensions())->toBe(['ini']);
        });
    });

    describe('load', function (): void {
        test('loads valid ini file with sections', function (): void {
            $result = $this->loader->load(fixturesPath('config.ini'));

            expect($result)->toBeArray()
                ->and($result['database']['host'])->toBe('localhost')
                ->and($result['database']['port'])->toBe(5_432);
        });

        test('throws exception for invalid ini', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/invalid.ini';
            // Create truly invalid INI content
            file_put_contents($file, "[section\nkey=value");

            try {
                $this->loader->load($file);
            } catch (LoaderException $loaderException) {
                expect($loaderException->getMessage())->toContain('Failed to parse INI');

                return;
            } finally {
                removeDir($tempDir);
            }

            $this->fail('Expected LoaderException was not thrown');
        });
    });

    describe('encode', function (): void {
        test('encodes array to ini string', function (): void {
            $data = ['section' => ['key' => 'value']];
            $encoded = $this->loader->encode($data);

            expect($encoded)->toBeString()
                ->and($encoded)->toContain('[section]')
                ->and($encoded)->toContain('key = "value"');
        });

        test('encodes boolean values correctly', function (): void {
            $data = ['enabled' => true, 'disabled' => false];
            $encoded = $this->loader->encode($data);

            expect($encoded)->toContain('enabled = true')
                ->and($encoded)->toContain('disabled = false');
        });

        test('encodes numeric values without quotes', function (): void {
            $data = ['port' => 5_432, 'ratio' => 1.5];
            $encoded = $this->loader->encode($data);

            expect($encoded)->toContain('port = 5432')
                ->and($encoded)->toContain('ratio = 1.5');
        });

        test('encodes null values', function (): void {
            $data = ['empty' => null];
            $encoded = $this->loader->encode($data);

            expect($encoded)->toContain('empty = null');
        });

        test('encodes array values in section', function (): void {
            $data = ['section' => ['items' => ['a', 'b', 'c']]];
            $encoded = $this->loader->encode($data);

            expect($encoded)->toContain('items[] = "a"')
                ->and($encoded)->toContain('items[] = "b"')
                ->and($encoded)->toContain('items[] = "c"');
        });

        test('escapes quotes in string values', function (): void {
            $data = ['message' => 'Hello "World"'];
            $encoded = $this->loader->encode($data);

            expect($encoded)->toContain('message = "Hello \"World\""');
        });
    });

    describe('load edge cases', function (): void {
        test('returns empty array for empty file', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/empty.ini';
            file_put_contents($file, '');

            try {
                $result = $this->loader->load($file);
                expect($result)->toBe([]);
            } finally {
                removeDir($tempDir);
            }
        });
    });
});
