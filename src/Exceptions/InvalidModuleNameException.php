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
 * Exception thrown when a module name is invalid.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidModuleNameException extends InvalidConfigurationException
{
    public static function forName(string $moduleName): self
    {
        return new self(sprintf(
            'Invalid module name "%s". Module name must be a non-empty string containing only alphanumeric characters, hyphens, and underscores.',
            $moduleName,
        ));
    }
}
