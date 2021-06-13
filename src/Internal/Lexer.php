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
use Phplrt\Contracts\Lexer\TokenInterface;
use Phplrt\Lexer\Lexer as Runtime;
use Phplrt\Lexer\Token\Token;
use Phplrt\Source\File;

/**
 * @internal Lexer is an internal library class, please do not use it in your code.
 * @psalm-internal Bic\Preprocessor\Internal
 */
final class Lexer implements LexerInterface
{
    /**
     * @var string[]
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
     * @var string[]
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

        $this->runtime = new Runtime(self::LEXEMES, [
            Token::END_OF_INPUT,
        ]);
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
