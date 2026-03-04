<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Ferret\FerretManager;
use Illuminate\Support\Collection;

describe('Typed Accessors', function (): void {
    beforeEach(function (): void {
        $this->manager = new FerretManager();
        $this->manager->set('test', 'string_value', 'hello');
        $this->manager->set('test', 'integer_value', 42);
        $this->manager->set('test', 'float_value', 3.14);
        $this->manager->set('test', 'boolean_true', true);
        $this->manager->set('test', 'boolean_false', false);
        $this->manager->set('test', 'string_true', 'true');
        $this->manager->set('test', 'string_false', 'false');
        $this->manager->set('test', 'string_one', '1');
        $this->manager->set('test', 'string_zero', '0');
        $this->manager->set('test', 'string_yes', 'yes');
        $this->manager->set('test', 'string_no', 'no');
        $this->manager->set('test', 'array_value', ['a', 'b', 'c']);
        $this->manager->set('test', 'nested', ['key' => 'value', 'items' => [1, 2, 3]]);
        $this->manager->set('test', 'numeric_string', '123');
        $this->manager->set('test', 'float_string', '3.14159');
        $this->manager->set('test', 'null_value', null);
    });

    describe('string', function (): void {
        test('returns string value', function (): void {
            expect($this->manager->string('test', 'string_value'))->toBe('hello');
        });

        test('casts integer to string', function (): void {
            expect($this->manager->string('test', 'integer_value'))->toBe('42');
        });

        test('casts float to string', function (): void {
            expect($this->manager->string('test', 'float_value'))->toBe('3.14');
        });

        test('casts boolean true to string', function (): void {
            expect($this->manager->string('test', 'boolean_true'))->toBe('1');
        });

        test('casts boolean false to string', function (): void {
            expect($this->manager->string('test', 'boolean_false'))->toBe('');
        });

        test('throws exception for null value', function (): void {
            $this->manager->string('test', 'null_value');
        })->throws(InvalidArgumentException::class, 'must be a string, null given');

        test('throws exception for array value', function (): void {
            $this->manager->string('test', 'array_value');
        })->throws(InvalidArgumentException::class, 'must be a string, array given');

        test('throws exception for non-existent key', function (): void {
            $this->manager->string('test', 'nonexistent');
        })->throws(InvalidArgumentException::class, 'must be a string, null given');
    });

    describe('integer', function (): void {
        test('returns integer value', function (): void {
            expect($this->manager->integer('test', 'integer_value'))->toBe(42);
        });

        test('casts numeric string to integer', function (): void {
            expect($this->manager->integer('test', 'numeric_string'))->toBe(123);
        });

        test('casts float to integer (truncates)', function (): void {
            expect($this->manager->integer('test', 'float_value'))->toBe(3);
        });

        test('casts float string to integer (truncates)', function (): void {
            expect($this->manager->integer('test', 'float_string'))->toBe(3);
        });

        test('throws exception for null value', function (): void {
            $this->manager->integer('test', 'null_value');
        })->throws(InvalidArgumentException::class, 'must be an integer, null given');

        test('throws exception for array value', function (): void {
            $this->manager->integer('test', 'array_value');
        })->throws(InvalidArgumentException::class, 'must be an integer, array given');

        test('throws exception for non-numeric string', function (): void {
            $this->manager->integer('test', 'string_value');
        })->throws(InvalidArgumentException::class, 'must be an integer, non-numeric string given');

        test('throws exception for non-existent key', function (): void {
            $this->manager->integer('test', 'nonexistent');
        })->throws(InvalidArgumentException::class, 'must be an integer, null given');
    });

    describe('float', function (): void {
        test('returns float value', function (): void {
            expect($this->manager->float('test', 'float_value'))->toBe(3.14);
        });

        test('casts integer to float', function (): void {
            expect($this->manager->float('test', 'integer_value'))->toBe(42.0);
        });

        test('casts numeric string to float', function (): void {
            expect($this->manager->float('test', 'numeric_string'))->toBe(123.0);
        });

        test('casts float string to float', function (): void {
            expect($this->manager->float('test', 'float_string'))->toBe(3.141_59);
        });

        test('throws exception for null value', function (): void {
            $this->manager->float('test', 'null_value');
        })->throws(InvalidArgumentException::class, 'must be a float, null given');

        test('throws exception for array value', function (): void {
            $this->manager->float('test', 'array_value');
        })->throws(InvalidArgumentException::class, 'must be a float, array given');

        test('throws exception for non-numeric string', function (): void {
            $this->manager->float('test', 'string_value');
        })->throws(InvalidArgumentException::class, 'must be a float, non-numeric string given');

        test('throws exception for non-existent key', function (): void {
            $this->manager->float('test', 'nonexistent');
        })->throws(InvalidArgumentException::class, 'must be a float, null given');
    });

    describe('boolean', function (): void {
        test('returns true boolean value', function (): void {
            expect($this->manager->boolean('test', 'boolean_true'))->toBeTrue();
        });

        test('returns false boolean value', function (): void {
            expect($this->manager->boolean('test', 'boolean_false'))->toBeFalse();
        });

        test('casts string "true" to true', function (): void {
            expect($this->manager->boolean('test', 'string_true'))->toBeTrue();
        });

        test('casts string "false" to false', function (): void {
            expect($this->manager->boolean('test', 'string_false'))->toBeFalse();
        });

        test('casts string "1" to true', function (): void {
            expect($this->manager->boolean('test', 'string_one'))->toBeTrue();
        });

        test('casts string "0" to false', function (): void {
            expect($this->manager->boolean('test', 'string_zero'))->toBeFalse();
        });

        test('casts string "yes" to true', function (): void {
            expect($this->manager->boolean('test', 'string_yes'))->toBeTrue();
        });

        test('casts string "no" to false', function (): void {
            expect($this->manager->boolean('test', 'string_no'))->toBeFalse();
        });

        test('casts integer 1 to true', function (): void {
            $this->manager->set('test', 'int_one', 1);
            expect($this->manager->boolean('test', 'int_one'))->toBeTrue();
        });

        test('casts integer 0 to false', function (): void {
            $this->manager->set('test', 'int_zero', 0);
            expect($this->manager->boolean('test', 'int_zero'))->toBeFalse();
        });

        test('throws exception for null value', function (): void {
            $this->manager->boolean('test', 'null_value');
        })->throws(InvalidArgumentException::class, 'must be a boolean, null given');

        test('throws exception for array value', function (): void {
            $this->manager->boolean('test', 'array_value');
        })->throws(InvalidArgumentException::class, 'must be a boolean, array given');

        test('throws exception for non-existent key', function (): void {
            $this->manager->boolean('test', 'nonexistent');
        })->throws(InvalidArgumentException::class, 'must be a boolean, null given');
    });

    describe('array', function (): void {
        test('returns array value', function (): void {
            expect($this->manager->array('test', 'array_value'))->toBe(['a', 'b', 'c']);
        });

        test('returns nested array value', function (): void {
            expect($this->manager->array('test', 'nested'))->toBe(['key' => 'value', 'items' => [1, 2, 3]]);
        });

        test('returns nested items array using dot notation', function (): void {
            expect($this->manager->array('test', 'nested.items'))->toBe([1, 2, 3]);
        });

        test('throws exception for null value', function (): void {
            $this->manager->array('test', 'null_value');
        })->throws(InvalidArgumentException::class, 'must be an array, null given');

        test('throws exception for scalar value', function (): void {
            $this->manager->array('test', 'string_value');
        })->throws(InvalidArgumentException::class, 'must be an array, scalar given');

        test('throws exception for non-existent key', function (): void {
            $this->manager->array('test', 'nonexistent');
        })->throws(InvalidArgumentException::class, 'must be an array, null given');
    });

    describe('collection', function (): void {
        test('returns Collection instance', function (): void {
            $collection = $this->manager->collection('test', 'array_value');

            expect($collection)->toBeInstanceOf(Collection::class)
                ->and($collection->toArray())->toBe(['a', 'b', 'c']);
        });

        test('returns Collection from nested array', function (): void {
            $collection = $this->manager->collection('test', 'nested.items');

            expect($collection)->toBeInstanceOf(Collection::class)
                ->and($collection->toArray())->toBe([1, 2, 3]);
        });

        test('collection is usable with Laravel collection methods', function (): void {
            $collection = $this->manager->collection('test', 'array_value');

            expect($collection->count())->toBe(3)
                ->and($collection->first())->toBe('a')
                ->and($collection->last())->toBe('c')
                ->and($collection->contains('b'))->toBeTrue();
        });

        test('throws exception for null value', function (): void {
            $this->manager->collection('test', 'null_value');
        })->throws(InvalidArgumentException::class, 'must be an array, null given');

        test('throws exception for scalar value', function (): void {
            $this->manager->collection('test', 'string_value');
        })->throws(InvalidArgumentException::class, 'must be an array, scalar given');
    });

    describe('with loaded config files', function (): void {
        beforeEach(function (): void {
            $this->manager = new FerretManager();
            $this->manager->load(fixturesPath('config.json'), 'app');
        });

        test('string accessor works with loaded config', function (): void {
            expect($this->manager->string('app', 'database.host'))->toBe('localhost');
        });

        test('integer accessor works with loaded config', function (): void {
            expect($this->manager->integer('app', 'database.port'))->toBe(5_432);
        });

        test('boolean accessor works with loaded config', function (): void {
            expect($this->manager->boolean('app', 'debug'))->toBeTrue();
        });
    });
});
