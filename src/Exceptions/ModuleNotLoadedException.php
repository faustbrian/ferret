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
 * Exception thrown when a module is not loaded or has no configuration.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ModuleNotLoadedException extends ConfigurationNotFoundException
{
    public static function forModule(string $moduleName): self
    {
        return new self(sprintf(
            "Module '%s' is not loaded or has no configuration.",
            $moduleName,
        ));
    }
}
