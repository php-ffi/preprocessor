<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Directive;

use FFI\Contracts\Preprocessor\Directive\DirectiveInterface;
use FFI\Contracts\Preprocessor\Directive\RegistrarInterface;
use FFI\Contracts\Preprocessor\Directive\RepositoryInterface;
use FFI\Preprocessor\Exception\DirectiveDefinitionException;

final class Repository implements RepositoryInterface, RegistrarInterface, \IteratorAggregate
{
    /**
     * @var array<string, DirectiveInterface>
     */
    private array $directives = [];

    /**
     * @param iterable<string, DirectiveInterface> $directives
     */
    public function __construct(iterable $directives = [])
    {
        foreach ($directives as $directive => $definition) {
            $this->define($directive, $definition);
        }
    }

    /**
     * @param string|callable|DirectiveInterface $directive
     * @return DirectiveInterface
     * @throws \ReflectionException
     */
    private function cast($directive): DirectiveInterface
    {
        switch (true) {
            case $directive instanceof DirectiveInterface:
                return $directive;
            case \is_callable($directive):
                return new FunctionDirective($directive);
            case \is_scalar($directive):
            case $directive instanceof \Stringable:
            case \is_object($directive) && \method_exists($directive, '__toString'):
                return new ObjectLikeDirective((string)$directive);
            default:
                return $directive;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function define(string $directive, $value = DirectiveInterface::DEFAULT_VALUE): void
    {
        try {
            $expr = $this->cast($value);
        } catch (\Throwable $e) {
            throw new DirectiveDefinitionException($e->getMessage(), (int)$e->getCode(), $e);
        }

        if ($expr instanceof ObjectLikeDirective) {
            $this->directives = \array_merge([$directive => $expr], $this->directives);
        } else {
            $this->directives[$directive] = $expr;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function undef(string $directive): bool
    {
        $exists = $this->defined($directive);

        unset($this->directives[$directive]);

        return $exists;
    }

    /**
     * {@inheritDoc}
     */
    public function defined(string $directive): bool
    {
        return isset($this->directives[$directive]);
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $directive): ?DirectiveInterface
    {
        return $this->directives[$directive] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->directives);
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return \count($this->directives);
    }
}
