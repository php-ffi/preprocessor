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

    public function testDiagnosticsKnowNothingOfThePreprocessor(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('FFI\Preprocessor\Diagnostic'))
            ->canOnly()
            ->dependOn()
            ->classes(
                Selector::inNamespace('FFI\Preprocessor\Diagnostic'),
                Selector::classname('FFI\Preprocessor\Exception\InvalidStreamException'),
                Selector::inNamespace('Phplrt\Contracts\Source'),
                Selector::inNamespace('Phplrt\Contracts\Position'),
                Selector::inNamespace('Phplrt\Position'),
                Selector::inNamespace('Psr\Log'),
            )
            ->because('a diagnostic is a message about a place of a file, written to a stream or a logger');
    }

    public function testLexerOnlySplitsTextIntoTokens(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('FFI\Preprocessor\Lexer'))
            ->canOnly()
            ->dependOn()
            ->classes(
                Selector::inNamespace('FFI\Preprocessor\Lexer'),
                Selector::classname('FFI\Preprocessor\Stream\Chunk\TaggedChunk'),
                Selector::inNamespace('Phplrt\Contracts'),
            )
            ->because('the tokens are read from the text of a chunk and know nothing of the macros');
    }

    public function testMacrosKnowNothingOfTheDirectives(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('FFI\Preprocessor\Macro'))
            ->canOnly()
            ->dependOn()
            ->classes(
                Selector::inNamespace('FFI\Preprocessor\Macro'),
                Selector::inNamespace('FFI\Preprocessor\Lexer'),
                Selector::inNamespace('FFI\Preprocessor\Stream'),
                Selector::inNamespace('FFI\Preprocessor\Diagnostic'),
                Selector::inNamespace('FFI\Preprocessor\Exception'),
                Selector::inNamespace('Phplrt\Contracts'),
                Selector::inNamespace('Phplrt\Source'),
            )
            ->because('the macros are defined and replaced the same way whatever directive uses them');
    }

    public function testEmbedParametersAreOnlyTokens(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('FFI\Preprocessor\Embed'))
            ->canOnly()
            ->dependOn()
            ->classes(
                Selector::inNamespace('FFI\Preprocessor\Embed'),
                Selector::inNamespace('FFI\Preprocessor\Lexer'),
                Selector::inNamespace('FFI\Preprocessor\Exception'),
            )
            ->because('the parameters of #embed and __has_embed are read the same way');
    }

    public function testConditionKnowsNothingOfTheDirectives(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('FFI\Preprocessor\Condition'))
            ->canOnly()
            ->dependOn()
            ->classes(
                Selector::inNamespace('FFI\Preprocessor\Condition'),
                Selector::inNamespace('FFI\Preprocessor\Macro'),
                Selector::inNamespace('FFI\Preprocessor\Lexer'),
                Selector::inNamespace('FFI\Preprocessor\Expression'),
                Selector::inNamespace('FFI\Preprocessor\Embed'),
                Selector::classname('FFI\Preprocessor\Stream\Chunk\TaggedChunk'),
                Selector::inNamespace('FFI\Preprocessor\Exception'),
                Selector::inNamespace('Phplrt\Contracts'),
                Selector::classname('Phplrt\Source\StringSource'),
            )
            ->because('the conditions are evaluated through the context, whatever runs the directives');
    }

    public function testDirectivesKnowNothingOfTheExecutor(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('FFI\Preprocessor\Directive'))
            ->canOnly()
            ->dependOn()
            ->classes(
                Selector::inNamespace('FFI\Preprocessor\Directive'),
                Selector::inNamespace('FFI\Preprocessor\Condition'),
                Selector::inNamespace('FFI\Preprocessor\Macro'),
                Selector::inNamespace('FFI\Preprocessor\Lexer'),
                Selector::inNamespace('FFI\Preprocessor\Embed'),
                Selector::inNamespace('FFI\Preprocessor\Diagnostic'),
                Selector::inNamespace('FFI\Preprocessor\Exception'),
                Selector::inNamespace('FFI\Preprocessor\Expression\Evaluator'),
                Selector::classname('FFI\Preprocessor\Stream\Chunk\TaggedChunk'),
                Selector::classname('FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceDirective'),
                Selector::classname('FFI\Preprocessor\Stream\Chunk\TaggedChunk\GeneratedChunk'),
                Selector::inNamespace('Phplrt\Contracts'),
            )
            ->because('a directive handler uses nothing but its context, as a handler of a user does');
    }

    public function testPresetsConfigureThroughThePublicApi(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('FFI\Preprocessor\Preset'))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('FFI\Preprocessor\Stream\Executor'),
                Selector::classname('FFI\Preprocessor\Macro\MacroExpander'),
                Selector::inNamespace('FFI\Preprocessor\Macro\MacroExpander'),
                Selector::classname('FFI\Preprocessor\Condition\ConditionEvaluator'),
            )
            ->because('a preset configures the preprocessor the way a user does');
    }

    public function testCoreKnowsNothingOfThePresets(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('FFI\Preprocessor'))
            ->excluding(
                Selector::inNamespace('FFI\Preprocessor\Preset'),
                Selector::classname('FFI\Preprocessor\Preprocessor'),
                Selector::inNamespace('FFI\Preprocessor\Tests'),
            )
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace('FFI\Preprocessor\Preset'))
            ->because('the extensions are added by the presets, the core has none');
    }
}
