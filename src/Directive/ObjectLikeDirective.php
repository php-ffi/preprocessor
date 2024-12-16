<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive;

final class ObjectLikeDirective extends Directive
{
    private string $body;

    public function __construct(string $value = self::DEFAULT_VALUE)
    {
        $this->body = $this->normalizeBody($value);
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function __invoke(string ...$args): string
    {
        $this->assertArgumentsCount($args);

        return $this->body;
    }
}
