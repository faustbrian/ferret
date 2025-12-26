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
 * Exception thrown when a configuration value is non-numeric but an integer was expected.
 *
 * This exception is raised during configuration access when a typed integer accessor
 * encounters a non-numeric string value that cannot be converted to an integer.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class IntegerValueMustBeNumericException extends InvalidIntegerValueException
{
    /**
     * Creates an exception for a non-numeric integer configuration value.
     *
     * @param  string $moduleName The configuration module name containing the invalid value
     * @param  string $key        The configuration key that was accessed
     * @return self   The configured exception instance with descriptive error message
     */
    public static function forKey(string $moduleName, string $key): self
    {
        return new self(sprintf(
            'Configuration value for [%s.%s] must be an integer, non-numeric string given.',
            $moduleName,
            $key,
        ));
    }
}
