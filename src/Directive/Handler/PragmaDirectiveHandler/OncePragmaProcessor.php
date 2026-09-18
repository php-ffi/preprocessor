<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler\PragmaDirectiveHandler;

use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\DirectiveChunk;

/**
 * `#pragma once`: the file is included once and skipped each next time. The
 * pragma itself leaves nothing in the output.
 */
final class OncePragmaProcessor implements PragmaProcessorInterface
{
    public function supports(string $body): bool
    {
        return $body === 'once';
    }

    public function apply(DirectiveChunk $directive, DirectiveContext $context): iterable
    {
        $context->executor->skip($directive->source);

        return [];
    }
}
