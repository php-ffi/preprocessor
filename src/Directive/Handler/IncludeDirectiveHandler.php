<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler;

use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\Exception\InvalidDirectiveBodyException;
use FFI\Preprocessor\Macro\Exception\MacroInvocationException;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\DirectiveChunk;

/**
 * `#include` includes a header or a source file (6.10.3).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.3 Source file inclusion
 */
final class IncludeDirectiveHandler extends DirectiveHandler
{
    /**
     * @throws InvalidDirectiveBodyException in case of the body is not a header name, nor is replaced by one
     * @throws MacroInvocationException in case of an invocation of a macro in the body is malformed
     */
    public function handle(DirectiveChunk $directive, DirectiveContext $context): iterable
    {
        $name = $this->getHeaderName($directive, $context);
        $this->reportExtraTokens($context, $directive, $name->length);

        $file = $this->findFile($context, $directive, $name);

        if ($file === null) {
            $this->reportMissingFile($context, $directive, $name);

            return [];
        }

        return $context->executor->execute($file);
    }
}
