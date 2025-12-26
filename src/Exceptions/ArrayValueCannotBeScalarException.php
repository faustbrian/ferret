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
 * Exception thrown when a configuration value is a scalar but an array was expected.
 *
 * This exception indicates a type mismatch where the configuration expects
 * an array type but encountered a scalar value (string, int, float, or bool)
 * during validation or retrieval.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ArrayValueCannotBeScalarException extends InvalidArrayValueException
{
    /**
     * Create a new exception for a specific configuration key.
     *
     * Generates a descriptive error message indicating which configuration
     * key expected an array but received a scalar value instead.
     *
     * @param  string $moduleName The module or configuration namespace
     * @param  string $key        The specific configuration key that failed validation
     * @return self   The exception instance with formatted error message
     */
    public static function forKey(string $moduleName, string $key): self
    {
        return new self(sprintf(
            'Configuration value for [%s.%s] must be an array, scalar given.',
            $moduleName,
            $key,
        ));
    }
}
