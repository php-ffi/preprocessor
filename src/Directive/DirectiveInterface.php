<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Directive;

/**
 * @mixin callable
 */
interface DirectiveInterface
{
    /**
     * @var string
     */
    public const DEFAULT_VALUE = '';

    /**
     * @param string ...$args
     * @return string
     */
    public function __invoke(string ...$args): string;

    /**
     * @return int
     */
    public function getMaxArgumentsCount(): int;

    /**
     * @return int
     */
    public function getMinArgumentsCount(): int;
}
