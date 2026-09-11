<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro\Executor\MacroExpander;

use Phplrt\Lexer\Lexer;

/**
 * The lexer of the preprocessing tokens (6.4) and the identifiers of the
 * token kinds it marks, generated from `resources/tokens.pp3`.
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Macro\Executor
 */
final class CompiledTokenLexer extends Lexer
{
    /** @var int */
    public const T_WHITESPACE = 0;

    /** @var int */
    public const T_STRING = 1;

    /** @var int */
    public const T_CHAR = 2;

    /** @var int */
    public const T_NUMBER = 3;

    /** @var int */
    public const T_IDENTIFIER = 4;

    /** @var int */
    public const T_PUNCTUATOR = 5;

    /** @var int */
    public const T_OTHER = 6;

    /**
     * Every token at the current offset, the identifier of its kind is the
     * mark of the match
     *
     * @var non-empty-string
     */
    public const PATTERN = '/\\G(?|(?:(?:(?:[\\x20\\t\\f\\x0B\\r\\n]++|\\/\\*(?:[^*]++|\\*(?!\\/))*+(?:\\*\\/|\\z)|\\/\\/[^\\r\\n]*+)++)(*MARK:0))|(?:(?:(?:u8|[uUL])?"(?:[^"\\\\\\r\\n]++|(?:\\\\[^\\r\\n]))*+"?)(*MARK:1))|(?:(?:(?:u8|[uUL])?\'(?:[^\'\\\\\\r\\n]++|(?:\\\\[^\\r\\n]))*+\'?)(*MARK:2))|(?:(?:\\.?[0-9](?:[eEpP][+-]|[0-9A-Za-z_\\x80-\\xFF.]|(?:\\\\(?:u[0-9A-Fa-f]{4}|U[0-9A-Fa-f]{8}))|\'[0-9A-Za-z_])*+)(*MARK:3))|(?:(?:(?:[A-Za-z_\\x80-\\xFF]|(?:\\\\(?:u[0-9A-Fa-f]{4}|U[0-9A-Fa-f]{8})))(?:[0-9A-Za-z_\\x80-\\xFF]|(?:\\\\(?:u[0-9A-Fa-f]{4}|U[0-9A-Fa-f]{8})))*+)(*MARK:4))|(?:(?:%:%:|\\.\\.\\.|<<=|>>=|->|\\+\\+|--|<<|>>|<=|>=|==|!=|&&|\\|\\||\\*=|\\/=|%=|\\+=|-=|&=|\\^=|\\|=|\\#\\#|::|<:|:>|<%|%>|%:|[\\[\\](){}.&*+\\-~!\\/%<>^|?:;=,\\#])(*MARK:5))|(?:(?:[\\s\\S])(*MARK:6))|(?:(?:[^\\s]++)(*MARK:7)))/Ssm';

    public function __construct()
    {
        parent::__construct(pattern: self::PATTERN);
    }
}
