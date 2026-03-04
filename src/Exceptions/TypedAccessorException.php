<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Exceptions;

use InvalidArgumentException;

/**
 * Base exception for typed accessor errors.
 *
 * This abstract exception class serves as the foundation for all exceptions
 * thrown by typed accessor methods (string(), integer(), float(), boolean(),
 * array(), collection()) when configuration values don't match expected types.
 * All typed accessor exceptions extend this class to provide consistent error
 * handling across type validation failures.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class TypedAccessorException extends InvalidArgumentException implements FerretException {}
