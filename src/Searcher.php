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
use Cline\Ferret\Exceptions\FileNotFoundException;
use Cline\Ferret\Exceptions\InvalidModuleNameException;
use Cline\Ferret\Exceptions\LoaderException;
use Cline\Ferret\Exceptions\MissingPackageJsonPropertyException;
use Cline\Ferret\Exceptions\UnsupportedExtensionException;
use Cline\Ferret\Loaders\IniLoader;
use Cline\Ferret\Loaders\JsonLoader;
use Cline\Ferret\Loaders\NeonLoader;
use Cline\Ferret\Loaders\PhpLoader;
use Cline\Ferret\Loaders\TomlLoader;
use Cline\Ferret\Loaders\XmlLoader;
use Cline\Ferret\Loaders\YamlLoader;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Request;

use const PATHINFO_EXTENSION;

use function array_key_exists;
use function basename;
use function dirname;
use function file_exists;
use function getcwd;
use function is_array;
use function is_dir;
use function is_readable;
use function is_string;
use function mb_rtrim;
use function mb_substr;
use function pathinfo;
use function preg_match;
use function realpath;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;

/**
 * Searches for configuration files in the filesystem.
 *
 * Implements a flexible configuration file discovery system supporting multiple
 * formats (JSON, YAML, PHP, INI, TOML, NEON, XML) and search strategies
 * (none, project, global). Searches follow cosmiconfig-style patterns with
 * support for rc files, config directories, and package.json properties.
 *
 * The searcher supports:
 * - Multiple file formats with pluggable loaders
 * - Directory traversal with configurable strategies
 * - Caching for performance optimization
 * - Custom search places and stop directories
 * - Transform functions for post-processing
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Searcher
{
    /**
     * Registered loaders indexed by file extension.
     *
     * Maps file extensions (e.g., 'json', 'yaml') to their corresponding
     * loader implementations for parsing configuration files.
     *
     * @var array<string, LoaderInterface>
     */
    private array $loadersByExtension = [];

    /**
     * Cache of loaded configuration files.
     *
     * Stores SearchResult objects indexed by file path to avoid
     * re-parsing files during the same request.
     *
     * @var array<string, SearchResult>
     */
    private array $loadCache = [];

    /**
     * Cache of configuration search results.
     *
     * Stores search results indexed by search parameters to avoid
     * redundant filesystem traversal during the same request.
     *
     * @var array<string, ?SearchResult>
     */
    private array $searchCache = [];

    /**
     * Creates a new searcher instance for configuration file discovery.
     *
     * Initializes the searcher with module name, search locations, and behavior
     * options. Automatically registers default loaders for all supported formats
     * and processes custom configuration.
     *
     * @param string                             $moduleName     The module name to search for (e.g., 'myapp'). Used to
     *                                                           construct search file patterns like '.myapprc', 'myapp.config.js'.
     *                                                           Must contain only alphanumeric characters, hyphens, and underscores.
     * @param array<string>                      $searchPlaces   Custom search places as relative paths from search directory.
     *                                                           If empty, defaults to standard patterns (rc files, .config dir,
     *                                                           package.json). Examples: ['.myapprc', '.config/myapp.json'].
     * @param array<string, LoaderInterface>     $loaders        Custom loaders keyed by file extension. These override or
     *                                                           extend the default loaders (json, yaml, php, ini, toml, neon, xml).
     *                                                           Use to add support for custom file formats.
     * @param SearchStrategy                     $searchStrategy Directory traversal strategy controlling how far to search.
     *                                                           None: only search start directory. Project: stop at project root
     *                                                           (composer.json/package.json). Global: traverse up to home directory.
     * @param null|string                        $stopDir        Absolute path to directory where traversal should stop, overriding
     *                                                           the behavior defined by searchStrategy. Useful for limiting search
     *                                                           scope in monorepos or nested project structures.
     * @param null|string                        $packageProp    Property name to extract from package.json. If null, uses moduleName
     *                                                           as the property name. Allows reading configuration from package.json
     *                                                           fields like "myapp": {...}.
     * @param bool                               $cache          Whether to enable result caching. When enabled, prevents redundant
     *                                                           file parsing and filesystem traversal for the same searches within
     *                                                           a single request. Recommended for production use.
     * @param null|callable(SearchResult): mixed $transform      Optional transform function to process loaded configuration before
     *                                                           returning. Receives SearchResult, must return SearchResult or array.
     *                                                           Use for validation, normalization, or environment-specific adjustments.
     * @param bool                               $ignoreEmpty    Whether to skip empty configuration files during search. When true,
     *                                                           continues searching if a config file is found but contains no data.
     *                                                           When false, returns empty configs immediately.
     *
     * @throws InvalidModuleNameException If moduleName contains invalid characters or is empty
     */
    public function __construct(
        private readonly string $moduleName,
        private array $searchPlaces = [],
        array $loaders = [],
        private readonly SearchStrategy $searchStrategy = SearchStrategy::None,
        private readonly ?string $stopDir = null,
        private readonly ?string $packageProp = null,
        private readonly bool $cache = true,
        /** @var null|callable(SearchResult): mixed */
        private $transform = null,
        private readonly bool $ignoreEmpty = true,
    ) {
        $this->validateModuleName($moduleName);
        $this->registerDefaultLoaders();
        $this->registerLoaders($loaders);
        $this->initializeSearchPlaces();
    }

    /**
     * Searches for configuration files starting from a directory.
     *
     * Traverses the directory tree according to the configured search strategy,
     * checking each search place in order. Returns the first valid configuration
     * found, or null if no configuration exists. Results are cached if caching
     * is enabled.
     *
     * @param  null|string       $searchFrom Directory to start searching from. Defaults to current
     *                                       working directory. Can be a file path (directory will be extracted).
     * @return null|SearchResult The found configuration wrapped in SearchResult, or null if no configuration found
     */
    public function search(?string $searchFrom = null): ?SearchResult
    {
        $searchFrom ??= (string) getcwd();
        $searchFrom = realpath($searchFrom) ?: $searchFrom;

        $cacheKey = sprintf('search:%s:%s', $this->moduleName, $searchFrom);

        if ($this->cache && array_key_exists($cacheKey, $this->searchCache)) {
            return $this->searchCache[$cacheKey];
        }

        $result = $this->performSearch($searchFrom);

        if ($this->cache) {
            $this->searchCache[$cacheKey] = $result;
        }

        return $result;
    }

    /**
     * Load a specific configuration file.
     *
     * @param string $filepath Absolute path to the configuration file
     *
     * @throws FileNotFoundException         If file doesn't exist
     * @throws LoaderException               If file cannot be parsed
     * @throws UnsupportedExtensionException If file extension is not supported
     *
     * @return SearchResult The loaded configuration
     */
    public function load(string $filepath): SearchResult
    {
        $cacheKey = 'load:'.$filepath;

        if ($this->cache && array_key_exists($cacheKey, $this->loadCache)) {
            return $this->loadCache[$cacheKey];
        }

        if (!file_exists($filepath) || !is_readable($filepath)) {
            throw FileNotFoundException::forPath($filepath);
        }

        $result = $this->loadFile($filepath);

        if ($this->cache) {
            $this->loadCache[$cacheKey] = $result;
        }

        return $result;
    }

    /**
     * Clears the load cache.
     *
     * Removes all cached file load results. Useful when configuration
     * files may have changed during request execution.
     */
    public function clearLoadCache(): void
    {
        $this->loadCache = [];
    }

    /**
     * Clears the search cache.
     *
     * Removes all cached search results. Useful when configuration
     * files may have changed during request execution.
     */
    public function clearSearchCache(): void
    {
        $this->searchCache = [];
    }

    /**
     * Clears all caches.
     *
     * Convenience method that clears both load and search caches.
     * Equivalent to calling clearLoadCache() and clearSearchCache().
     */
    public function clearCaches(): void
    {
        $this->clearLoadCache();
        $this->clearSearchCache();
    }

    /**
     * Gets the loader for a specific file based on its extension.
     *
     * Extracts the file extension and returns the registered loader
     * capable of parsing that file format.
     *
     * @param string $filepath Path to the file (extension will be extracted)
     *
     * @throws UnsupportedExtensionException If no loader exists for the file extension
     *
     * @return LoaderInterface The loader instance for the file's format
     */
    public function getLoaderForFile(string $filepath): LoaderInterface
    {
        $extension = $this->getExtension($filepath);

        if (!array_key_exists($extension, $this->loadersByExtension)) {
            throw UnsupportedExtensionException::forExtension($extension);
        }

        return $this->loadersByExtension[$extension];
    }

    /**
     * Checks if a loader exists for the given file extension.
     *
     * @param  string $extension The file extension to check (e.g., 'json', 'yaml')
     * @return bool   True if a loader is registered for this extension, false otherwise
     */
    public function hasLoaderForExtension(string $extension): bool
    {
        return array_key_exists($extension, $this->loadersByExtension);
    }

    /**
     * Gets the configured module name.
     *
     * @return string The module name used for configuration file search patterns
     */
    public function getModuleName(): string
    {
        return $this->moduleName;
    }

    /**
     * Gets the configured search places.
     *
     * @return array<string> Array of relative paths to search for configuration files
     */
    public function getSearchPlaces(): array
    {
        return $this->searchPlaces;
    }

    /**
     * Validates the module name format.
     *
     * Ensures the module name contains only alphanumeric characters,
     * hyphens, and underscores. Empty strings are not allowed.
     *
     * @param string $moduleName The module name to validate
     *
     * @throws InvalidModuleNameException If module name is empty or contains invalid characters
     */
    private function validateModuleName(string $moduleName): void
    {
        if ($moduleName === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $moduleName)) {
            throw InvalidModuleNameException::forName($moduleName);
        }
    }

    /**
     * Registers default loaders for all supported file formats.
     *
     * Creates and registers loaders for JSON, YAML, PHP, INI, NEON, TOML,
     * and XML formats. Each loader is mapped to its supported file extensions.
     */
    private function registerDefaultLoaders(): void
    {
        $defaultLoaders = [
            new JsonLoader(),
            new YamlLoader(),
            new PhpLoader(),
            new IniLoader(),
            new NeonLoader(),
            new TomlLoader(),
            new XmlLoader(),
        ];

        foreach ($defaultLoaders as $loader) {
            foreach ($loader->extensions() as $ext) {
                $this->loadersByExtension[$ext] = $loader;
            }
        }
    }

    /**
     * Registers custom loaders, overriding defaults if necessary.
     *
     * Allows extending or replacing the default loader set with custom
     * implementations for specific file extensions.
     *
     * @param array<string, LoaderInterface> $loaders Custom loaders keyed by file extension
     */
    private function registerLoaders(array $loaders): void
    {
        foreach ($loaders as $extension => $loader) {
            $this->loadersByExtension[$extension] = $loader;
        }
    }

    /**
     * Initializes search places with default patterns if not provided.
     *
     * Generates cosmiconfig-style search patterns including rc files,
     * .config directory files, and package.json. Patterns are constructed
     * using the module name and support all registered file extensions.
     */
    private function initializeSearchPlaces(): void
    {
        if ($this->searchPlaces !== []) {
            return;
        }

        $name = $this->moduleName;

        // Default search places matching JS ferret behavior
        $this->searchPlaces = [
            'package.json',
            sprintf('.%src', $name),
            sprintf('.%src.json', $name),
            sprintf('.%src.yaml', $name),
            sprintf('.%src.yml', $name),
            sprintf('.%src.php', $name),
            sprintf('.%src.ini', $name),
            sprintf('.%src.neon', $name),
            sprintf('.%src.toml', $name),
            sprintf('.%src.xml', $name),
            sprintf('.config/%src', $name),
            sprintf('.config/%src.json', $name),
            sprintf('.config/%src.yaml', $name),
            sprintf('.config/%src.yml', $name),
            sprintf('.config/%src.php', $name),
            sprintf('.config/%src.ini', $name),
            sprintf('.config/%src.neon', $name),
            sprintf('.config/%src.toml', $name),
            sprintf('.config/%src.xml', $name),
            $name.'.config.php',
            $name.'.config.json',
            $name.'.config.neon',
            $name.'.config.xml',
        ];
    }

    /**
     * Performs the actual filesystem search for configuration files.
     *
     * Traverses the directory tree starting from the given directory,
     * checking each search place at every level according to the configured
     * strategy. Skips unreadable or unparseable files. For global strategy,
     * also checks ~/.config/{moduleName}/ if no config found.
     *
     * @param  string            $searchFrom Starting directory for the search
     * @return null|SearchResult The first valid configuration found, or null if none exists
     */
    private function performSearch(string $searchFrom): ?SearchResult
    {
        $directory = is_dir($searchFrom) ? $searchFrom : dirname($searchFrom);
        $stopDir = $this->getStopDir();

        while (true) {
            foreach ($this->searchPlaces as $searchPlace) {
                $filepath = mb_rtrim($directory, '/').'/'.$searchPlace;

                if (!file_exists($filepath)) {
                    continue;
                }

                if (!is_readable($filepath)) {
                    continue;
                }

                try {
                    $result = $this->loadFile($filepath);

                    if ($this->ignoreEmpty && $result->isEmpty()) {
                        continue;
                    }

                    return $result;
                } catch (LoaderException|UnsupportedExtensionException|MissingPackageJsonPropertyException) {
                    // Skip files that can't be loaded and try next
                    continue;
                }
            }

            // Check if we should stop traversing
            if ($this->shouldStopSearching($directory, $stopDir)) {
                break;
            }

            // Move to parent directory
            $parent = dirname($directory);

            if ($parent === $directory) {
                break; // Reached filesystem root
            }

            $directory = $parent;
        }

        // For global strategy, also check ~/.config/{moduleName}/
        if ($this->searchStrategy === SearchStrategy::Global) {
            $result = $this->searchGlobalConfig();

            if ($result instanceof SearchResult) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Determines if directory traversal should stop at this directory.
     *
     * Respects the configured stop directory and search strategy. For 'none'
     * strategy, stops immediately. For 'project' strategy, stops at project
     * roots (composer.json or package.json).
     *
     * @param  string      $directory Current directory being checked
     * @param  null|string $stopDir   Configured stop directory, if any
     * @return bool        True if search should stop, false to continue traversing
     */
    private function shouldStopSearching(string $directory, ?string $stopDir): bool
    {
        // Always stop if we've reached the stop directory
        if ($stopDir !== null && $directory === $stopDir) {
            return true;
        }

        // For "none" strategy, don't traverse at all
        if ($this->searchStrategy === SearchStrategy::None) {
            return true;
        }

        // For "project" strategy, stop when we find composer.json or package.json
        return $this->searchStrategy === SearchStrategy::Project && (file_exists($directory.'/composer.json') || file_exists($directory.'/package.json'));
    }

    /**
     * Gets the directory where search traversal should stop.
     *
     * Returns the configured stopDir if set, or the home directory for
     * global strategy, or null for no stop directory.
     *
     * @return null|string Absolute path to stop directory, or null for no limit
     */
    private function getStopDir(): ?string
    {
        if ($this->stopDir !== null) {
            return realpath($this->stopDir) ?: $this->stopDir;
        }

        if ($this->searchStrategy === SearchStrategy::Global) {
            $home = $this->getHomeDirectory();

            return $home !== null ? realpath($home) ?: $home : null;
        }

        return null;
    }

    /**
     * Gets the user's home directory from server variables.
     *
     * Checks HOME environment variable for the user's home directory path.
     *
     * @return null|string The home directory path, or null if not available
     */
    private function getHomeDirectory(): ?string
    {
        $home = Request::server('HOME') ?? Env::get('HOME');

        return is_string($home) ? $home : null;
    }

    /**
     * Searches the global configuration directory.
     *
     * Looks for configuration files in ~/.config/{moduleName}/ directory.
     * Checks for config files with common extensions (json, yaml, yml, php, ini).
     *
     * @return null|SearchResult The configuration if found, or null
     */
    private function searchGlobalConfig(): ?SearchResult
    {
        $home = $this->getHomeDirectory();

        if ($home === null) {
            return null;
        }

        $globalConfigDir = $home.'/.config/'.$this->moduleName;

        if (!is_dir($globalConfigDir)) {
            return null;
        }

        $extensions = ['json', 'yaml', 'yml', 'php', 'ini'];

        foreach ($extensions as $ext) {
            $filepath = $globalConfigDir.'/config.'.$ext;

            if (!file_exists($filepath)) {
                continue;
            }

            if (!is_readable($filepath)) {
                continue;
            }

            try {
                return $this->loadFile($filepath);
            } catch (LoaderException|UnsupportedExtensionException) {
                continue;
            }
        }

        return null;
    }

    /**
     * Loads a configuration file using the appropriate loader.
     *
     * Handles special cases like package.json and extensionless rc files.
     * Delegates to the appropriate loader based on file extension.
     *
     * @param string $filepath Absolute path to the configuration file
     *
     * @throws LoaderException               If file cannot be parsed
     * @throws UnsupportedExtensionException If no loader exists for the extension
     *
     * @return SearchResult The loaded configuration wrapped in SearchResult
     */
    private function loadFile(string $filepath): SearchResult
    {
        // Handle package.json specially
        if (str_ends_with($filepath, 'package.json')) {
            return $this->loadPackageJson($filepath);
        }

        // Handle extensionless rc files
        $extension = $this->getExtension($filepath);

        if ($extension === '') {
            return $this->loadExtensionlessFile($filepath);
        }

        $loader = $this->getLoaderForFile($filepath);
        $config = $loader->load($filepath);
        $isEmpty = $config === [];

        $result = new SearchResult($config, $filepath, $isEmpty);

        return $this->applyTransform($result);
    }

    /**
     * Loads a package.json file and extracts the module-specific property.
     *
     * Parses package.json and extracts configuration from a property matching
     * the module name or configured packageProp. Non-array values are wrapped
     * in a 'value' key for consistency.
     *
     * @param string $filepath Absolute path to package.json
     *
     * @throws LoaderException                     If package.json cannot be parsed
     * @throws MissingPackageJsonPropertyException If the expected property doesn't exist
     *
     * @return SearchResult The extracted configuration
     */
    private function loadPackageJson(string $filepath): SearchResult
    {
        $loader = $this->loadersByExtension['json'];
        $packageData = $loader->load($filepath);
        $prop = $this->packageProp ?? $this->moduleName;

        if (!array_key_exists($prop, $packageData)) {
            throw MissingPackageJsonPropertyException::forProperty($filepath, $prop);
        }

        $config = $packageData[$prop];

        if (!is_array($config)) {
            $config = ['value' => $config];
        }

        /** @var array<string, mixed> $config */
        $result = new SearchResult($config, $filepath, $config === []);

        return $this->applyTransform($result);
    }

    /**
     * Loads an extensionless rc file with format auto-detection.
     *
     * Attempts to parse the file as JSON first, then falls back to YAML
     * if JSON parsing fails. This supports common rc file conventions like
     * .eslintrc or .prettierrc that can be in either format.
     *
     * @param string $filepath Absolute path to the extensionless file
     *
     * @throws LoaderException If the file cannot be parsed as JSON or YAML
     *
     * @return SearchResult The parsed configuration
     */
    private function loadExtensionlessFile(string $filepath): SearchResult
    {
        // Try JSON first
        try {
            $config = $this->loadersByExtension['json']->load($filepath);

            $result = new SearchResult($config, $filepath, $config === []);

            return $this->applyTransform($result);
        } catch (LoaderException) {
            // Not JSON, try YAML
        }

        // Try YAML
        $config = $this->loadersByExtension['yaml']->load($filepath);

        $result = new SearchResult($config, $filepath, $config === []);

        return $this->applyTransform($result);
    }

    /**
     * Gets the file extension from a filepath.
     *
     * Handles dotfiles correctly - files like ".myapprc" that start with
     * a dot and contain no other dots are considered extensionless. This
     * is important for proper rc file detection.
     *
     * @param  string $filepath Path to extract extension from
     * @return string The file extension without the dot, or empty string for extensionless files
     */
    private function getExtension(string $filepath): string
    {
        $basename = basename($filepath);

        // If the filename starts with a dot and has no other dots, it's extensionless
        if (str_starts_with($basename, '.') && !str_contains(mb_substr($basename, 1), '.')) {
            return '';
        }

        return pathinfo($filepath, PATHINFO_EXTENSION);
    }

    /**
     * Applies the configured transform function to a search result.
     *
     * If a transform function is configured, calls it with the result.
     * The transform can return a modified SearchResult, an array (which
     * will be wrapped in a new SearchResult), or any other value (which
     * will be ignored, returning the original result).
     *
     * @param  SearchResult $result The result to transform
     * @return SearchResult The transformed result or original if no transform configured
     */
    private function applyTransform(SearchResult $result): SearchResult
    {
        if ($this->transform === null) {
            return $result;
        }

        $transformed = ($this->transform)($result);

        if ($transformed instanceof SearchResult) {
            return $transformed;
        }

        if (is_array($transformed)) {
            /** @var array<string, mixed> $transformed */
            return new SearchResult($transformed, $result->filepath, $result->isEmpty);
        }

        return $result;
    }
}
