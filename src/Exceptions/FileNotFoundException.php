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
 * Exception thrown when a configuration file cannot be found.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FileNotFoundException extends ConfigurationNotFoundException
{
    public static function forPath(string $filepath): self
    {
        return new self(sprintf(
            'Configuration file not found: "%s"',
            $filepath,
        ));
    }
}
