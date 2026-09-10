<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler;

use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\Exception\InvalidDirectiveBodyException;
use FFI\Preprocessor\Directive\OutputIndependentDirectiveHandlerInterface;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceDirective;

/**
 * `#ifdef` and `#ifndef` open a conditional section by whether a macro is defined (6.10.2).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.2 Conditional inclusion
 */
final class IfDefinedDirectiveHandler extends DirectiveHandler implements OutputIndependentDirectiveHandlerInterface
{
    private const NEGATIVE_DIRECTIVE = 'ifndef';

    /**
     * @throws InvalidDirectiveBodyException in case of the macro name is not an identifier
     */
    public function handle(SourceDirective $directive, DirectiveContext $context): iterable
    {
        $output = $context->output;

        if (!$output->isEnabled()) {
            $output->push(false);

            return [];
        }

        $name = $this->getMacroName($directive);
        $this->reportExtraTokens($context, $directive, \strlen($name));

        $isDefined = $context->macros->find($name) !== null;

        if ($directive->name === self::NEGATIVE_DIRECTIVE) {
            $isDefined = !$isDefined;
        }

        $output->push($isDefined);

        return [];
    }
}
