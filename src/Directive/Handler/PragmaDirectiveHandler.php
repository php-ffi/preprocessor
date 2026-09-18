<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler;

use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\DirectiveHandlerInterface;
use FFI\Preprocessor\Directive\Handler\PragmaDirectiveHandler\OncePragmaProcessor;
use FFI\Preprocessor\Directive\Handler\PragmaDirectiveHandler\PragmaProcessorInterface;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\DirectiveChunk;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\GeneratedChunk;

/**
 * `#pragma` passes an implementation-defined instruction (6.10.8).
 *
 * The first processor that supports it executes it,
 * a pragma no processor supports is passed to the output.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.8 Pragma directive
 */
final class PragmaDirectiveHandler implements DirectiveHandlerInterface
{
    public function __construct(
        /**
         * The processors of the pragmas in the order they are asked
         *
         * @var list<PragmaProcessorInterface>
         */
        private readonly array $processors = [],
    ) {}

    /**
     * Creates the handler with the processors of the pragmas the preprocessor executes.
     */
    public static function createDefault(): self
    {
        return new self([
            new OncePragmaProcessor(),
        ]);
    }

    public function handle(DirectiveChunk $directive, DirectiveContext $context): iterable
    {
        $body = \trim($directive->body);

        foreach ($this->processors as $processor) {
            if ($processor->supports($body)) {
                return $processor->apply($directive, $context);
            }
        }

        return [self::createFallbackChunk($directive, $body)];
    }

    private static function createFallbackChunk(DirectiveChunk $directive, string $body): GeneratedChunk
    {
        $content = "#pragma\n";

        if ($body !== '') {
            $content = "#pragma $body\n";
        }

        return new GeneratedChunk($content, $directive->source, $directive->getPhysicalOffset(0));
    }
}
