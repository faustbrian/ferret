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
 * Uses saloonphp/xml-wrangler for reading XML into arrays and
 * spatie/array-to-xml for writing arrays back to XML format.
 * Automatically unwraps single root elements for cleaner data structures.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class XmlLoader implements LoaderInterface
{
    /**
     * Returns the file extensions supported by this loader.
     *
     * @return array<int, string> Array containing 'xml' extension
     */
    #[Override()]
    public function extensions(): array
    {
        return ['xml'];
    }

    /**
     * Loads and parses an XML configuration file.
     *
     * Uses XmlReader to parse XML into array structure. Automatically
     * unwraps single root elements to extract the configuration content
     * directly. Returns empty array for empty files.
     *
     * @param string $filepath Absolute path to the XML file to load
     *
     * @throws InvalidXmlConfigurationException If the file cannot be read or parsed
     *
     * @return array<string, mixed> Parsed configuration data
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
     * Encodes configuration data to XML format string.
     *
     * Uses ArrayToXml to convert array structure to XML with a 'config'
     * root element. Spaces in keys are converted to underscores, and
     * UTF-8 encoding is used for proper character support.
     *
     * @param array<string, mixed> $data Configuration data to encode
     *
     * @throws ConfigurationEncodingFailedException If XML encoding fails
     *
     * @return string XML-formatted configuration string
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
