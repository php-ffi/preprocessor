<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests;

use FFI\Contracts\Preprocessor\Directive\RepositoryInterface;
use FFI\Preprocessor\Directive\FunctionDirective;
use FFI\Preprocessor\Directive\FunctionLikeDirective;
use FFI\Preprocessor\Directive\ObjectLikeDirective;
use FFI\Preprocessor\Directive\Repository;
use FFI\Preprocessor\Exception\DirectiveEvaluationException;
use FFI\Preprocessor\Internal\Runtime\DirectiveExecutor;

class DirectivesTest extends TestCase
{
    public function testInitialState(): void
    {
        $repository = $this->repository();

        $this->assertCount(0, $repository);
        $this->assertSame([], \iterator_to_array($repository));
    }

    private function repository(): Repository
    {
        return new Repository();
    }

    public function testAddition(): void
    {
        $repository = $this->repository();

        $repository->define('example');
        $this->assertCount(1, $repository);
    }

    public function testRemoving(): void
    {
        $repository = $this->repository();

        $repository->define('example');
        $this->assertCount(1, $repository);

        $repository->undef('example');
        $this->assertCount(0, $repository);
    }

    public function testDirectiveDefault(): void
    {
        $repository = $this->repository();
        $repository->define('example');

        $this->assertSame('', $this->execute($repository, 'example'));
    }

    private function execute(RepositoryInterface $repository, string $name = 'test', array $args = []): string
    {
        $executor = new DirectiveExecutor($repository);

        return $executor->execute($name, $args);
    }

    public function testDirectiveNonRegistered(): void
    {
        $repository = $this->repository();

        $this->assertSame('example', $this->execute($repository, 'example'));
    }

    public function testDirectiveFromString(): void
    {
        $repository = $this->repositoryWith('result');

        $this->assertSame('result', $this->execute($repository));
    }

    private function repositoryWith($value): Repository
    {
        $repository = $this->repository();
        $repository->define('test', $value);

        return $repository;
    }

    public function testDirectiveFromCallable(): void
    {
        $repository = $this->repositoryWith(function () {
            return 'result';
        });

        $this->assertSame('result', $this->execute($repository));
    }

    public function testLazyDirective(): void
    {
        $repository = $this->repositoryWith(new FunctionDirective(function () {
            return 'result';
        }));

        $this->assertSame('result', $this->execute($repository));
    }

    /**
     * @throws \ReflectionException
     */
    public function testLazyDirectiveWithMissingArguments(): void
    {
        $this->expectException(DirectiveEvaluationException::class);

        $repository = $this->repositoryWith(new FunctionDirective(function (string $arg) {
            return 'result';
        }));

        $this->execute($repository);
    }

    public function testLazyDirectiveWithExtraArguments(): void
    {
        $repository = $this->repositoryWith(new FunctionDirective(function () {
            return 'result';
        }));

        $this->assertSame('result', $this->execute($repository, 'test', ['example']));
    }

    public function testObjectLikeDirective(): void
    {
        $repository = $this->repositoryWith(new ObjectLikeDirective('result'));

        $this->assertSame('result', $this->execute($repository));
    }

    public function testObjectLikeDirectiveWithExtraArguments(): void
    {
        $this->expectException(DirectiveEvaluationException::class);

        $repository = $this->repositoryWith(new ObjectLikeDirective('result'));

        $this->assertSame('result', $this->execute($repository, 'test', ['example']));
    }

    public function testFunctionLikeDirective(): void
    {
        $repository = $this->repositoryWith(new FunctionLikeDirective([], 'result'));

        $this->assertSame('result', $this->execute($repository));
    }

    public function testFunctionLikeDirectiveWithMissingArguments(): void
    {
        $this->expectException(DirectiveEvaluationException::class);

        $repository = $this->repositoryWith(new FunctionLikeDirective(['argument'], 'result'));

        $this->execute($repository);
    }

    public function testFunctionLikeDirectiveWithExtraArguments(): void
    {
        $this->expectException(DirectiveEvaluationException::class);

        $repository = $this->repositoryWith(new FunctionLikeDirective([], 'result'));

        $this->execute($repository, 'test', ['example']);
    }

    public function functionLikeDirectiveDataProvider(): array
    {
        return [
            /**
             * <code>
             *  #define test(arg)      return arg;
             *  test(test) // output:  return test;
             * </code>
             */
            [['arg'], 'return arg;', ['test'], 'return test;'],

            /**
             * <code>
             *  #define test(arg)      return #arg;
             *  test(test) // output:  return "test";
             * </code>
             */
            [['arg'], 'return #arg;', ['test'], 'return "test";'],

            /**
             * <code>
             *  #define test(arg)      return (arg);
             *  test(test) // output:  return (test);
             * </code>
             */
            [['arg'], 'return (arg);', ['test'], 'return (test);'],

            /**
             * <code>
             *  #define test(arg)      return (arg, arg);
             *  test(test) // output:  return (test, test);
             * </code>
             */
            [['arg'], 'return (arg, arg);', ['test'], 'return (test, test);'],

            /**
             * <code>
             *  #define test(arg)      return _arg;
             *  test(test) // output:  return _arg;
             * </code>
             */
            [['arg'], 'return _arg;', ['test'], 'return _arg;'],

            /**
             * <code>
             *  #define test(arg)      return _##arg;
             *  test(test) // output:  return _test;
             * </code>
             */
            [['arg'], 'return _##arg;', ['test'], 'return _test;'],

            /**
             * <code>
             *  #define test(arg)      return arg_;
             *  test(test) // output:  return arg_;
             * </code>
             */
            [['arg'], 'return arg_;', ['test'], 'return arg_;'],

            /**
             * <code>
             *  #define test(arg)      return arg##_;
             *  test(test) // output:  return test_;
             * </code>
             */
            [['arg'], 'return arg##_;', ['test'], 'return test_;'],
        ];
    }

    /**
     * @dataProvider functionLikeDirectiveDataProvider
     */
    public function testFunctionLikeDirectiveReplacements(array $args, string $body, array $pass, string $result): void
    {
        $repository = $this->repositoryWith(new FunctionLikeDirective($args, $body));

        $this->assertSame($result, $this->execute($repository, 'test', $pass));
    }
}
