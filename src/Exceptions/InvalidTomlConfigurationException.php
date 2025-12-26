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
 * Exception thrown when TOML configuration cannot be parsed.
 *
 * This exception is raised when loading a TOML configuration file fails due to
 * syntax errors, invalid format, or parsing issues. TOML (Tom's Obvious, Minimal
 * Language) is a configuration format designed for readability and unambiguous
 * semantics.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidTomlConfigurationException extends LoaderException
{
    /**
     * Creates an exception for a TOML file parsing failure.
     *
     * @param  string $filepath The path to the TOML file that failed to parse
     * @param  string $error    Detailed error message from the TOML parser describing the failure
     * @return self   The configured exception instance with descriptive error message
     */
    public static function fromFile(string $filepath, string $error): self
    {
        return new self(sprintf(
            'Failed to parse TOML file "%s": %s',
            $filepath,
            $error,
        ));
    }
}
