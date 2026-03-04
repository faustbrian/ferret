<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Loaders;

use Cline\Ferret\Contracts\LoaderInterface;
use Cline\Ferret\Exceptions\InvalidPhpConfigurationException;
use Cline\Ferret\Exceptions\PhpConfigurationMustReturnArrayException;
use Override;
use Throwable;

use function is_array;
use function var_export;

/**
 * Loader for PHP configuration files.
 *
 * Executes PHP files using require and expects them to return an array.
 * This is the standard Laravel configuration file format. Supports
 * encoding using var_export for round-trip compatibility.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PhpLoader implements LoaderInterface
{
    /**
     * Returns the file extensions supported by this loader.
     *
     * @return array<int, string> Array containing 'php' extension
     */
    #[Override()]
    public function extensions(): array
    {
        return ['php'];
    }

    /**
     * Loads and executes a PHP configuration file.
     *
     * Uses require to execute the PHP file and expects it to return an
     * associative array. This allows configuration files to use PHP
     * logic for dynamic configuration generation.
     *
     * @param string $filepath Absolute path to the PHP file to load
     *
     * @throws InvalidPhpConfigurationException         If the file cannot be executed
     * @throws PhpConfigurationMustReturnArrayException If the file doesn't return an array
     *
     * @return array<string, mixed> The array returned by the PHP file
     */
    #[Override()]
    public function load(string $filepath): array
    {
        try {
            $data = require $filepath;
        } catch (Throwable $throwable) {
            throw InvalidPhpConfigurationException::fromFile($filepath, $throwable);
        }

        if (!is_array($data)) {
            throw PhpConfigurationMustReturnArrayException::fromFile($filepath);
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * Encodes configuration data to PHP format string.
     *
     * Generates a PHP file that returns the configuration array using
     * var_export. The resulting file can be directly required as a
     * configuration source.
     *
     * @param  array<string, mixed> $data Configuration data to encode
     * @return string               PHP code that returns the configuration array
     */
    #[Override()]
    public function encode(array $data): string
    {
        return "<?php\n\nreturn ".var_export($data, return: true).";\n";
    }
}
