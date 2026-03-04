<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Exceptions;

use Throwable;

/**
 * Marker interface for all Ferret package exceptions.
 *
 * Provides a common type for exception handling across the Ferret
 * configuration management package. Consumers can catch this interface
 * to handle any exception thrown by Ferret, enabling centralized error
 * handling without catching individual exception types.
 *
 * ```php
 * try {
 *     $config = $ferret->load('app');
 * } catch (FerretException $e) {
 *     // Handle all Ferret-related errors
 * }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface FerretException extends Throwable {}
