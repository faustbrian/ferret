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
 * Exception thrown when a required property is missing from package.json.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MissingPackageJsonPropertyException extends InvalidConfigurationException
{
    public static function forProperty(string $filepath, string $property): self
    {
        return new self(sprintf(
            'Property "%s" not found in package.json at "%s"',
            $property,
            $filepath,
        ));
    }
}
