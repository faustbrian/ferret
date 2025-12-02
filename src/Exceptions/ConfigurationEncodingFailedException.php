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
 * Exception thrown when configuration cannot be encoded to a format.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ConfigurationEncodingFailedException extends LoaderException
{
    public static function forFormat(string $format, string $error): self
    {
        return new self(sprintf(
            'Failed to encode configuration to %s: %s',
            $format,
            $error,
        ));
    }
}
