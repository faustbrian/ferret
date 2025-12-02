<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Ferret\Exceptions\LoaderException;
use Cline\Ferret\Loaders\PhpLoader;

describe('PhpLoader', function (): void {
    beforeEach(function (): void {
        $this->loader = new PhpLoader();
    });

    describe('extensions', function (): void {
        test('returns php extension', function (): void {
            expect($this->loader->extensions())->toBe(['php']);
        });
    });

    describe('load', function (): void {
        test('loads valid php file', function (): void {
            $result = $this->loader->load(fixturesPath('config.php'));

            expect($result)->toBeArray()
                ->and($result['database']['host'])->toBe('localhost')
                ->and($result['database']['port'])->toBe(5_432)
                ->and($result['debug'])->toBeTrue();
        });

        test('throws exception when file does not return array', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/string.php';
            file_put_contents($file, '<?php return "not an array";');

            try {
                $this->loader->load($file);
            } catch (LoaderException $loaderException) {
                expect($loaderException->getMessage())->toContain('must return an array');

                return;
            } finally {
                removeDir($tempDir);
            }

            $this->fail('Expected LoaderException was not thrown');
        });

        test('throws exception for invalid php', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/invalid.php';
            file_put_contents($file, '<?php invalid syntax here');

            try {
                $this->loader->load($file);
            } catch (LoaderException $loaderException) {
                expect($loaderException->getMessage())->toContain('Failed to load PHP');

                return;
            } finally {
                removeDir($tempDir);
            }

            $this->fail('Expected LoaderException was not thrown');
        });
    });

    describe('encode', function (): void {
        test('encodes array to php return statement', function (): void {
            $data = ['key' => 'value'];
            $encoded = $this->loader->encode($data);

            expect($encoded)->toBeString()
                ->and($encoded)->toContain('<?php')
                ->and($encoded)->toContain('return');
        });
    });
});
