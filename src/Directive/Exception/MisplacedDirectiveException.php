<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Exception;

use FFI\Preprocessor\Exception\RuntimeException;
use Phplrt\Contracts\Source\ReadableInterface;

/**
 * Occurs when a directive stands where it cannot: without the directive it
 * continues or after the one that ends what it belongs to (6.10.2).
 */
final class MisplacedDirectiveException extends RuntimeException
{
    /**
     * @param non-empty-string $directive name of the directive
     * @param non-empty-string $opening name of the directive it cannot stand without
     * @param int<0, max> $offset offset of the first byte of the directive in the source
     */
    public static function becauseOpeningIsMissing(
        ReadableInterface $source,
        int $offset,
        string $directive,
        string $opening,
    ): self {
        return new self(\sprintf('#%s without #%s', $directive, $opening), $source, $offset);
    }

    /**
     * @param non-empty-string $directive name of the directive
     * @param non-empty-string $ending name of the directive it cannot stand after
     * @param int<0, max> $offset offset of the first byte of the directive in the source
     */
    public static function becauseEndingIsPassed(
        ReadableInterface $source,
        int $offset,
        string $directive,
        string $ending,
    ): self {
        return new self(\sprintf('#%s after #%s', $directive, $ending), $source, $offset);
    }
}
