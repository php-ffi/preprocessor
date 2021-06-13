<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Directives\Directive;

final class FunctionDirective extends Directive
{
    /**
     * @var \Closure
     */
    private \Closure $callback;

    /**
     * @param callable $cb
     * @throws \ReflectionException
     */
    public function __construct(callable $cb)
    {
        $this->callback = \Closure::fromCallable($cb);

        $reflection = new \ReflectionFunction($this->callback);

        $this->minArgumentsCount = $reflection->getNumberOfRequiredParameters();
        $this->maxArgumentsCount = $reflection->getNumberOfParameters();
    }

    /**
     * @param string ...$args
     * @return string
     */
    public function __invoke(string ...$args): string
    {
        return (string)($this->callback)(...$args);
    }
}
