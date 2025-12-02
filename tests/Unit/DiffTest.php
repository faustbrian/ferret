<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Ferret\Exceptions\ModuleNotLoadedException;
use Cline\Ferret\FerretManager;

describe('Configuration Diffing', function (): void {
    beforeEach(function (): void {
        $this->manager = new FerretManager();
        $this->tempDir = sys_get_temp_dir().'/ferret-diff-'.uniqid();
        mkdir($this->tempDir, 0o755, true);
    });

    afterEach(function (): void {
        if (is_dir($this->tempDir)) {
            array_map(unlink(...), glob($this->tempDir.'/*') ?: []);
            rmdir($this->tempDir);
        }
    });

    describe('diff()', function (): void {
        test('detects added keys', function (): void {
            $original = ['a' => 1];
            $modified = ['a' => 1, 'b' => 2];

            $result = $this->manager->diff($original, $modified);

            expect($result['added'])->toBe(['b' => 2])
                ->and($result['removed'])->toBe([])
                ->and($result['changed'])->toBe([]);
        });

        test('detects removed keys', function (): void {
            $original = ['a' => 1, 'b' => 2];
            $modified = ['a' => 1];

            $result = $this->manager->diff($original, $modified);

            expect($result['added'])->toBe([])
                ->and($result['removed'])->toBe(['b' => 2])
                ->and($result['changed'])->toBe([]);
        });

        test('detects changed values', function (): void {
            $original = ['a' => 1, 'b' => 'old'];
            $modified = ['a' => 1, 'b' => 'new'];

            $result = $this->manager->diff($original, $modified);

            expect($result['added'])->toBe([])
                ->and($result['removed'])->toBe([])
                ->and($result['changed'])->toBe([
                    'b' => ['from' => 'old', 'to' => 'new'],
                ]);
        });

        test('handles nested arrays with dot notation', function (): void {
            $original = [
                'database' => [
                    'host' => 'localhost',
                    'port' => 3_306,
                ],
            ];
            $modified = [
                'database' => [
                    'host' => 'production.db',
                    'port' => 3_306,
                    'ssl' => true,
                ],
            ];

            $result = $this->manager->diff($original, $modified);

            expect($result['added'])->toBe(['database.ssl' => true])
                ->and($result['removed'])->toBe([])
                ->and($result['changed'])->toBe([
                    'database.host' => ['from' => 'localhost', 'to' => 'production.db'],
                ]);
        });

        test('detects multiple changes at once', function (): void {
            $original = ['a' => 1, 'b' => 2, 'c' => 3];
            $modified = ['a' => 1, 'b' => 'changed', 'd' => 4];

            $result = $this->manager->diff($original, $modified);

            expect($result['added'])->toBe(['d' => 4])
                ->and($result['removed'])->toBe(['c' => 3])
                ->and($result['changed'])->toBe([
                    'b' => ['from' => 2, 'to' => 'changed'],
                ]);
        });

        test('returns empty arrays when configs are identical', function (): void {
            $config = ['a' => 1, 'b' => ['c' => 2]];

            $result = $this->manager->diff($config, $config);

            expect($result)->toBe([
                'added' => [],
                'removed' => [],
                'changed' => [],
            ]);
        });

        test('handles empty configs', function (): void {
            $result = $this->manager->diff([], []);

            expect($result)->toBe([
                'added' => [],
                'removed' => [],
                'changed' => [],
            ]);
        });

        test('detects type changes', function (): void {
            $original = ['value' => '123'];
            $modified = ['value' => 123];

            $result = $this->manager->diff($original, $modified);

            expect($result['changed'])->toBe([
                'value' => ['from' => '123', 'to' => 123],
            ]);
        });
    });

    describe('diffFiles()', function (): void {
        test('compares two configuration files', function (): void {
            $fileA = $this->tempDir.'/config-a.json';
            $fileB = $this->tempDir.'/config-b.json';

            file_put_contents($fileA, json_encode(['api' => ['version' => 'v1']]));
            file_put_contents($fileB, json_encode(['api' => ['version' => 'v2', 'timeout' => 30]]));

            $result = $this->manager->diffFiles($fileA, $fileB);

            expect($result['added'])->toBe(['api.timeout' => 30])
                ->and($result['changed'])->toBe([
                    'api.version' => ['from' => 'v1', 'to' => 'v2'],
                ]);
        });

        test('compares files in different formats', function (): void {
            $jsonFile = $this->tempDir.'/config.json';
            $yamlFile = $this->tempDir.'/config.yaml';

            file_put_contents($jsonFile, json_encode(['name' => 'app', 'debug' => false]));
            file_put_contents($yamlFile, "name: app\ndebug: true\nversion: 1.0");

            $result = $this->manager->diffFiles($jsonFile, $yamlFile);

            expect($result['added'])->toBe(['version' => 1.0])
                ->and($result['changed'])->toBe([
                    'debug' => ['from' => false, 'to' => true],
                ]);
        });

        test('cleans up temporary modules after comparison', function (): void {
            $fileA = $this->tempDir.'/a.json';
            $fileB = $this->tempDir.'/b.json';

            file_put_contents($fileA, json_encode(['a' => 1]));
            file_put_contents($fileB, json_encode(['b' => 2]));

            $this->manager->diffFiles($fileA, $fileB);

            expect($this->manager->isLoaded('__diff_temp_a__'))->toBeFalse()
                ->and($this->manager->isLoaded('__diff_temp_b__'))->toBeFalse();
        });
    });

    describe('diffModules()', function (): void {
        test('compares two loaded modules', function (): void {
            $fileA = $this->tempDir.'/module-a.json';
            $fileB = $this->tempDir.'/module-b.json';

            file_put_contents($fileA, json_encode(['setting' => 'alpha']));
            file_put_contents($fileB, json_encode(['setting' => 'beta', 'extra' => true]));

            $this->manager->load($fileA, 'mod-a');
            $this->manager->load($fileB, 'mod-b');

            $result = $this->manager->diffModules('mod-a', 'mod-b');

            expect($result['added'])->toBe(['extra' => true])
                ->and($result['changed'])->toBe([
                    'setting' => ['from' => 'alpha', 'to' => 'beta'],
                ]);
        });

        test('throws exception for unloaded first module', function (): void {
            $file = $this->tempDir.'/loaded.json';
            file_put_contents($file, json_encode(['a' => 1]));
            $this->manager->load($file, 'loaded');

            expect(fn () => $this->manager->diffModules('not-loaded', 'loaded'))
                ->toThrow(ModuleNotLoadedException::class);
        });

        test('throws exception for unloaded second module', function (): void {
            $file = $this->tempDir.'/loaded.json';
            file_put_contents($file, json_encode(['a' => 1]));
            $this->manager->load($file, 'loaded');

            expect(fn () => $this->manager->diffModules('loaded', 'not-loaded'))
                ->toThrow(ModuleNotLoadedException::class);
        });
    });
});
