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
 * Supports searching for configuration files in various formats (JSON, YAML, PHP, INI)
 * and locations (rc files, config directories, package.json properties).
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Searcher
{
    /**
     * Registered loaders indexed by extension.
     *
     * @var array<string, LoaderInterface>
     */
    private array $loadersByExtension = [];

    /**
     * Cache of loaded configurations.
     *
     * @var array<string, SearchResult>
     */
    private array $loadCache = [];

    /**
     * Cache of search results.
     *
     * @var array<string, ?SearchResult>
     */
    private array $searchCache = [];

    /**
     * Create a new searcher instance.
     *
     * @param string                             $moduleName     The module name to search for
     * @param array<string>                      $searchPlaces   Custom search places (relative paths)
     * @param array<string, LoaderInterface>     $loaders        Custom loaders keyed by extension
     * @param SearchStrategy                     $searchStrategy Directory traversal strategy
     * @param null|string                        $stopDir        Directory to stop searching at
     * @param null|string                        $packageProp    Property name in package.json
     * @param bool                               $cache          Whether to enable caching
     * @param null|callable(SearchResult): mixed $transform      Transform function for loaded config
     * @param bool                               $ignoreEmpty    Skip empty configuration files
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
     * Search for configuration starting from a directory.
     *
     * @param  null|string       $searchFrom Directory to start searching from (defaults to cwd)
     * @return null|SearchResult The found configuration, or null if not found
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
     * Clear the load cache.
     */
    public function clearLoadCache(): void
    {
        $this->loadCache = [];
    }

    /**
     * Clear the search cache.
     */
    public function clearSearchCache(): void
    {
        $this->searchCache = [];
    }

    /**
     * Clear all caches.
     */
    public function clearCaches(): void
    {
        $this->clearLoadCache();
        $this->clearSearchCache();
    }

    /**
     * Get the loader for a specific file.
     *
     * @throws UnsupportedExtensionException If no loader exists for the extension
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
     * Check if a loader exists for the given extension.
     */
    public function hasLoaderForExtension(string $extension): bool
    {
        return array_key_exists($extension, $this->loadersByExtension);
    }

    /**
     * Get the module name.
     */
    public function getModuleName(): string
    {
        return $this->moduleName;
    }

    /**
     * Get the search places.
     *
     * @return array<string>
     */
    public function getSearchPlaces(): array
    {
        return $this->searchPlaces;
    }

    /**
     * Validate the module name.
     *
     * @throws InvalidModuleNameException If module name is invalid
     */
    private function validateModuleName(string $moduleName): void
    {
        if ($moduleName === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $moduleName)) {
            throw InvalidModuleNameException::forName($moduleName);
        }
    }

    /**
     * Register default loaders.
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
     * Register custom loaders.
     *
     * @param array<string, LoaderInterface> $loaders
     */
    private function registerLoaders(array $loaders): void
    {
        foreach ($loaders as $extension => $loader) {
            $this->loadersByExtension[$extension] = $loader;
        }
    }

    /**
     * Initialize search places with defaults if not provided.
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
     * Perform the actual search.
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
     * Determine if search should stop at this directory.
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
     * Get the stop directory.
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
     * Get the user's home directory.
     */
    private function getHomeDirectory(): ?string
    {
        $home = Request::server('HOME') ?? Env::get('HOME');

        return is_string($home) ? $home : null;
    }

    /**
     * Search global config directory.
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

            if (file_exists($filepath) && is_readable($filepath)) {
                try {
                    return $this->loadFile($filepath);
                } catch (LoaderException|UnsupportedExtensionException|MissingPackageJsonPropertyException) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Load a configuration file.
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
     * Load a package.json file and extract the module property.
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
     * Load an extensionless rc file.
     *
     * Tries JSON first, then YAML.
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
     * Get the file extension.
     *
     * Handles dotfiles correctly - a file like ".myapprc" has no extension.
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
     * Apply transform function to result.
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
