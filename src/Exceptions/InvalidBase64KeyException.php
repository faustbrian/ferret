<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Exceptions;

use InvalidArgumentException;

/**
 * Exception thrown when a base64 encoded encryption key is invalid.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidBase64KeyException extends InvalidArgumentException implements FerretException
{
    public static function invalidEncoding(): self
    {
        return new self('Invalid base64 encoded encryption key.');
    }
}
