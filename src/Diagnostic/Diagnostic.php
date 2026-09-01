<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Diagnostic;

use FFI\Preprocessor\Diagnostic\Diagnostic\Severity;
use Phplrt\Contracts\Position\PositionInterface;
use Phplrt\Contracts\Source\Exception\SourceExceptionInterface;
use Phplrt\Contracts\Source\FileInterface;
use Phplrt\Position\PositionFactory;

/**
 * Message about a place of the preprocessed source that does not stop the
 * preprocessing, such as the text of `#warning` (6.10.7).
 */
final class Diagnostic
{
    public function __construct(
        /**
         * How much the message matters
         */
        public readonly Severity $severity,
        /**
         * Text of the message
         */
        public readonly string $message,
        /**
         * The file the message is about, under its virtual pathname
         */
        public readonly FileInterface $source,
        /**
         * Offset of the first byte of the fragment in the file
         *
         * @var int<0, max>
         */
        public readonly int $offset,
        /**
         * Size of the fragment in bytes, `0` in case of it is not known
         *
         * @var int<0, max>
         */
        public readonly int $length = 0,
    ) {}

    /**
     * Finds the line and the column of the fragment in the file.
     *
     * @return PositionInterface|null the position, {@see null} in case of
     *         the file cannot be read again
     */
    public function findPosition(): ?PositionInterface
    {
        try {
            return (new PositionFactory())->createFromOffset($this->source, $this->offset);
        } catch (SourceExceptionInterface) {
            return null;
        }
    }
}
