<?php

use FFI\Preprocessor\Internal\Expression\Ast;

return [
    'initial' => 17,
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
    'transitions' => [
    ],
    'grammar' => [
        'AdditiveExpression' => new Phplrt\Grammar\Concatenation(['MultiplicativeExpression', 51]),
        'AndExpression' => new Phplrt\Grammar\Concatenation(['EqualityExpression', 32]),
        'PrefixIncrement' => new Phplrt\Grammar\Concatenation([15, 'UnaryExpression']),
        'ShiftExpression' => new Phplrt\Grammar\Concatenation(['AdditiveExpression', 46]),
        'TypeName' => new Phplrt\Grammar\Concatenation(['TypeSpecifier']),
        'TypeSpecifier' => new Phplrt\Grammar\Lexeme('T_IDENTIFIER', true),
        'UnaryBitwiseNot' => new Phplrt\Grammar\Concatenation([14, 'UnaryExpression']),
        'UnaryExpression' => new Phplrt\Grammar\Alternation(['PrefixIncrement', 'PrefixDecrement', 'PrimaryExpression', 'UnaryPlus', 'UnaryMinus', 'UnaryNot', 'UnaryBitwiseNot']),
        'UnaryMinus' => new Phplrt\Grammar\Concatenation([12, 'UnaryExpression']),
        'UnaryNot' => new Phplrt\Grammar\Concatenation([13, 'UnaryExpression']),
        'UnaryPlus' => new Phplrt\Grammar\Concatenation([11, 'UnaryExpression']),
        0 => new Phplrt\Grammar\Lexeme('T_IDENTIFIER', true),
        'CastExpression' => new Phplrt\Grammar\Alternation([60, 'UnaryExpression']),
        'ConditionalExpression' => new Phplrt\Grammar\Concatenation(['LogicalOrExpression']),
        'EqualityExpression' => new Phplrt\Grammar\Concatenation(['ShiftExpression', 41]),
        'ExclusiveOrExpression' => new Phplrt\Grammar\Concatenation(['AndExpression', 29]),
        'Expression' => new Phplrt\Grammar\Concatenation(['ConditionalExpression']),
        'InclusiveOrExpression' => new Phplrt\Grammar\Concatenation(['ExclusiveOrExpression', 26]),
        'LogicalAndExpression' => new Phplrt\Grammar\Concatenation(['InclusiveOrExpression', 23]),
        'LogicalOrExpression' => new Phplrt\Grammar\Concatenation(['LogicalAndExpression', 20]),
        'MultiplicativeExpression' => new Phplrt\Grammar\Concatenation([57, 'CastExpression']),
        'PrefixDecrement' => new Phplrt\Grammar\Concatenation([16, 'UnaryExpression']),
        1 => new Phplrt\Grammar\Lexeme('T_DEC_CONSTANT', true),
        2 => new Phplrt\Grammar\Lexeme('T_HEX_CONSTANT', true),
        3 => new Phplrt\Grammar\Lexeme('T_OCT_CONSTANT', true),
        4 => new Phplrt\Grammar\Lexeme('T_BIN_CONSTANT', true),
        5 => new Phplrt\Grammar\Lexeme('T_BOOL_CONSTANT', true),
        6 => new Phplrt\Grammar\Lexeme('T_STRING_LITERAL', true),
        7 => new Phplrt\Grammar\Alternation([1, 2, 3, 4, 5, 6]),
        8 => new Phplrt\Grammar\Lexeme('T_FLOAT_CONSTANT', true),
        9 => new Phplrt\Grammar\Lexeme('T_DEC_CONSTANT', true),
        10 => new Phplrt\Grammar\Lexeme('T_CHAR_CONSTANT', true),
        11 => new Phplrt\Grammar\Lexeme('T_PLUS', false),
        12 => new Phplrt\Grammar\Lexeme('T_MINUS', false),
        13 => new Phplrt\Grammar\Lexeme('T_NOT', false),
        14 => new Phplrt\Grammar\Lexeme('T_BIT_NOT', false),
        15 => new Phplrt\Grammar\Lexeme('T_PLUS_PLUS', true),
        16 => new Phplrt\Grammar\Lexeme('T_MINUS_MINUS', true),
        17 => new Phplrt\Grammar\Concatenation(['Expression']),
        18 => new Phplrt\Grammar\Lexeme('T_BOOL_OR', false),
        19 => new Phplrt\Grammar\Concatenation([18, 'LogicalOrExpression']),
        20 => new Phplrt\Grammar\Optional(19),
        21 => new Phplrt\Grammar\Lexeme('T_BOOL_AND', false),
        22 => new Phplrt\Grammar\Concatenation([21, 'LogicalAndExpression']),
        23 => new Phplrt\Grammar\Optional(22),
        24 => new Phplrt\Grammar\Lexeme('T_BIN_OR', false),
        25 => new Phplrt\Grammar\Concatenation([24, 'InclusiveOrExpression']),
        26 => new Phplrt\Grammar\Optional(25),
        27 => new Phplrt\Grammar\Lexeme('T_BIN_XOR', false),
        28 => new Phplrt\Grammar\Concatenation([27, 'ExclusiveOrExpression']),
        29 => new Phplrt\Grammar\Optional(28),
        30 => new Phplrt\Grammar\Lexeme('T_BIN_AND', false),
        31 => new Phplrt\Grammar\Concatenation([30, 'AndExpression']),
        32 => new Phplrt\Grammar\Optional(31),
        33 => new Phplrt\Grammar\Lexeme('T_EQ', true),
        34 => new Phplrt\Grammar\Lexeme('T_NEQ', true),
        35 => new Phplrt\Grammar\Lexeme('T_GT', true),
        36 => new Phplrt\Grammar\Lexeme('T_LT', true),
        37 => new Phplrt\Grammar\Lexeme('T_GTE', true),
        38 => new Phplrt\Grammar\Lexeme('T_LTE', true),
        39 => new Phplrt\Grammar\Alternation([33, 34, 35, 36, 37, 38]),
        40 => new Phplrt\Grammar\Concatenation([39, 'EqualityExpression']),
        41 => new Phplrt\Grammar\Optional(40),
        42 => new Phplrt\Grammar\Lexeme('T_L_SHIFT', true),
        43 => new Phplrt\Grammar\Lexeme('T_R_SHIFT', true),
        44 => new Phplrt\Grammar\Alternation([42, 43]),
        45 => new Phplrt\Grammar\Concatenation([44, 'ShiftExpression']),
        46 => new Phplrt\Grammar\Optional(45),
        47 => new Phplrt\Grammar\Lexeme('T_PLUS', true),
        48 => new Phplrt\Grammar\Lexeme('T_MINUS', true),
        49 => new Phplrt\Grammar\Alternation([47, 48]),
        50 => new Phplrt\Grammar\Concatenation([49, 'AdditiveExpression']),
        51 => new Phplrt\Grammar\Optional(50),
        52 => new Phplrt\Grammar\Lexeme('T_DIV', true),
        53 => new Phplrt\Grammar\Lexeme('T_MUL', true),
        54 => new Phplrt\Grammar\Lexeme('T_MOD', true),
        55 => new Phplrt\Grammar\Alternation([52, 53, 54]),
        56 => new Phplrt\Grammar\Concatenation(['CastExpression', 55]),
        57 => new Phplrt\Grammar\Repetition(56, 0, INF),
        58 => new Phplrt\Grammar\Lexeme('T_RND_BRACKET_OPEN', false),
        59 => new Phplrt\Grammar\Lexeme('T_RND_BRACKET_CLOSE', false),
        60 => new Phplrt\Grammar\Concatenation([58, 'TypeName', 59, 'CastExpression']),
        61 => new Phplrt\Grammar\Lexeme('T_RND_BRACKET_OPEN', false),
        62 => new Phplrt\Grammar\Lexeme('T_RND_BRACKET_CLOSE', false),
        63 => new Phplrt\Grammar\Concatenation([61, 'Expression', 62]),
        'PrimaryExpression' => new Phplrt\Grammar\Alternation([0, 7, 63]),
    ],
    'reducers' => [
        0 => function (Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Literal\IdentifierLiteral($children->getValue());
        },
        1 => function (Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Literal\IntegerLiteral((int) $children[0]->getValue(), $children[1]->getValue());
        },
        2 => function (Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Literal\HexIntegerLiteral((string) $children[0]->getValue(), $children[1]->getValue());
        },
        3 => function (Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Literal\OctIntegerLiteral((string) $children[0]->getValue(), $children[1]->getValue());
        },
        4 => function (Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Literal\OctIntegerLiteral((string) $children[0]->getValue(), $children[1]->getValue());
        },
        5 => function (Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Literal\BooleanLiteral(
                $children->getValue() === 'true'
            );
        },
        6 => function (Phplrt\Parser\Context $ctx, $children) {
            $value = Ast\Literal\StringLiteral::parse(
                $children[1]->getValue()
            );

            return new Ast\Literal\StringLiteral($value, $children[0]->getValue() !== '');
        },
        'UnaryPlus' => function (Phplrt\Parser\Context $ctx, $children) {
            return $children[0];
        },
        'UnaryMinus' => function (Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Math\UnaryMinus($children[0]);
        },
        'UnaryNot' => function (Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Math\NotExpression($children[0]);
        },
        'UnaryBitwiseNot' => function (Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Math\BitwiseNotExpression($children[1]);
        },
        'PrefixIncrement' => function (Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Math\PrefixIncrement($children[1]);
        },
        'PrefixDecrement' => function (Phplrt\Parser\Context $ctx, $children) {
            return new Ast\Math\PrefixDecrement($children[1]);
        },
        17 => function (Phplrt\Parser\Context $ctx, $children) {
            return $children[0];
        },
        'LogicalOrExpression' => function (Phplrt\Parser\Context $ctx, $children) {
            if (\count($children) === 2) {
                return new Ast\Logical\OrExpression($children[0], $children[1]);
            }

            return $children;
        },
        'LogicalAndExpression' => function (Phplrt\Parser\Context $ctx, $children) {
            if (\count($children) === 2) {
                return new Ast\Logical\AndExpression($children[0], $children[1]);
            }

            return $children;
        },
        'InclusiveOrExpression' => function (Phplrt\Parser\Context $ctx, $children) {
            if (\count($children) === 2) {
                return new Ast\Logical\BitwiseOrExpression($children[0], $children[1]);
            }

            return $children;
        },
        'ExclusiveOrExpression' => function (Phplrt\Parser\Context $ctx, $children) {
            if (\count($children) === 2) {
                return new Ast\Logical\BitwiseXorExpression($children[0], $children[1]);
            }

            return $children;
        },
        'AndExpression' => function (Phplrt\Parser\Context $ctx, $children) {
            if (\count($children) === 2) {
                return new Ast\Logical\BitwiseAndExpression($children[0], $children[1]);
            }

            return $children;
        },
        'EqualityExpression' => function (Phplrt\Parser\Context $ctx, $children) {
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
        'ShiftExpression' => function (Phplrt\Parser\Context $ctx, $children) {
            if (\count($children) === 3) {
                switch ($children[1]->getName()) {
                    case 'T_L_SHIFT': return new Ast\Math\BitwiseLeftShiftExpression($children[0], $children[2]);
                    case 'T_R_SHIFT': return new Ast\Math\BitwiseRightShiftExpression($children[0], $children[2]);
                }
            }

            return $children;
        },
        'AdditiveExpression' => function (Phplrt\Parser\Context $ctx, $children) {
            if (\count($children) === 3) {
                switch ($children[1]->getName()) {
                    case 'T_PLUS': return new Ast\Math\SumExpression($children[0], $children[2]);
                    case 'T_MINUS': return new Ast\Math\SubtractionExpression($children[0], $children[2]);
                }
            }

            return $children;
        },
        'MultiplicativeExpression' => function (Phplrt\Parser\Context $ctx, $children) {
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
        'CastExpression' => function (Phplrt\Parser\Context $ctx, $children) {
            if (\is_array($children) && \count($children) === 2) {
                return new Ast\CastExpression($children[0]->getValue(), $children[1]);
            }

            return $children;
        },
    ],
];
