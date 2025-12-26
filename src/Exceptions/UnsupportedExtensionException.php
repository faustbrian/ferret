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
 * Exception thrown when a file extension has no registered loader.
 *
 * This exception is raised when attempting to load or save a configuration file
 * with an extension that has no corresponding loader registered in Ferret. Each
 * file format (JSON, YAML, NEON, INI, XML, PHP) requires a specific loader to
 * parse and encode the configuration data. This exception indicates the need to
 * register a custom loader or use a supported file format.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class UnsupportedExtensionException extends InvalidConfigurationException
{
    /**
     * Create an exception for an unsupported file extension.
     *
     * @param  string $extension The file extension (without the dot) that has no registered loader
     * @return self   The exception instance with a descriptive error message
     */
    public static function forExtension(string $extension): self
    {
        return new self(sprintf(
            'No loader registered for file extension ".%s"',
            $extension,
        ));
    }
}
