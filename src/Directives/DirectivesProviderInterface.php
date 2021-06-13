<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Directives;

interface DirectivesProviderInterface
{
    /**
     * @var string
     */
    public const DEFAULT_VALUE = '';

    /**
     * @param string $directive
     * @param string|callable $value
     */
    public function define(string $directive, $value = self::DEFAULT_VALUE): void;

    /**
     * @param string $directive
     */
    public function undef(string $directive): void;

    /**
     * @param string $directive
     * @return bool
     */
    public function defined(string $directive): bool;
}
