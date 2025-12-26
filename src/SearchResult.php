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
 * Immutable value object that encapsulates the result of a successful
 * configuration file search. Contains the parsed configuration data,
 * the source file location, and emptiness metadata. Provides convenient
 * methods for accessing nested configuration values using dot notation.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class SearchResult
{
    /**
     * Creates a new search result instance.
     *
     * @param array<string, mixed> $config   The parsed configuration data as an associative array.
     *                                       Contains the complete configuration structure loaded
     *                                       from the file, with nested arrays for hierarchical data.
     * @param string               $filepath The absolute path to the configuration file where this
     *                                       configuration was found. Used for error reporting,
     *                                       debugging, and cache invalidation.
     * @param bool                 $isEmpty  Whether the configuration file was empty (no data).
     *                                       True if the file exists but contains no configuration,
     *                                       false if it contains data. Useful for search logic
     *                                       that may want to skip empty files.
     */
    public function __construct(
        public array $config,
        public string $filepath,
        public bool $isEmpty = false,
    ) {}

    /**
     * Checks if the configuration is empty.
     *
     * A configuration is considered empty if either the isEmpty flag is set
     * or the config array contains no elements.
     *
     * @return bool True if the configuration is empty, false if it contains data
     */
    public function isEmpty(): bool
    {
        return $this->isEmpty || $this->config === [];
    }

    /**
     * Gets a value from the configuration using dot notation.
     *
     * Supports nested access using dot notation (e.g., 'database.connections.mysql').
     * If no key is provided, returns the entire configuration array.
     *
     * ```php
     * $result->get('database.host', 'localhost');
     * $result->get('app.features.0.name'); // Access array elements
     * $result->get(); // Returns entire config array
     * ```
     *
     * @param  null|string $key     The key to retrieve using dot notation, or null to get entire config
     * @param  mixed       $default Default value returned if the key doesn't exist
     * @return mixed       The configuration value at the specified path, or default if not found
     */
    public function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return data_get($this->config, $key, $default);
    }

    /**
     * Checks if the configuration has a specific key.
     *
     * Uses dot notation to check for nested keys. Returns true if the
     * key exists and is not null.
     *
     * @param  string $key The key to check using dot notation (e.g., 'database.host')
     * @return bool   True if the key exists and is not null, false otherwise
     */
    public function has(string $key): bool
    {
        return data_get($this->config, $key) !== null;
    }

    /**
     * Converts the search result to an array representation.
     *
     * Returns a structured array containing all public properties of the
     * search result. Useful for serialization, logging, or API responses.
     *
     * @return array{config: array<string, mixed>, filepath: string, isEmpty: bool} Array with config, filepath, and isEmpty keys
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
