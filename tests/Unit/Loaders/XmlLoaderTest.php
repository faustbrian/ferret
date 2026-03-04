<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Ferret\Exceptions\InvalidXmlConfigurationException;
use Cline\Ferret\Loaders\XmlLoader;

describe('XmlLoader', function (): void {
    beforeEach(function (): void {
        $this->loader = new XmlLoader();
    });

    describe('extensions', function (): void {
        test('returns xml extension', function (): void {
            expect($this->loader->extensions())->toBe(['xml']);
        });
    });

    describe('load', function (): void {
        test('loads valid xml file', function (): void {
            $result = $this->loader->load(fixturesPath('config.xml'));

            expect($result)->toBeArray()
                ->and($result['database']['host'])->toBe('localhost')
                ->and($result['database']['port'])->toBe('5432')
                ->and($result['debug'])->toBe('true');
        });

        test('returns empty array for empty file', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/empty.xml';
            file_put_contents($file, '');

            try {
                $result = $this->loader->load($file);
                expect($result)->toBe([]);
            } finally {
                removeDir($tempDir);
            }
        });

        test('throws exception for invalid xml', function (): void {
            $tempDir = createTempDir();
            $file = $tempDir.'/invalid.xml';
            file_put_contents($file, '<config><unclosed>');

            try {
                $this->loader->load($file);
            } catch (InvalidXmlConfigurationException $invalidXmlConfigurationException) {
                expect($invalidXmlConfigurationException->getMessage())->toContain('Failed to parse XML');

                return;
            } finally {
                removeDir($tempDir);
            }

            $this->fail('Expected InvalidXmlConfigurationException was not thrown');
        });

        test('throws exception for non-existent file', function (): void {
            $this->loader->load('/nonexistent/file.xml');
        })->throws(InvalidXmlConfigurationException::class);
    });

    describe('encode', function (): void {
        test('encodes array to xml string', function (): void {
            $data = ['key' => 'value', 'nested' => ['a' => '1']];
            $encoded = $this->loader->encode($data);

            expect($encoded)->toBeString()
                ->and($encoded)->toContain('<?xml version="1.0" encoding="UTF-8"?>')
                ->and($encoded)->toContain('<key>value</key>')
                ->and($encoded)->toContain('<config>');
        });
    });
});
