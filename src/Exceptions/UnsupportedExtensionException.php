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
 * Exception thrown when a file extension has no registered loader.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class UnsupportedExtensionException extends InvalidConfigurationException
{
    public static function forExtension(string $extension): self
    {
        return new self(sprintf(
            'No loader registered for file extension ".%s"',
            $extension,
        ));
    }
}
