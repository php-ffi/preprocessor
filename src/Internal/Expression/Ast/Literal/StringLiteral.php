<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Literal;

final class StringLiteral extends Literal
{
    /**
     * ISO/IEC 9899:TC2 Grammar
     * <code>
     *  (6.4.4.4) simple-escape-sequence: one of
     *      \' \" \? \\
     *      \a \b \f \n \r \t \v
     * </code>
     *
     * @var string
     */
    private const ESCAPE_SEQUENCES = [
        '\\\\' => "\\",
        '\"'   => '"',
        "\'"   => "'",
        '\?'   => "\u{003F}",
        '\a'   => "\u{0007}",
        '\b'   => "\u{0008}",
        '\f'   => "\u{000C}",
        '\n'   => "\u{000A}",
        '\r'   => "\u{000D}",
        '\t'   => "\u{0009}",
        '\v'   => "\u{000B}",
    ];

    /**
     * @var string
     */
    private string $value;

    /**
     * @var bool
     */
    private bool $wideChar;

    /**
     * @param string $value
     * @param bool $wideChar
     */
    public function __construct(string $value, bool $wideChar = false)
    {
        $this->value = $value;
        $this->wideChar = $wideChar;
    }

    /**
     * @param string $value
     * @return string
     */
    public static function parse(string $value): string
    {
        return \str_replace(
            \array_keys(self::ESCAPE_SEQUENCES),
            \array_values(self::ESCAPE_SEQUENCES),
            $value
        );
    }

    /**
     * @return string
     */
    public function eval(): string
    {
        return $this->value;
    }
}
