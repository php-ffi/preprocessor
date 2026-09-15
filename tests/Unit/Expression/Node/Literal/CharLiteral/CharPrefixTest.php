<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Expression\Node\Literal\CharLiteral;

use FFI\Preprocessor\Expression\Node\Literal\CharLiteral\CharPrefix;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CharPrefix::class)]
final class CharPrefixTest extends TestCase
{
    public function testPrefixTellsSignedness(): void
    {
        self::assertFalse(CharPrefix::Plain->isUnsigned());
        self::assertFalse(CharPrefix::Wide->isUnsigned());
        self::assertTrue(CharPrefix::Utf8->isUnsigned());
        self::assertTrue(CharPrefix::Utf16->isUnsigned());
        self::assertTrue(CharPrefix::Utf32->isUnsigned());
    }
}
