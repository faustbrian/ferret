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
 * Exception thrown when configuration value is non-numeric but float was expected.
 *
 * Occurs during typed configuration access when attempting to retrieve a float
 * value but the underlying configuration data contains a non-numeric string that
 * cannot be converted to a float representation.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FloatValueMustBeNumericException extends InvalidFloatValueException
{
    /**
     * Create exception for a specific configuration key.
     *
     * @param string $moduleName Configuration module or namespace where the key resides
     * @param string $key        Configuration key name that was accessed with incorrect type
     */
    public static function forKey(string $moduleName, string $key): self
    {
        return new self(sprintf(
            'Configuration value for [%s.%s] must be a float, non-numeric string given.',
            $moduleName,
            $key,
        ));
    }
}
