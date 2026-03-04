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
 * Exception thrown when attempting to write to a read-only configuration file.
 *
 * This exception is raised when Ferret attempts to save configuration changes
 * to a file or directory that lacks write permissions. This can occur due to
 * file system permissions, read-only file attributes, or when the parent directory
 * is not writable. The exception helps prevent data loss from failed write operations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ReadOnlyConfigurationException extends InvalidConfigurationException
{
    /**
     * Create an exception for a read-only file or directory.
     *
     * @param  string $filepath The path to the configuration file or directory that cannot be written to
     * @return self   The exception instance with a descriptive error message
     */
    public static function forPath(string $filepath): self
    {
        return new self(sprintf(
            'Cannot write to read-only configuration file: "%s"',
            $filepath,
        ));
    }
}
