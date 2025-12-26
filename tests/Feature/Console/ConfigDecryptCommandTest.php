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

    test('decrypts an encrypted configuration file and deletes encrypted by default', function (): void {
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
            ->and(file_get_contents($file))->toBe($content)
            ->and(file_exists($result['path']))->toBeFalse();
    });

    test('fails without key option', function (): void {
        artisan('ferret:decrypt', ['file' => '/some/file.json.encrypted'])
            ->assertFailed()
            ->expectsOutputToContain('No decryption key provided');
    });

    test('keep option preserves encrypted file', function (): void {
        $file = $this->tempDir.'/config.json';
        file_put_contents($file, json_encode(['data' => 'test']));

        $manager = resolve(FerretManager::class);
        $result = $manager->encrypt($file);

        unlink($file);

        artisan('ferret:decrypt', [
            'file' => $result['path'],
            '--key' => $result['key'],
            '--keep' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Encrypted file has been kept');

        expect(file_exists($result['path']))->toBeTrue()
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
            '--key' => base64_encode(random_bytes(32)),
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
            '--key' => base64_encode(random_bytes(32)),
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

    test('decrypts all encrypted files in a directory', function (): void {
        $content1 = json_encode(['key1' => 'value1']);
        $content2 = json_encode(['key2' => 'value2']);
        file_put_contents($this->tempDir.'/config1.json', $content1);
        file_put_contents($this->tempDir.'/config2.json', $content2);

        $manager = resolve(FerretManager::class);
        $result = $manager->encryptDirectory($this->tempDir, prune: true);

        artisan('ferret:decrypt', [
            'file' => $this->tempDir,
            '--key' => $result['key'],
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('2 file(s) decrypted');

        expect(file_exists($this->tempDir.'/config1.json'))->toBeTrue()
            ->and(file_get_contents($this->tempDir.'/config1.json'))->toBe($content1)
            ->and(file_exists($this->tempDir.'/config2.json'))->toBeTrue()
            ->and(file_get_contents($this->tempDir.'/config2.json'))->toBe($content2);
    });

    test('decrypts directory recursively', function (): void {
        mkdir($this->tempDir.'/subdir', 0o755, true);
        $content1 = json_encode(['key1' => 'value1']);
        $content2 = json_encode(['key2' => 'value2']);
        file_put_contents($this->tempDir.'/config1.json', $content1);
        file_put_contents($this->tempDir.'/subdir/config2.json', $content2);

        $manager = resolve(FerretManager::class);
        $result = $manager->encryptDirectory($this->tempDir, prune: true, recursive: true);

        artisan('ferret:decrypt', [
            'file' => $this->tempDir,
            '--key' => $result['key'],
            '--recursive' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('2 file(s) decrypted');

        expect(file_exists($this->tempDir.'/config1.json'))->toBeTrue()
            ->and(file_get_contents($this->tempDir.'/config1.json'))->toBe($content1)
            ->and(file_exists($this->tempDir.'/subdir/config2.json'))->toBeTrue()
            ->and(file_get_contents($this->tempDir.'/subdir/config2.json'))->toBe($content2);
    });

    test('decrypts directory with keep option preserves encrypted files', function (): void {
        file_put_contents($this->tempDir.'/config.json', json_encode(['data' => 'test']));

        $manager = resolve(FerretManager::class);
        $result = $manager->encryptDirectory($this->tempDir, prune: true);

        artisan('ferret:decrypt', [
            'file' => $this->tempDir,
            '--key' => $result['key'],
            '--keep' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Encrypted files have been kept');

        expect(file_exists($this->tempDir.'/config.json'))->toBeTrue()
            ->and(file_exists($this->tempDir.'/config.json.encrypted'))->toBeTrue();
    });

    test('decrypts empty directory with warning', function (): void {
        artisan('ferret:decrypt', [
            'file' => $this->tempDir,
            '--key' => base64_encode(random_bytes(32)),
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('No encrypted files found');
    });

    test('decrypts directory with force option overwrites existing files', function (): void {
        $originalContent = json_encode(['data' => 'original']);
        file_put_contents($this->tempDir.'/config.json', $originalContent);

        $manager = resolve(FerretManager::class);
        $result = $manager->encryptDirectory($this->tempDir);

        // Modify the decrypted file
        file_put_contents($this->tempDir.'/config.json', json_encode(['data' => 'modified']));

        artisan('ferret:decrypt', [
            'file' => $this->tempDir,
            '--key' => $result['key'],
            '--force' => true,
        ])
            ->assertSuccessful();

        expect(file_get_contents($this->tempDir.'/config.json'))->toBe($originalContent);
    });

    test('decrypts with --app-key flag using APP_KEY', function (): void {
        // Set APP_KEY in config
        $key = base64_encode(random_bytes(32));
        config(['app.key' => 'base64:'.$key]);

        $file = $this->tempDir.'/config.json';
        $content = json_encode(['secret' => 'value']);
        file_put_contents($file, $content);

        $manager = resolve(FerretManager::class);
        $result = $manager->encrypt($file, $key);

        unlink($file);

        artisan('ferret:decrypt', [
            'file' => $result['path'],
            '--app-key' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('decrypted successfully');

        expect(file_exists($file))->toBeTrue()
            ->and(file_get_contents($file))->toBe($content);
    });

    test('decrypts with config use_app_key enabled', function (): void {
        // Set APP_KEY and config option
        $key = base64_encode(random_bytes(32));
        config(['app.key' => 'base64:'.$key]);
        config(['ferret.encryption.use_app_key' => true]);

        $file = $this->tempDir.'/config.json';
        $content = json_encode(['secret' => 'value']);
        file_put_contents($file, $content);

        $manager = resolve(FerretManager::class);
        $result = $manager->encrypt($file, $key);

        unlink($file);

        artisan('ferret:decrypt', [
            'file' => $result['path'],
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('decrypted successfully');

        expect(file_exists($file))->toBeTrue()
            ->and(file_get_contents($file))->toBe($content);
    });

    test('--key option takes precedence over --app-key flag', function (): void {
        // Set a different APP_KEY in config
        $appKey = base64_encode(random_bytes(32));
        config(['app.key' => 'base64:'.$appKey]);

        // Use a separate key for encryption
        $encryptionKey = base64_encode(random_bytes(32));

        $file = $this->tempDir.'/config.json';
        $content = json_encode(['secret' => 'value']);
        file_put_contents($file, $content);

        $manager = resolve(FerretManager::class);
        $result = $manager->encrypt($file, $encryptionKey);

        unlink($file);

        // Passing both --key and --app-key, --key should win
        artisan('ferret:decrypt', [
            'file' => $result['path'],
            '--key' => $encryptionKey,
            '--app-key' => true,
        ])
            ->assertSuccessful();

        expect(file_exists($file))->toBeTrue()
            ->and(file_get_contents($file))->toBe($content);
    });

    test('fails with --app-key when APP_KEY is not set', function (): void {
        config(['app.key' => null]);

        artisan('ferret:decrypt', [
            'file' => '/some/file.json.encrypted',
            '--app-key' => true,
        ])
            ->assertFailed()
            ->expectsOutputToContain('APP_KEY is not set');
    });
});
