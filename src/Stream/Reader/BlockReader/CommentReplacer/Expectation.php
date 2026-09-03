<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Reader\BlockReader\CommentReplacer;

/**
 * What the next token of a line of a directive may be, as far as the
 * comments are concerned: a header name holds none.
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Stream\Reader
 */
enum Expectation
{
    /**
     * The name of the directive
     */
    case DirectiveName;

    /**
     * The header name of `#include`, `#include_next`, `#import` or `#embed`
     */
    case DirectiveHeader;

    /**
     * The expression of `#if` or `#elif`, where `__has_include` and
     * `__has_embed` take a header name
     */
    case Condition;

    /**
     * The parenthesis after `__has_include`, `__has_include_next` or
     * `__has_embed`
     */
    case OperatorParenthesis;

    /**
     * The header name in the parentheses of `__has_include`,
     * `__has_include_next` or `__has_embed`
     */
    case OperatorHeader;

    /**
     * Anything: the rest of the line holds no header name
     */
    case Rest;
}
