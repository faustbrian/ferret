<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Exceptions;

use Throwable;

use function sprintf;

/**
 * Exception thrown when PHP configuration file cannot be loaded.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidPhpConfigurationException extends LoaderException
{
    public static function fromFile(string $filepath, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Failed to load PHP configuration file "%s"', $filepath),
            previous: $previous,
        );
    }
}
