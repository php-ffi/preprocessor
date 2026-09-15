<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Directive;

use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\DirectiveHandlerInterface;
use FFI\Preprocessor\Directive\DirectiveRepository;
use FFI\Preprocessor\Directive\Exception\InvalidDirectiveNameException;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\DirectiveChunk;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DirectiveRepository::class)]
#[CoversClass(InvalidDirectiveNameException::class)]
final class DirectiveRepositoryTest extends TestCase
{
    private static function createHandler(): DirectiveHandlerInterface
    {
        return new class implements DirectiveHandlerInterface {
            public function handle(DirectiveChunk $directive, DirectiveContext $context): iterable
            {
                return [];
            }
        };
    }

    public function testRepositoryIsEmptyByDefault(): void
    {
        self::assertNull((new DirectiveRepository())->find('include'));
    }

    public function testHandlerIsAddedUnderName(): void
    {
        $handler = self::createHandler();
        $repository = new DirectiveRepository();

        $repository->add('import', $handler);

        self::assertSame($handler, $repository->find('import'));
    }

    public function testAddedHandlerIsNotReplacedByAdding(): void
    {
        $repository = new DirectiveRepository();
        $repository->add('include', self::createHandler());

        $this->expectException(InvalidDirectiveNameException::class);
        $this->expectExceptionMessage('A handler of the "#include" directive is already added, replace it instead');

        $repository->add('include', self::createHandler());
    }

    public function testHandlerIsReplaced(): void
    {
        $handler = self::createHandler();
        $repository = new DirectiveRepository();
        $repository->add('include', self::createHandler());

        $repository->replace('include', $handler);
        $repository->replace('ident', $handler);

        self::assertSame($handler, $repository->find('include'));
        self::assertSame($handler, $repository->find('ident'));
    }

    public function testHandlerIsRemoved(): void
    {
        $repository = new DirectiveRepository();
        $repository->add('include', self::createHandler());

        $repository->remove('include');
        $repository->remove('missing');

        self::assertNull($repository->find('include'));
    }

    public function testConditionalDirectiveIsReplaceable(): void
    {
        $handler = self::createHandler();
        $repository = new DirectiveRepository();

        $repository->add('if', $handler);

        self::assertSame($handler, $repository->find('if'));
    }

    /**
     * @return iterable<non-empty-string, array{string, string}>
     */
    public static function invalidNameDataProvider(): iterable
    {
        yield 'empty' => ['', 'Directive name "" is not an identifier'];
        yield 'number' => ['1x', 'Directive name "1x" is not an identifier'];
        yield 'space' => ['a b', 'Directive name "a b" is not an identifier'];
        yield 'quote' => ['a"b', 'Directive name "a\"b" is not an identifier'];
    }

    #[DataProvider('invalidNameDataProvider')]
    public function testInvalidNameIsRejected(string $name, string $message): void
    {
        $repository = new DirectiveRepository();

        $this->expectException(InvalidDirectiveNameException::class);
        $this->expectExceptionMessage($message);

        $repository->add($name, self::createHandler());
    }

    public function testInvalidNameIsRejectedOnReplacing(): void
    {
        $this->expectException(InvalidDirectiveNameException::class);

        (new DirectiveRepository())->replace('a b', self::createHandler());
    }

    public function testHandlerOfPreviousRepositoryIsFoundAndReplacedWithoutChangingIt(): void
    {
        $inner = self::createHandler();
        $outer = self::createHandler();
        $prev = new DirectiveRepository();
        $prev->add('include', $inner);
        $repository = new DirectiveRepository($prev);

        self::assertSame($inner, $repository->find('include'));

        $repository->replace('include', $outer);

        self::assertSame($outer, $repository->find('include'));
        self::assertSame($inner, $prev->find('include'));
    }

    public function testHandlerOfPreviousRepositoryIsNotAddedTwice(): void
    {
        $prev = new DirectiveRepository();
        $prev->add('include', self::createHandler());

        $this->expectException(InvalidDirectiveNameException::class);

        (new DirectiveRepository($prev))->add('include', self::createHandler());
    }

    public function testRemovedHandlerOfPreviousRepositoryIsHiddenWithoutChangingIt(): void
    {
        $prev = new DirectiveRepository();
        $prev->add('include', self::createHandler());
        $repository = new DirectiveRepository($prev);

        $repository->remove('include');

        self::assertNull($repository->find('include'));
        self::assertNotNull($prev->find('include'));
    }
}
