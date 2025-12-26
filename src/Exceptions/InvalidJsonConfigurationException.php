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
 * Exception thrown when JSON configuration cannot be parsed.
 *
 * This exception is raised when loading a JSON configuration file fails due to
 * syntax errors, invalid JSON format, or parsing issues. The error message includes
 * the file path and specific parsing error details from the JSON parser.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidJsonConfigurationException extends LoaderException
{
    /**
     * Creates an exception for a JSON file parsing failure.
     *
     * @param  string $filepath The path to the JSON file that failed to parse
     * @param  string $error    Detailed error message from the JSON parser describing the failure
     * @return self   The configured exception instance with descriptive error message
     */
    public static function fromFile(string $filepath, string $error): self
    {
        return new self(sprintf(
            'Failed to parse JSON file "%s": %s',
            $filepath,
            $error,
        ));
    }
}
