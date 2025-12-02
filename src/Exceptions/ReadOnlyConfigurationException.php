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
 * Exception thrown when attempting to write to a read-only configuration file.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ReadOnlyConfigurationException extends InvalidConfigurationException
{
    public static function forPath(string $filepath): self
    {
        return new self(sprintf(
            'Cannot write to read-only configuration file: "%s"',
            $filepath,
        ));
    }
}
