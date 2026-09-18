<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler\PragmaDirectiveHandler;

use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\DirectiveChunk;

/**
 * Executes one implementation-defined `#pragma` (6.10.8). A pragma no
 * processor supports is passed to the output.
 */
interface PragmaProcessorInterface
{
    /**
     * Tells whether the processor executes the pragma of the given body.
     */
    public function supports(string $body): bool;

    /**
     * Returns the chunks the pragma is replaced with, empty in case of the pragma leaves nothing in the output.
     *
     * @return iterable<array-key, TaggedChunk>
     */
    public function apply(DirectiveChunk $directive, DirectiveContext $context): iterable;
}
