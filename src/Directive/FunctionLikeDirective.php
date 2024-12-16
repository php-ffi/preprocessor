<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive;

use FFI\Preprocessor\Directive\FunctionLikeDirective\Compiler;

final class FunctionLikeDirective extends Directive
{
    /**
     * @var array|string[]
     */
    private array $args;

    private string $body;

    private ?\Closure $compiled = null;

    public function __construct(array $args = [], string $value = self::DEFAULT_VALUE)
    {
        $this->args = $this->filter($args);
        $this->minArgumentsCount = $this->maxArgumentsCount = \count($this->args);
        $this->body = $this->normalizeBody($value);
    }

    private function filter(array $args): array
    {
        $args = \array_map('\\trim', $args);

        return \array_filter($args, static fn(string $arg): bool => $arg !== '');
    }

    public function __invoke(string ...$args): string
    {
        $this->assertArgumentsCount($args);

        if ($this->compiled === null) {
            $this->compiled = Compiler::compile($this->body, $this->args);
        }

        return $this->render(($this->compiled)(...$args));
    }
}
