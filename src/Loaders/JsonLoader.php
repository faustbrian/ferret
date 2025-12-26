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
use Cline\Ferret\Exceptions\InvalidJsonConfigurationException;
use JsonException;
use Override;

use const JSON_ERROR_NONE;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

use function file_exists;
use function file_get_contents;
use function is_array;
use function is_readable;
use function json_decode;
use function json_encode;
use function json_last_error;
use function json_last_error_msg;

/**
 * Loader for JSON configuration files.
 *
 * Parses JSON files with strict error handling and automatic type conversion.
 * Supports encoding with pretty printing and Unicode preservation for
 * human-readable output.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class JsonLoader implements LoaderInterface
{
    /**
     * Returns the file extensions supported by this loader.
     *
     * @return array<int, string> Array containing 'json' extension
     */
    #[Override()]
    public function extensions(): array
    {
        return ['json'];
    }

    /**
     * Loads and parses a JSON configuration file.
     *
     * Validates file existence and readability before parsing. Returns empty
     * array for empty files. Decodes JSON as associative arrays and validates
     * the result is an array type.
     *
     * @param string $filepath Absolute path to the JSON file to load
     *
     * @throws InvalidJsonConfigurationException If the file cannot be read or parsed
     *
     * @return array<string, mixed> Parsed configuration data
     */
    #[Override()]
    public function load(string $filepath): array
    {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            throw InvalidJsonConfigurationException::fromFile($filepath, 'Could not read file');
        }

        $contents = file_get_contents($filepath);

        if ($contents === false) {
            throw InvalidJsonConfigurationException::fromFile($filepath, 'Could not read file');
        }

        if ($contents === '') {
            return [];
        }

        $data = json_decode($contents, associative: true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw InvalidJsonConfigurationException::fromFile($filepath, json_last_error_msg());
        }

        if (!is_array($data)) {
            return [];
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * Encodes configuration data to JSON format string.
     *
     * Uses JSON_PRETTY_PRINT for readability, JSON_UNESCAPED_SLASHES to
     * preserve URLs, and JSON_UNESCAPED_UNICODE to maintain Unicode
     * characters. Throws on encoding errors.
     *
     * @param array<string, mixed> $data Configuration data to encode
     *
     * @throws ConfigurationEncodingFailedException If JSON encoding fails
     *
     * @return string Pretty-printed JSON string
     */
    #[Override()]
    public function encode(array $data): string
    {
        try {
            return json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $jsonException) {
            throw ConfigurationEncodingFailedException::forFormat('JSON', $jsonException->getMessage());
        }
    }
}
