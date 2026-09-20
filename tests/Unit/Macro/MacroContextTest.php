<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Macro;

use FFI\Preprocessor\Macro\MacroContext;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MacroContext::class)]
final class MacroContextTest extends TestCase
{
    public function testLineIsCountedFromOne(): void
    {
        self::assertSame(1, self::createMacroContext("a\nb\nc", 0)->getLine());
        self::assertSame(1, self::createMacroContext("a\nb\nc", 1)->getLine());
        self::assertSame(2, self::createMacroContext("a\nb\nc", 2)->getLine());
        self::assertSame(3, self::createMacroContext("a\nb\nc", 4)->getLine());
    }
}
