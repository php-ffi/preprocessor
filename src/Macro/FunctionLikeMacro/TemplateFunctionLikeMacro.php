<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro\FunctionLikeMacro;

use FFI\Preprocessor\Macro\FunctionLikeMacro;

/**
 * Function-like macro compiled to a `sprintf` format: the Nth `%s` is
 * replaced by the Nth argument.
 */
final class TemplateFunctionLikeMacro extends FunctionLikeMacro
{
    /**
     * @param int<0, max> $params
     */
    public function __construct(
        /**
         * The text of the replacement, a `%` that is not a parameter, is written `%%`
         */
        public readonly string $format,
        int $params,
        bool $isVariadic = false,
    ) {
        parent::__construct($params, $isVariadic);
    }

    public function replace(array $arguments): string
    {
        return \vsprintf($this->format, $arguments + \array_fill(0, $this->params, ''));
    }
}
