<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive;

final class FunctionDirective extends Directive
{
    private \Closure $callback;

    /**
     * @throws \ReflectionException
     */
    public function __construct(callable $cb)
    {
        $this->callback = \Closure::fromCallable($cb);

        $reflection = new \ReflectionFunction($this->callback);

        $this->minArgumentsCount = $reflection->getNumberOfRequiredParameters();
        $this->maxArgumentsCount = $reflection->getNumberOfParameters();
    }

    public function __invoke(string ...$args): string
    {
        return self::render(($this->callback)(...$args));
    }
}
