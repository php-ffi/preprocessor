<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class ExpressionArchitectureTest
{
    public function testNodesDependOnNothing(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('FFI\Preprocessor\Expression\Node'))
            ->canOnly()
            ->dependOn()
            ->classes(Selector::inNamespace('FFI\Preprocessor\Expression\Node'))
            ->because('the nodes of the expression are data only');
    }

    public function testParserBuildsNodesOnly(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('FFI\Preprocessor\Expression\Parser'))
            ->canOnly()
            ->dependOn()
            ->classes(
                Selector::inNamespace('FFI\Preprocessor\Expression\Parser'),
                Selector::inNamespace('FFI\Preprocessor\Expression\Node'),
                Selector::inNamespace('FFI\Preprocessor\Expression\Exception'),
                Selector::inNamespace('Phplrt'),
            )
            ->because('the parser turns the text of an expression into its nodes');
    }

    public function testEvaluatorComputesTheNodes(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('FFI\Preprocessor\Expression\Evaluator'))
            ->canOnly()
            ->dependOn()
            ->classes(
                Selector::inNamespace('FFI\Preprocessor\Expression\Evaluator'),
                Selector::inNamespace('FFI\Preprocessor\Expression\Node'),
                Selector::inNamespace('FFI\Preprocessor\Expression\Exception'),
                Selector::classname('FFI\Preprocessor\Exception\InternalException'),
                Selector::classname('Phplrt\Contracts\Source\ReadableInterface'),
            )
            ->because('the evaluator computes the value of the nodes, the text is the place of a failure');
    }

    public function testMathDependsOnNothing(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('FFI\Preprocessor\Expression\Evaluator\Math'))
            ->canOnly()
            ->dependOn()
            ->classes(
                Selector::inNamespace('FFI\Preprocessor\Expression\Evaluator\Math'),
                Selector::classname('FFI\Preprocessor\Exception\InternalException'),
            )
            ->because('the arithmetic only computes the integers of 64 bits');
    }

    public function testExceptionsExtendLibraryException(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('FFI\Preprocessor\Expression\Exception'))
            ->canOnly()
            ->dependOn()
            ->classes(
                Selector::inNamespace('FFI\Preprocessor\Expression\Exception'),
                Selector::classname('FFI\Preprocessor\Exception\ParsingException'),
            )
            ->because('a failure of an expression is a failure at a place of its text');
    }
}
