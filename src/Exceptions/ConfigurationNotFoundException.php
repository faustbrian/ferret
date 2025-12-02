<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Exceptions;

use RuntimeException;

/**
 * Base exception for configuration not found errors.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class ConfigurationNotFoundException extends RuntimeException implements FerretException {}
