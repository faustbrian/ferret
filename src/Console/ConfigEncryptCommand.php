<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Console;

use Cline\Ferret\Exceptions\ConfigurationNotFoundException;
use Cline\Ferret\Exceptions\DirectoryNotFoundException;
use Cline\Ferret\Exceptions\InvalidConfigurationException;
use Cline\Ferret\FerretManager;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;

use function base64_encode;
use function count;
use function is_dir;
use function sprintf;

/**
 * Artisan command to encrypt configuration files.
 *
 * Provides CLI interface for encrypting sensitive configuration files
 * using Laravel's Encrypter. Supports custom keys, ciphers, environment-specific
 * files, and automatic cleanup of original files.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ConfigEncryptCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ferret:encrypt
        {file : The configuration file or directory to encrypt}
        {--key= : The encryption key (generates one if not provided)}
        {--cipher= : The encryption cipher (default: AES-256-CBC)}
        {--env= : The environment suffix or directory}
        {--env-style= : Environment file style: suffix or directory}
        {--prune : Delete the original file(s) after encryption}
        {--force : Overwrite existing encrypted file(s)}
        {--recursive : Process subdirectories recursively (directories only)}
        {--glob= : Glob pattern to filter files (directories only, e.g., *.json)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt a configuration file or directory';

    /**
     * Execute the console command.
     */
    public function handle(FerretManager $manager): int
    {
        /** @var string $file */
        $file = $this->argument('file');

        /** @var null|string $key */
        $key = $this->option('key');

        /** @var null|string $cipher */
        $cipher = $this->option('cipher');

        /** @var null|string $env */
        $env = $this->option('env');

        /** @var null|string $envStyle */
        $envStyle = $this->option('env-style');

        /** @var bool $prune */
        $prune = (bool) $this->option('prune');

        /** @var bool $force */
        $force = (bool) $this->option('force');

        /** @var bool $recursive */
        $recursive = (bool) $this->option('recursive');

        /** @var null|string $glob */
        $glob = $this->option('glob');

        // Generate key if not provided
        if ($key === null) {
            $resolvedCipher = $cipher ?? 'AES-256-CBC';
            $key = base64_encode(
                Encrypter::generateKey($resolvedCipher),
            );
        }

        // Handle directory encryption
        if (is_dir($file)) {
            return $this->handleDirectory($manager, $file, $key, $cipher, $prune, $force, $recursive, $glob);
        }

        // Handle single file encryption
        return $this->handleFile($manager, $file, $key, $cipher, $prune, $force, $env, $envStyle);
    }

    /**
     * Handle encryption of a single file.
     */
    private function handleFile(
        FerretManager $manager,
        string $file,
        string $key,
        ?string $cipher,
        bool $prune,
        bool $force,
        ?string $env,
        ?string $envStyle,
    ): int {
        try {
            $result = $manager->encrypt(
                filepath: $file,
                key: $key,
                cipher: $cipher,
                prune: $prune,
                force: $force,
                env: $env,
                envStyle: $envStyle,
            );

            $this->components->info('Configuration file encrypted successfully.');
            $this->components->twoColumnDetail('Encrypted file', $result['path']);
            $this->components->twoColumnDetail('Cipher', $cipher ?? 'AES-256-CBC');

            $this->newLine();
            $this->components->warn('Store this key securely. You will need it to decrypt the file:');
            $this->newLine();
            $this->line('  php artisan ferret:decrypt '.$result['path'].' --key="'.$result['key'].'"');

            if ($prune) {
                $this->newLine();
                $this->components->info('Original file has been deleted.');
            }

            return self::SUCCESS;
        } catch (ConfigurationNotFoundException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (InvalidConfigurationException $e) {
            $this->components->error('Encryption failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Handle encryption of a directory.
     */
    private function handleDirectory(
        FerretManager $manager,
        string $directory,
        string $key,
        ?string $cipher,
        bool $prune,
        bool $force,
        bool $recursive,
        ?string $glob,
    ): int {
        try {
            $result = $manager->encryptDirectory(
                directory: $directory,
                key: $key,
                cipher: $cipher,
                prune: $prune,
                force: $force,
                recursive: $recursive,
                glob: $glob,
            );

            $fileCount = count($result['files']);

            if ($fileCount === 0) {
                $this->components->warn('No files found to encrypt in directory.');

                return self::SUCCESS;
            }

            $this->components->info(sprintf('Directory encrypted successfully. %d file(s) encrypted.', $fileCount));
            $this->components->twoColumnDetail('Directory', $directory);
            $this->components->twoColumnDetail('Cipher', $cipher ?? 'AES-256-CBC');
            $this->components->twoColumnDetail('Recursive', $recursive ? 'Yes' : 'No');

            if ($glob !== null) {
                $this->components->twoColumnDetail('Pattern', $glob);
            }

            $this->newLine();
            $this->components->twoColumnDetail('Encrypted files', '');

            foreach ($result['files'] as $fileResult) {
                $this->line('  • '.$fileResult['path']);
            }

            $this->newLine();
            $this->components->warn('Store this key securely. You will need it to decrypt the files:');
            $this->newLine();
            $this->line('  php artisan ferret:decrypt '.$directory.' --key="'.$result['key'].'"'.($recursive ? ' --recursive' : ''));

            if ($prune) {
                $this->newLine();
                $this->components->info('Original files have been deleted.');
            }

            return self::SUCCESS;
        } catch (DirectoryNotFoundException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (InvalidConfigurationException $e) {
            $this->components->error('Encryption failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
