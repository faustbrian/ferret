<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Loaders;

use Cline\Ferret\Contracts\LoaderInterface;
use Cline\Ferret\Exceptions\InvalidYamlConfigurationException;
use Override;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

use function file_exists;
use function file_get_contents;
use function is_array;
use function is_readable;

/**
 * Loader for YAML configuration files.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class YamlLoader implements LoaderInterface
{
    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function extensions(): array
    {
        return ['yaml', 'yml'];
    }

    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function load(string $filepath): array
    {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            throw InvalidYamlConfigurationException::fromFile($filepath, 'Could not read file');
        }

        $contents = file_get_contents($filepath);

        if ($contents === false) {
            throw InvalidYamlConfigurationException::fromFile($filepath, 'Could not read file');
        }

        if ($contents === '') {
            return [];
        }

        try {
            $data = Yaml::parse($contents);
        } catch (ParseException $parseException) {
            throw InvalidYamlConfigurationException::fromFile($filepath, $parseException->getMessage());
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
        return Yaml::dump($data, inline: 4, indent: 2);
    }
}
