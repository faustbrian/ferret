<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Exceptions;

use function sprintf;

/**
 * Exception thrown when a configuration module cannot be found.
 *
 * This exception is raised when Ferret searches for a configuration module
 * but cannot locate any matching configuration files in the specified search
 * paths. This typically occurs when the module name doesn't match any existing
 * configuration files or when searching from an incorrect directory.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ModuleNotFoundException extends ConfigurationNotFoundException
{
    /**
     * Create an exception for a module that cannot be found.
     *
     * @param  string $moduleName The name of the configuration module being searched for
     * @param  string $searchPath The directory path where the search was initiated
     * @return self   The exception instance with a descriptive error message
     */
    public static function forModule(string $moduleName, string $searchPath): self
    {
        return new self(sprintf(
            'No configuration found for "%s" searching from "%s"',
            $moduleName,
            $searchPath,
        ));
    }
}
