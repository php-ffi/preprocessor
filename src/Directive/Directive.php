<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive;

use FFI\Contracts\Preprocessor\Directive\FunctionLikeDirectiveInterface;

abstract class Directive implements FunctionLikeDirectiveInterface
{
    /**
     * @var non-empty-string
     */
    private const ERROR_TOO_MANY_ARGUMENTS = 'Too many arguments when macro directive is called, %s required';

    /**
     * @var non-empty-string
     */
    private const ERROR_TOO_FEW_ARGUMENTS = 'Too few arguments when macro directive is called, %s required';

    /**
     * @var int<0, max>
     */
    protected int $minArgumentsCount = 0;

    /**
     * @var int<0, max>
     */
    protected int $maxArgumentsCount = 0;

    protected function normalizeBody(string $body): string
    {
        return \str_replace("\\\n", "\n", $body);
    }

    /**
     * @param list<string> $arguments
     */
    protected function assertArgumentsCount(array $arguments): void
    {
        $haystack = \count($arguments);

        if ($haystack > $this->getMaxArgumentsCount()) {
            throw new \ArgumentCountError(\sprintf(self::ERROR_TOO_MANY_ARGUMENTS, $this->getMaxArgumentsCount()));
        }

        if ($haystack < $this->getMinArgumentsCount()) {
            throw new \ArgumentCountError(\sprintf(self::ERROR_TOO_FEW_ARGUMENTS, $this->getMinArgumentsCount()));
        }
    }

    public function getMaxArgumentsCount(): int
    {
        return $this->maxArgumentsCount;
    }

    public function getMinArgumentsCount(): int
    {
        return $this->minArgumentsCount;
    }

    public static function render($result): string
    {
        switch (true) {
            case $result === true:
                return '1';
            case $result === null:
            case $result === false:
                return '0';
            default:
                return (string)$result;
        }
    }
}
