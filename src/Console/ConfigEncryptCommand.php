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
use Illuminate\Encryption\Encrypter;

use function base64_encode;

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
        {file : The configuration file to encrypt}
        {--key= : The encryption key (generates one if not provided)}
        {--cipher= : The encryption cipher (default: AES-256-CBC)}
        {--env= : The environment suffix or directory}
        {--env-style= : Environment file style: suffix or directory}
        {--prune : Delete the original file after encryption}
        {--force : Overwrite existing encrypted file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt a configuration file';

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

        // Generate key if not provided
        if ($key === null) {
            $resolvedCipher = $cipher ?? 'AES-256-CBC';
            $key = 'base64:'.base64_encode(
                Encrypter::generateKey($resolvedCipher),
            );
        }

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
            $this->line('  '.$result['key']);

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
}
