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
 * @author Brian Faust <brian@cline.sh>
 */
final class IniLoader implements LoaderInterface
{
    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function extensions(): array
    {
        return ['ini'];
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
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
     * Format a single INI key-value pair.
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
     * Escape and format a value for INI output.
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
