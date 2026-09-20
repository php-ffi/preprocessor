<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Macro\FunctionLikeMacro;

use FFI\Preprocessor\Macro\FunctionLikeMacro\TemplateFunctionLikeMacro;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TemplateFunctionLikeMacro::class)]
final class FormattedMacroTest extends TestCase
{
    public function testArgumentsAreSubstitutedInOrder(): void
    {
        $macro = new TemplateFunctionLikeMacro('(%s) > (%s) %% 2', 2);

        self::assertSame('(1) > (2) % 2', $macro->replace(['1', '2'], self::createMacroContext()));
    }

    public function testMissingArgumentIsEmpty(): void
    {
        self::assertSame('[1][]', (new TemplateFunctionLikeMacro('[%s][%s]', 2))->replace(['1'], self::createMacroContext()));
    }

    public function testMacroWithoutParametersIsItsFormat(): void
    {
        self::assertSame('x + y', (new TemplateFunctionLikeMacro('x + y', 0))->replace([], self::createMacroContext()));
    }

    public function testArgumentsAndVariadicFlagAreKept(): void
    {
        $macro = new TemplateFunctionLikeMacro('f(%s, %s)', 2, isVariadic: true);

        self::assertSame(2, $macro->params);
        self::assertTrue($macro->isVariadic);
        self::assertSame('f(x, 1, 2)', $macro->replace(['x', '1, 2'], self::createMacroContext()));
    }
}
