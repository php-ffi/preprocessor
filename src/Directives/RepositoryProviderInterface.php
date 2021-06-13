<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Directives;

/**
 * @implements \IteratorAggregate<string, DirectiveInterface>
 */
interface RepositoryProviderInterface extends DirectivesProviderInterface, \IteratorAggregate, \Countable
{
    /**
     * @param string $name
     * @return DirectiveInterface|null
     */
    public function find(string $name): ?DirectiveInterface;
}
