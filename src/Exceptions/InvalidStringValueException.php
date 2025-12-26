<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Exceptions;

/**
 * Base exception for invalid string value errors.
 *
 * Thrown when configuration access expects a string value but receives
 * a different type or invalid format. Subclasses provide specific error
 * contexts for various string validation failures such as null values
 * or non-string types.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class InvalidStringValueException extends TypedAccessorException {}
