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
 * Exception thrown when INI configuration cannot be parsed.
 *
 * This exception is raised when loading an INI configuration file fails due to
 * syntax errors, invalid format, or parsing issues. The error message includes
 * the file path and any specific parsing error details when available.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidIniConfigurationException extends LoaderException
{
    /**
     * Creates an exception for an INI file parsing failure.
     *
     * @param  string      $filepath The path to the INI file that failed to parse
     * @param  null|string $error    Optional detailed error message describing the parsing failure
     * @return self        The configured exception instance with descriptive error message
     */
    public static function fromFile(string $filepath, ?string $error = null): self
    {
        $message = $error !== null
            ? sprintf('Failed to parse INI file "%s": %s', $filepath, $error)
            : sprintf('Failed to parse INI file "%s"', $filepath);

        return new self($message);
    }
}
