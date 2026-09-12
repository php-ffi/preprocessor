<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro\FunctionLikeMacro\CompiledFunctionLikeMacro;

/**
 * Makes a string literal of an argument (6.10.5.2).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.5.2 The # operator
 */
final class ParamStringifier
{
    /**
     * A character constant or a string literal, which keeps its blanks, or the blanks between tokens
     */
    private const PATTERN = '~'
        . '((?:u8|[uUL])?(?:"(?:[^"\\\\\n]|\\\\.)*+"|\'(?:[^\'\\\\\n]|\\\\.)*+\'))'
        . '|[ \t\n\r\f\x0B]++'
        . '~';

    public function stringify(string $argument): string
    {
        $text = \preg_replace_callback(self::PATTERN, $this->stringifyPiece(...), $argument);

        return '"' . \trim((string) $text, ' ') . '"';
    }

    /**
     * @param array<array-key, string> $match
     */
    private function stringifyPiece(array $match): string
    {
        if (isset($match[1])) {
            return \addcslashes($match[1], '"\\');
        }

        return ' ';
    }
}
