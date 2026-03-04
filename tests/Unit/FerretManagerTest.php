<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Ferret\Exceptions\DecryptionFailedException;
use Cline\Ferret\Exceptions\DirectoryNotFoundException;
use Cline\Ferret\Exceptions\EncryptionFailedException;
use Cline\Ferret\Exceptions\FileNotFoundException;
use Cline\Ferret\Exceptions\InvalidConfigurationException;
use Cline\Ferret\Exceptions\ModuleNotFoundException;
use Cline\Ferret\Exceptions\ReadOnlyConfigurationException;
use Cline\Ferret\Exceptions\UnsupportedExtensionException;
use Cline\Ferret\FerretManager;
use Cline\Ferret\Searcher;

describe('FerretManager', function (): void {
    describe('explorer', function (): void {
        test('creates searcher for module', function (): void {
            $manager = new FerretManager();
            $searcher = $manager->explorer('myapp');

            expect($searcher)->toBeInstanceOf(Searcher::class)
                ->and($searcher->getModuleName())->toBe('myapp');
        });

        test('returns same searcher instance for same module', function (): void {
            $manager = new FerretManager();

            $searcher1 = $manager->explorer('myapp');
            $searcher2 = $manager->explorer('myapp');

            expect($searcher1)->toBe($searcher2);
        });

        test('applies default options to searchers', function (): void {
            $manager = new FerretManager([
                'cache' => false,
            ]);

            $searcher = $manager->explorer('myapp');

            expect($searcher)->toBeInstanceOf(Searcher::class);
        });
    });

    describe('search', function (): void {
        test('finds and stores configuration', function (): void {
            $manager = new FerretManager();
            $result = $manager->search('myapp', fixturesPath());

            expect($result)->not->toBeNull()
                ->and($manager->isLoaded('myapp'))->toBeTrue();
        });

        test('returns null when not found', function (): void {
            $tempDir = createTempDir();

            try {
                $manager = new FerretManager();
                $result = $manager->search('nonexistent', $tempDir);

                expect($result)->toBeNull();
            } finally {
                removeDir($tempDir);
            }
        });
    });

    describe('load', function (): void {
        test('loads specific configuration file', function (): void {
            $manager = new FerretManager();
            $result = $manager->load(fixturesPath('config.json'), 'myconfig');

            expect($result->config['database']['host'])->toBe('localhost')
                ->and($manager->isLoaded('myconfig'))->toBeTrue();
        });

        test('throws exception for non-existent file', function (): void {
            $manager = new FerretManager();
            $manager->load('/nonexistent/file.json');
        })->throws(FileNotFoundException::class);
    });

    describe('get', function (): void {
        beforeEach(function (): void {
            $this->manager = new FerretManager();
            $this->manager->load(fixturesPath('config.json'), 'test');
        });

        test('returns entire configuration', function (): void {
            $config = $this->manager->get('test');

            expect($config)->toBeArray()
                ->and($config['database'])->toBeArray();
        });

        test('returns value for key', function (): void {
            expect($this->manager->get('test', 'debug'))->toBeTrue();
        });

        test('returns nested value using dot notation', function (): void {
            expect($this->manager->get('test', 'database.host'))->toBe('localhost')
                ->and($this->manager->get('test', 'database.port'))->toBe(5_432);
        });

        test('returns default for non-existent key', function (): void {
            expect($this->manager->get('test', 'nonexistent', 'default'))->toBe('default');
        });

        test('returns default when module not loaded', function (): void {
            expect($this->manager->get('notloaded', 'key', 'default'))->toBe('default');
        });
    });

    describe('set', function (): void {
        beforeEach(function (): void {
            $this->manager = new FerretManager();
            $this->manager->load(fixturesPath('config.json'), 'test');
        });

        test('sets simple value', function (): void {
            $this->manager->set('test', 'newkey', 'newvalue');

            expect($this->manager->get('test', 'newkey'))->toBe('newvalue');
        });

        test('sets nested value using dot notation', function (): void {
            $this->manager->set('test', 'database.timeout', 30);

            expect($this->manager->get('test', 'database.timeout'))->toBe(30);
        });

        test('overwrites existing value', function (): void {
            $this->manager->set('test', 'database.host', '127.0.0.1');

            expect($this->manager->get('test', 'database.host'))->toBe('127.0.0.1');
        });

        test('creates new module config if not loaded', function (): void {
            $this->manager->set('newmodule', 'key', 'value');

            expect($this->manager->get('newmodule', 'key'))->toBe('value');
        });

        test('returns self for chaining', function (): void {
            $result = $this->manager->set('test', 'key', 'value');

            expect($result)->toBe($this->manager);
        });
    });

    describe('has', function (): void {
        beforeEach(function (): void {
            $this->manager = new FerretManager();
            $this->manager->load(fixturesPath('config.json'), 'test');
        });

        test('returns true for existing key', function (): void {
            expect($this->manager->has('test', 'database'))->toBeTrue();
        });

        test('returns true for nested key', function (): void {
            expect($this->manager->has('test', 'database.host'))->toBeTrue();
        });

        test('returns false for non-existent key', function (): void {
            expect($this->manager->has('test', 'nonexistent'))->toBeFalse();
        });

        test('returns false for non-loaded module', function (): void {
            expect($this->manager->has('notloaded', 'key'))->toBeFalse();
        });
    });

    describe('forget', function (): void {
        beforeEach(function (): void {
            $this->manager = new FerretManager();
            $this->manager->load(fixturesPath('config.json'), 'test');
        });

        test('removes key from configuration', function (): void {
            $this->manager->forget('test', 'debug');

            expect($this->manager->has('test', 'debug'))->toBeFalse();
        });

        test('removes nested key', function (): void {
            $this->manager->forget('test', 'database.host');

            expect($this->manager->has('test', 'database.host'))->toBeFalse()
                ->and($this->manager->has('test', 'database.port'))->toBeTrue();
        });

        test('returns self for chaining', function (): void {
            $result = $this->manager->forget('test', 'key');

            expect($result)->toBe($this->manager);
        });

        test('returns self when forgetting from non-loaded module', function (): void {
            $manager = new FerretManager();
            $result = $manager->forget('notloaded', 'key');

            expect($result)->toBe($manager);
        });

        test('returns self when module config not found after search', function (): void {
            $tempDir = createTempDir();

            try {
                $manager = new FerretManager();
                $result = $manager->forget('nonexistent', 'key');

                expect($result)->toBe($manager);
            } finally {
                removeDir($tempDir);
            }
        });
    });

    describe('push', function (): void {
        test('pushes value onto array', function (): void {
            $manager = new FerretManager();
            $manager->set('test', 'items', ['a', 'b']);
            $manager->push('test', 'items', 'c');

            expect($manager->get('test', 'items'))->toBe(['a', 'b', 'c']);
        });

        test('converts non-array to array before pushing', function (): void {
            $manager = new FerretManager();
            $manager->set('test', 'item', 'single');
            $manager->push('test', 'item', 'new');

            expect($manager->get('test', 'item'))->toBe(['single', 'new']);
        });
    });

    describe('prepend', function (): void {
        test('prepends value to array', function (): void {
            $manager = new FerretManager();
            $manager->set('test', 'items', ['b', 'c']);
            $manager->prepend('test', 'items', 'a');

            expect($manager->get('test', 'items'))->toBe(['a', 'b', 'c']);
        });

        test('converts non-array to array before prepending', function (): void {
            $manager = new FerretManager();
            $manager->set('test', 'item', 'existing');
            $manager->prepend('test', 'item', 'first');

            expect($manager->get('test', 'item'))->toBe(['first', 'existing']);
        });
    });

    describe('isDirty', function (): void {
        test('returns false for unmodified config', function (): void {
            $manager = new FerretManager();
            $manager->load(fixturesPath('config.json'), 'test');

            expect($manager->isDirty('test'))->toBeFalse();
        });

        test('returns true for modified config', function (): void {
            $manager = new FerretManager();
            $manager->load(fixturesPath('config.json'), 'test');
            $manager->set('test', 'newkey', 'newvalue');

            expect($manager->isDirty('test'))->toBeTrue();
        });

        test('returns false for non-loaded module', function (): void {
            $manager = new FerretManager();

            expect($manager->isDirty('notloaded'))->toBeFalse();
        });
    });

    describe('rollback', function (): void {
        test('discards unsaved changes', function (): void {
            $manager = new FerretManager();
            $manager->load(fixturesPath('config.json'), 'test');
            $manager->set('test', 'database.host', 'modified');

            $manager->rollback('test');

            expect($manager->get('test', 'database.host'))->toBe('localhost')
                ->and($manager->isDirty('test'))->toBeFalse();
        });
    });

    describe('save', function (): void {
        test('saves configuration to disk', function (): void {
            $tempDir = createTempDir();
            $configFile = $tempDir.'/config.json';
            file_put_contents($configFile, '{"key": "original"}');

            try {
                $manager = new FerretManager();
                $manager->load($configFile, 'test');
                $manager->set('test', 'key', 'modified');
                $manager->save('test');

                $contents = json_decode(file_get_contents($configFile), true);
                expect($contents['key'])->toBe('modified');
            } finally {
                removeDir($tempDir);
            }
        });

        test('saves to custom filepath', function (): void {
            $tempDir = createTempDir();
            $originalFile = $tempDir.'/original.json';
            $newFile = $tempDir.'/new.json';
            file_put_contents($originalFile, '{"key": "value"}');

            try {
                $manager = new FerretManager();
                $manager->load($originalFile, 'test');
                $manager->save('test', $newFile);

                expect(file_exists($newFile))->toBeTrue();
            } finally {
                removeDir($tempDir);
            }
        });

        test('throws exception when module not loaded', function (): void {
            $manager = new FerretManager();
            $manager->save('notloaded');
        })->throws(ModuleNotFoundException::class);

        test('throws exception when directory is not writable', function (): void {
            // Skip this test when running as root (e.g., in Docker)
            if (posix_getuid() === 0) {
                $this->markTestSkipped('Cannot test write permissions as root');
            }

            $tempDir = createTempDir();
            $configFile = $tempDir.'/config.json';
            file_put_contents($configFile, '{"key": "value"}');

            // Make directory read-only
            chmod($tempDir, 0o555);

            try {
                $manager = new FerretManager();
                $manager->load($configFile, 'test');
                $manager->save('test');
            } catch (ReadOnlyConfigurationException $readOnlyConfigurationException) {
                expect($readOnlyConfigurationException->getMessage())->toContain('read-only');

                return;
            } finally {
                chmod($tempDir, 0o755);
                removeDir($tempDir);
            }

            $this->fail('Expected ReadOnlyConfigurationException was not thrown');
        });
    });

    describe('filepath', function (): void {
        test('returns filepath for loaded configuration', function (): void {
            $manager = new FerretManager();
            $manager->load(fixturesPath('config.json'), 'test');

            expect($manager->filepath('test'))->toBe(fixturesPath('config.json'));
        });

        test('returns null for non-loaded module', function (): void {
            $manager = new FerretManager();

            expect($manager->filepath('notloaded'))->toBeNull();
        });
    });

    describe('original', function (): void {
        test('returns original search result', function (): void {
            $manager = new FerretManager();
            $manager->load(fixturesPath('config.json'), 'test');
            $manager->set('test', 'modified', true);

            $original = $manager->original('test');

            expect($original)->not->toBeNull()
                ->and($original->config)->not->toHaveKey('modified');
        });
    });

    describe('clearCache', function (): void {
        test('clears all caches when no module specified', function (): void {
            $manager = new FerretManager();
            $manager->load(fixturesPath('config.json'), 'test');

            $manager->clearCache();

            expect($manager->isLoaded('test'))->toBeFalse();
        });

        test('clears cache for specific module', function (): void {
            $manager = new FerretManager();
            $manager->load(fixturesPath('config.json'), 'test1');
            $manager->load(fixturesPath('config.yaml'), 'test2');

            $manager->clearCache('test1');

            expect($manager->isLoaded('test1'))->toBeFalse()
                ->and($manager->isLoaded('test2'))->toBeTrue();
        });

        test('does nothing when clearing cache for non-existent module', function (): void {
            $manager = new FerretManager();
            $result = $manager->clearCache('nonexistent');

            expect($result)->toBe($manager);
        });
    });

    describe('clearSearchCache', function (): void {
        test('clears all search caches when no module specified', function (): void {
            $manager = new FerretManager();
            $manager->search('myapp', fixturesPath());

            $result = $manager->clearSearchCache();

            expect($result)->toBe($manager);
        });

        test('clears search cache for specific module', function (): void {
            $manager = new FerretManager();
            $manager->search('myapp', fixturesPath());

            $result = $manager->clearSearchCache('myapp');

            expect($result)->toBe($manager);
        });

        test('does nothing when clearing search cache for non-existent module', function (): void {
            $manager = new FerretManager();
            $result = $manager->clearSearchCache('nonexistent');

            expect($result)->toBe($manager);
        });
    });

    describe('clearLoadCache', function (): void {
        test('clears all load caches when no module specified', function (): void {
            $manager = new FerretManager();
            $manager->load(fixturesPath('config.json'), 'test');

            $result = $manager->clearLoadCache();

            expect($result)->toBe($manager);
        });

        test('clears load cache for specific module', function (): void {
            $manager = new FerretManager();
            $manager->load(fixturesPath('config.json'), 'test');

            $result = $manager->clearLoadCache('test');

            expect($result)->toBe($manager);
        });

        test('does nothing when clearing load cache for non-existent module', function (): void {
            $manager = new FerretManager();
            $result = $manager->clearLoadCache('nonexistent');

            expect($result)->toBe($manager);
        });
    });

    describe('loadedModules', function (): void {
        test('returns list of loaded module names', function (): void {
            $manager = new FerretManager();
            $manager->load(fixturesPath('config.json'), 'module1');
            $manager->load(fixturesPath('config.yaml'), 'module2');

            expect($manager->loadedModules())->toBe(['module1', 'module2']);
        });

        test('returns empty array when no modules loaded', function (): void {
            $manager = new FerretManager();

            expect($manager->loadedModules())->toBe([]);
        });
    });

    describe('loadDirectory', function (): void {
        test('loads all configuration files from directory', function (): void {
            $manager = new FerretManager();
            $result = $manager->loadDirectory(fixturesPath('carriers'), 'carriers');

            expect($result->isEmpty)->toBeFalse()
                ->and($manager->isLoaded('carriers'))->toBeTrue()
                ->and($manager->has('carriers', 'ups'))->toBeTrue()
                ->and($manager->has('carriers', 'fedex'))->toBeTrue()
                ->and($manager->has('carriers', 'dhl'))->toBeTrue();
        });

        test('loads files matching pattern', function (): void {
            $manager = new FerretManager();
            $result = $manager->loadDirectory(fixturesPath('carriers'), 'neon-only', '*.neon');

            expect($manager->has('neon-only', 'ups'))->toBeTrue()
                ->and($manager->has('neon-only', 'fedex'))->toBeTrue()
                ->and($manager->has('neon-only', 'dhl'))->toBeFalse();
        });

        test('supports dot notation access across files', function (): void {
            $manager = new FerretManager();
            $manager->loadDirectory(fixturesPath('carriers'), 'carriers');

            expect($manager->get('carriers', 'ups.name'))->toBe('UPS')
                ->and($manager->get('carriers', 'ups.rates.domestic'))->toBe(5.99)
                ->and($manager->get('carriers', 'fedex.code'))->toBe('fedex')
                ->and($manager->get('carriers', 'dhl.rates.international'))->toBe(12.99);
        });

        test('supports mutation via dot notation', function (): void {
            $manager = new FerretManager();
            $manager->loadDirectory(fixturesPath('carriers'), 'carriers');

            $manager->set('carriers', 'ups.rates.domestic', 7.99);

            expect($manager->get('carriers', 'ups.rates.domestic'))->toBe(7.99)
                ->and($manager->isDirty('carriers'))->toBeTrue();
        });

        test('returns empty result for empty directory', function (): void {
            $tempDir = createTempDir();

            try {
                $manager = new FerretManager();
                $result = $manager->loadDirectory($tempDir, 'empty');

                expect($result->isEmpty)->toBeTrue()
                    ->and($manager->get('empty'))->toBe([]);
            } finally {
                removeDir($tempDir);
            }
        });

        test('throws exception for non-existent directory', function (): void {
            $manager = new FerretManager();
            $manager->loadDirectory('/nonexistent/directory', 'test');
        })->throws(DirectoryNotFoundException::class);

        test('uses custom key pattern', function (): void {
            $tempDir = createTempDir();
            file_put_contents($tempDir.'/carrier-ups-v1.json', '{"name": "UPS"}');
            file_put_contents($tempDir.'/carrier-fedex-v1.json', '{"name": "FedEx"}');

            try {
                $manager = new FerretManager();
                $manager->loadDirectory($tempDir, 'carriers', '*.json', '/carrier-([a-z]+)-v\d+\.json/');

                expect($manager->has('carriers', 'ups'))->toBeTrue()
                    ->and($manager->has('carriers', 'fedex'))->toBeTrue()
                    ->and($manager->get('carriers', 'ups.name'))->toBe('UPS');
            } finally {
                removeDir($tempDir);
            }
        });

        test('skips unsupported file types', function (): void {
            $tempDir = createTempDir();
            file_put_contents($tempDir.'/config.json', '{"valid": true}');
            file_put_contents($tempDir.'/readme.txt', 'Not a config file');

            try {
                $manager = new FerretManager();
                $result = $manager->loadDirectory($tempDir, 'test');

                expect($manager->has('test', 'config'))->toBeTrue()
                    ->and($manager->has('test', 'readme'))->toBeFalse();
            } finally {
                removeDir($tempDir);
            }
        });
    });

    describe('convert', function (): void {
        test('converts json to yaml', function (): void {
            $tempDir = createTempDir();
            $destFile = $tempDir.'/converted.yaml';

            try {
                $manager = new FerretManager();
                $manager->convert(fixturesPath('config.json'), $destFile);

                expect(file_exists($destFile))->toBeTrue();

                $contents = file_get_contents($destFile);
                expect($contents)->toContain('database:')
                    ->and($contents)->toContain('host: localhost');
            } finally {
                removeDir($tempDir);
            }
        });

        test('converts yaml to json', function (): void {
            $tempDir = createTempDir();
            $destFile = $tempDir.'/converted.json';

            try {
                $manager = new FerretManager();
                $manager->convert(fixturesPath('config.yaml'), $destFile);

                expect(file_exists($destFile))->toBeTrue();

                $contents = json_decode(file_get_contents($destFile), true);
                expect($contents['database']['host'])->toBe('localhost');
            } finally {
                removeDir($tempDir);
            }
        });

        test('converts neon to json', function (): void {
            $tempDir = createTempDir();
            $destFile = $tempDir.'/converted.json';

            try {
                $manager = new FerretManager();
                $manager->convert(fixturesPath('config.neon'), $destFile);

                expect(file_exists($destFile))->toBeTrue();

                $contents = json_decode(file_get_contents($destFile), true);
                expect($contents['database']['host'])->toBe('localhost');
            } finally {
                removeDir($tempDir);
            }
        });

        test('converts json to xml', function (): void {
            $tempDir = createTempDir();
            $destFile = $tempDir.'/converted.xml';

            try {
                $manager = new FerretManager();
                $manager->convert(fixturesPath('config.json'), $destFile);

                expect(file_exists($destFile))->toBeTrue();

                $contents = file_get_contents($destFile);
                expect($contents)->toContain('<?xml version="1.0"')
                    ->and($contents)->toContain('<database>');
            } finally {
                removeDir($tempDir);
            }
        });

        test('returns self for chaining', function (): void {
            $tempDir = createTempDir();

            try {
                $manager = new FerretManager();
                $result = $manager->convert(fixturesPath('config.json'), $tempDir.'/out.yaml');

                expect($result)->toBe($manager);
            } finally {
                removeDir($tempDir);
            }
        });

        test('throws exception for invalid destination directory', function (): void {
            $manager = new FerretManager();
            $manager->convert(fixturesPath('config.json'), '/nonexistent/dir/file.yaml');
        })->throws(ReadOnlyConfigurationException::class);
    });

    describe('toFormat', function (): void {
        beforeEach(function (): void {
            $this->manager = new FerretManager();
            $this->manager->load(fixturesPath('config.json'), 'test');
        });

        test('converts to json string', function (): void {
            $json = $this->manager->toFormat('test', 'json');

            expect($json)->toBeString();

            $decoded = json_decode($json, true);
            expect($decoded['database']['host'])->toBe('localhost');
        });

        test('converts to yaml string', function (): void {
            $yaml = $this->manager->toFormat('test', 'yaml');

            expect($yaml)->toBeString()
                ->and($yaml)->toContain('database:')
                ->and($yaml)->toContain('host: localhost');
        });

        test('converts to neon string', function (): void {
            $neon = $this->manager->toFormat('test', 'neon');

            expect($neon)->toBeString()
                ->and($neon)->toContain('database:')
                ->and($neon)->toContain('host: localhost');
        });

        test('converts to xml string', function (): void {
            $xml = $this->manager->toFormat('test', 'xml');

            expect($xml)->toBeString()
                ->and($xml)->toContain('<?xml version="1.0"')
                ->and($xml)->toContain('<database>');
        });

        test('throws exception for unsupported format', function (): void {
            $this->manager->toFormat('test', 'unsupported');
        })->throws(UnsupportedExtensionException::class);

        test('throws exception for non-loaded module', function (): void {
            $manager = new FerretManager();
            $manager->toFormat('notloaded', 'json');
        })->throws(ModuleNotFoundException::class);
    });

    describe('getLoader', function (): void {
        test('returns loader for valid extension', function (): void {
            $manager = new FerretManager();

            $jsonLoader = $manager->getLoader('json');
            $yamlLoader = $manager->getLoader('yaml');
            $neonLoader = $manager->getLoader('neon');
            $xmlLoader = $manager->getLoader('xml');

            expect($jsonLoader->extensions())->toContain('json')
                ->and($yamlLoader->extensions())->toContain('yaml')
                ->and($neonLoader->extensions())->toContain('neon')
                ->and($xmlLoader->extensions())->toContain('xml');
        });

        test('throws exception for unsupported extension', function (): void {
            $manager = new FerretManager();
            $manager->getLoader('unsupported');
        })->throws(UnsupportedExtensionException::class);
    });

    describe('combine', function (): void {
        test('combines multiple config files into one', function (): void {
            $tempDir = createTempDir();
            $file1 = $tempDir.'/config1.json';
            $file2 = $tempDir.'/config2.json';
            $output = $tempDir.'/combined.json';

            file_put_contents($file1, json_encode(['database' => ['host' => 'localhost']]));
            file_put_contents($file2, json_encode(['cache' => ['driver' => 'redis']]));

            try {
                $manager = new FerretManager();
                $manager->combine($output, [$file1, $file2]);

                expect(file_exists($output))->toBeTrue();

                $contents = json_decode(file_get_contents($output), true);
                expect($contents['database']['host'])->toBe('localhost')
                    ->and($contents['cache']['driver'])->toBe('redis');
            } finally {
                removeDir($tempDir);
            }
        });

        test('deep merges nested arrays by default', function (): void {
            $tempDir = createTempDir();
            $file1 = $tempDir.'/config1.json';
            $file2 = $tempDir.'/config2.json';
            $output = $tempDir.'/combined.json';

            file_put_contents($file1, json_encode(['database' => ['host' => 'localhost', 'port' => 5_432]]));
            file_put_contents($file2, json_encode(['database' => ['host' => 'production', 'name' => 'mydb']]));

            try {
                $manager = new FerretManager();
                $manager->combine($output, [$file1, $file2]);

                $contents = json_decode(file_get_contents($output), true);
                expect($contents['database']['host'])->toBe('production')
                    ->and($contents['database']['port'])->toBe(5_432)
                    ->and($contents['database']['name'])->toBe('mydb');
            } finally {
                removeDir($tempDir);
            }
        });

        test('shallow merges when deep is false', function (): void {
            $tempDir = createTempDir();
            $file1 = $tempDir.'/config1.json';
            $file2 = $tempDir.'/config2.json';
            $output = $tempDir.'/combined.json';

            file_put_contents($file1, json_encode(['database' => ['host' => 'localhost', 'port' => 5_432]]));
            file_put_contents($file2, json_encode(['database' => ['host' => 'production']]));

            try {
                $manager = new FerretManager();
                $manager->combine($output, [$file1, $file2], deep: false);

                $contents = json_decode(file_get_contents($output), true);
                expect($contents['database']['host'])->toBe('production')
                    ->and($contents['database'])->not->toHaveKey('port');
            } finally {
                removeDir($tempDir);
            }
        });

        test('converts between formats during combine', function (): void {
            $tempDir = createTempDir();
            $jsonFile = $tempDir.'/config1.json';
            $yamlFile = $tempDir.'/config2.yaml';
            $output = $tempDir.'/combined.yaml';

            file_put_contents($jsonFile, json_encode(['from_json' => true]));
            file_put_contents($yamlFile, "from_yaml: true\n");

            try {
                $manager = new FerretManager();
                $manager->combine($output, [$jsonFile, $yamlFile]);

                expect(file_exists($output))->toBeTrue();
                $contents = file_get_contents($output);
                expect($contents)->toContain('from_json: true')
                    ->and($contents)->toContain('from_yaml: true');
            } finally {
                removeDir($tempDir);
            }
        });

        test('throws exception for non-existent source file', function (): void {
            $tempDir = createTempDir();
            $output = $tempDir.'/combined.json';

            try {
                $manager = new FerretManager();
                $manager->combine($output, ['/nonexistent/file.json']);
            } finally {
                removeDir($tempDir);
            }
        })->throws(FileNotFoundException::class);

        test('throws exception for empty source array', function (): void {
            $tempDir = createTempDir();
            $output = $tempDir.'/combined.json';

            try {
                $manager = new FerretManager();
                $manager->combine($output, []);
            } finally {
                removeDir($tempDir);
            }
        })->throws(InvalidConfigurationException::class);
    });

    describe('encrypt and decrypt', function (): void {
        test('encrypts config file and returns key', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/config.json';
            file_put_contents($file, json_encode(['secret' => 'value']));

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($file);

                expect($result)->toHaveKey('path')
                    ->and($result)->toHaveKey('key')
                    ->and(file_exists($result['path']))->toBeTrue()
                    ->and($result['path'])->toBe($file.'.encrypted');

                // Encrypted content should be different from original
                $encrypted = file_get_contents($result['path']);
                expect($encrypted)->not->toContain('secret');
            } finally {
                removeDir($tempDir);
            }
        });

        test('decrypts encrypted file', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/config.json';
            $originalContent = json_encode(['secret' => 'value', 'nested' => ['a' => 1]]);
            file_put_contents($file, $originalContent);

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($file);

                // Remove original to test decryption creates new file
                unlink($file);

                $decryptedPath = $manager->decrypt($result['path'], $result['key']);

                expect(file_exists($decryptedPath))->toBeTrue()
                    ->and($decryptedPath)->toBe($file)
                    ->and(file_get_contents($decryptedPath))->toBe($originalContent);
            } finally {
                removeDir($tempDir);
            }
        });

        test('uses provided encryption key with base64 prefix', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/config.json';
            file_put_contents($file, json_encode(['data' => 'test']));
            $customKey = base64_encode(random_bytes(32));

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($file, $customKey);

                // Decrypt with same key
                unlink($file);
                $manager->decrypt($result['path'], $customKey);
                expect(file_exists($file))->toBeTrue();
            } finally {
                removeDir($tempDir);
            }
        });

        test('returns base64 prefixed key when auto-generated', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/config.json';
            file_put_contents($file, json_encode(['data' => 'test']));

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($file);

                expect($result['key'])->toMatch('/^[A-Za-z0-9+\\/=]+$/');
            } finally {
                removeDir($tempDir);
            }
        });

        test('throws exception when decrypting with wrong key', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/config.json';
            file_put_contents($file, json_encode(['data' => 'test']));

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($file);
                unlink($file);

                $wrongKey = base64_encode(random_bytes(32));
                $manager->decrypt($result['path'], $wrongKey);
            } finally {
                removeDir($tempDir);
            }
        })->throws(DecryptionFailedException::class);

        test('throws exception when decrypted file exists without force', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/config.json';
            file_put_contents($file, json_encode(['data' => 'test']));

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($file);

                // Original file still exists
                $manager->decrypt($result['path'], $result['key']);
            } finally {
                removeDir($tempDir);
            }
        })->throws(DecryptionFailedException::class);

        test('overwrites existing file with force flag', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/config.json';
            $newContent = json_encode(['data' => 'updated']);
            file_put_contents($file, $newContent);

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($file);

                // Modify original file
                file_put_contents($file, json_encode(['data' => 'modified']));

                // Decrypt with force should overwrite
                $decryptedPath = $manager->decrypt($result['path'], $result['key'], force: true);

                expect(file_get_contents($decryptedPath))->toBe($newContent);
            } finally {
                removeDir($tempDir);
            }
        });

        test('supports custom cipher', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/config.json';
            $content = json_encode(['data' => 'test']);
            file_put_contents($file, $content);

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($file, cipher: 'AES-128-CBC');

                unlink($file);
                $manager->decrypt($result['path'], $result['key'], cipher: 'AES-128-CBC');

                expect(file_get_contents($file))->toBe($content);
            } finally {
                removeDir($tempDir);
            }
        });

        test('throws exception for non-existent file during encryption', function (): void {
            $manager = new FerretManager();
            $manager->encrypt('/nonexistent/file.json');
        })->throws(FileNotFoundException::class);

        test('throws exception for non-existent file during decryption', function (): void {
            $manager = new FerretManager();
            $manager->decrypt('/nonexistent/file.json.encrypted', 'somekey');
        })->throws(FileNotFoundException::class);

        test('works with any file type not just config formats', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/secrets.env';
            $content = "DB_PASSWORD=supersecret\nAPI_KEY=abc123";
            file_put_contents($file, $content);

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($file);

                unlink($file);
                $manager->decrypt($result['path'], $result['key']);

                expect(file_get_contents($file))->toBe($content);
            } finally {
                removeDir($tempDir);
            }
        });

        test('prune option deletes original file after encryption', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/config.json';
            file_put_contents($file, json_encode(['secret' => 'value']));

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($file, prune: true);

                expect(file_exists($file))->toBeFalse()
                    ->and(file_exists($result['path']))->toBeTrue();
            } finally {
                removeDir($tempDir);
            }
        });

        test('force option overwrites existing encrypted file', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/config.json';
            file_put_contents($file, json_encode(['data' => 'original']));

            try {
                $manager = new FerretManager();
                $result1 = $manager->encrypt($file);
                $firstEncrypted = file_get_contents($result1['path']);

                // Modify and re-encrypt with force
                file_put_contents($file, json_encode(['data' => 'updated']));
                $result2 = $manager->encrypt($file, force: true);

                $secondEncrypted = file_get_contents($result2['path']);
                expect($secondEncrypted)->not->toBe($firstEncrypted);
            } finally {
                removeDir($tempDir);
            }
        });

        test('throws exception when encrypted file exists without force', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/config.json';
            file_put_contents($file, json_encode(['data' => 'test']));

            try {
                $manager = new FerretManager();
                $manager->encrypt($file);
                $manager->encrypt($file); // Second encrypt should fail
            } finally {
                removeDir($tempDir);
            }
        })->throws(EncryptionFailedException::class);

        test('env option encrypts environment-specific file', function (): void {
            $tempDir = createTempDir();
            $baseFile = $tempDir.'/config.json';
            $envFile = $tempDir.'/config.production.json';
            file_put_contents($envFile, json_encode(['env' => 'production']));

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($baseFile, env: 'production');

                expect($result['path'])->toBe($envFile.'.encrypted')
                    ->and(file_exists($result['path']))->toBeTrue();
            } finally {
                removeDir($tempDir);
            }
        });

        test('decrypts environment-specific file with env option', function (): void {
            $tempDir = createTempDir();
            $baseFile = $tempDir.'/config.json';
            $envFile = $tempDir.'/config.staging.json';
            $content = json_encode(['env' => 'staging', 'debug' => false]);
            file_put_contents($envFile, $content);

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($baseFile, env: 'staging');

                unlink($envFile);
                $decryptedPath = $manager->decrypt($baseFile, $result['key'], env: 'staging');

                expect($decryptedPath)->toBe($envFile)
                    ->and(file_get_contents($decryptedPath))->toBe($content);
            } finally {
                removeDir($tempDir);
            }
        });

        test('decrypt path option outputs to custom directory', function (): void {
            $tempDir = createTempDir();
            $outputDir = $tempDir.'/output';
            mkdir($outputDir);
            $file = $tempDir.'/config.json';
            $content = json_encode(['data' => 'test']);
            file_put_contents($file, $content);

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($file);

                $decryptedPath = $manager->decrypt($result['path'], $result['key'], path: $outputDir);

                expect($decryptedPath)->toBe($outputDir.'/config.json')
                    ->and(file_get_contents($decryptedPath))->toBe($content);
            } finally {
                removeDir($tempDir);
            }
        });

        test('decrypt filename option uses custom filename', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/config.json';
            $content = json_encode(['data' => 'test']);
            file_put_contents($file, $content);

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($file);

                $decryptedPath = $manager->decrypt($result['path'], $result['key'], filename: 'decrypted-config.json');

                expect($decryptedPath)->toBe($tempDir.'/decrypted-config.json')
                    ->and(file_get_contents($decryptedPath))->toBe($content);
            } finally {
                removeDir($tempDir);
            }
        });

        test('decrypt with path and filename options combined', function (): void {
            $tempDir = createTempDir();
            $outputDir = $tempDir.'/custom';
            mkdir($outputDir);
            $file = $tempDir.'/config.json';
            $content = json_encode(['data' => 'test']);
            file_put_contents($file, $content);

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($file);

                $decryptedPath = $manager->decrypt(
                    $result['path'],
                    $result['key'],
                    path: $outputDir,
                    filename: 'my-config.json',
                );

                expect($decryptedPath)->toBe($outputDir.'/my-config.json')
                    ->and(file_get_contents($decryptedPath))->toBe($content);
            } finally {
                removeDir($tempDir);
            }
        });

        test('decrypt prune option deletes encrypted file after decryption', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/config.json';
            $content = json_encode(['data' => 'test']);
            file_put_contents($file, $content);

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($file);
                $encryptedPath = $result['path'];

                unlink($file);
                $manager->decrypt($encryptedPath, $result['key'], prune: true);

                expect(file_exists($encryptedPath))->toBeFalse()
                    ->and(file_exists($file))->toBeTrue();
            } finally {
                removeDir($tempDir);
            }
        });

        test('throws exception when custom output directory does not exist', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/config.json';
            file_put_contents($file, json_encode(['data' => 'test']));

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($file);

                $manager->decrypt($result['path'], $result['key'], path: '/nonexistent/directory');
            } finally {
                removeDir($tempDir);
            }
        })->throws(DirectoryNotFoundException::class);

        test('envStyle directory encrypts file in environment subdirectory', function (): void {
            $tempDir = createTempDir();
            $envDir = $tempDir.'/production';
            mkdir($envDir);
            $baseFile = $tempDir.'/config.json';
            $envFile = $envDir.'/config.json';
            file_put_contents($envFile, json_encode(['env' => 'production', 'debug' => false]));

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($baseFile, env: 'production', envStyle: 'directory');

                expect($result['path'])->toBe($envFile.'.encrypted')
                    ->and(file_exists($result['path']))->toBeTrue();
            } finally {
                removeDir($tempDir);
            }
        });

        test('envStyle directory decrypts file from environment subdirectory', function (): void {
            $tempDir = createTempDir();
            $envDir = $tempDir.'/staging';
            mkdir($envDir);
            $baseFile = $tempDir.'/config.json';
            $envFile = $envDir.'/config.json';
            $content = json_encode(['env' => 'staging', 'api_key' => 'secret']);
            file_put_contents($envFile, $content);

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($baseFile, env: 'staging', envStyle: 'directory');

                unlink($envFile);
                $decryptedPath = $manager->decrypt($baseFile, $result['key'], env: 'staging', envStyle: 'directory');

                expect($decryptedPath)->toBe($envFile)
                    ->and(file_get_contents($decryptedPath))->toBe($content);
            } finally {
                removeDir($tempDir);
            }
        });

        test('envStyle suffix explicitly uses suffix pattern', function (): void {
            $tempDir = createTempDir();
            $baseFile = $tempDir.'/config.json';
            $envFile = $tempDir.'/config.local.json';
            file_put_contents($envFile, json_encode(['env' => 'local']));

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($baseFile, env: 'local', envStyle: 'suffix');

                expect($result['path'])->toBe($envFile.'.encrypted')
                    ->and(file_exists($result['path']))->toBeTrue();
            } finally {
                removeDir($tempDir);
            }
        });

        test('envStyle directory with nested path structure', function (): void {
            $tempDir = createTempDir();
            $carriersDir = $tempDir.'/carriers';
            $envDir = $carriersDir.'/production';
            mkdir($carriersDir);
            mkdir($envDir);
            $baseFile = $carriersDir.'/dhl.neon';
            $envFile = $envDir.'/dhl.neon';
            file_put_contents($envFile, "api_key: secret\nendpoint: https://prod.dhl.com");

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($baseFile, env: 'production', envStyle: 'directory');

                expect($result['path'])->toBe($envFile.'.encrypted')
                    ->and(file_exists($result['path']))->toBeTrue();

                unlink($envFile);
                $decryptedPath = $manager->decrypt($baseFile, $result['key'], env: 'production', envStyle: 'directory');

                expect($decryptedPath)->toBe($envFile)
                    ->and(file_exists($decryptedPath))->toBeTrue();
            } finally {
                removeDir($tempDir);
            }
        });

        test('encrypt and decrypt roundtrip with directory envStyle preserves content', function (): void {
            $tempDir = createTempDir();
            $envDir = $tempDir.'/testing';
            mkdir($envDir);
            $baseFile = $tempDir.'/secrets.yaml';
            $envFile = $envDir.'/secrets.yaml';
            $content = "database:\n  password: supersecret\n  host: localhost";
            file_put_contents($envFile, $content);

            try {
                $manager = new FerretManager();
                $result = $manager->encrypt($baseFile, env: 'testing', envStyle: 'directory');

                unlink($envFile);
                expect(file_exists($envFile))->toBeFalse();

                $decryptedPath = $manager->decrypt($baseFile, $result['key'], env: 'testing', envStyle: 'directory');

                expect(file_get_contents($decryptedPath))->toBe($content);
            } finally {
                removeDir($tempDir);
            }
        });
    });
});
