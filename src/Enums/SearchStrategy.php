<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Enums;

/**
 * Defines the strategy for searching configuration files across directories.
 *
 * Controls how the searcher traverses the filesystem when looking for
 * configuration files. Each strategy provides different behavior for
 * upward directory traversal.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum SearchStrategy: string
{
    /**
     * Only check the starting directory, no upward traversal.
     */
    case None = 'none';

    /**
     * Traverse upward until finding a directory containing composer.json.
     */
    case Project = 'project';

    /**
     * Traverse upward to stopDir (default: home directory).
     * Also checks OS config directory (~/.config/{moduleName}/).
     */
    case Global = 'global';
}
