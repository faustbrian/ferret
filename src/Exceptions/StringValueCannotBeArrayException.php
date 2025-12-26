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
 * Exception thrown when a configuration value is an array but a string was expected.
 *
 * This exception is raised when calling the string() typed accessor method on
 * a configuration key that contains an array value. The typed accessor methods
 * enforce strict type validation to prevent type-related bugs and ensure
 * configuration values match expected types in application code.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class StringValueCannotBeArrayException extends InvalidStringValueException
{
    /**
     * Create an exception for an array value where a string was expected.
     *
     * @param  string $moduleName The name of the configuration module containing the key
     * @param  string $key        The configuration key in dot notation that contains the array value
     * @return self   The exception instance with a descriptive error message
     */
    public static function forKey(string $moduleName, string $key): self
    {
        return new self(sprintf(
            'Configuration value for [%s.%s] must be a string, array given.',
            $moduleName,
            $key,
        ));
    }
}
