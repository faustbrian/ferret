<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Loaders;

use Cline\Ferret\Contracts\LoaderInterface;
use Cline\Ferret\Exceptions\InvalidNeonConfigurationException;
use Nette\Neon\Exception as NeonException;
use Nette\Neon\Neon;
use Override;

use function file_exists;
use function file_get_contents;
use function is_array;
use function is_readable;

/**
 * Loader for NEON configuration files.
 *
 * NEON is a human-readable data serialization format used by Nette Framework
 * and PHPStan. It's similar to YAML but with some differences.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class NeonLoader implements LoaderInterface
{
    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function extensions(): array
    {
        return ['neon'];
    }

    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function load(string $filepath): array
    {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            throw InvalidNeonConfigurationException::fromFile($filepath, 'Could not read file');
        }

        $contents = file_get_contents($filepath);

        if ($contents === false) {
            throw InvalidNeonConfigurationException::fromFile($filepath, 'Could not read file');
        }

        if ($contents === '') {
            return [];
        }

        try {
            $data = Neon::decode($contents);
        } catch (NeonException $neonException) {
            throw InvalidNeonConfigurationException::fromFile($filepath, $neonException->getMessage());
        }

        if (!is_array($data)) {
            return [];
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
        return Neon::encode($data, blockMode: true);
    }
}
