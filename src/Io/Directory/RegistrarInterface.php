<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Io\Directory;

interface RegistrarInterface
{
    /**
     * @param string $directory
     */
    public function include(string $directory): void;

    /**
     * @param string $directory
     */
    public function exclude(string $directory): void;
}
