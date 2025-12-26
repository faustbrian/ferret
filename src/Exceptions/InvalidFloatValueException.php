<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Exceptions;

/**
 * Base exception for invalid float value errors.
 *
 * Thrown when configuration access expects a float value but receives
 * a different type. Subclasses provide specific error contexts for
 * various float validation failures.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class InvalidFloatValueException extends TypedAccessorException {}
