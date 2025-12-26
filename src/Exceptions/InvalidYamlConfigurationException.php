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
 * Exception thrown when YAML configuration cannot be parsed.
 *
 * This exception is raised when loading a YAML configuration file fails due to
 * syntax errors, invalid format, indentation issues, or parsing problems. YAML
 * (YAML Ain't Markup Language) is a human-readable data serialization format
 * commonly used for configuration files.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidYamlConfigurationException extends LoaderException
{
    /**
     * Creates an exception for a YAML file parsing failure.
     *
     * @param  string $filepath The path to the YAML file that failed to parse
     * @param  string $error    Detailed error message from the YAML parser describing the failure
     * @return self   The configured exception instance with descriptive error message
     */
    public static function fromFile(string $filepath, string $error): self
    {
        return new self(sprintf(
            'Failed to parse YAML file "%s": %s',
            $filepath,
            $error,
        ));
    }
}
