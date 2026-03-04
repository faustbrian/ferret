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
 * Exception thrown when PHP configuration file does not return an array.
 *
 * This exception is raised when loading a PHP configuration file that should
 * return an array but returns a different type instead. PHP configuration files
 * must use a `return` statement to provide an associative array of configuration
 * values for proper parsing by Ferret's PHP loader.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PhpConfigurationMustReturnArrayException extends LoaderException
{
    /**
     * Create an exception for a PHP file that doesn't return an array.
     *
     * @param  string $filepath The path to the PHP configuration file that returned an invalid type
     * @return self   The exception instance with a descriptive error message
     */
    public static function fromFile(string $filepath): self
    {
        return new self(sprintf(
            'PHP configuration file "%s" must return an array',
            $filepath,
        ));
    }
}
