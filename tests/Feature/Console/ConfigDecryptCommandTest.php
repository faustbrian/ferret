<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Ferret\FerretManager;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;

describe('ConfigDecryptCommand', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/ferret-test-'.uniqid('', true);
        mkdir($this->tempDir, 0o755, true);
    });

    afterEach(function (): void {
        File::deleteDirectory($this->tempDir);
    });

    test('decrypts an encrypted configuration file', function (): void {
        $file = $this->tempDir.'/config.json';
        $content = json_encode(['secret' => 'value']);
        file_put_contents($file, $content);

        $manager = resolve(FerretManager::class);
        $result = $manager->encrypt($file);

        unlink($file);

        artisan('ferret:decrypt', [
            'file' => $result['path'],
            '--key' => $result['key'],
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('decrypted successfully');

        expect(file_exists($file))->toBeTrue()
            ->and(file_get_contents($file))->toBe($content);
    });

    test('fails without key option', function (): void {
        artisan('ferret:decrypt', ['file' => '/some/file.json.encrypted'])
            ->assertFailed()
            ->expectsOutputToContain('--key option is required');
    });

    test('prune option deletes encrypted file', function (): void {
        $file = $this->tempDir.'/config.json';
        file_put_contents($file, json_encode(['data' => 'test']));

        $manager = resolve(FerretManager::class);
        $result = $manager->encrypt($file);

        unlink($file);

        artisan('ferret:decrypt', [
            'file' => $result['path'],
            '--key' => $result['key'],
            '--prune' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('deleted');

        expect(file_exists($result['path']))->toBeFalse()
            ->and(file_exists($file))->toBeTrue();
    });

    test('force option overwrites existing decrypted file', function (): void {
        $file = $this->tempDir.'/config.json';
        file_put_contents($file, json_encode(['data' => 'original']));

        $manager = resolve(FerretManager::class);
        $result = $manager->encrypt($file);

        // Modify original file
        file_put_contents($file, json_encode(['data' => 'modified']));

        artisan('ferret:decrypt', [
            'file' => $result['path'],
            '--key' => $result['key'],
            '--force' => true,
        ])
            ->assertSuccessful();

        expect(json_decode(file_get_contents($file), true)['data'])->toBe('original');
    });

    test('fails for non-existent file', function (): void {
        artisan('ferret:decrypt', [
            'file' => '/nonexistent/file.json.encrypted',
            '--key' => 'base64:'.base64_encode(random_bytes(32)),
        ])
            ->assertFailed();
    });

    test('fails with wrong key', function (): void {
        $file = $this->tempDir.'/config.json';
        file_put_contents($file, json_encode(['data' => 'test']));

        $manager = resolve(FerretManager::class);
        $result = $manager->encrypt($file);

        unlink($file);

        artisan('ferret:decrypt', [
            'file' => $result['path'],
            '--key' => 'base64:'.base64_encode(random_bytes(32)),
        ])
            ->assertFailed()
            ->expectsOutputToContain('Decryption failed');
    });

    test('outputs to custom path', function (): void {
        $file = $this->tempDir.'/config.json';
        $outputDir = $this->tempDir.'/output';
        mkdir($outputDir);
        file_put_contents($file, json_encode(['data' => 'test']));

        $manager = resolve(FerretManager::class);
        $result = $manager->encrypt($file);

        artisan('ferret:decrypt', [
            'file' => $result['path'],
            '--key' => $result['key'],
            '--path' => $outputDir,
        ])
            ->assertSuccessful();

        expect(file_exists($outputDir.'/config.json'))->toBeTrue();
    });

    test('uses custom filename', function (): void {
        $file = $this->tempDir.'/config.json';
        file_put_contents($file, json_encode(['data' => 'test']));

        $manager = resolve(FerretManager::class);
        $result = $manager->encrypt($file);

        artisan('ferret:decrypt', [
            'file' => $result['path'],
            '--key' => $result['key'],
            '--filename' => 'decrypted.json',
        ])
            ->assertSuccessful();

        expect(file_exists($this->tempDir.'/decrypted.json'))->toBeTrue();
    });

    test('decrypts environment-specific file with suffix style', function (): void {
        $baseFile = $this->tempDir.'/config.json';
        $envFile = $this->tempDir.'/config.staging.json';
        $content = json_encode(['env' => 'staging']);
        file_put_contents($envFile, $content);

        $manager = resolve(FerretManager::class);
        $result = $manager->encrypt($baseFile, env: 'staging');

        unlink($envFile);

        artisan('ferret:decrypt', [
            'file' => $baseFile,
            '--key' => $result['key'],
            '--env' => 'staging',
        ])
            ->assertSuccessful();

        expect(file_exists($envFile))->toBeTrue()
            ->and(file_get_contents($envFile))->toBe($content);
    });

    test('decrypts environment-specific file with directory style', function (): void {
        $envDir = $this->tempDir.'/production';
        mkdir($envDir);
        $baseFile = $this->tempDir.'/config.json';
        $envFile = $envDir.'/config.json';
        $content = json_encode(['env' => 'production']);
        file_put_contents($envFile, $content);

        $manager = resolve(FerretManager::class);
        $result = $manager->encrypt($baseFile, env: 'production', envStyle: 'directory');

        unlink($envFile);

        artisan('ferret:decrypt', [
            'file' => $baseFile,
            '--key' => $result['key'],
            '--env' => 'production',
            '--env-style' => 'directory',
        ])
            ->assertSuccessful();

        expect(file_exists($envFile))->toBeTrue()
            ->and(file_get_contents($envFile))->toBe($content);
    });
});
