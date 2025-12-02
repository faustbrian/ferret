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
use Cline\Ferret\Exceptions\InvalidXmlConfigurationException;
use Override;
use Saloon\XmlWrangler\XmlReader;
use Spatie\ArrayToXml\ArrayToXml;
use Throwable;

use function count;
use function file_exists;
use function file_get_contents;
use function is_array;
use function is_readable;
use function reset;

/**
 * Loader for XML configuration files.
 *
 * Uses saloonphp/xml-wrangler for reading and spatie/array-to-xml for writing.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class XmlLoader implements LoaderInterface
{
    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function extensions(): array
    {
        return ['xml'];
    }

    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function load(string $filepath): array
    {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            throw InvalidXmlConfigurationException::fromFile($filepath, 'Could not read file');
        }

        $contents = file_get_contents($filepath);

        if ($contents === false) {
            throw InvalidXmlConfigurationException::fromFile($filepath, 'Could not read file');
        }

        if ($contents === '') {
            return [];
        }

        try {
            $reader = XmlReader::fromString($contents);
            $data = $reader->values();

            if ($data === []) {
                return [];
            }

            // XML reader returns the root element as a key, extract contents
            // e.g., ['config' => [...]] becomes [...]
            if (count($data) === 1) {
                $firstValue = reset($data);

                if (is_array($firstValue)) {
                    /** @var array<string, mixed> $firstValue */
                    return $firstValue;
                }
            }

            /** @var array<string, mixed> $data */
            return $data;
        } catch (Throwable $throwable) {
            throw InvalidXmlConfigurationException::fromFile($filepath, $throwable->getMessage());
        }
    }

    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function encode(array $data): string
    {
        try {
            return ArrayToXml::convert(
                $data,
                rootElement: 'config',
                replaceSpacesByUnderScoresInKeyNames: true,
                xmlEncoding: 'UTF-8',
            );
        } catch (Throwable $throwable) {
            throw ConfigurationEncodingFailedException::forFormat('XML', $throwable->getMessage());
        }
    }
}
