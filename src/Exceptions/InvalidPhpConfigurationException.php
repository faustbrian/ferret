<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Exceptions;

use Throwable;

use function sprintf;

/**
 * Exception thrown when PHP configuration file cannot be loaded.
 *
 * This exception is raised when loading a PHP configuration file fails due to
 * syntax errors, runtime exceptions during file execution, or file system issues.
 * The exception can wrap the underlying error for debugging purposes.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidPhpConfigurationException extends LoaderException
{
    /**
     * Creates an exception for a PHP file loading failure.
     *
     * @param  string         $filepath The path to the PHP file that failed to load
     * @param  null|Throwable $previous Optional previous exception that caused the loading failure
     * @return self           The configured exception instance with descriptive error message
     */
    public static function fromFile(string $filepath, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Failed to load PHP configuration file "%s"', $filepath),
            previous: $previous,
        );
    }
}
