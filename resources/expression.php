<?php

declare(strict_types=1);

use FFI\Preprocessor\Internal\Expression\Ast;

/**
 * @var array{
 *     initial: array-key,
 *     tokens: array{
 *         default: array<non-empty-string, non-empty-string>,
 *         ...
 *     },
 *     skip: list<non-empty-string>,
 *     grammar: array<array-key, \Phplrt\Parser\Grammar\RuleInterface>,
 *     reducers: array<array-key, callable(\Phplrt\Parser\Context, mixed):mixed>,
 *     transitions?: array<array-key, mixed>
 * }
 */
return [
    'initial' => 27,
    'tokens' => [
        'default' => [
            'T_HEX_CONSTANT' => '0x([0-9a-fA-F]+)((?i)[ul]*)',
            'T_BIN_CONSTANT' => '0b([0-1]+)((?i)[ul]*)',
            'T_OCT_CONSTANT' => '0([0-7]+)((?i)[ul]*)',
            'T_DEC_CONSTANT' => '([1-9]\\d*|[0-9])((?i)[ul]*)',
            'T_FLOAT_CONSTANT' => '\\bx\\b',
            'T_DEC_FLOAT_CONSTANT' => '\\bx\\b',
            'T_HEX_FLOAT_CONSTANT' => '\\bx\\b',
            'T_STRING_LITERAL' => '(L?)"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"',
            'T_CHAR_CONSTANT' => '(L?)\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\'',
            'T_BOOL_CONSTANT' => '\\b(?:true|false)\\b',
            'T_NULL_CONSTANT' => '\\b(?i)(?:null)\\b',
            'T_BOOL_OR' => '\\|\\|',
            'T_BOOL_AND' => '&&',
            'T_MUL' => '\\*',
            'T_DIV' => '/',
            'T_MOD' => '%',
            'T_PLUS_PLUS' => '\\+\\+',
            'T_PLUS' => '\\+',
            'T_MINUS_MINUS' => '\\-\\-',
            'T_MINUS' => '\\-',
            'T_L_SHIFT' => '<<',
            'T_R_SHIFT' => '>>',
            'T_BIN_AND' => '&',
            'T_BIN_OR' => '\\|',
            'T_BIN_XOR' => '\\^',
            'T_BIT_NOT' => '~',
            'T_EQ' => '==',
            'T_NEQ' => '!=',
            'T_GTE' => '>=',
            'T_LTE' => '<=',
            'T_GT' => '>',
            'T_LT' => '<',
            'T_NOT' => '!',
            'T_ASSIGN' => '=',
            'T_SEMICOLON' => ';',
            'T_COMMA' => ',',
            'T_RND_BRACKET_OPEN' => '\\(',
            'T_RND_BRACKET_CLOSE' => '\\)',
            'T_IDENTIFIER' => '[a-zA-Z_\\x80-\\xff][a-zA-Z0-9_\\x80-\\xff]*',
            'T_WHITESPACE' => '\\s+',
            'T_BLOCK_COMMENT' => '\\h*/\\*.*?\\*/',
            'T_COMMENT' => '\\h*//[^\\n]*\\n*',
        ],
    ],
    'skip' => [
        'T_WHITESPACE',
        'T_BLOCK_COMMENT',
        'T_COMMENT',
    ],
    'transitions' => [],
    'grammar' => [
        new \Phplrt\Parser\Grammar\Lexeme('T_IDENTIFIER', true),
        new \Phplrt\Parser\Grammar\Lexeme('T_DEC_CONSTANT', true),
        new \Phplrt\Parser\Grammar\Lexeme('T_HEX_CONSTANT', true),
        new \Phplrt\Parser\Grammar\Lexeme('T_OCT_CONSTANT', true),
        new \Phplrt\Parser\Grammar\Lexeme('T_BIN_CONSTANT', true),
        new \Phplrt\Parser\Grammar\Lexeme('T_BOOL_CONSTANT', true),
        new \Phplrt\Parser\Grammar\Lexeme('T_STRING_LITERAL', true),
        new \Phplrt\Parser\Grammar\Alternation([1, 2, 3, 4, 5, 6]),
        new \Phplrt\Parser\Grammar\Lexeme('T_FLOAT_CONSTANT', true),
        new \Phplrt\Parser\Grammar\Lexeme('T_DEC_CONSTANT', true),
        new \Phplrt\Parser\Grammar\Lexeme('T_CHAR_CONSTANT', true),
        new \Phplrt\Parser\Grammar\Lexeme('T_IDENTIFIER', true),
        new \Phplrt\Parser\Grammar\Concatenation([11]),
        new \Phplrt\Parser\Grammar\Concatenation([25, 20]),
        new \Phplrt\Parser\Grammar\Concatenation([26, 20]),
        new \Phplrt\Parser\Grammar\Alternation([0, 7, 85]),
        new \Phplrt\Parser\Grammar\Concatenation([21, 20]),
        new \Phplrt\Parser\Grammar\Concatenation([22, 20]),
        new \Phplrt\Parser\Grammar\Concatenation([23, 20]),
        new \Phplrt\Parser\Grammar\Concatenation([24, 20]),
        new \Phplrt\Parser\Grammar\Alternation([13, 14, 15, 16, 17, 18, 19]),
        new \Phplrt\Parser\Grammar\Lexeme('T_PLUS', false),
        new \Phplrt\Parser\Grammar\Lexeme('T_MINUS', false),
        new \Phplrt\Parser\Grammar\Lexeme('T_NOT', false),
        new \Phplrt\Parser\Grammar\Lexeme('T_BIT_NOT', false),
        new \Phplrt\Parser\Grammar\Lexeme('T_PLUS_PLUS', true),
        new \Phplrt\Parser\Grammar\Lexeme('T_MINUS_MINUS', true),
        new \Phplrt\Parser\Grammar\Concatenation([28]),
        new \Phplrt\Parser\Grammar\Concatenation([29]),
        new \Phplrt\Parser\Grammar\Concatenation([30]),
        new \Phplrt\Parser\Grammar\Concatenation([31, 34]),
        new \Phplrt\Parser\Grammar\Concatenation([35, 38]),
        new \Phplrt\Parser\Grammar\Lexeme('T_BOOL_OR', false),
        new \Phplrt\Parser\Grammar\Concatenation([32, 30]),
        new \Phplrt\Parser\Grammar\Optional(33),
        new \Phplrt\Parser\Grammar\Concatenation([39, 42]),
        new \Phplrt\Parser\Grammar\Lexeme('T_BOOL_AND', false),
        new \Phplrt\Parser\Grammar\Concatenation([36, 31]),
        new \Phplrt\Parser\Grammar\Optional(37),
        new \Phplrt\Parser\Grammar\Concatenation([43, 46]),
        new \Phplrt\Parser\Grammar\Lexeme('T_BIN_OR', false),
        new \Phplrt\Parser\Grammar\Concatenation([40, 35]),
        new \Phplrt\Parser\Grammar\Optional(41),
        new \Phplrt\Parser\Grammar\Concatenation([47, 50]),
        new \Phplrt\Parser\Grammar\Lexeme('T_BIN_XOR', false),
        new \Phplrt\Parser\Grammar\Concatenation([44, 39]),
        new \Phplrt\Parser\Grammar\Optional(45),
        new \Phplrt\Parser\Grammar\Concatenation([51, 60]),
        new \Phplrt\Parser\Grammar\Lexeme('T_BIN_AND', false),
        new \Phplrt\Parser\Grammar\Concatenation([48, 43]),
        new \Phplrt\Parser\Grammar\Optional(49),
        new \Phplrt\Parser\Grammar\Concatenation([61, 66]),
        new \Phplrt\Parser\Grammar\Lexeme('T_EQ', true),
        new \Phplrt\Parser\Grammar\Lexeme('T_NEQ', true),
        new \Phplrt\Parser\Grammar\Lexeme('T_GT', true),
        new \Phplrt\Parser\Grammar\Lexeme('T_LT', true),
        new \Phplrt\Parser\Grammar\Lexeme('T_GTE', true),
        new \Phplrt\Parser\Grammar\Lexeme('T_LTE', true),
        new \Phplrt\Parser\Grammar\Alternation([52, 53, 54, 55, 56, 57]),
        new \Phplrt\Parser\Grammar\Concatenation([58, 47]),
        new \Phplrt\Parser\Grammar\Optional(59),
        new \Phplrt\Parser\Grammar\Concatenation([67, 72]),
        new \Phplrt\Parser\Grammar\Lexeme('T_L_SHIFT', true),
        new \Phplrt\Parser\Grammar\Lexeme('T_R_SHIFT', true),
        new \Phplrt\Parser\Grammar\Alternation([62, 63]),
        new \Phplrt\Parser\Grammar\Concatenation([64, 51]),
        new \Phplrt\Parser\Grammar\Optional(65),
        new \Phplrt\Parser\Grammar\Concatenation([79, 73]),
        new \Phplrt\Parser\Grammar\Lexeme('T_PLUS', true),
        new \Phplrt\Parser\Grammar\Lexeme('T_MINUS', true),
        new \Phplrt\Parser\Grammar\Alternation([68, 69]),
        new \Phplrt\Parser\Grammar\Concatenation([70, 61]),
        new \Phplrt\Parser\Grammar\Optional(71),
        new \Phplrt\Parser\Grammar\Alternation([82, 20]),
        new \Phplrt\Parser\Grammar\Lexeme('T_DIV', true),
        new \Phplrt\Parser\Grammar\Lexeme('T_MUL', true),
        new \Phplrt\Parser\Grammar\Lexeme('T_MOD', true),
        new \Phplrt\Parser\Grammar\Alternation([74, 75, 76]),
        new \Phplrt\Parser\Grammar\Concatenation([73, 77]),
        new \Phplrt\Parser\Grammar\Repetition(78, 0, INF),
        new \Phplrt\Parser\Grammar\Lexeme('T_RND_BRACKET_OPEN', false),
        new \Phplrt\Parser\Grammar\Lexeme('T_RND_BRACKET_CLOSE', false),
        new \Phplrt\Parser\Grammar\Concatenation([80, 12, 81, 73]),
        new \Phplrt\Parser\Grammar\Lexeme('T_RND_BRACKET_OPEN', false),
        new \Phplrt\Parser\Grammar\Lexeme('T_RND_BRACKET_CLOSE', false),
        new \Phplrt\Parser\Grammar\Concatenation([83, 28, 84]),
    ],
    'reducers' => [
        0 => static function (\Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Literal\IdentifierLiteral($children->getValue());
        },
        1 => static function (\Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Literal\IntegerLiteral((int)$children[0]->getValue(), $children[1]->getValue());
        },
        2 => static function (\Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Literal\HexIntegerLiteral((string)$children[0]->getValue(), $children[1]->getValue());
        },
        3 => static function (\Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Literal\OctIntegerLiteral((string)$children[0]->getValue(), $children[1]->getValue());
        },
        4 => static function (\Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Literal\OctIntegerLiteral((string)$children[0]->getValue(), $children[1]->getValue());
        },
        5 => static function (\Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Literal\BooleanLiteral(
                $children->getValue() === 'true'
            );
        },
        6 => static function (\Phplrt\Parser\Context $ctx, $children) {
            $value = Ast\Literal\StringLiteral::parse(
                $children[1]->getValue()
            );

            return new Ast\Literal\StringLiteral($value, $children[0]->getValue() !== '');
        },
        13 => static function (\Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Math\PrefixIncrement($children[1]);
        },
        14 => static function (\Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Math\PrefixDecrement($children[1]);
        },
        16 => static function (\Phplrt\Parser\Context $ctx, $children) {
            return $children[0];
        },
        17 => static function (\Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Math\UnaryMinus($children[0]);
        },
        18 => static function (\Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Math\NotExpression($children[0]);
        },
        19 => static function (\Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Math\BitwiseNotExpression($children[1]);
        },
        27 => static function (\Phplrt\Parser\Context $ctx, $children) {
            return $children[0];
        },
        30 => static function (\Phplrt\Parser\Context $ctx, $children) {
            if (\count($children) === 2) {
                return new Ast\Logical\OrExpression($children[0], $children[1]);
            }

            return $children;
        },
        31 => static function (\Phplrt\Parser\Context $ctx, $children) {
            if (\count($children) === 2) {
                return new Ast\Logical\AndExpression($children[0], $children[1]);
            }

            return $children;
        },
        35 => static function (\Phplrt\Parser\Context $ctx, $children) {
            if (\count($children) === 2) {
                return new Ast\Logical\BitwiseOrExpression($children[0], $children[1]);
            }

            return $children;
        },
        39 => static function (\Phplrt\Parser\Context $ctx, $children) {
            if (\count($children) === 2) {
                return new Ast\Logical\BitwiseXorExpression($children[0], $children[1]);
            }

            return $children;
        },
        43 => static function (\Phplrt\Parser\Context $ctx, $children) {
            if (\count($children) === 2) {
                return new Ast\Logical\BitwiseAndExpression($children[0], $children[1]);
            }

            return $children;
        },
        47 => static function (\Phplrt\Parser\Context $ctx, $children) {
            if (\count($children) === 3) {
                switch ($children[1]->getName()) {
                    case 'T_EQ': return new Ast\Comparison\Equal($children[0], $children[2]);
                    case 'T_NEQ': return new Ast\Comparison\NotEqual($children[0], $children[2]);
                    case 'T_GT': return new Ast\Comparison\GreaterThan($children[0], $children[2]);
                    case 'T_GTE': return new Ast\Comparison\GreaterThanOrEqual($children[0], $children[2]);
                    case 'T_LT': return new Ast\Comparison\LessThan($children[0], $children[2]);
                    case 'T_LTE': return new Ast\Comparison\LessThanOrEqual($children[0], $children[2]);
                }
            }

            return $children;
        },
        51 => static function (\Phplrt\Parser\Context $ctx, $children) {
            if (\count($children) === 3) {
                switch ($children[1]->getName()) {
                    case 'T_L_SHIFT': return new Ast\Math\BitwiseLeftShiftExpression($children[0], $children[2]);
                    case 'T_R_SHIFT': return new Ast\Math\BitwiseRightShiftExpression($children[0], $children[2]);
                }
            }

            return $children;
        },
        61 => static function (\Phplrt\Parser\Context $ctx, $children) {
            if (\count($children) === 3) {
                switch ($children[1]->getName()) {
                    case 'T_PLUS': return new Ast\Math\SumExpression($children[0], $children[2]);
                    case 'T_MINUS': return new Ast\Math\SubtractionExpression($children[0], $children[2]);
                }
            }

            return $children;
        },
        67 => static function (\Phplrt\Parser\Context $ctx, $children) {
            while (\count($children) >= 3) {
                [$a, $op, $b] = [
                    \array_shift($children),
                    \array_shift($children),
                    \array_shift($children),
                ];

                switch ($op->getName()) {
                    case 'T_MOD':
                        \array_unshift($children, new Ast\Math\ModExpression($a, $b));
                        break;

                    case 'T_DIV':
                        \array_unshift($children, new Ast\Math\DivExpression($a, $b));
                        break;

                    case 'T_MUL':
                        \array_unshift($children, new Ast\Math\MulExpression($a, $b));
                        break;
                }
            }

            return $children;
        },
        73 => static function (\Phplrt\Parser\Context $ctx, $children) {
            if (\is_array($children) && \count($children) === 2) {
                return new Ast\CastExpression($children[0]->getValue(), $children[1]);
            }

            return $children;
        },
    ],
];