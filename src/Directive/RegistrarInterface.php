<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Directive;

use FFI\Preprocessor\Exception\DirectiveDefinitionException;

interface RegistrarInterface
{
    /**
     * @param string $directive
     * @param mixed $value
     * @throws DirectiveDefinitionException
     */
    public function define(string $directive, mixed $value = DirectiveInterface::DEFAULT_VALUE): void;

    /**
     * @param string $directive
     * @return bool
     */
    public function undef(string $directive): bool;
}
