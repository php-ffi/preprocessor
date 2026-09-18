<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Macro\ObjectLikeMacro;

use FFI\Preprocessor\Macro\Exception\InvalidMacroException;
use FFI\Preprocessor\Macro\ObjectLikeMacro\ClosureObjectLikeMacro;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ClosureObjectLikeMacro::class)]
final class ClosureObjectLikeMacroTest extends TestCase
{
    public function testClosureIsCalledEachTimeTheMacroIsReplaced(): void
    {
        $count = 0;

        $macro = ClosureObjectLikeMacro::createFromClosure(static function () use (&$count): int {
            return ++$count;
        });

        self::assertSame('1', $macro->replace([]));
        self::assertSame('2', $macro->replace([]));
    }

    public function testClosureWithParametersIsRejected(): void
    {
        $this->expectException(InvalidMacroException::class);

        ClosureObjectLikeMacro::createFromClosure(static fn(string $x): string => $x);
    }
}
