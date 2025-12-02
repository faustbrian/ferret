<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Loaders;

use Cline\Ferret\Contracts\LoaderInterface;
use Cline\Ferret\Exceptions\ConfigurationEncodingFailedException;
use Cline\Ferret\Exceptions\InvalidTomlConfigurationException;
use Override;
use Throwable;
use Yosymfony\Toml\Toml;
use Yosymfony\Toml\TomlBuilder;

use function array_keys;
use function count;
use function file_exists;
use function is_array;
use function is_readable;
use function range;
use function sprintf;

/**
 * Loader for TOML configuration files.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class TomlLoader implements LoaderInterface
{
    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function extensions(): array
    {
        return ['toml'];
    }

    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function load(string $filepath): array
    {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            throw InvalidTomlConfigurationException::fromFile($filepath, 'Could not read file');
        }

        try {
            /** @var array<string, mixed> */
            return Toml::parseFile($filepath);
        } catch (Throwable $throwable) {
            throw InvalidTomlConfigurationException::fromFile($filepath, $throwable->getMessage());
        }
    }

    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function encode(array $data): string
    {
        try {
            $builder = new TomlBuilder();

            $this->buildToml($builder, $data);

            return $builder->getTomlString();
        } catch (Throwable $throwable) {
            throw ConfigurationEncodingFailedException::forFormat('TOML', $throwable->getMessage());
        }
    }

    /**
     * Recursively build TOML structure.
     *
     * @param array<string, mixed> $data
     */
    private function buildToml(TomlBuilder $builder, array $data, string $prefix = ''): void
    {
        foreach ($data as $key => $value) {
            $key = (string) $key;
            $fullKey = $prefix !== '' ? sprintf('%s.%s', $prefix, $key) : $key;

            if (is_array($value) && $this->isAssociativeArray($value)) {
                $builder->addTable($fullKey);

                /** @var array<string, mixed> $value */
                $this->buildToml($builder, $value, $fullKey);
            } elseif (is_array($value)) {
                /** @var array<int|string, mixed> $value */
                $builder->addValue($key, $value);
            } else {
                /** @var bool|float|int|string $value */
                $builder->addValue($key, $value);
            }
        }
    }

    /**
     * Check if array is associative (has string keys).
     *
     * @param array<mixed> $array
     */
    private function isAssociativeArray(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
