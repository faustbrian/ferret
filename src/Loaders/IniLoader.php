<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Loaders;

use Cline\Ferret\Contracts\LoaderInterface;
use Cline\Ferret\Exceptions\InvalidIniConfigurationException;
use Override;
use Stringable;

use const INI_SCANNER_TYPED;

use function addcslashes;
use function array_map;
use function implode;
use function is_array;
use function is_numeric;
use function is_scalar;
use function parse_ini_file;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;

/**
 * Loader for INI configuration files.
 *
 * Parses INI files using PHP's built-in parse_ini_file with typed scanning
 * enabled. Supports encoding arrays back to INI format with proper escaping
 * and section handling.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class IniLoader implements LoaderInterface
{
    /**
     * Returns the file extensions supported by this loader.
     *
     * @return array<int, string> Array containing 'ini' extension
     */
    #[Override()]
    public function extensions(): array
    {
        return ['ini'];
    }

    /**
     * Loads and parses an INI configuration file.
     *
     * Uses INI_SCANNER_TYPED to automatically convert values to their
     * appropriate types (boolean, integer, string, etc.). Processes
     * sections to create nested arrays.
     *
     * @param string $filepath Absolute path to the INI file to load
     *
     * @throws InvalidIniConfigurationException If the file cannot be parsed or read
     *
     * @return array<string, mixed> Parsed configuration data with sections as nested arrays
     */
    #[Override()]
    public function load(string $filepath): array
    {
        set_error_handler(static function (int $errno, string $errstr) use ($filepath): never {
            throw InvalidIniConfigurationException::fromFile($filepath, $errstr);
        });

        try {
            $data = parse_ini_file($filepath, process_sections: true, scanner_mode: INI_SCANNER_TYPED);

            if ($data === false) {
                throw InvalidIniConfigurationException::fromFile($filepath);
            }

            /** @var array<string, mixed> $data */
            return $data;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Encodes configuration data to INI format string.
     *
     * Handles nested arrays as INI sections and properly escapes values
     * containing special characters. Array values are converted to
     * bracket notation (key[] = value).
     *
     * @param  array<string, mixed> $data Configuration data to encode
     * @return string               INI-formatted configuration string
     */
    #[Override()]
    public function encode(array $data): string
    {
        $lines = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $lines[] = sprintf('[%s]', $key);

                foreach ($value as $subKey => $subValue) {
                    $lines[] = $this->formatValue((string) $subKey, $subValue);
                }

                $lines[] = '';
            } else {
                $lines[] = $this->formatValue((string) $key, $value);
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Formats a single INI key-value pair.
     *
     * Handles array values by creating multiple lines with bracket notation.
     * Non-array values are formatted as simple key = value pairs.
     *
     * @param  string $key   The configuration key
     * @param  mixed  $value The configuration value (scalar or array)
     * @return string Formatted INI line(s)
     */
    private function formatValue(string $key, mixed $value): string
    {
        if (is_array($value)) {
            return implode("\n", array_map(
                fn (mixed $v): string => $key.'[] = '.$this->escapeValue($v),
                $value,
            ));
        }

        return $key.' = '.$this->escapeValue($value);
    }

    /**
     * Escapes and formats a value for INI output.
     *
     * Converts booleans to 'true'/'false', null to 'null', and numbers
     * to their string representation. Strings are quoted and escaped
     * to prevent syntax errors in INI files.
     *
     * @param  mixed  $value The value to escape (boolean, null, numeric, or stringable)
     * @return string The escaped and formatted value suitable for INI files
     */
    private function escapeValue(mixed $value): string
    {
        if ($value === true) {
            return 'true';
        }

        if ($value === false) {
            return 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        $stringValue = is_scalar($value) || $value instanceof Stringable ? (string) $value : '';

        return '"'.addcslashes($stringValue, '"').'"';
    }
}
