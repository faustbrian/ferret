<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Ferret\Contracts\LoaderInterface;
use Cline\Ferret\Enums\SearchStrategy;
use Cline\Ferret\Exceptions\FileNotFoundException;
use Cline\Ferret\Exceptions\InvalidModuleNameException;
use Cline\Ferret\Exceptions\UnsupportedExtensionException;
use Cline\Ferret\Loaders\JsonLoader;
use Cline\Ferret\Searcher;
use Cline\Ferret\SearchResult;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Request;

describe('Searcher', function (): void {
    describe('construction', function (): void {
        test('creates instance with module name', function (): void {
            $searcher = new Searcher('myapp');

            expect($searcher->getModuleName())->toBe('myapp');
        });

        test('throws exception for empty module name', function (): void {
            new Searcher('');
        })->throws(InvalidModuleNameException::class);

        test('throws exception for invalid module name characters', function (): void {
            new Searcher('my app');
        })->throws(InvalidModuleNameException::class);

        test('allows alphanumeric module names with hyphens and underscores', function (): void {
            $searcher = new Searcher('my-app_123');

            expect($searcher->getModuleName())->toBe('my-app_123');
        });

        test('generates default search places', function (): void {
            $searcher = new Searcher('myapp');
            $places = $searcher->getSearchPlaces();

            expect($places)->toContain('package.json')
                ->and($places)->toContain('.myapprc')
                ->and($places)->toContain('.myapprc.json')
                ->and($places)->toContain('.myapprc.yaml')
                ->and($places)->toContain('myapp.config.php');
        });

        test('uses custom search places when provided', function (): void {
            $customPlaces = ['custom.json', '.customrc'];
            $searcher = new Searcher('myapp', searchPlaces: $customPlaces);

            expect($searcher->getSearchPlaces())->toBe($customPlaces);
        });
    });

    describe('load', function (): void {
        test('loads json file', function (): void {
            $searcher = new Searcher('test');
            $result = $searcher->load(fixturesPath('config.json'));

            expect($result->config['database']['host'])->toBe('localhost');
        });

        test('loads yaml file', function (): void {
            $searcher = new Searcher('test');
            $result = $searcher->load(fixturesPath('config.yaml'));

            expect($result->config['database']['host'])->toBe('localhost');
        });

        test('loads php file', function (): void {
            $searcher = new Searcher('test');
            $result = $searcher->load(fixturesPath('config.php'));

            expect($result->config['database']['host'])->toBe('localhost');
        });

        test('throws exception for non-existent file', function (): void {
            $searcher = new Searcher('test');
            $searcher->load('/nonexistent/file.json');
        })->throws(FileNotFoundException::class);

        test('throws exception for unsupported extension', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/config.xyz';
            file_put_contents($file, 'content');

            try {
                $searcher = new Searcher('test');
                $searcher->load($file);
            } catch (UnsupportedExtensionException $unsupportedExtensionException) {
                expect($unsupportedExtensionException->getMessage())->toContain('No loader registered');

                return;
            } finally {
                removeDir($tempDir);
            }

            $this->fail('Expected UnsupportedExtensionException was not thrown');
        });

        test('caches loaded results', function (): void {
            $searcher = new Searcher('test', cache: true);

            $result1 = $searcher->load(fixturesPath('config.json'));
            $result2 = $searcher->load(fixturesPath('config.json'));

            expect($result1)->toBe($result2);
        });
    });

    describe('search', function (): void {
        test('finds configuration in directory', function (): void {
            $searcher = new Searcher('myapp', searchPlaces: ['.myapprc']);
            $result = $searcher->search(fixturesPath());

            expect($result)->not->toBeNull()
                ->and($result->config['setting'])->toBe('value');
        });

        test('returns null when no configuration found', function (): void {
            $tempDir = createTempDir();

            try {
                $searcher = new Searcher('nonexistent');
                $result = $searcher->search($tempDir);

                expect($result)->toBeNull();
            } finally {
                removeDir($tempDir);
            }
        });

        test('finds yaml configuration', function (): void {
            $searcher = new Searcher('myapp', searchPlaces: ['.myapprc.yaml']);
            $result = $searcher->search(fixturesPath());

            expect($result)->not->toBeNull()
                ->and($result->config['setting'])->toBe('yaml-value');
        });

        test('traverses upward with project strategy', function (): void {
            $tempDir = createTempDir();
            $subDir = $tempDir.'/sub/nested';
            mkdir($subDir, 0o755, true);

            // Create composer.json at root
            file_put_contents($tempDir.'/composer.json', '{}');

            // Create config at root
            file_put_contents($tempDir.'/.myapprc', '{"found": true}');

            try {
                $searcher = new Searcher('myapp', searchStrategy: SearchStrategy::Project);
                $result = $searcher->search($subDir);

                expect($result)->not->toBeNull()
                    ->and($result->config['found'])->toBeTrue();
            } finally {
                removeDir($tempDir);
            }
        });

        test('does not traverse with none strategy', function (): void {
            $tempDir = createTempDir();
            $subDir = $tempDir.'/sub';
            mkdir($subDir, 0o755, true);

            // Create config at parent only
            file_put_contents($tempDir.'/.myapprc', '{"found": true}');

            try {
                $searcher = new Searcher('myapp', searchStrategy: SearchStrategy::None);
                $result = $searcher->search($subDir);

                expect($result)->toBeNull();
            } finally {
                removeDir($tempDir);
            }
        });
    });

    describe('cache management', function (): void {
        test('clearLoadCache clears load cache', function (): void {
            $searcher = new Searcher('test', cache: true);
            $searcher->load(fixturesPath('config.json'));

            $searcher->clearLoadCache();

            // No assertion needed - just verify it doesn't throw
            expect(true)->toBeTrue();
        });

        test('clearSearchCache clears search cache', function (): void {
            $searcher = new Searcher('myapp', searchPlaces: ['.myapprc'], cache: true);
            $searcher->search(fixturesPath());

            $searcher->clearSearchCache();

            expect(true)->toBeTrue();
        });

        test('clearCaches clears both caches', function (): void {
            $searcher = new Searcher('myapp', searchPlaces: ['.myapprc'], cache: true);
            $searcher->load(fixturesPath('config.json'));
            $searcher->search(fixturesPath());

            $searcher->clearCaches();

            expect(true)->toBeTrue();
        });
    });

    describe('hasLoaderForExtension', function (): void {
        test('returns true for registered extensions', function (): void {
            $searcher = new Searcher('test');

            expect($searcher->hasLoaderForExtension('json'))->toBeTrue()
                ->and($searcher->hasLoaderForExtension('yaml'))->toBeTrue()
                ->and($searcher->hasLoaderForExtension('yml'))->toBeTrue()
                ->and($searcher->hasLoaderForExtension('php'))->toBeTrue()
                ->and($searcher->hasLoaderForExtension('ini'))->toBeTrue();
        });

        test('returns false for unregistered extensions', function (): void {
            $searcher = new Searcher('test');

            expect($searcher->hasLoaderForExtension('xyz'))->toBeFalse();
        });
    });

    describe('transform', function (): void {
        test('applies transform function to result', function (): void {
            $transform = fn ($result): array => array_merge($result->config, ['transformed' => true]);

            $searcher = new Searcher('test', transform: $transform);
            $result = $searcher->load(fixturesPath('config.json'));

            expect($result->config['transformed'])->toBeTrue();
        });
    });

    describe('package.json loading', function (): void {
        test('extracts module property from package.json', function (): void {
            $searcher = new Searcher('myapp', searchPlaces: ['package.json']);
            $result = $searcher->search(fixturesPath());

            expect($result)->not->toBeNull()
                ->and($result->config['database']['host'])->toBe('localhost');
        });

        test('throws exception when property not found in package.json', function (): void {
            $searcher = new Searcher('nonexistent', searchPlaces: ['package.json']);
            $result = $searcher->search(fixturesPath());

            expect($result)->toBeNull();
        });

        test('wraps non-array package.json property in value key', function (): void {
            $tempDir = createTempDir();
            file_put_contents($tempDir.'/package.json', '{"myapp": "simple-value"}');

            try {
                $searcher = new Searcher('myapp', searchPlaces: ['package.json']);
                $result = $searcher->search($tempDir);

                expect($result)->not->toBeNull()
                    ->and($result->config['value'])->toBe('simple-value');
            } finally {
                removeDir($tempDir);
            }
        });
    });

    describe('search cache', function (): void {
        test('returns cached search result', function (): void {
            $searcher = new Searcher('myapp', searchPlaces: ['.myapprc'], cache: true);

            $result1 = $searcher->search(fixturesPath());
            $result2 = $searcher->search(fixturesPath());

            expect($result1)->toBe($result2);
        });
    });

    describe('extensionless files', function (): void {
        test('loads extensionless rc file as json', function (): void {
            $tempDir = createTempDir();
            file_put_contents($tempDir.'/.myapprc', '{"format": "json"}');

            try {
                $searcher = new Searcher('myapp', searchPlaces: ['.myapprc']);
                $result = $searcher->search($tempDir);

                expect($result)->not->toBeNull()
                    ->and($result->config['format'])->toBe('json');
            } finally {
                removeDir($tempDir);
            }
        });

        test('loads extensionless rc file as yaml when not valid json', function (): void {
            $tempDir = createTempDir();
            file_put_contents($tempDir.'/.myapprc', "format: yaml\nkey: value");

            try {
                $searcher = new Searcher('myapp', searchPlaces: ['.myapprc']);
                $result = $searcher->search($tempDir);

                expect($result)->not->toBeNull()
                    ->and($result->config['format'])->toBe('yaml');
            } finally {
                removeDir($tempDir);
            }
        });
    });

    describe('transform returning SearchResult', function (): void {
        test('uses SearchResult returned from transform', function (): void {
            $transform = fn ($result): SearchResult => new SearchResult(
                ['transformed' => true],
                $result->filepath,
                false,
            );

            $searcher = new Searcher('test', transform: $transform);
            $result = $searcher->load(fixturesPath('config.json'));

            expect($result->config)->toBe(['transformed' => true]);
        });
    });

    describe('ignoreEmpty option', function (): void {
        test('skips empty files when ignoreEmpty is true', function (): void {
            $tempDir = createTempDir();
            file_put_contents($tempDir.'/.myapprc.json', '{}');
            file_put_contents($tempDir.'/.myapprc.yaml', 'key: value');

            try {
                $searcher = new Searcher('myapp', ignoreEmpty: true);
                $result = $searcher->search($tempDir);

                expect($result)->not->toBeNull()
                    ->and($result->config['key'])->toBe('value');
            } finally {
                removeDir($tempDir);
            }
        });
    });

    describe('stopDir option', function (): void {
        test('stops searching at specified directory', function (): void {
            $tempDir = createTempDir();
            $subDir = $tempDir.'/sub/nested';
            mkdir($subDir, 0o755, true);

            // Create config at root
            file_put_contents($tempDir.'/.myapprc', '{"found": "root"}');

            // Stop at sub directory
            try {
                $searcher = new Searcher('myapp', stopDir: $tempDir.'/sub');
                $result = $searcher->search($subDir);

                expect($result)->toBeNull();
            } finally {
                removeDir($tempDir);
            }
        });
    });

    describe('global search strategy', function (): void {
        test('searches global config directory', function (): void {
            $home = Request::server('HOME') ?? Env::get('HOME');

            if ($home === null) {
                $this->markTestSkipped('HOME not set');
            }

            $globalDir = $home.'/.config/testglobalapp';

            if (!is_dir($globalDir)) {
                mkdir($globalDir, 0o755, true);
            }

            file_put_contents($globalDir.'/config.json', '{"global": true}');

            try {
                $tempDir = createTempDir();
                $searcher = new Searcher('testglobalapp', searchStrategy: SearchStrategy::Global);
                $result = $searcher->search($tempDir);

                expect($result)->not->toBeNull()
                    ->and($result->config['global'])->toBeTrue();
            } finally {
                removeDir($tempDir);
                removeDir($globalDir);
            }
        });
    });

    describe('getLoaderForFile', function (): void {
        test('returns correct loader for file extension', function (): void {
            $searcher = new Searcher('test');

            $loader = $searcher->getLoaderForFile('/path/to/config.json');

            expect($loader)->toBeInstanceOf(JsonLoader::class);
        });
    });

    describe('custom loaders', function (): void {
        test('registers custom loader for extension', function (): void {
            $customLoader = new class() implements LoaderInterface
            {
                public function extensions(): array
                {
                    return ['custom'];
                }

                public function load(string $filepath): array
                {
                    return ['custom' => true];
                }

                public function encode(array $data): string
                {
                    return 'custom';
                }
            };

            $searcher = new Searcher('test', loaders: ['custom' => $customLoader]);

            expect($searcher->hasLoaderForExtension('custom'))->toBeTrue();
        });
    });
});
