<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;

describe('ConfigEncryptCommand', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/ferret-test-'.uniqid('', true);
        mkdir($this->tempDir, 0o755, true);
    });

    afterEach(function (): void {
        File::deleteDirectory($this->tempDir);
    });

    test('encrypts a configuration file', function (): void {
        $file = $this->tempDir.'/config.json';
        file_put_contents($file, json_encode(['secret' => 'value']));

        artisan('ferret:encrypt', ['file' => $file])
            ->assertSuccessful()
            ->expectsOutputToContain('encrypted successfully');

        expect(file_exists($file.'.encrypted'))->toBeTrue();
    });

    test('generates encryption key when not provided', function (): void {
        $file = $this->tempDir.'/config.json';
        file_put_contents($file, json_encode(['data' => 'test']));

        artisan('ferret:encrypt', ['file' => $file])
            ->assertSuccessful()
            ->expectsOutputToContain('Store this key securely');
    });

    test('uses provided encryption key', function (): void {
        $file = $this->tempDir.'/config.json';
        file_put_contents($file, json_encode(['data' => 'test']));
        $key = 'base64:'.base64_encode(random_bytes(32));

        artisan('ferret:encrypt', ['file' => $file, '--key' => $key])
            ->assertSuccessful();

        expect(file_exists($file.'.encrypted'))->toBeTrue();
    });

    test('prune option deletes original file', function (): void {
        $file = $this->tempDir.'/config.json';
        file_put_contents($file, json_encode(['data' => 'test']));

        artisan('ferret:encrypt', ['file' => $file, '--prune' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('deleted');

        expect(file_exists($file))->toBeFalse()
            ->and(file_exists($file.'.encrypted'))->toBeTrue();
    });

    test('force option overwrites existing encrypted file', function (): void {
        $file = $this->tempDir.'/config.json';
        file_put_contents($file, json_encode(['data' => 'original']));

        artisan('ferret:encrypt', ['file' => $file])
            ->assertSuccessful();

        file_put_contents($file, json_encode(['data' => 'updated']));

        artisan('ferret:encrypt', ['file' => $file, '--force' => true])
            ->assertSuccessful();
    });

    test('fails for non-existent file', function (): void {
        artisan('ferret:encrypt', ['file' => '/nonexistent/file.json'])
            ->assertFailed();
    });

    test('encrypts environment-specific file with suffix style', function (): void {
        $baseFile = $this->tempDir.'/config.json';
        $envFile = $this->tempDir.'/config.production.json';
        file_put_contents($envFile, json_encode(['env' => 'production']));

        artisan('ferret:encrypt', ['file' => $baseFile, '--env' => 'production'])
            ->assertSuccessful();

        expect(file_exists($envFile.'.encrypted'))->toBeTrue();
    });

    test('encrypts environment-specific file with directory style', function (): void {
        $envDir = $this->tempDir.'/production';
        mkdir($envDir);
        $baseFile = $this->tempDir.'/config.json';
        $envFile = $envDir.'/config.json';
        file_put_contents($envFile, json_encode(['env' => 'production']));

        artisan('ferret:encrypt', [
            'file' => $baseFile,
            '--env' => 'production',
            '--env-style' => 'directory',
        ])
            ->assertSuccessful();

        expect(file_exists($envFile.'.encrypted'))->toBeTrue();
    });

    test('uses custom cipher', function (): void {
        $file = $this->tempDir.'/config.json';
        file_put_contents($file, json_encode(['data' => 'test']));

        artisan('ferret:encrypt', ['file' => $file, '--cipher' => 'AES-128-CBC'])
            ->assertSuccessful()
            ->expectsOutputToContain('AES-128-CBC');
    });
});
