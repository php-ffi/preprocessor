<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Literal;

class IntegerLiteral extends Literal
{
    /**
     * @var int
     */
    public const TYPE_LONG = 0x00;

    /**
     * @var int
     */
    public const TYPE_UNSIGNED_LONG = 0x01;

    /**
     * @var int
     */
    public const TYPE_LONG_LONG = 0x02;

    /**
     * @var int
     */
    public const TYPE_UNSIGNED_LONG_LONG = 0x02;

    /**
     * @var int[]
     */
    private const TYPE_MAPPINGS = [
        ''    => self::TYPE_LONG,
        'l'   => self::TYPE_LONG,
        'ul'  => self::TYPE_UNSIGNED_LONG,
        'u'   => self::TYPE_UNSIGNED_LONG,
        'll'  => self::TYPE_LONG_LONG,
        'ull' => self::TYPE_UNSIGNED_LONG_LONG,
    ];

    /**
     * @var int
     */
    private int $value;

    /**
     * @var int
     */
    private int $type;

    /**
     * @param int $value
     * @param string $suffix
     */
    public function __construct(int $value, string $suffix)
    {
        $this->value = $value;
        $this->type = $this->parseType($suffix);
    }

    /**
     * @param string $suffix
     * @return int
     */
    private function parseType(string $suffix): int
    {
        $type = self::TYPE_MAPPINGS[\strtolower($suffix)] ?? null;

        if ($type === null) {
            throw new \LogicException('Unknown integer literal suffix "' . $suffix . '"');
        }

        return $type;
    }

    /**
     * @return int
     */
    public function eval(): int
    {
        return $this->value;
    }
}
