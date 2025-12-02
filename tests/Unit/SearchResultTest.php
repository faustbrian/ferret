<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Ferret\SearchResult;

describe('SearchResult', function (): void {
    describe('construction', function (): void {
        test('creates instance with config and filepath', function (): void {
            $config = ['key' => 'value'];
            $filepath = '/path/to/config.json';

            $result = new SearchResult($config, $filepath);

            expect($result->config)->toBe($config)
                ->and($result->filepath)->toBe($filepath)
                ->and($result->isEmpty)->toBeFalse();
        });

        test('creates instance with isEmpty flag', function (): void {
            $result = new SearchResult([], '/path/to/empty.json', isEmpty: true);

            expect($result->isEmpty)->toBeTrue();
        });
    });

    describe('isEmpty', function (): void {
        test('returns true for empty config', function (): void {
            $result = new SearchResult([], '/path/to/config.json');

            expect($result->isEmpty())->toBeTrue();
        });

        test('returns true when isEmpty flag is set', function (): void {
            $result = new SearchResult(['key' => 'value'], '/path/to/config.json', isEmpty: true);

            expect($result->isEmpty)->toBeTrue();
        });

        test('returns false for non-empty config', function (): void {
            $result = new SearchResult(['key' => 'value'], '/path/to/config.json');

            expect($result->isEmpty())->toBeFalse();
        });
    });

    describe('get', function (): void {
        beforeEach(function (): void {
            $this->result = new SearchResult([
                'database' => [
                    'host' => 'localhost',
                    'port' => 5_432,
                ],
                'debug' => true,
            ], '/path/to/config.json');
        });

        test('returns entire config when key is null', function (): void {
            expect($this->result->get())->toBe($this->result->config);
        });

        test('returns value for simple key', function (): void {
            expect($this->result->get('debug'))->toBeTrue();
        });

        test('returns value for nested key using dot notation', function (): void {
            expect($this->result->get('database.host'))->toBe('localhost')
                ->and($this->result->get('database.port'))->toBe(5_432);
        });

        test('returns default for non-existent key', function (): void {
            expect($this->result->get('nonexistent', 'default'))->toBe('default');
        });

        test('returns null for non-existent key without default', function (): void {
            expect($this->result->get('nonexistent'))->toBeNull();
        });
    });

    describe('has', function (): void {
        beforeEach(function (): void {
            $this->result = new SearchResult([
                'database' => [
                    'host' => 'localhost',
                ],
                'enabled' => false,
            ], '/path/to/config.json');
        });

        test('returns true for existing key', function (): void {
            expect($this->result->has('database'))->toBeTrue();
        });

        test('returns true for nested key', function (): void {
            expect($this->result->has('database.host'))->toBeTrue();
        });

        test('returns false for non-existent key', function (): void {
            expect($this->result->has('nonexistent'))->toBeFalse();
        });
    });

    describe('toArray', function (): void {
        test('returns array representation', function (): void {
            $config = ['key' => 'value'];
            $filepath = '/path/to/config.json';

            $result = new SearchResult($config, $filepath, isEmpty: false);
            $array = $result->toArray();

            expect($array)->toBe([
                'config' => $config,
                'filepath' => $filepath,
                'isEmpty' => false,
            ]);
        });
    });
});
