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
use Cline\Ferret\Exceptions\LoaderException;
use Cline\Ferret\FerretManager;
use Illuminate\Console\Command;

use const PATHINFO_EXTENSION;

use function pathinfo;

/**
 * Artisan command to convert configuration files between formats.
 *
 * Provides CLI interface for converting configuration files between
 * supported formats (JSON, YAML, NEON, XML, INI, PHP). The output
 * format is determined by the destination file extension.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ConfigConvertCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ferret:convert
        {source : The source configuration file}
        {destination : The destination file (format determined by extension)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert a configuration file to another format';

    /**
     * Execute the console command.
     */
    public function handle(FerretManager $manager): int
    {
        /** @var string $source */
        $source = $this->argument('source');

        /** @var string $destination */
        $destination = $this->argument('destination');

        $sourceExt = pathinfo($source, PATHINFO_EXTENSION);
        $destExt = pathinfo($destination, PATHINFO_EXTENSION);

        try {
            $manager->convert($source, $destination);

            $this->components->info('Configuration file converted successfully.');
            $this->components->twoColumnDetail('Source', $source);
            $this->components->twoColumnDetail('Source format', $sourceExt ?: 'unknown');
            $this->components->twoColumnDetail('Destination', $destination);
            $this->components->twoColumnDetail('Destination format', $destExt ?: 'unknown');

            return self::SUCCESS;
        } catch (ConfigurationNotFoundException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (InvalidConfigurationException|LoaderException $e) {
            $this->components->error('Conversion failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
