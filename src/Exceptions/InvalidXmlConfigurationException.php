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
 * Exception thrown when XML configuration cannot be parsed.
 *
 * This exception is raised when loading an XML configuration file fails due to
 * syntax errors, invalid markup, malformed structure, or parsing issues. The error
 * message includes both the file path and specific details from the XML parser.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidXmlConfigurationException extends LoaderException
{
    /**
     * Creates an exception for an XML file parsing failure.
     *
     * @param  string $filepath The path to the XML file that failed to parse
     * @param  string $error    Detailed error message from the XML parser describing the failure
     * @return self   The configured exception instance with descriptive error message
     */
    public static function fromFile(string $filepath, string $error): self
    {
        return new self(sprintf(
            'Failed to parse XML file "%s": %s',
            $filepath,
            $error,
        ));
    }
}
