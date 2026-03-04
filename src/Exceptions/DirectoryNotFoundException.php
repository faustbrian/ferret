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
 * Exception thrown when a configuration directory cannot be found.
 *
 * Occurs when attempting to load configuration from a directory path
 * that does not exist or is not accessible due to filesystem permissions.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DirectoryNotFoundException extends ConfigurationNotFoundException
{
    /**
     * Create exception for a specific directory path.
     *
     * @param string $directory Absolute or relative path to the configuration directory that was not found
     */
    public static function forPath(string $directory): self
    {
        return new self(sprintf(
            'Configuration directory not found: "%s"',
            $directory,
        ));
    }
}
