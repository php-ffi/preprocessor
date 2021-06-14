<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Internal;

use FFI\Preprocessor\Internal\Lexer\Simplifier;
use Phplrt\Contracts\Lexer\LexerInterface;
use Phplrt\Contracts\Lexer\TokenInterface;
use FFI\Preprocessor\Internal\ExpressionToken;
use Phplrt\Lexer\Lexer as Runtime;
use Phplrt\Lexer\Token\Token;
use Phplrt\Source\File;

/**
 * @internal Lexer is an internal library class, please do not use it in your code.
 * @psalm-internal FFI\Preprocessor\Internal
 *
 * @psalm-type TokenType = Lexer::T_*
 */
final class Lexer implements LexerInterface
{
    /**
     * @var TokenType
     */
    public const T_QUOTED_INCLUDE = 'T_QUOTED_INCLUDE';

    /**
     * @var TokenType
     */
    public const T_ANGLE_BRACKET_INCLUDE = 'T_ANGLE_BRACKET_INCLUDE';

    /**
     * @var TokenType
     */
    public const T_FUNCTION_MACRO = 'T_FUNCTION_MACRO';

    /**
     * @var TokenType
     */
    public const T_OBJECT_MACRO = 'T_OBJECT_MACRO';

    /**
     * @var TokenType
     */
    public const T_UNDEF = 'T_UNDEF';

    /**
     * @var TokenType
     */
    public const T_IFDEF = 'T_IFDEF';

    /**
     * @var TokenType
     */
    public const T_IFNDEF = 'T_IFNDEF';

    /**
     * @var TokenType
     */
    public const T_ENDIF = 'T_ENDIF';

    /**
     * @var TokenType
     */
    public const T_IF = 'T_IF';

    /**
     * @var TokenType
     */
    public const T_ELSE_IF = 'T_ELSE_IF';

    /**
     * @var TokenType
     */
    public const T_ELSE = 'T_ELSE';

    /**
     * @var TokenType
     */
    public const T_ERROR = 'T_ERROR';

    /**
     * @var TokenType
     */
    public const T_WARNING = 'T_WARNING';

    /**
     * @var TokenType
     */
    public const T_SOURCE = 'T_SOURCE';

    /**
     * @var TokenType
     */
    public const T_EOI = TokenInterface::END_OF_INPUT;

    /**
     * @var array<TokenType, string>
     */
    private const LEXEMES = [
        self::T_QUOTED_INCLUDE        => '^\\h*#\\h*include\\h+"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"',
        self::T_ANGLE_BRACKET_INCLUDE => '^\\h*#\\h*include\\h+<\\h*([^\\n]+)\\h*>',
        self::T_FUNCTION_MACRO        => '^\\h*#\\h*define\\h+(\\w+)\\(([^\\n]+?)\\)\\h*((?:\\\\s|\\\\\\n|[^\\n])+)?$',
        self::T_OBJECT_MACRO          => '^\\h*#\\h*define\\h+(\\w+)\\h*((?:\\\\s|\\\\\\n|[^\\n])+)?$',
        self::T_UNDEF                 => '^\\h*#\\h*undef\\h+(\\w+)$',
        self::T_IFDEF                 => '^\\h*#\\h*ifdef\\b\\h*((?:\\\\s|\\\\\\n|[^\\n])+)',
        self::T_IFNDEF                => '^\\h*#\\h*ifndef\\b\\h*((?:\\\\s|\\\\\\n|[^\\n])+)',
        self::T_ENDIF                 => '^\\h*#\\h*endif\\b\\h*',
        self::T_IF                    => '^\\h*#\\h*if\\b\\h*((?:\\\\s|\\\\\\n|[^\\n])+)',
        self::T_ELSE_IF               => '^\\h*#\\h*elif\\b\\h*((?:\\\\s|\\\\\\n|[^\\n])+)',
        self::T_ELSE                  => '^\\h*#\\h*else',
        self::T_ERROR                 => '^\\h*#\\h*error\\h+((?:\\\\s|\\\\\\n|[^\\n])+)',
        self::T_WARNING               => '^\\h*#\\h*warning\\h+((?:\\\\s|\\\\\\n|[^\\n])+)',
        self::T_SOURCE                => '[^\\n]+|\\n+',
    ];

    /**
     * @var array<TokenType>
     */
    private const MERGE = [
        self::T_SOURCE,
    ];

    /**
     * @var Runtime
     */
    private Runtime $runtime;

    /**
     * @var Simplifier
     */
    private Simplifier $simplifier;

    /**
     * Lexer constructor.
     */
    public function __construct()
    {
        $this->simplifier = new Simplifier();
        $this->runtime = new Runtime(self::LEXEMES, [self::T_EOI]);
    }

    /**
     * {@inheritDoc}
     */
    public function lex($source, int $offset = 0): iterable
    {
        $source = $this->simplifier->simplify(File::new($source));

        $stream = $this->runtime->lex($source, $offset);
        $previous = null;

        foreach ($stream as $token) {
            // Should be merged
            foreach (self::MERGE as $merge) {
                if ($update = $this->merge($merge, $previous, $token)) {
                    $previous = $update;
                    continue 2;
                }
            }

            if ($previous) {
                yield $previous;
            }

            $previous = $token;
        }

        if ($previous) {
            yield $previous;
        }
    }

    /**
     * @param string $name
     * @param TokenInterface|null $prev
     * @param TokenInterface $current
     * @return TokenInterface|null
     */
    private function merge(string $name, ?TokenInterface $prev, TokenInterface $current): ?TokenInterface
    {
        if ($prev && $prev->getName() === $name && $current->getName() === $name) {
            $body = $prev->getValue() . $current->getValue();

            return new Token($name, $body, $prev->getOffset());
        }

        return null;
    }
}
