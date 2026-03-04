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
 * Exception thrown when NEON configuration cannot be parsed.
 *
 * This exception is raised when loading a NEON configuration file fails due to
 * syntax errors, invalid format, or parsing issues. NEON is a human-friendly
 * configuration format used primarily in PHP applications.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidNeonConfigurationException extends LoaderException
{
    /**
     * Creates an exception for a NEON file parsing failure.
     *
     * @param  string $filepath The path to the NEON file that failed to parse
     * @param  string $error    Detailed error message from the NEON parser describing the failure
     * @return self   The configured exception instance with descriptive error message
     */
    public static function fromFile(string $filepath, string $error): self
    {
        return new self(sprintf(
            'Failed to parse NEON file "%s": %s',
            $filepath,
            $error,
        ));
    }
}
