<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Facades;

use Cline\Ferret\Contracts\LoaderInterface;
use Cline\Ferret\FerretManager;
use Cline\Ferret\Searcher;
use Cline\Ferret\SearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for accessing Ferret configuration functionality.
 *
 * @method static array<array-key, mixed>                                                                                                  array(string $moduleName, string $key)
 * @method static bool                                                                                                                     boolean(string $moduleName, string $key)
 * @method static FerretManager                                                                                                            clearCache(?string $moduleName = null)
 * @method static FerretManager                                                                                                            clearLoadCache(?string $moduleName = null)
 * @method static FerretManager                                                                                                            clearSearchCache(?string $moduleName = null)
 * @method static Collection<array-key, mixed>                                                                                             collection(string $moduleName, string $key)
 * @method static FerretManager                                                                                                            combine(string $destinationPath, array<string> $sourcePaths, bool $deep = true)
 * @method static FerretManager                                                                                                            convert(string $sourcePath, string $destinationPath)
 * @method static string                                                                                                                   decrypt(string $encryptedPath, string $key, bool $force = false, ?string $cipher = null, ?string $path = null, ?string $filename = null, ?string $env = null, bool $prune = false, ?string $envStyle = null)
 * @method static array<string>                                                                                                            decryptDirectory(string $directory, string $key, bool $force = false, ?string $cipher = null, bool $prune = false, bool $recursive = false)
 * @method static array{added: array<string, mixed>, removed: array<string, mixed>, changed: array<string, array{from: mixed, to: mixed}>} diff(array $original, array $modified)
 * @method static array{added: array<string, mixed>, removed: array<string, mixed>, changed: array<string, array{from: mixed, to: mixed}>} diffFiles(string $pathA, string $pathB)
 * @method static array{added: array<string, mixed>, removed: array<string, mixed>, changed: array<string, array{from: mixed, to: mixed}>} diffModules(string $moduleA, string $moduleB)
 * @method static array{path: string, key: string}                                                                                         encrypt(string $filepath, ?string $key = null, ?string $cipher = null, bool $prune = false, bool $force = false, ?string $env = null, ?string $envStyle = null)
 * @method static array{files: array<array{path: string, key: string}>, key: string}                                                       encryptDirectory(string $directory, ?string $key = null, ?string $cipher = null, bool $prune = false, bool $force = false, bool $recursive = false, ?string $glob = null)
 * @method static Searcher                                                                                                                 explorer(string $moduleName, array<string, mixed> $options = [])
 * @method static null|string                                                                                                              filepath(string $moduleName)
 * @method static float                                                                                                                    float(string $moduleName, string $key)
 * @method static FerretManager                                                                                                            forget(string $moduleName, string $key)
 * @method static mixed                                                                                                                    get(string $moduleName, ?string $key = null, mixed $default = null)
 * @method static mixed                                                                                                                    getInterpolated(string $moduleName, ?string $key = null, mixed $default = null)
 * @method static LoaderInterface                                                                                                          getLoader(string $extension)
 * @method static bool                                                                                                                     has(string $moduleName, string $key)
 * @method static int                                                                                                                      integer(string $moduleName, string $key)
 * @method static mixed                                                                                                                    interpolate(mixed $value)
 * @method static bool                                                                                                                     isDirty(string $moduleName)
 * @method static bool                                                                                                                     isLoaded(string $moduleName)
 * @method static SearchResult                                                                                                             load(string $filepath, string $moduleName = 'default')
 * @method static SearchResult                                                                                                             loadDirectory(string $directory, string $moduleName = 'default', string $pattern = '*', ?string $keyPattern = null)
 * @method static array<string>                                                                                                            loadedModules()
 * @method static null|SearchResult                                                                                                        original(string $moduleName)
 * @method static FerretManager                                                                                                            prepend(string $moduleName, string $key, mixed $value)
 * @method static FerretManager                                                                                                            push(string $moduleName, string $key, mixed $value)
 * @method static FerretManager                                                                                                            rollback(string $moduleName)
 * @method static FerretManager                                                                                                            save(string $moduleName, ?string $filepath = null)
 * @method static null|SearchResult                                                                                                        search(string $moduleName, ?string $searchFrom = null)
 * @method static FerretManager                                                                                                            set(string $moduleName, string $key, mixed $value)
 * @method static string                                                                                                                   string(string $moduleName, string $key)
 * @method static string                                                                                                                   toFormat(string $moduleName, string $format)
 *
 * @author Brian Faust <brian@cline.sh>
 * @see FerretManager
 */
final class Ferret extends Facade
{
    /**
     * Get the accessor key for the underlying FerretManager instance.
     *
     * Returns the service container binding key that Laravel uses to resolve
     * the FerretManager instance when static methods are called on this facade.
     *
     * @return string The service container binding key for FerretManager
     */
    protected static function getFacadeAccessor(): string
    {
        return FerretManager::class;
    }
}
