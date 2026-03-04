<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Loaders;

use Cline\Ferret\Contracts\LoaderInterface;
use Cline\Ferret\Exceptions\ConfigurationEncodingFailedException;
use Cline\Ferret\Exceptions\InvalidTomlConfigurationException;
use Override;
use Throwable;
use Yosymfony\Toml\Toml;
use Yosymfony\Toml\TomlBuilder;

use function array_keys;
use function count;
use function file_exists;
use function is_array;
use function is_readable;
use function range;
use function sprintf;

/**
 * Loader for TOML configuration files.
 *
 * Parses TOML files using yosymfony/toml library and supports encoding
 * with automatic table detection for nested structures. TOML is commonly
 * used in Rust and other modern tooling ecosystems.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class TomlLoader implements LoaderInterface
{
    /**
     * Returns the file extensions supported by this loader.
     *
     * @return array<int, string> Array containing 'toml' extension
     */
    #[Override()]
    public function extensions(): array
    {
        return ['toml'];
    }

    /**
     * Loads and parses a TOML configuration file.
     *
     * Uses Toml::parseFile to read and decode TOML format files with
     * automatic type conversion and section parsing.
     *
     * @param string $filepath Absolute path to the TOML file to load
     *
     * @throws InvalidTomlConfigurationException If the file cannot be read or parsed
     *
     * @return array<string, mixed> Parsed configuration data
     */
    #[Override()]
    public function load(string $filepath): array
    {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            throw InvalidTomlConfigurationException::fromFile($filepath, 'Could not read file');
        }

        try {
            /** @var array<string, mixed> */
            return Toml::parseFile($filepath);
        } catch (Throwable $throwable) {
            throw InvalidTomlConfigurationException::fromFile($filepath, $throwable->getMessage());
        }
    }

    /**
     * Encodes configuration data to TOML format string.
     *
     * Uses TomlBuilder to recursively construct TOML structure with
     * automatic table detection for nested associative arrays and
     * proper value encoding for arrays and scalars.
     *
     * @param array<string, mixed> $data Configuration data to encode
     *
     * @throws ConfigurationEncodingFailedException If TOML encoding fails
     *
     * @return string TOML-formatted configuration string
     */
    #[Override()]
    public function encode(array $data): string
    {
        try {
            $builder = new TomlBuilder();

            $this->buildToml($builder, $data);

            return $builder->getTomlString();
        } catch (Throwable $throwable) {
            throw ConfigurationEncodingFailedException::forFormat('TOML', $throwable->getMessage());
        }
    }

    /**
     * Recursively builds TOML structure from nested arrays.
     *
     * Distinguishes between associative arrays (converted to TOML tables)
     * and indexed arrays (converted to TOML arrays). Handles nested
     * structures with dot-notation keys for table paths.
     *
     * @param TomlBuilder          $builder The TomlBuilder instance to populate
     * @param array<string, mixed> $data    The data to convert to TOML structure
     * @param string               $prefix  The current table path prefix for nested structures
     */
    private function buildToml(TomlBuilder $builder, array $data, string $prefix = ''): void
    {
        foreach ($data as $key => $value) {
            $key = (string) $key;
            $fullKey = $prefix !== '' ? sprintf('%s.%s', $prefix, $key) : $key;

            if (is_array($value) && $this->isAssociativeArray($value)) {
                $builder->addTable($fullKey);

                /** @var array<string, mixed> $value */
                $this->buildToml($builder, $value, $fullKey);
            } elseif (is_array($value)) {
                /** @var array<int|string, mixed> $value */
                $builder->addValue($key, $value);
            } else {
                /** @var bool|float|int|string $value */
                $builder->addValue($key, $value);
            }
        }
    }

    /**
     * Checks if an array is associative rather than indexed.
     *
     * An array is considered associative if its keys are not a sequential
     * numeric sequence starting from 0. This distinction is important for
     * TOML encoding where associative arrays become tables and indexed
     * arrays become arrays.
     *
     * @param  array<mixed> $array The array to check
     * @return bool         True if the array has non-sequential or string keys, false otherwise
     */
    private function isAssociativeArray(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
