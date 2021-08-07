<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Directive;

abstract class Directive implements DirectiveInterface
{
    /**
     * @var string
     */
    protected const ERROR_TOO_MANY_ARGUMENTS = 'Too many arguments when macro directive is called, %s required';

    /**
     * @var string
     */
    protected const ERROR_TOO_FEW_ARGUMENTS = 'Too few arguments when macro directive is called, %s required';

    /**
     * @var int
     */
    protected int $minArgumentsCount = 0;

    /**
     * @var int
     */
    protected int $maxArgumentsCount = 0;

    /**
     * @param string $body
     * @return string
     */
    protected function normalizeBody(string $body): string
    {
        return \str_replace("\\\n", "\n", $body);
    }

    /**
     * @param array|string[] $arguments
     * @return void
     */
    protected function assertArgumentsCount(array $arguments): void
    {
        $haystack = \count($arguments);

        if ($haystack > $this->getMaxArgumentsCount()) {
            throw new \ArgumentCountError(\sprintf(static::ERROR_TOO_MANY_ARGUMENTS, $this->getMaxArgumentsCount()));
        }

        if ($haystack < $this->getMinArgumentsCount()) {
            throw new \ArgumentCountError(\sprintf(static::ERROR_TOO_FEW_ARGUMENTS, $this->getMinArgumentsCount()));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxArgumentsCount(): int
    {
        return $this->maxArgumentsCount;
    }

    /**
     * {@inheritDoc}
     */
    public function getMinArgumentsCount(): int
    {
        return $this->minArgumentsCount;
    }

    /**
     * @param mixed $result
     * @return string
     */
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
