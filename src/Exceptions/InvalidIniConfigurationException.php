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
 * Exception thrown when INI configuration cannot be parsed.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidIniConfigurationException extends LoaderException
{
    public static function fromFile(string $filepath, ?string $error = null): self
    {
        $message = $error !== null
            ? sprintf('Failed to parse INI file "%s": %s', $filepath, $error)
            : sprintf('Failed to parse INI file "%s"', $filepath);

        return new self($message);
    }
}
