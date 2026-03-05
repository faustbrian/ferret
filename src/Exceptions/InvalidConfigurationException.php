<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Ferret\Exceptions;

use Facade\IgnitionContracts\BaseSolution;
use Facade\IgnitionContracts\ProvidesSolution;
use Facade\IgnitionContracts\Solution;
use RuntimeException;

/**
 * Base exception for invalid configuration errors.
 *
 * Thrown when configuration loading or parsing fails due to malformed data,
 * invalid format, or structural issues. Subclasses provide specific error
 * contexts for different configuration file formats and failure modes.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class InvalidConfigurationException extends RuntimeException implements FerretException, ProvidesSolution
{
    public function getSolution(): Solution
    {
        /** @var BaseSolution $solution */
        $solution = BaseSolution::create('Review package usage and configuration.');

        return $solution
            ->setSolutionDescription('Exception: '.$this->getMessage())
            ->setDocumentationLinks([
                'Package documentation' => 'https://github.com/cline/ferret',
            ]);
    }
}
