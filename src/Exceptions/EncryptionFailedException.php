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
 * Exception thrown when configuration encryption fails.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class EncryptionFailedException extends InvalidConfigurationException
{
    public static function forPath(string $filepath, string $reason): self
    {
        return new self(sprintf(
            'Failed to encrypt configuration file "%s": %s',
            $filepath,
            $reason,
        ));
    }
}
