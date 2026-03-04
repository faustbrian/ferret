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
 * Base exception for all configuration loading errors.
 *
 * Thrown when configuration file loading or parsing fails due to format-specific
 * issues, syntax errors, or file system problems. Subclasses provide specific error
 * contexts for different configuration formats (JSON, YAML, XML, TOML, etc.).
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class LoaderException extends RuntimeException implements FerretException {}
