<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Directives\Directive;

use FFI\Preprocessor\Directives\Compiler;

final class FunctionLikeDirective extends Directive
{
    /**
     * @var array|string[]
     */
    private array $args;

    /**
     * @var string
     */
    private string $body;

    /**
     * @var \Closure|null
     */
    private ?\Closure $compiled = null;

    /**
     * @param array $args
     * @param string $value
     */
    public function __construct(array $args = [], string $value = self::DEFAULT_VALUE)
    {
        $this->args = $this->filter($args);
        $this->minArgumentsCount = $this->maxArgumentsCount = \count($this->args);
        $this->body = $this->normalizeBody($value);
    }

    /**
     * @param array $args
     * @return array
     */
    private function filter(array $args): array
    {
        $args = \array_map('\\trim', $args);

        return \array_filter($args, static fn(string $arg): bool => $arg !== '');
    }

    /**
     * @param string ...$args
     * @return string
     */
    public function __invoke(string ...$args): string
    {
        $this->assertArgumentsCount($args);

        if ($this->compiled === null) {
            $this->compiled = Compiler::compile($this->body, $this->args);
        }

        return $this->toString(($this->compiled)(...$args));
    }
}
