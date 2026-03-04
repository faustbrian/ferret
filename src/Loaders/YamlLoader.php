<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Loaders;

use Cline\Ferret\Contracts\LoaderInterface;
use Cline\Ferret\Exceptions\InvalidYamlConfigurationException;
use Override;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

use function file_exists;
use function file_get_contents;
use function is_array;
use function is_readable;

/**
 * Loader for YAML configuration files.
 *
 * Parses YAML files using Symfony's YAML component with support for
 * both .yaml and .yml extensions. Supports encoding with configurable
 * inline depth and indentation for readable output.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class YamlLoader implements LoaderInterface
{
    /**
     * Returns the file extensions supported by this loader.
     *
     * @return array<int, string> Array containing 'yaml' and 'yml' extensions
     */
    #[Override()]
    public function extensions(): array
    {
        return ['yaml', 'yml'];
    }

    /**
     * Loads and parses a YAML configuration file.
     *
     * Uses Symfony Yaml::parse to decode YAML format files. Returns empty
     * array for empty files. Validates the result is an array type.
     *
     * @param string $filepath Absolute path to the YAML file to load
     *
     * @throws InvalidYamlConfigurationException If the file cannot be read or parsed
     *
     * @return array<string, mixed> Parsed configuration data
     */
    #[Override()]
    public function load(string $filepath): array
    {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            throw InvalidYamlConfigurationException::fromFile($filepath, 'Could not read file');
        }

        $contents = file_get_contents($filepath);

        if ($contents === false) {
            throw InvalidYamlConfigurationException::fromFile($filepath, 'Could not read file');
        }

        if ($contents === '') {
            return [];
        }

        try {
            $data = Yaml::parse($contents);
        } catch (ParseException $parseException) {
            throw InvalidYamlConfigurationException::fromFile($filepath, $parseException->getMessage());
        }

        if (!is_array($data)) {
            return [];
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * Encodes configuration data to YAML format string.
     *
     * Uses Yaml::dump with inline depth of 4 (expand up to 4 levels deep)
     * and 2-space indentation for readable, well-structured output.
     *
     * @param  array<string, mixed> $data Configuration data to encode
     * @return string               YAML-formatted configuration string
     */
    #[Override()]
    public function encode(array $data): string
    {
        return Yaml::dump($data, inline: 4, indent: 2);
    }
}
