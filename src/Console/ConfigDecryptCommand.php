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

use function count;
use function is_dir;
use function sprintf;

/**
 * Artisan command to decrypt configuration files.
 *
 * Provides CLI interface for decrypting encrypted configuration files
 * using Laravel's Encrypter. Supports custom keys, ciphers, environment-specific
 * files, custom output paths, and automatic cleanup of encrypted files.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ConfigDecryptCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ferret:decrypt
        {file : The encrypted configuration file or directory to decrypt}
        {--key= : The encryption key (required)}
        {--cipher= : The encryption cipher (default: AES-256-CBC)}
        {--env= : The environment suffix or directory}
        {--env-style= : Environment file style: suffix or directory}
        {--path= : Custom output directory}
        {--filename= : Custom output filename}
        {--keep : Keep the encrypted file(s) after decryption}
        {--force : Overwrite existing decrypted file(s)}
        {--recursive : Process subdirectories recursively (directories only)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Decrypt an encrypted configuration file or directory';

    /**
     * Execute the console command.
     */
    public function handle(FerretManager $manager): int
    {
        /** @var string $file */
        $file = $this->argument('file');

        /** @var null|string $key */
        $key = $this->option('key');

        if ($key === null) {
            $this->components->error('The --key option is required for decryption.');

            return self::FAILURE;
        }

        /** @var null|string $cipher */
        $cipher = $this->option('cipher');

        /** @var null|string $env */
        $env = $this->option('env');

        /** @var null|string $envStyle */
        $envStyle = $this->option('env-style');

        /** @var null|string $path */
        $path = $this->option('path');

        /** @var null|string $filename */
        $filename = $this->option('filename');

        /** @var bool $keep */
        $keep = (bool) $this->option('keep');

        /** @var bool $force */
        $force = (bool) $this->option('force');

        /** @var bool $recursive */
        $recursive = (bool) $this->option('recursive');

        // Handle directory decryption
        if (is_dir($file)) {
            return $this->handleDirectory($manager, $file, $key, $cipher, $keep, $force, $recursive);
        }

        // Handle single file decryption
        return $this->handleFile($manager, $file, $key, $cipher, $keep, $force, $path, $filename, $env, $envStyle);
    }

    /**
     * Handle decryption of a single file.
     */
    private function handleFile(
        FerretManager $manager,
        string $file,
        string $key,
        ?string $cipher,
        bool $keep,
        bool $force,
        ?string $path,
        ?string $filename,
        ?string $env,
        ?string $envStyle,
    ): int {
        try {
            $decryptedPath = $manager->decrypt(
                encryptedPath: $file,
                key: $key,
                force: $force,
                cipher: $cipher,
                path: $path,
                filename: $filename,
                env: $env,
                prune: !$keep,
                envStyle: $envStyle,
            );

            $this->components->info('Configuration file decrypted successfully.');
            $this->components->twoColumnDetail('Decrypted file', $decryptedPath);

            if ($keep) {
                $this->newLine();
                $this->components->warn('Encrypted file has been kept.');
            }

            return self::SUCCESS;
        } catch (ConfigurationNotFoundException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (InvalidConfigurationException $e) {
            $this->components->error('Decryption failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Handle decryption of a directory.
     */
    private function handleDirectory(
        FerretManager $manager,
        string $directory,
        string $key,
        ?string $cipher,
        bool $keep,
        bool $force,
        bool $recursive,
    ): int {
        try {
            $decryptedPaths = $manager->decryptDirectory(
                directory: $directory,
                key: $key,
                force: $force,
                cipher: $cipher,
                prune: !$keep,
                recursive: $recursive,
            );

            $fileCount = count($decryptedPaths);

            if ($fileCount === 0) {
                $this->components->warn('No encrypted files found in directory.');

                return self::SUCCESS;
            }

            $this->components->info(sprintf('Directory decrypted successfully. %d file(s) decrypted.', $fileCount));
            $this->components->twoColumnDetail('Directory', $directory);
            $this->components->twoColumnDetail('Recursive', $recursive ? 'Yes' : 'No');

            $this->newLine();
            $this->components->twoColumnDetail('Decrypted files', '');

            foreach ($decryptedPaths as $decryptedPath) {
                $this->line('  • '.$decryptedPath);
            }

            if ($keep) {
                $this->newLine();
                $this->components->warn('Encrypted files have been kept.');
            }

            return self::SUCCESS;
        } catch (DirectoryNotFoundException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (InvalidConfigurationException $e) {
            $this->components->error('Decryption failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
