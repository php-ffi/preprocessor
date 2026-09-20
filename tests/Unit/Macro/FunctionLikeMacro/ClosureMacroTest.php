<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Macro\FunctionLikeMacro;

use FFI\Preprocessor\Macro\FunctionLikeMacro\ClosureFunctionLikeMacro;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ClosureFunctionLikeMacro::class)]
final class ClosureMacroTest extends TestCase
{
    public function testParametersOfTheClosureAreTheParametersOfTheMacro(): void
    {
        $macro = ClosureFunctionLikeMacro::createFromClosure(static fn(string $foo, string $bar): string => $foo . $bar);

        self::assertSame(2, $macro->params);
        self::assertFalse($macro->isVariadic);
        self::assertSame('ab', $macro->replace(['a', 'b'], self::createMacroContext()));
    }

    public function testVariadicClosureMakesVariadicMacro(): void
    {
        $macro = ClosureFunctionLikeMacro::createFromClosure(static fn(string $format, string ...$rest): string => $format . \implode('', $rest));

        self::assertSame(2, $macro->params);
        self::assertTrue($macro->isVariadic);
        self::assertSame('f123', $macro->replace(['f', '1', '2', '3'], self::createMacroContext()));
    }

    public function testClosureWithoutParameters(): void
    {
        $macro = ClosureFunctionLikeMacro::createFromClosure(static fn(): int => 42);

        self::assertSame(0, $macro->params);
        self::assertSame('42', $macro->replace([], self::createMacroContext()));
    }
}
