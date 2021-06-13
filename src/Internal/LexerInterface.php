<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Internal;

use Phplrt\Contracts\Lexer\LexerInterface as BaseLexerInterface;

interface LexerInterface extends BaseLexerInterface
{
    /**
     * @var string
     */
    public const T_QUOTED_INCLUDE = 'T_QUOTED_INCLUDE';

    /**
     * @var string
     */
    public const T_ANGLE_BRACKET_INCLUDE = 'T_ANGLE_BRACKET_INCLUDE';

    /**
     * @var string
     */
    public const T_FUNCTION_MACRO = 'T_FUNCTION_MACRO';

    /**
     * @var string
     */
    public const T_OBJECT_MACRO = 'T_OBJECT_MACRO';

    /**
     * @var string
     */
    public const T_UNDEF = 'T_UNDEF';

    /**
     * @var string
     */
    public const T_IFDEF = 'T_IFDEF';

    /**
     * @var string
     */
    public const T_IFNDEF = 'T_IFNDEF';

    /**
     * @var string
     */
    public const T_ENDIF = 'T_ENDIF';

    /**
     * @var string
     */
    public const T_IF = 'T_IF';

    /**
     * @var string
     */
    public const T_ELSE_IF = 'T_ELSE_IF';

    /**
     * @var string
     */
    public const T_ELSE = 'T_ELSE';

    /**
     * @var string
     */
    public const T_ERROR = 'T_ERROR';

    /**
     * @var string
     */
    public const T_WARNING = 'T_WARNING';

    /**
     * @var string
     */
    public const T_SOURCE = 'T_SOURCE';
}
