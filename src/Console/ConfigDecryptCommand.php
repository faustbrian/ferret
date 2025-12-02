<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Console;

use Cline\Ferret\Exceptions\ConfigurationNotFoundException;
use Cline\Ferret\Exceptions\InvalidConfigurationException;
use Cline\Ferret\FerretManager;
use Illuminate\Console\Command;

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
        {file : The encrypted configuration file to decrypt}
        {--key= : The encryption key (required)}
        {--cipher= : The encryption cipher (default: AES-256-CBC)}
        {--env= : The environment suffix or directory}
        {--env-style= : Environment file style: suffix or directory}
        {--path= : Custom output directory}
        {--filename= : Custom output filename}
        {--prune : Delete the encrypted file after decryption}
        {--force : Overwrite existing decrypted file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Decrypt an encrypted configuration file';

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

        /** @var bool $prune */
        $prune = (bool) $this->option('prune');

        /** @var bool $force */
        $force = (bool) $this->option('force');

        try {
            $decryptedPath = $manager->decrypt(
                encryptedPath: $file,
                key: $key,
                force: $force,
                cipher: $cipher,
                path: $path,
                filename: $filename,
                env: $env,
                prune: $prune,
                envStyle: $envStyle,
            );

            $this->components->info('Configuration file decrypted successfully.');
            $this->components->twoColumnDetail('Decrypted file', $decryptedPath);

            if ($prune) {
                $this->newLine();
                $this->components->info('Encrypted file has been deleted.');
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
}
