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
 * Exception thrown when a configuration module cannot be found.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ModuleNotFoundException extends ConfigurationNotFoundException
{
    public static function forModule(string $moduleName, string $searchPath): self
    {
        return new self(sprintf(
            'No configuration found for "%s" searching from "%s"',
            $moduleName,
            $searchPath,
        ));
    }
}
