<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Macro\ObjectLikeMacro;

use FFI\Preprocessor\Macro\ObjectLikeMacro\StringObjectLikeMacro;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(StringObjectLikeMacro::class)]
final class StringObjectLikeMacroTest extends TestCase
{
    public function testNameIsReplacedByTheText(): void
    {
        self::assertSame('4096', (new StringObjectLikeMacro('4096'))->replace([]));
    }
}
