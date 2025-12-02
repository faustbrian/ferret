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
 * Exception thrown when PHP configuration file does not return an array.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PhpConfigurationMustReturnArrayException extends LoaderException
{
    public static function fromFile(string $filepath): self
    {
        return new self(sprintf(
            'PHP configuration file "%s" must return an array',
            $filepath,
        ));
    }
}
