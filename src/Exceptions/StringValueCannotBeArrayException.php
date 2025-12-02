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
 * @author Brian Faust <brian@cline.sh>
 */
final class StringValueCannotBeArrayException extends InvalidStringValueException
{
    public static function forKey(string $moduleName, string $key): self
    {
        return new self(sprintf(
            'Configuration value for [%s.%s] must be a string, array given.',
            $moduleName,
            $key,
        ));
    }
}
