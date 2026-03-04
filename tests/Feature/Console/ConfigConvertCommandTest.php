<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;

describe('ConfigConvertCommand', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/ferret-test-'.uniqid('', true);
        mkdir($this->tempDir, 0o755, true);
    });

    afterEach(function (): void {
        File::deleteDirectory($this->tempDir);
    });

    test('converts JSON to YAML', function (): void {
        $source = $this->tempDir.'/config.json';
        $dest = $this->tempDir.'/config.yaml';
        file_put_contents($source, json_encode(['database' => ['host' => 'localhost']]));

        artisan('ferret:convert', ['source' => $source, 'destination' => $dest])
            ->assertSuccessful()
            ->expectsOutputToContain('converted successfully');

        expect(file_exists($dest))->toBeTrue()
            ->and(file_get_contents($dest))->toContain('database:')
            ->and(file_get_contents($dest))->toContain('host: localhost');
    });

    test('converts YAML to JSON', function (): void {
        $source = $this->tempDir.'/config.yaml';
        $dest = $this->tempDir.'/config.json';
        file_put_contents($source, "database:\n  host: localhost\n  port: 5432");

        artisan('ferret:convert', ['source' => $source, 'destination' => $dest])
            ->assertSuccessful();

        $content = json_decode(file_get_contents($dest), true);
        expect($content['database']['host'])->toBe('localhost')
            ->and($content['database']['port'])->toBe(5_432);
    });

    test('converts JSON to NEON', function (): void {
        $source = $this->tempDir.'/config.json';
        $dest = $this->tempDir.'/config.neon';
        file_put_contents($source, json_encode(['app' => ['name' => 'MyApp', 'debug' => true]]));

        artisan('ferret:convert', ['source' => $source, 'destination' => $dest])
            ->assertSuccessful();

        expect(file_exists($dest))->toBeTrue()
            ->and(file_get_contents($dest))->toContain('app:')
            ->and(file_get_contents($dest))->toContain('name: MyApp');
    });

    test('converts JSON to XML', function (): void {
        $source = $this->tempDir.'/config.json';
        $dest = $this->tempDir.'/config.xml';
        file_put_contents($source, json_encode(['settings' => ['key' => 'value']]));

        artisan('ferret:convert', ['source' => $source, 'destination' => $dest])
            ->assertSuccessful();

        expect(file_exists($dest))->toBeTrue()
            ->and(file_get_contents($dest))->toContain('<?xml')
            ->and(file_get_contents($dest))->toContain('<key>value</key>');
    });

    test('converts JSON to INI', function (): void {
        $source = $this->tempDir.'/config.json';
        $dest = $this->tempDir.'/config.ini';
        file_put_contents($source, json_encode(['section' => ['key' => 'value']]));

        artisan('ferret:convert', ['source' => $source, 'destination' => $dest])
            ->assertSuccessful();

        expect(file_exists($dest))->toBeTrue()
            ->and(file_get_contents($dest))->toContain('[section]')
            ->and(file_get_contents($dest))->toContain('key');
    });

    test('converts JSON to PHP', function (): void {
        $source = $this->tempDir.'/config.json';
        $dest = $this->tempDir.'/config.php';
        file_put_contents($source, json_encode(['app' => ['name' => 'MyApp']]));

        artisan('ferret:convert', ['source' => $source, 'destination' => $dest])
            ->assertSuccessful();

        expect(file_exists($dest))->toBeTrue()
            ->and(file_get_contents($dest))->toContain('<?php')
            ->and(file_get_contents($dest))->toContain('return');

        $config = require $dest;
        expect($config['app']['name'])->toBe('MyApp');
    });

    test('fails for non-existent source file', function (): void {
        $dest = $this->tempDir.'/config.yaml';

        artisan('ferret:convert', ['source' => '/nonexistent/file.json', 'destination' => $dest])
            ->assertFailed();
    });

    test('displays source and destination formats', function (): void {
        $source = $this->tempDir.'/config.json';
        $dest = $this->tempDir.'/config.yaml';
        file_put_contents($source, json_encode(['key' => 'value']));

        artisan('ferret:convert', ['source' => $source, 'destination' => $dest])
            ->assertSuccessful()
            ->expectsOutputToContain('json')
            ->expectsOutputToContain('yaml');
    });
});
