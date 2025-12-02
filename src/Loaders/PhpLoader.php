<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Loaders;

use Cline\Ferret\Contracts\LoaderInterface;
use Cline\Ferret\Exceptions\InvalidPhpConfigurationException;
use Cline\Ferret\Exceptions\PhpConfigurationMustReturnArrayException;
use Override;
use Throwable;

use function is_array;
use function var_export;

/**
 * Loader for PHP configuration files.
 *
 * Expects files that return an array.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PhpLoader implements LoaderInterface
{
    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function extensions(): array
    {
        return ['php'];
    }

    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function load(string $filepath): array
    {
        try {
            $data = require $filepath;
        } catch (Throwable $throwable) {
            throw InvalidPhpConfigurationException::fromFile($filepath, $throwable);
        }

        if (!is_array($data)) {
            throw PhpConfigurationMustReturnArrayException::fromFile($filepath);
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function encode(array $data): string
    {
        return "<?php\n\nreturn ".var_export($data, return: true).";\n";
    }
}
