<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive;

final class ObjectLikeDirective extends Directive
{
    /**
     * @var string
     */
    private string $body;

    /**
     * @param string $value
     */
    public function __construct(string $value = self::DEFAULT_VALUE)
    {
        $this->body = $this->normalizeBody($value);
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @param string ...$args
     * @return string
     */
    public function __invoke(string ...$args): string
    {
        $this->assertArgumentsCount($args);

        return $this->body;
    }
}
