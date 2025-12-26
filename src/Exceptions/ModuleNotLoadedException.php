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
 * Exception thrown when a module is not loaded or has no configuration.
 *
 * This exception is raised when attempting to access a configuration module
 * that has not been loaded into the FerretManager. This can occur when trying
 * to perform operations on a module before calling search() or load(), or when
 * a module exists but contains no configuration data.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ModuleNotLoadedException extends ConfigurationNotFoundException
{
    /**
     * Create an exception for a module that is not loaded.
     *
     * @param  string $moduleName The name of the configuration module that should be loaded
     * @return self   The exception instance with a descriptive error message
     */
    public static function forModule(string $moduleName): self
    {
        return new self(sprintf(
            "Module '%s' is not loaded or has no configuration.",
            $moduleName,
        ));
    }
}
