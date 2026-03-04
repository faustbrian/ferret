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
 * Exception thrown when configuration cannot be encoded to a format.
 *
 * This exception is raised when a loader fails to encode configuration data
 * into the target format (JSON, YAML, XML, etc.). The encoding failure could
 * result from invalid data structures, unsupported types, or format-specific
 * constraints.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ConfigurationEncodingFailedException extends LoaderException
{
    /**
     * Create a new exception for a specific format encoding failure.
     *
     * Generates a descriptive error message including both the target format
     * and the underlying error that caused the encoding to fail.
     *
     * @param  string $format The target format that failed to encode (e.g., JSON, YAML)
     * @param  string $error  The underlying error message describing why encoding failed
     * @return self   The exception instance with formatted error message
     */
    public static function forFormat(string $format, string $error): self
    {
        return new self(sprintf(
            'Failed to encode configuration to %s: %s',
            $format,
            $error,
        ));
    }
}
