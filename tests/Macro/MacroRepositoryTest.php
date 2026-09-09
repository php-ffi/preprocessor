<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Macro;

use FFI\Preprocessor\Macro\Exception\InvalidMacroException;
use FFI\Preprocessor\Macro\MacroRepository;
use FFI\Preprocessor\Macro\ObjectLikeMacro;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MacroRepository::class)]
final class MacroRepositoryTest extends TestCase
{
    public function testDefinedMacroIsFound(): void
    {
        $macros = new MacroRepository();
        $macros->define('DEBUG');
        $macros->defineByClosure('NOW', static fn(): string => 'now');

        self::assertSame('1', $macros->find('DEBUG')?->replace([]));
        self::assertSame('now', $macros->find('NOW')?->replace([]));
        self::assertNull($macros->find('MISSING'));
    }

    public function testRedefinitionReplacesTheMacro(): void
    {
        $macros = new MacroRepository();
        $macros->defineByString('X', '1');
        $macros->defineByString('X', '2');

        self::assertSame('2', $macros->find('X')?->replace([]));
    }

    public function testRemovedMacroIsNotFound(): void
    {
        $macros = new MacroRepository();
        $macros->define('X');
        $macros->remove('X');
        $macros->remove('UNKNOWN');

        self::assertNull($macros->find('X'));
    }

    public function testPreviousRepositoryIsHiddenButNotChanged(): void
    {
        $prev = new MacroRepository();
        $prev->defineByString('A', '1');
        $prev->defineByString('B', '2');

        $macros = new MacroRepository($prev);
        $macros->remove('A');
        $macros->defineByString('B', '3');
        $macros->defineByString('C', '4');

        self::assertNull($macros->find('A'));
        self::assertSame('3', $macros->find('B')?->replace([]));
        self::assertSame('1', $prev->find('A')?->replace([]));
        self::assertSame(['B', 'C'], \array_keys([...$macros->getAll()]));
    }

    public function testRemovedMacroCanBeDefinedAgain(): void
    {
        $prev = new MacroRepository();
        $prev->define('A');
        $macros = new MacroRepository($prev);
        $macros->remove('A');
        $macros->defineByMacro('A', new ObjectLikeMacro('5'));

        self::assertSame('5', $macros->find('A')?->replace([]));
    }

    public function testNameThatIsNotIdentifierIsRejected(): void
    {
        $this->expectException(InvalidMacroException::class);

        (new MacroRepository())->define('F(x)');
    }
}
