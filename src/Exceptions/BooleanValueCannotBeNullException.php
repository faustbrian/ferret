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
 * Exception thrown when a configuration value is null but a boolean was expected.
 *
 * This exception indicates a type mismatch where the configuration expects
 * a boolean type but encountered a null value during validation or retrieval.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class BooleanValueCannotBeNullException extends InvalidBooleanValueException
{
    /**
     * Create a new exception for a specific configuration key.
     *
     * Generates a descriptive error message indicating which configuration
     * key expected a boolean but received null instead.
     *
     * @param  string $moduleName The module or configuration namespace
     * @param  string $key        The specific configuration key that failed validation
     * @return self   The exception instance with formatted error message
     */
    public static function forKey(string $moduleName, string $key): self
    {
        return new self(sprintf(
            'Configuration value for [%s.%s] must be a boolean, null given.',
            $moduleName,
            $key,
        ));
    }
}
