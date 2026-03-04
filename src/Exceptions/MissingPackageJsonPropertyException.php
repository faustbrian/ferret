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
 * Exception thrown when a required property is missing from package.json.
 *
 * This exception is raised when attempting to access a required property in a
 * package.json file that does not exist. Common missing properties include name,
 * version, dependencies, or custom application-specific configuration keys.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MissingPackageJsonPropertyException extends InvalidConfigurationException
{
    /**
     * Creates an exception for a missing package.json property.
     *
     * @param  string $filepath The path to the package.json file where the property is missing
     * @param  string $property The name of the required property that was not found
     * @return self   The configured exception instance with descriptive error message
     */
    public static function forProperty(string $filepath, string $property): self
    {
        return new self(sprintf(
            'Property "%s" not found in package.json at "%s"',
            $property,
            $filepath,
        ));
    }
}
