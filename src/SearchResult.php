<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret;

use function data_get;

/**
 * Data transfer object representing a configuration search result.
 *
 * Contains the parsed configuration data, the filepath where it was found,
 * and metadata about the configuration file.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class SearchResult
{
    /**
     * Create a new search result.
     *
     * @param array<string, mixed> $config   The parsed configuration data
     * @param string               $filepath The absolute path to the configuration file
     * @param bool                 $isEmpty  Whether the configuration file was empty
     */
    public function __construct(
        public array $config,
        public string $filepath,
        public bool $isEmpty = false,
    ) {}

    /**
     * Check if the configuration is empty.
     */
    public function isEmpty(): bool
    {
        return $this->isEmpty || $this->config === [];
    }

    /**
     * Get a value from the configuration using dot notation.
     *
     * @param  null|string $key     The key to retrieve (null returns entire config)
     * @param  mixed       $default Default value if key not found
     * @return mixed       The configuration value or default
     */
    public function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return data_get($this->config, $key, $default);
    }

    /**
     * Check if the configuration has a key.
     *
     * @param string $key The key to check using dot notation
     */
    public function has(string $key): bool
    {
        return data_get($this->config, $key) !== null;
    }

    /**
     * Convert to array representation.
     *
     * @return array{config: array<string, mixed>, filepath: string, isEmpty: bool}
     */
    public function toArray(): array
    {
        return [
            'config' => $this->config,
            'filepath' => $this->filepath,
            'isEmpty' => $this->isEmpty,
        ];
    }
}
