<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Exception;

use Phplrt\Contracts\Source\FileInterface;
use Phplrt\Contracts\Source\ReadableInterface;
use Phplrt\Exception\ErrorPrinter;
use Phplrt\Source\StringSource;
use Phplrt\Source\VirtualSource;

/**
 * Base of the failures that point to a particular place in a source.
 */
abstract class RuntimeException extends PreprocessorException
{
    protected function __construct(
        string $message,
        /**
         * The source of the failure occurred in
         */
        public readonly ReadableInterface $source,
        /**
         * Offset of the first byte of the failed fragment in the source
         *
         * @var int<0, max>
         */
        public readonly int $offset,
        /**
         * The size of the failed fragment in bytes, `0` in case of it is not known
         *
         * @var int<0, max>
         */
        public readonly int $length = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Prints the failure along with the snippet of the source that holds it,
     * or as any other exception in case of the source cannot be read again.
     */
    public function __toString(): string
    {
        try {
            $source = $this->createSnippetSource($this->source);
        } catch (\Throwable) {
            return parent::__toString();
        }

        return (new ErrorPrinter())
            ->print($this)
            ->withSource($source)
            ->withMessage($this->message)
            ->withClass(static::class)
            ->withInterval($this->offset, $this->length)
            ->render();
    }

    /**
     * Creates the source of the snippet, with the pathname of a file.
     *
     * @throws \Throwable in case of internal error occurs
     */
    private function createSnippetSource(ReadableInterface $source): ReadableInterface
    {
        $contents = $source->content;

        if ($this->source instanceof FileInterface) {
            return VirtualSource::createFromString($this->source->pathname, $contents);
        }

        return new StringSource($contents);
    }
}
