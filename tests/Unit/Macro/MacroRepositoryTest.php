<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Macro;

use FFI\Preprocessor\Macro\Exception\InvalidMacroException;
use FFI\Preprocessor\Macro\FunctionLikeMacro;
use FFI\Preprocessor\Macro\MacroRepository;
use FFI\Preprocessor\Macro\ObjectLikeMacro\ClosureObjectLikeMacro;
use FFI\Preprocessor\Macro\ObjectLikeMacro\StringObjectLikeMacro;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Clock\ClockInterface;

#[CoversClass(MacroRepository::class)]
final class MacroRepositoryTest extends TestCase
{
    public function testDefinedMacroIsFound(): void
    {
        $macros = new MacroRepository();
        $macros->define('DEBUG');
        $macros->defineFunctionLikeByClosure('NOW', static fn(): string => 'now');

        self::assertSame('1', $macros->find('DEBUG')?->replace([], self::createMacroContext()));
        self::assertSame('now', $macros->find('NOW')?->replace([], self::createMacroContext()));
        self::assertNull($macros->find('MISSING'));
    }

    public function testRedefinitionReplacesTheMacro(): void
    {
        $macros = new MacroRepository();
        $macros->defineByString('X', '1');
        $macros->defineByString('X', '2');

        self::assertSame('2', $macros->find('X')?->replace([], self::createMacroContext()));
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
        self::assertSame('3', $macros->find('B')?->replace([], self::createMacroContext()));
        self::assertSame('1', $prev->find('A')?->replace([], self::createMacroContext()));
        self::assertSame(['B', 'C'], \array_keys([...$macros->getAll()]));
    }

    public function testRemovedMacroCanBeDefinedAgain(): void
    {
        $prev = new MacroRepository();
        $prev->define('A');
        $macros = new MacroRepository($prev);
        $macros->remove('A');
        $macros->defineByMacro('A', new StringObjectLikeMacro('5'));

        self::assertSame('5', $macros->find('A')?->replace([], self::createMacroContext()));
    }

    public function testNameThatIsNotIdentifierIsRejected(): void
    {
        $this->expectException(InvalidMacroException::class);

        (new MacroRepository())->define('F(x)');
    }

    public function testDefaultRepositoryDefinesTheMandatoryMacros(): void
    {
        $macros = MacroRepository::createDefault();

        self::assertSame('1', $macros->find('__STDC__')?->replace([], self::createMacroContext()));
        self::assertSame('202311L', $macros->find('__STDC_VERSION__')?->replace([], self::createMacroContext()));
        self::assertSame('1', $macros->find('__STDC_HOSTED__')?->replace([], self::createMacroContext()));
        self::assertSame('2', $macros->find('__STDC_EMBED_EMPTY__')?->replace([], self::createMacroContext()));
    }

    public function testDefaultDateAndTimeHaveTheFormOfTheStandard(): void
    {
        $macros = MacroRepository::createDefault();

        self::assertMatchesRegularExpression('/\A"[A-Z][a-z]{2} [ \d]\d \d{4}"\z/', $macros->find('__DATE__')?->replace([], self::createMacroContext()) ?? '');
        self::assertMatchesRegularExpression('/\A"\d{2}:\d{2}:\d{2}"\z/', $macros->find('__TIME__')?->replace([], self::createMacroContext()) ?? '');
    }

    public function testDefaultDateAndTimeAreTakenFromTheClock(): void
    {
        $clock = new class implements ClockInterface {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2024-02-03 04:05:06');
            }
        };

        $macros = MacroRepository::createDefault($clock);

        self::assertSame('"Feb  3 2024"', $macros->find('__DATE__')?->replace([], self::createMacroContext()));
        self::assertSame('"04:05:06"', $macros->find('__TIME__')?->replace([], self::createMacroContext()));
    }

    public function testClosureWithoutParametersDefinesAnObjectLikeMacroEvaluatedEachTime(): void
    {
        $count = 0;

        $macros = new MacroRepository();
        $macros->define('COUNTER', static function () use (&$count): int {
            return $count++;
        });

        self::assertInstanceOf(ClosureObjectLikeMacro::class, $macros->find('COUNTER'));
        self::assertSame('0', $macros->find('COUNTER')->replace([], self::createMacroContext()));
        self::assertSame('1', $macros->find('COUNTER')->replace([], self::createMacroContext()));
    }

    public function testClosureWithParametersIsNotAnObjectLikeMacro(): void
    {
        $this->expectException(InvalidMacroException::class);

        (new MacroRepository())->define('F', static fn(string $x): string => $x);
    }

    public function testReplacementOfAnotherTypeIsRejected(): void
    {
        $this->expectException(InvalidMacroException::class);
        $this->expectExceptionMessage('Macro replacement of type "int" is not supported');

        (new MacroRepository())->define('X', 42);
    }

    public function testClosureDefinedByDefineByClosureIsFunctionLike(): void
    {
        $macros = new MacroRepository();
        $macros->defineFunctionLikeByClosure('F', static fn(): string => 'x');

        self::assertInstanceOf(FunctionLikeMacro::class, $macros->find('F'));
    }

    public function testDefaultDateAndTimeAreEvaluatedEachTimeTheyAreReplaced(): void
    {
        $clock = new class implements ClockInterface {
            private int $seconds = 0;

            public function now(): \DateTimeImmutable
            {
                return (new \DateTimeImmutable('2024-02-03 04:05:00'))->modify('+' . $this->seconds++ . ' seconds');
            }
        };

        $macros = MacroRepository::createDefault($clock);

        self::assertSame('"04:05:00"', $macros->find('__TIME__')?->replace([], self::createMacroContext()));
        self::assertSame('"04:05:01"', $macros->find('__TIME__')?->replace([], self::createMacroContext()));
    }

    public function testDefaultMacrosDoNotOverrideThePreviousRepository(): void
    {
        $prev = new MacroRepository();
        $prev->defineByString('__STDC_VERSION__', '199901L');

        $macros = MacroRepository::createWithDefault($prev);

        self::assertSame('199901L', $macros->find('__STDC_VERSION__')?->replace([], self::createMacroContext()));
        self::assertSame('1', $macros->find('__STDC__')?->replace([], self::createMacroContext()));
        self::assertNull($prev->find('__STDC__'));
    }
}
