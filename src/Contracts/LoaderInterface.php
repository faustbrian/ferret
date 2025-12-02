<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Contracts;

use Cline\Ferret\Exceptions\LoaderException;

/**
 * Interface for configuration file loaders.
 *
 * Each loader is responsible for parsing a specific file format
 * (JSON, YAML, PHP, INI, etc.) and returning the configuration
 * as an associative array.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface LoaderInterface
{
    /**
     * Get the file extensions this loader supports.
     *
     * @return array<string> List of supported extensions without dots (e.g., ['json'])
     */
    public function extensions(): array;

    /**
     * Load and parse a configuration file.
     *
     * @param string $filepath Absolute path to the configuration file
     *
     * @throws LoaderException If the file cannot be parsed
     *
     * @return array<string, mixed> Parsed configuration data
     */
    public function load(string $filepath): array;

    /**
     * Encode configuration data to the loader's format.
     *
     * @param array<string, mixed> $data Configuration data to encode
     *
     * @throws LoaderException If the data cannot be encoded
     *
     * @return string Encoded configuration string
     */
    public function encode(array $data): string;
}
