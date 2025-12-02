<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret;

use Cline\Ferret\Contracts\LoaderInterface;
use Cline\Ferret\Enums\SearchStrategy;
use Cline\Ferret\Exceptions\ConfigurationNotFoundException;
use Cline\Ferret\Exceptions\DecryptionFailedException;
use Cline\Ferret\Exceptions\DirectoryNotFoundException;
use Cline\Ferret\Exceptions\EncryptionFailedException;
use Cline\Ferret\Exceptions\FileNotFoundException;
use Cline\Ferret\Exceptions\InvalidBase64KeyException;
use Cline\Ferret\Exceptions\InvalidConfigurationException;
use Cline\Ferret\Exceptions\ModuleNotFoundException;
use Cline\Ferret\Exceptions\ModuleNotLoadedException;
use Cline\Ferret\Exceptions\ReadOnlyConfigurationException;
use Cline\Ferret\Exceptions\UnsupportedExtensionException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Arr;
use Throwable;

use const DIRECTORY_SEPARATOR;
use const GLOB_NOSORT;
use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_unshift;
use function base64_decode;
use function base64_encode;
use function basename;
use function config;
use function dirname;
use function env;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function function_exists;
use function getcwd;
use function getenv;
use function glob;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function is_writable;
use function mb_rtrim;
use function mb_substr;
use function pathinfo;
use function preg_match;
use function preg_replace_callback;
use function str_starts_with;
use function throw_if;
use function unlink;

/**
 * Main manager for ferret configuration operations.
 *
 * Provides a fluent API for searching, loading, reading, and writing
 * configuration files in various formats.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FerretManager
{
    /**
     * Active configurations indexed by module name.
     *
     * @var array<string, SearchResult>
     */
    private array $configurations = [];

    /**
     * Modified configurations indexed by module name.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $modified = [];

    /**
     * Searchers indexed by module name.
     *
     * @var array<string, Searcher>
     */
    private array $searchers = [];

    /**
     * Create a new FerretManager instance.
     *
     * @param array{
     *     searchPlaces?: array<string>,
     *     loaders?: array<string, LoaderInterface>,
     *     searchStrategy?: SearchStrategy,
     *     stopDir?: string,
     *     cache?: bool,
     *     transform?: callable(SearchResult): mixed,
     *     ignoreEmpty?: bool
     * } $defaultOptions Default options for all searchers
     */
    public function __construct(
        /**
         * Default options for new searchers.
         *
         * @var array{
         *     searchPlaces?: array<string>,
         *     loaders?: array<string, LoaderInterface>,
         *     searchStrategy?: SearchStrategy,
         *     stopDir?: string,
         *     cache?: bool,
         *     transform?: callable(SearchResult): mixed,
         *     ignoreEmpty?: bool
         * }
         */
        private readonly array $defaultOptions = [],
    ) {}

    /**
     * Create or get a searcher for a module.
     *
     * @param string $moduleName The module name
     * @param array{
     *     searchPlaces?: array<string>,
     *     loaders?: array<string, LoaderInterface>,
     *     searchStrategy?: SearchStrategy,
     *     stopDir?: string,
     *     packageProp?: string,
     *     cache?: bool,
     *     transform?: callable(SearchResult): mixed,
     *     ignoreEmpty?: bool
     * } $options Options for the searcher
     * @return Searcher The configured searcher
     */
    public function explorer(string $moduleName, array $options = []): Searcher
    {
        if (!array_key_exists($moduleName, $this->searchers)) {
            $mergedOptions = [...$this->defaultOptions, ...$options];

            $this->searchers[$moduleName] = new Searcher(
                moduleName: $moduleName,
                searchPlaces: $mergedOptions['searchPlaces'] ?? [],
                loaders: $mergedOptions['loaders'] ?? [],
                searchStrategy: $mergedOptions['searchStrategy'] ?? SearchStrategy::None,
                stopDir: $mergedOptions['stopDir'] ?? null,
                packageProp: $mergedOptions['packageProp'] ?? null,
                cache: $mergedOptions['cache'] ?? true,
                transform: $mergedOptions['transform'] ?? null,
                ignoreEmpty: $mergedOptions['ignoreEmpty'] ?? true,
            );
        }

        return $this->searchers[$moduleName];
    }

    /**
     * Search for configuration.
     *
     * @param  string            $moduleName The module name to search for
     * @param  null|string       $searchFrom Directory to start searching from
     * @return null|SearchResult The found configuration, or null if not found
     */
    public function search(string $moduleName, ?string $searchFrom = null): ?SearchResult
    {
        $result = $this->explorer($moduleName)->search($searchFrom);

        if ($result instanceof SearchResult) {
            $this->configurations[$moduleName] = $result;
            $this->modified[$moduleName] = $result->config;
        }

        return $result;
    }

    /**
     * Load a specific configuration file.
     *
     * @param string $filepath   Absolute path to the configuration file
     * @param string $moduleName Optional module name to associate with this config
     *
     * @throws ConfigurationNotFoundException If file doesn't exist
     *
     * @return SearchResult The loaded configuration
     */
    public function load(string $filepath, string $moduleName = 'default'): SearchResult
    {
        $result = $this->explorer($moduleName)->load($filepath);

        $this->configurations[$moduleName] = $result;
        $this->modified[$moduleName] = $result->config;

        return $result;
    }

    /**
     * Get the entire configuration or a specific value.
     *
     * @param  string      $moduleName The module name
     * @param  null|string $key        The key to retrieve (dot notation), null for entire config
     * @param  mixed       $default    Default value if key not found
     * @return mixed       The configuration value
     */
    public function get(string $moduleName, ?string $key = null, mixed $default = null): mixed
    {
        // Ensure config is loaded
        if (!array_key_exists($moduleName, $this->modified)) {
            $result = $this->search($moduleName);

            if (!$result instanceof SearchResult) {
                return $default;
            }
        }

        $config = $this->modified[$moduleName];

        if ($key === null) {
            return $config;
        }

        return Arr::get($config, $key, $default);
    }

    /**
     * Set a configuration value.
     *
     * @param  string $moduleName The module name
     * @param  string $key        The key to set (dot notation)
     * @param  mixed  $value      The value to set
     * @return $this
     */
    public function set(string $moduleName, string $key, mixed $value): self
    {
        // Ensure config is loaded
        if (!array_key_exists($moduleName, $this->modified)) {
            $this->search($moduleName);

            if (!array_key_exists($moduleName, $this->modified)) {
                $this->modified[$moduleName] = [];
            }
        }

        $config = $this->modified[$moduleName];
        Arr::set($config, $key, $value);

        /** @var array<string, mixed> $config */
        $this->modified[$moduleName] = $config;

        return $this;
    }

    /**
     * Check if a configuration key exists.
     *
     * @param  string $moduleName The module name
     * @param  string $key        The key to check (dot notation)
     * @return bool   True if the key exists
     */
    public function has(string $moduleName, string $key): bool
    {
        // Ensure config is loaded
        if (!array_key_exists($moduleName, $this->modified)) {
            $result = $this->search($moduleName);

            if (!$result instanceof SearchResult) {
                return false;
            }
        }

        return Arr::has($this->modified[$moduleName], $key);
    }

    /**
     * Remove a configuration key.
     *
     * @param  string $moduleName The module name
     * @param  string $key        The key to remove (dot notation)
     * @return $this
     */
    public function forget(string $moduleName, string $key): self
    {
        // Ensure config is loaded
        if (!array_key_exists($moduleName, $this->modified)) {
            $this->search($moduleName);

            if (!array_key_exists($moduleName, $this->modified)) {
                return $this;
            }
        }

        $config = $this->modified[$moduleName];
        Arr::forget($config, $key);

        /** @var array<string, mixed> $config */
        $this->modified[$moduleName] = $config;

        return $this;
    }

    /**
     * Push a value onto an array configuration key.
     *
     * @param  string $moduleName The module name
     * @param  string $key        The key to push to (dot notation)
     * @param  mixed  $value      The value to push
     * @return $this
     */
    public function push(string $moduleName, string $key, mixed $value): self
    {
        $array = $this->get($moduleName, $key, []);

        if (!is_array($array)) {
            $array = [$array];
        }

        $array[] = $value;

        return $this->set($moduleName, $key, $array);
    }

    /**
     * Prepend a value to an array configuration key.
     *
     * @param  string $moduleName The module name
     * @param  string $key        The key to prepend to (dot notation)
     * @param  mixed  $value      The value to prepend
     * @return $this
     */
    public function prepend(string $moduleName, string $key, mixed $value): self
    {
        $array = $this->get($moduleName, $key, []);

        if (!is_array($array)) {
            $array = [$array];
        }

        array_unshift($array, $value);

        return $this->set($moduleName, $key, $array);
    }

    /**
     * Save the configuration to disk.
     *
     * @param string      $moduleName The module name
     * @param null|string $filepath   Custom filepath (uses original if null)
     *
     * @throws ConfigurationNotFoundException If no configuration is loaded
     * @throws InvalidConfigurationException  If file is not writable
     *
     * @return $this
     */
    public function save(string $moduleName, ?string $filepath = null): self
    {
        if (!array_key_exists($moduleName, $this->configurations)) {
            throw ModuleNotFoundException::forModule($moduleName, (string) getcwd());
        }

        $filepath ??= $this->configurations[$moduleName]->filepath;
        $directory = dirname($filepath);

        if (!is_writable($directory) || (file_exists($filepath) && !is_writable($filepath))) {
            throw ReadOnlyConfigurationException::forPath($filepath);
        }

        $loader = $this->explorer($moduleName)->getLoaderForFile($filepath);
        $encoded = $loader->encode($this->modified[$moduleName]);

        file_put_contents($filepath, $encoded);

        // Update the stored configuration
        $this->configurations[$moduleName] = new SearchResult(
            $this->modified[$moduleName],
            $filepath,
            $this->modified[$moduleName] === [],
        );

        return $this;
    }

    /**
     * Check if a module's configuration has been modified.
     *
     * @param  string $moduleName The module name
     * @return bool   True if the configuration has unsaved changes
     */
    public function isDirty(string $moduleName): bool
    {
        if (!array_key_exists($moduleName, $this->configurations) || !array_key_exists($moduleName, $this->modified)) {
            return false;
        }

        return $this->configurations[$moduleName]->config !== $this->modified[$moduleName];
    }

    /**
     * Discard unsaved changes.
     *
     * @param  string $moduleName The module name
     * @return $this
     */
    public function rollback(string $moduleName): self
    {
        if (array_key_exists($moduleName, $this->configurations)) {
            $this->modified[$moduleName] = $this->configurations[$moduleName]->config;
        }

        return $this;
    }

    /**
     * Get the filepath for a loaded configuration.
     *
     * @param  string      $moduleName The module name
     * @return null|string The filepath, or null if not loaded
     */
    public function filepath(string $moduleName): ?string
    {
        if (!array_key_exists($moduleName, $this->configurations)) {
            return null;
        }

        return $this->configurations[$moduleName]->filepath;
    }

    /**
     * Get the original loaded configuration.
     *
     * @param  string            $moduleName The module name
     * @return null|SearchResult The original search result, or null if not loaded
     */
    public function original(string $moduleName): ?SearchResult
    {
        return $this->configurations[$moduleName] ?? null;
    }

    /**
     * Clear all caches for a module.
     *
     * @param  null|string $moduleName The module name (null clears all)
     * @return $this
     */
    public function clearCache(?string $moduleName = null): self
    {
        if ($moduleName === null) {
            foreach ($this->searchers as $searcher) {
                $searcher->clearCaches();
            }

            $this->configurations = [];
            $this->modified = [];
        } elseif (array_key_exists($moduleName, $this->searchers)) {
            $this->searchers[$moduleName]->clearCaches();
            unset($this->configurations[$moduleName], $this->modified[$moduleName]);
        }

        return $this;
    }

    /**
     * Clear the search cache for a module.
     *
     * @param  null|string $moduleName The module name (null clears all)
     * @return $this
     */
    public function clearSearchCache(?string $moduleName = null): self
    {
        if ($moduleName === null) {
            foreach ($this->searchers as $searcher) {
                $searcher->clearSearchCache();
            }
        } elseif (array_key_exists($moduleName, $this->searchers)) {
            $this->searchers[$moduleName]->clearSearchCache();
        }

        return $this;
    }

    /**
     * Clear the load cache for a module.
     *
     * @param  null|string $moduleName The module name (null clears all)
     * @return $this
     */
    public function clearLoadCache(?string $moduleName = null): self
    {
        if ($moduleName === null) {
            foreach ($this->searchers as $searcher) {
                $searcher->clearLoadCache();
            }
        } elseif (array_key_exists($moduleName, $this->searchers)) {
            $this->searchers[$moduleName]->clearLoadCache();
        }

        return $this;
    }

    /**
     * Check if a configuration is loaded for a module.
     *
     * @param  string $moduleName The module name
     * @return bool   True if configuration is loaded
     */
    public function isLoaded(string $moduleName): bool
    {
        return array_key_exists($moduleName, $this->configurations);
    }

    /**
     * Get all loaded module names.
     *
     * @return array<string>
     */
    public function loadedModules(): array
    {
        return array_keys($this->configurations);
    }

    /**
     * Load all configuration files from a directory into a namespaced structure.
     *
     * Each file becomes a top-level key (filename without extension) in the configuration.
     * Supports glob patterns for filtering (e.g., '*.neon', '*.json').
     *
     * @param string      $directory  The directory path to load from
     * @param string      $moduleName The module name for this configuration namespace
     * @param string      $pattern    Glob pattern to filter files (default: '*')
     * @param null|string $keyPattern Regex pattern to extract key from filename (default: uses filename without extension)
     *
     * @throws ConfigurationNotFoundException If directory doesn't exist
     *
     * @return SearchResult The merged configuration
     */
    public function loadDirectory(
        string $directory,
        string $moduleName = 'default',
        string $pattern = '*',
        ?string $keyPattern = null,
    ): SearchResult {
        if (!is_dir($directory)) {
            throw DirectoryNotFoundException::forPath($directory);
        }

        $files = glob($directory.'/'.$pattern, GLOB_NOSORT);

        if ($files === false || $files === []) {
            $config = [];
            $result = new SearchResult($config, $directory, true);
            $this->configurations[$moduleName] = $result;
            $this->modified[$moduleName] = $config;

            return $result;
        }

        /** @var array<string, array<string, mixed>> $config */
        $config = [];
        $searcher = $this->explorer($moduleName);

        foreach ($files as $filepath) {
            if (!is_file($filepath)) {
                continue;
            }

            // Determine the key for this file
            $key = $this->extractKeyFromFilename($filepath, $keyPattern);

            try {
                $loader = $searcher->getLoaderForFile($filepath);
                $config[$key] = $loader->load($filepath);
            } catch (UnsupportedExtensionException) {
                // Skip files with unsupported extensions
                continue;
            }
        }

        $result = new SearchResult($config, $directory, $config === []);
        $this->configurations[$moduleName] = $result;
        $this->modified[$moduleName] = $config;

        return $result;
    }

    /**
     * Convert a configuration file from one format to another.
     *
     * @param string $sourcePath      Path to the source configuration file
     * @param string $destinationPath Path for the converted configuration file
     *
     * @throws ConfigurationNotFoundException If source file doesn't exist
     * @throws InvalidConfigurationException  If source or destination format is not supported
     *
     * @return $this
     */
    public function convert(string $sourcePath, string $destinationPath): self
    {
        $sourceSearcher = $this->explorer('_convert_source');
        $destSearcher = $this->explorer('_convert_dest');

        // Load source file
        $sourceLoader = $sourceSearcher->getLoaderForFile($sourcePath);
        $config = $sourceLoader->load($sourcePath);

        // Encode to destination format
        $destLoader = $destSearcher->getLoaderForFile($destinationPath);
        $encoded = $destLoader->encode($config);

        // Ensure destination directory exists
        $destDir = dirname($destinationPath);

        if (!is_dir($destDir)) {
            throw ReadOnlyConfigurationException::forPath($destinationPath);
        }

        file_put_contents($destinationPath, $encoded);

        return $this;
    }

    /**
     * Convert a loaded module's configuration to a specific format string.
     *
     * @param string $moduleName The module name
     * @param string $format     The target format (json, yaml, neon, ini, xml, php)
     *
     * @throws ConfigurationNotFoundException If module is not loaded
     * @throws InvalidConfigurationException  If format is not supported
     *
     * @return string The encoded configuration string
     */
    public function toFormat(string $moduleName, string $format): string
    {
        if (!array_key_exists($moduleName, $this->modified)) {
            $result = $this->search($moduleName);

            if (!$result instanceof SearchResult) {
                throw ModuleNotFoundException::forModule($moduleName, (string) getcwd());
            }
        }

        $searcher = $this->explorer($moduleName);

        // Create a dummy filepath to get the loader for the format
        $dummyPath = 'config.'.$format;

        if (!$searcher->hasLoaderForExtension($format)) {
            throw UnsupportedExtensionException::forExtension($format);
        }

        $loader = $searcher->getLoaderForFile($dummyPath);

        return $loader->encode($this->modified[$moduleName]);
    }

    /**
     * Get a loader by extension.
     *
     * @param string $extension The file extension (e.g., 'json', 'yaml')
     *
     * @throws InvalidConfigurationException If extension is not supported
     *
     * @return LoaderInterface The loader for the extension
     */
    public function getLoader(string $extension): LoaderInterface
    {
        return $this->explorer('_loader')->getLoaderForFile('config.'.$extension);
    }

    /**
     * Combine multiple configuration files into one.
     *
     * @param string        $destinationPath Path for the combined configuration file
     * @param array<string> $sourcePaths     Paths to source configuration files
     * @param bool          $deep            Deep merge arrays (default: true)
     *
     * @throws ConfigurationNotFoundException If any source file doesn't exist
     * @throws InvalidConfigurationException  If any format is not supported
     *
     * @return $this
     */
    public function combine(string $destinationPath, array $sourcePaths, bool $deep = true): self
    {
        if ($sourcePaths === []) {
            throw ReadOnlyConfigurationException::forPath($destinationPath);
        }

        $combined = [];
        $searcher = $this->explorer('_combine');

        foreach ($sourcePaths as $sourcePath) {
            if (!file_exists($sourcePath)) {
                throw FileNotFoundException::forPath($sourcePath);
            }

            $loader = $searcher->getLoaderForFile($sourcePath);
            $config = $loader->load($sourcePath);

            $combined = $deep ? $this->deepMerge($combined, $config) : array_merge($combined, $config);
        }

        // Encode to destination format
        $destLoader = $searcher->getLoaderForFile($destinationPath);
        $encoded = $destLoader->encode($combined);

        // Ensure destination directory exists
        $destDir = dirname($destinationPath);

        if (!is_dir($destDir)) {
            throw ReadOnlyConfigurationException::forPath($destinationPath);
        }

        file_put_contents($destinationPath, $encoded);

        return $this;
    }

    /**
     * Encrypt a configuration file.
     *
     * Uses Laravel's Encrypter (AES-256-CBC). The encrypted file will have '.encrypted' appended.
     * Key format: 'base64:...' or raw base64 string.
     *
     * @param string      $filepath Path to the configuration file (or base path when using env)
     * @param null|string $key      Encryption key (generates one if null)
     * @param null|string $cipher   Cipher algorithm (default from config or AES-256-CBC)
     * @param bool        $prune    Delete the original file after encryption (default: false)
     * @param bool        $force    Overwrite existing encrypted file (default: false)
     * @param null|string $env      Environment name (e.g., 'production')
     * @param null|string $envStyle Environment style: 'suffix' (config.production.json) or 'directory' (production/config.json)
     *
     * @throws ConfigurationNotFoundException If file doesn't exist
     * @throws InvalidConfigurationException  If encryption fails or encrypted file exists and force is false
     *
     * @return array{path: string, key: string} The encrypted file path and key (base64: prefixed)
     */
    public function encrypt(
        string $filepath,
        ?string $key = null,
        ?string $cipher = null,
        bool $prune = false,
        bool $force = false,
        ?string $env = null,
        ?string $envStyle = null,
    ): array {
        /** @var string $resolvedCipher */
        $resolvedCipher = $cipher ?? $this->getEncryptionConfig('cipher', 'AES-256-CBC');

        // Handle environment-specific file paths
        $sourcePath = $this->resolveEnvFilePath($filepath, $env, $envStyle);

        if (!file_exists($sourcePath)) {
            throw FileNotFoundException::forPath($sourcePath);
        }

        $contents = file_get_contents($sourcePath);

        if ($contents === false) {
            throw ReadOnlyConfigurationException::forPath($sourcePath);
        }

        // Generate key if not provided
        $keyPassed = $key !== null;

        $key = $keyPassed ? $this->parseEncryptionKey($key) : Encrypter::generateKey($resolvedCipher);

        try {
            $encrypter = new Encrypter($key, $resolvedCipher);
            $encryptedData = $encrypter->encrypt($contents);
        } catch (Throwable $throwable) {
            throw EncryptionFailedException::forPath($sourcePath, $throwable->getMessage());
        }

        $encryptedPath = $sourcePath.'.encrypted';

        if (!$force && file_exists($encryptedPath)) {
            throw EncryptionFailedException::forPath($sourcePath, 'Encrypted file already exists. Use force=true to overwrite.');
        }

        file_put_contents($encryptedPath, $encryptedData);

        if ($prune) {
            unlink($sourcePath);
        }

        return [
            'path' => $encryptedPath,
            'key' => $keyPassed ? $key : 'base64:'.base64_encode($key),
        ];
    }

    /**
     * Decrypt an encrypted configuration file.
     *
     * @param string      $encryptedPath Path to the encrypted file (or base path when using env)
     * @param string      $key           The decryption key (base64: prefixed or raw)
     * @param bool        $force         Overwrite existing decrypted file (default: false)
     * @param null|string $cipher        Cipher algorithm (default from config or AES-256-CBC)
     * @param null|string $path          Custom output directory path
     * @param null|string $filename      Custom output filename
     * @param null|string $env           Environment name (e.g., 'production')
     * @param bool        $prune         Delete the encrypted file after decryption (default: false)
     * @param null|string $envStyle      Environment style: 'suffix' or 'directory'
     *
     * @throws ConfigurationNotFoundException If encrypted file doesn't exist
     * @throws InvalidConfigurationException  If decryption fails or file exists and force is false
     *
     * @return string Path to the decrypted file
     */
    public function decrypt(
        string $encryptedPath,
        string $key,
        bool $force = false,
        ?string $cipher = null,
        ?string $path = null,
        ?string $filename = null,
        ?string $env = null,
        bool $prune = false,
        ?string $envStyle = null,
    ): string {
        /** @var string $resolvedCipher */
        $resolvedCipher = $cipher ?? $this->getEncryptionConfig('cipher', 'AES-256-CBC');

        // Handle environment-specific file paths
        $sourcePath = $env !== null
            ? $this->resolveEnvFilePath($encryptedPath, $env, $envStyle).'.encrypted'
            : $encryptedPath;

        if (!file_exists($sourcePath)) {
            throw FileNotFoundException::forPath($sourcePath);
        }

        // Determine output path
        $decryptedPath = $this->resolveDecryptedPath($sourcePath, $path, $filename);

        if (!$force && file_exists($decryptedPath)) {
            throw DecryptionFailedException::forPath($sourcePath, 'Decrypted file already exists. Use force=true to overwrite.');
        }

        $encryptedContents = file_get_contents($sourcePath);

        if ($encryptedContents === false) {
            throw ReadOnlyConfigurationException::forPath($sourcePath);
        }

        try {
            $parsedKey = $this->parseEncryptionKey($key);
            $encrypter = new Encrypter($parsedKey, $resolvedCipher);
            $decrypted = $encrypter->decrypt($encryptedContents);
        } catch (Throwable $throwable) {
            throw DecryptionFailedException::forPath($sourcePath, $throwable->getMessage());
        }

        // Ensure output directory exists
        $outputDir = dirname($decryptedPath);

        if (!is_dir($outputDir)) {
            throw DirectoryNotFoundException::forPath($outputDir);
        }

        file_put_contents($decryptedPath, $decrypted);

        if ($prune) {
            unlink($sourcePath);
        }

        return $decryptedPath;
    }

    /**
     * Interpolate environment variables in configuration values.
     *
     * Replaces `${VAR}` and `${VAR:-default}` patterns with environment variable values.
     * Works recursively on arrays.
     *
     * @param  mixed $value The value to interpolate
     * @return mixed The interpolated value
     */
    public function interpolate(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->interpolateString($value);
        }

        if (is_array($value)) {
            return $this->interpolateArray($value);
        }

        return $value;
    }

    /**
     * Get configuration with environment variable interpolation.
     *
     * Same as get() but interpolates `${VAR}` patterns in string values.
     *
     * @param  string      $moduleName The module name
     * @param  null|string $key        The key to get (null for entire config)
     * @param  mixed       $default    Default value if key not found
     * @return mixed       The configuration value with env vars interpolated
     */
    public function getInterpolated(string $moduleName, ?string $key = null, mixed $default = null): mixed
    {
        $value = $this->get($moduleName, $key, $default);

        return $this->interpolate($value);
    }

    /**
     * Compare two configurations and return their differences.
     *
     * Returns an array with 'added', 'removed', and 'changed' keys showing
     * what differs between the two configs using dot notation for nested paths.
     *
     * @param  array<string, mixed>                                                                                                     $original The original configuration
     * @param  array<string, mixed>                                                                                                     $modified The modified configuration
     * @return array{added: array<string, mixed>, removed: array<string, mixed>, changed: array<string, array{from: mixed, to: mixed}>}
     */
    public function diff(array $original, array $modified): array
    {
        $originalFlat = Arr::dot($original);
        $modifiedFlat = Arr::dot($modified);

        /** @var array<string, mixed> $added */
        $added = [];

        /** @var array<string, mixed> $removed */
        $removed = [];

        /** @var array<string, array{from: mixed, to: mixed}> $changed */
        $changed = [];

        // Find removed and changed keys
        foreach ($originalFlat as $key => $value) {
            $key = (string) $key;

            if (!array_key_exists($key, $modifiedFlat)) {
                $removed[$key] = $value;
            } elseif ($modifiedFlat[$key] !== $value) {
                $changed[$key] = [
                    'from' => $value,
                    'to' => $modifiedFlat[$key],
                ];
            }
        }

        // Find added keys
        foreach ($modifiedFlat as $key => $value) {
            $key = (string) $key;

            if (!array_key_exists($key, $originalFlat)) {
                $added[$key] = $value;
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
        ];
    }

    /**
     * Compare two loaded module configurations.
     *
     * @param string $moduleA The first module name
     * @param string $moduleB The second module name
     *
     * @throws ConfigurationNotFoundException If either module is not loaded
     *
     * @return array{added: array<string, mixed>, removed: array<string, mixed>, changed: array<string, array{from: mixed, to: mixed}>}
     */
    public function diffModules(string $moduleA, string $moduleB): array
    {
        $configA = $this->get($moduleA);
        $configB = $this->get($moduleB);

        throw_if(
            !is_array($configA),
            ModuleNotLoadedException::forModule($moduleA),
        );

        throw_if(
            !is_array($configB),
            ModuleNotLoadedException::forModule($moduleB),
        );

        /** @var array<string, mixed> $configA */
        /** @var array<string, mixed> $configB */
        return $this->diff($configA, $configB);
    }

    /**
     * Compare two configuration files.
     *
     * Loads both files temporarily and compares them.
     *
     * @param string $pathA Path to first configuration file
     * @param string $pathB Path to second configuration file
     *
     * @throws ConfigurationNotFoundException If either file doesn't exist
     *
     * @return array{added: array<string, mixed>, removed: array<string, mixed>, changed: array<string, array{from: mixed, to: mixed}>}
     */
    public function diffFiles(string $pathA, string $pathB): array
    {
        $resultA = $this->load($pathA, '__diff_temp_a__');
        $resultB = $this->load($pathB, '__diff_temp_b__');

        $configA = $resultA->config;
        $configB = $resultB->config;

        // Clean up temporary modules
        unset($this->configurations['__diff_temp_a__'], $this->configurations['__diff_temp_b__']);

        return $this->diff($configA, $configB);
    }

    /**
     * Parse the encryption key (handles base64: prefix like Laravel).
     *
     * @param  string $key The encryption key
     * @return string The parsed key
     */
    private function parseEncryptionKey(string $key): string
    {
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(mb_substr($key, 7), true);

            throw_if($decoded === false, InvalidBase64KeyException::invalidEncoding());

            return $decoded;
        }

        return $key;
    }

    /**
     * Deep merge two arrays recursively.
     *
     * @param  array<string, mixed> $array1 Base array
     * @param  array<string, mixed> $array2 Array to merge into base
     * @return array<string, mixed> Merged array
     */
    private function deepMerge(array $array1, array $array2): array
    {
        foreach ($array2 as $key => $value) {
            if (is_array($value) && array_key_exists($key, $array1) && is_array($array1[$key])) {
                /** @var array<string, mixed> $existingValue */
                $existingValue = $array1[$key];

                /** @var array<string, mixed> $newValue */
                $newValue = $value;
                $array1[$key] = $this->deepMerge($existingValue, $newValue);
            } else {
                $array1[$key] = $value;
            }
        }

        return $array1;
    }

    /**
     * Resolve environment-specific file path.
     *
     * Supports two styles:
     * - 'suffix': config.json + env 'production' -> config.production.json
     * - 'directory': config.json + env 'production' -> production/config.json
     *
     * @param  string      $filepath Base file path
     * @param  null|string $env      Environment name (e.g., 'production', 'staging')
     * @param  null|string $envStyle Environment style: 'suffix' or 'directory' (default from config)
     * @return string      Resolved file path
     */
    private function resolveEnvFilePath(string $filepath, ?string $env, ?string $envStyle = null): string
    {
        if ($env === null) {
            return $filepath;
        }

        $envStyle ??= $this->getEncryptionConfig('env_style', 'suffix');
        $directory = dirname($filepath);
        $filename = basename($filepath);

        if ($envStyle === 'directory') {
            /** @var null|string $envDirectory */
            $envDirectory = $this->getEncryptionConfig('env_directory');

            if ($envDirectory !== null) {
                // Use configured env_directory as base: config/app.json -> config/{env_directory}/production/app.json
                return $directory.DIRECTORY_SEPARATOR.$envDirectory.DIRECTORY_SEPARATOR.$env.DIRECTORY_SEPARATOR.$filename;
            }

            // Default: config/app.json -> config/production/app.json
            return $directory.DIRECTORY_SEPARATOR.$env.DIRECTORY_SEPARATOR.$filename;
        }

        // Suffix style: config.json -> config.production.json
        $baseFilename = pathinfo($filepath, PATHINFO_FILENAME);
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);

        return $directory.DIRECTORY_SEPARATOR.$baseFilename.'.'.$env.'.'.$extension;
    }

    /**
     * Get encryption configuration value.
     *
     * @param  string $key     Configuration key within encryption array
     * @param  mixed  $default Default value if not configured
     * @return mixed  Configuration value
     */
    private function getEncryptionConfig(string $key, mixed $default = null): mixed
    {
        if (function_exists('config')) {
            return config('ferret.encryption.'.$key, $default);
        }

        return $default;
    }

    /**
     * Resolve the decrypted file output path.
     *
     * @param  string      $encryptedPath Original encrypted file path
     * @param  null|string $path          Custom output directory
     * @param  null|string $filename      Custom output filename
     * @return string      Resolved output path
     */
    private function resolveDecryptedPath(string $encryptedPath, ?string $path, ?string $filename): string
    {
        // Determine base filename (remove .encrypted suffix)
        $baseFilename = preg_match('/\.encrypted$/', $encryptedPath)
            ? basename($encryptedPath, '.encrypted')
            : basename($encryptedPath).'.decrypted';

        // Use custom filename if provided
        $outputFilename = $filename ?? $baseFilename;

        // Use custom path or original directory
        $outputDir = $path ?? dirname($encryptedPath);

        return mb_rtrim($outputDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$outputFilename;
    }

    /**
     * Extract the configuration key from a filename.
     *
     * @param  string      $filepath   The file path
     * @param  null|string $keyPattern Optional regex pattern with a capture group for the key
     * @return string      The extracted key
     */
    private function extractKeyFromFilename(string $filepath, ?string $keyPattern): string
    {
        $filename = basename($filepath);

        if ($keyPattern === null) {
            return pathinfo($filepath, PATHINFO_FILENAME);
        }

        if (preg_match($keyPattern, $filename, $matches) && array_key_exists(1, $matches)) {
            return $matches[1];
        }

        return pathinfo($filepath, PATHINFO_FILENAME);
    }

    /**
     * Interpolate environment variables in a string.
     *
     * Supports:
     * - `${VAR}` - replaced with env value or empty string
     * - `${VAR:-default}` - replaced with env value or default
     *
     * @param  string $value The string to interpolate
     * @return string The interpolated string
     */
    private function interpolateString(string $value): string
    {
        // Pattern: ${VAR} or ${VAR:-default}
        return preg_replace_callback(
            '/\$\{([A-Z_][A-Z0-9_]*)(?::-([^}]*))?\}/i',
            function (array $matches): string {
                $varName = $matches[1];
                $default = $matches[2] ?? '';

                $envValue = getenv($varName);

                if ($envValue === false) {
                    // Try Laravel's env() helper if available
                    if (function_exists('env')) {
                        /** @var null|string $laravelEnv */
                        $laravelEnv = env($varName);

                        return $laravelEnv ?? $default;
                    }

                    return $default;
                }

                return $envValue;
            },
            $value,
        ) ?? $value;
    }

    /**
     * Interpolate environment variables in an array recursively.
     *
     * @param  array<mixed> $array The array to interpolate
     * @return array<mixed> The interpolated array
     */
    private function interpolateArray(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $result[$key] = $this->interpolate($value);
        }

        return $result;
    }
}
