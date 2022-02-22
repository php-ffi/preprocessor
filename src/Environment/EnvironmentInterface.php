<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Environment;

use FFI\Contracts\Preprocessor\PreprocessorInterface;

interface EnvironmentInterface
{
    /**
     * @param PreprocessorInterface $pre
     */
    public function applyTo(PreprocessorInterface $pre): void;
}
