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
        $key = base64_encode(random_bytes(32));

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

    test('encrypts all files in a directory', function (): void {
        file_put_contents($this->tempDir.'/config1.json', json_encode(['key1' => 'value1']));
        file_put_contents($this->tempDir.'/config2.json', json_encode(['key2' => 'value2']));
        file_put_contents($this->tempDir.'/config3.yaml', "key3: value3\n");

        artisan('ferret:encrypt', ['file' => $this->tempDir])
            ->assertSuccessful()
            ->expectsOutputToContain('3 file(s) encrypted');

        expect(file_exists($this->tempDir.'/config1.json.encrypted'))->toBeTrue()
            ->and(file_exists($this->tempDir.'/config2.json.encrypted'))->toBeTrue()
            ->and(file_exists($this->tempDir.'/config3.yaml.encrypted'))->toBeTrue();
    });

    test('encrypts directory with glob pattern filter', function (): void {
        file_put_contents($this->tempDir.'/config1.json', json_encode(['key1' => 'value1']));
        file_put_contents($this->tempDir.'/config2.json', json_encode(['key2' => 'value2']));
        file_put_contents($this->tempDir.'/config3.yaml', "key3: value3\n");

        artisan('ferret:encrypt', ['file' => $this->tempDir, '--glob' => '*.json'])
            ->assertSuccessful()
            ->expectsOutputToContain('2 file(s) encrypted');

        expect(file_exists($this->tempDir.'/config1.json.encrypted'))->toBeTrue()
            ->and(file_exists($this->tempDir.'/config2.json.encrypted'))->toBeTrue()
            ->and(file_exists($this->tempDir.'/config3.yaml.encrypted'))->toBeFalse();
    });

    test('encrypts directory recursively', function (): void {
        mkdir($this->tempDir.'/subdir', 0o755, true);
        file_put_contents($this->tempDir.'/config1.json', json_encode(['key1' => 'value1']));
        file_put_contents($this->tempDir.'/subdir/config2.json', json_encode(['key2' => 'value2']));

        artisan('ferret:encrypt', ['file' => $this->tempDir, '--recursive' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('2 file(s) encrypted');

        expect(file_exists($this->tempDir.'/config1.json.encrypted'))->toBeTrue()
            ->and(file_exists($this->tempDir.'/subdir/config2.json.encrypted'))->toBeTrue();
    });

    test('encrypts directory with prune option deletes originals', function (): void {
        file_put_contents($this->tempDir.'/config1.json', json_encode(['key1' => 'value1']));
        file_put_contents($this->tempDir.'/config2.json', json_encode(['key2' => 'value2']));

        artisan('ferret:encrypt', ['file' => $this->tempDir, '--prune' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('deleted');

        expect(file_exists($this->tempDir.'/config1.json'))->toBeFalse()
            ->and(file_exists($this->tempDir.'/config2.json'))->toBeFalse()
            ->and(file_exists($this->tempDir.'/config1.json.encrypted'))->toBeTrue()
            ->and(file_exists($this->tempDir.'/config2.json.encrypted'))->toBeTrue();
    });

    test('encrypts empty directory with warning', function (): void {
        artisan('ferret:encrypt', ['file' => $this->tempDir])
            ->assertSuccessful()
            ->expectsOutputToContain('No files found');
    });

    test('skips already encrypted files when encrypting directory', function (): void {
        file_put_contents($this->tempDir.'/config.json', json_encode(['key' => 'value']));
        file_put_contents($this->tempDir.'/already.json.encrypted', 'encrypted-data');

        artisan('ferret:encrypt', ['file' => $this->tempDir])
            ->assertSuccessful()
            ->expectsOutputToContain('1 file(s) encrypted');

        expect(file_exists($this->tempDir.'/config.json.encrypted'))->toBeTrue();
    });
});
