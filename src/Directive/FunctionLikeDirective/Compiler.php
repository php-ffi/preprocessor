<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\FunctionLikeDirective;

use FFI\Preprocessor\Directive\FunctionLikeDirective;

/**
 * @internal Refers to {@see FunctionLikeDirective}
 */
final class Compiler
{
    /**
     * @var string
     */
    private const T_STRINGIZE = 'stringize';

    /**
     * @var string
     */
    private const T_CONCAT_LEFT = 'concat_left';

    /**
     * @var string
     */
    private const T_CONCAT_RIGHT= 'concat_right';

    /**
     * @var string
     */
    private const T_NATIVE = 'native';

    /**
     * @var string
     */
    private const TPL_PATTERN = '/' .
        '#\h*%1$s(*MARK:' . self::T_STRINGIZE . ')|' .
        '##\h*%1$s(?:\h*##)?(*MARK:' . self::T_CONCAT_LEFT . ')|' .
        '%1$s\h*##(*MARK:' . self::T_CONCAT_RIGHT . ')|' .
        '(?<![a-z0-9_])%1$s(?![a-z0-9_])(*MARK:' . self::T_NATIVE . ')' .
        '/Ssum'
    ;

    /**
     * @psalm-param array<array-key, string> $arguments
     * @psalm-return \Closure(mixed ...$args): string
     *
     * @param string $body
     * @param array $arguments
     * @return \Closure
     */
    public static function compile(string $body, array $arguments): \Closure
    {
        $template = self::build($body, $arguments);

        return static function (...$args) use ($template): string {
            $from = \array_map(static fn (int $i): string => "\0$i\0", \array_keys($args));

            return \str_replace($from, $args, $template);
        };
    }

    /**
     * @param string $body
     * @param array $arguments
     * @return string
     */
    private static function build(string $body, array $arguments): string
    {
        foreach ($arguments as $i => $name) {
            $body = self::replace($body, $i, $name);
        }

        return $body;
    }

    /**
     * @param string $body
     * @param int $i
     * @param string $name
     * @return string
     */
    private static function replace(string $body, int $i, string $name): string
    {
        $pattern = \sprintf(self::TPL_PATTERN, \preg_quote($name, '/'));

        return (string)\preg_replace_callback($pattern, self::callback($i), $body);
    }

    /**
     * @param int $i
     * @return \Closure
     */
    private static function callback(int $i): \Closure
    {
        return static function ($match) use ($i): string {
            return $match['MARK'] === self::T_STRINGIZE
                ? "\"\0$i\0\""
                : "\0$i\0";
        };
    }
}
