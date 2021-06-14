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
 * @template-extends \IteratorAggregate<DirectiveInterface>
 */
interface RepositoryInterface extends \Countable, \IteratorAggregate
{
    /**
     * @param string $directive
     * @return bool
     */
    public function defined(string $directive): bool;

    /**
     * @param string $directive
     * @return DirectiveInterface|null
     */
    public function find(string $directive): ?DirectiveInterface;
}
