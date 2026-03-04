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
 * Exception thrown when a module name fails validation.
 *
 * Module names must be non-empty strings containing only alphanumeric characters,
 * hyphens, and underscores. This exception is raised during configuration loading
 * or module registration when the provided name violates these constraints.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidModuleNameException extends InvalidConfigurationException
{
    /**
     * Creates an exception for an invalid module name.
     *
     * @param  string $moduleName The invalid module name that failed validation
     * @return self   The configured exception instance with validation error message
     */
    public static function forName(string $moduleName): self
    {
        return new self(sprintf(
            'Invalid module name "%s". Module name must be a non-empty string containing only alphanumeric characters, hyphens, and underscores.',
            $moduleName,
        ));
    }
}
