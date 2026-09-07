<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Exception;

use FFI\Preprocessor\Exception\ParsingException;
use Phplrt\Contracts\Source\ReadableInterface;

/**
 * Occurs when a conditional directive stands where it cannot, such as `#else`
 * without `#if` (6.10.2).
 */
final class MisplacedDirectiveException extends ParsingException
{
    /**
     * @param non-empty-string $name name of the directive, such as `else`
     * @param int<0, max> $offset offset of the first byte of the directive in the source
     */
    public static function becauseSectionIsNotOpen(ReadableInterface $source, int $offset, string $name): self
    {
        return new self(\sprintf('#%s without #if', $name), $source, $offset);
    }

    /**
     * @param non-empty-string $name name of the directive, such as `elifdef`
     * @param int<0, max> $offset offset of the first byte of the directive in the source
     */
    public static function becauseSectionHasElse(ReadableInterface $source, int $offset, string $name): self
    {
        return new self(\sprintf('#%s after #else', $name), $source, $offset);
    }
}
