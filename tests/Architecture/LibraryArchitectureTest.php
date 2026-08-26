<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class LibraryArchitectureTest
{
    public function testLibraryDoesNotDependOnTheDevelopmentPackages(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('FFI\Preprocessor'))
            ->excluding(Selector::inNamespace('FFI\Preprocessor\Tests'))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('Phplrt\Compiler'),
                Selector::inNamespace('PHPUnit'),
                Selector::inNamespace('PHPat'),
                Selector::inNamespace('PhpCsFixer'),
                Selector::inNamespace('Symfony\Component\VarDumper'),
            )
            ->because('the grammar is compiled at development time, the library needs the runtime packages only');
    }

    public function testReaderKnowsNothingOfWhatTheDirectivesDo(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('FFI\Preprocessor\Stream'))
            ->excluding(Selector::inNamespace('FFI\Preprocessor\Stream\Executor'))
            ->canOnly()
            ->dependOn()
            ->classes(
                Selector::inNamespace('FFI\Preprocessor\Stream'),
                Selector::inNamespace('FFI\Preprocessor\Exception'),
                Selector::inNamespace('Phplrt\Contracts'),
                Selector::inNamespace('Phplrt\Source'),
                Selector::inNamespace('Phplrt\Lexer'),
                Selector::inNamespace('Phplrt\Parser'),
            )
            ->because('the reader only splits a file into the source text and the directives');
    }
}
