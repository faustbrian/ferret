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
 * Exception thrown when NEON configuration cannot be parsed.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidNeonConfigurationException extends LoaderException
{
    public static function fromFile(string $filepath, string $error): self
    {
        return new self(sprintf(
            'Failed to parse NEON file "%s": %s',
            $filepath,
            $error,
        ));
    }
}
