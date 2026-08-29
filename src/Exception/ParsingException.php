<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Exception;

use Phplrt\Contracts\Source\Exception\SourceExceptionInterface;
use Phplrt\Contracts\Source\FileInterface;
use Phplrt\Contracts\Source\ReadableInterface;
use Phplrt\Exception\ErrorPrinter;
use Phplrt\Source\StringSource;
use Phplrt\Source\VirtualSource;

/**
 * Base of the failures caused by the source itself, i.e. the ones that
 * point to a particular place in it.
 */
abstract class ParsingException extends PreprocessorException
{
    protected function __construct(
        string $message,
        /**
         * The source the failure occurred in
         */
        public readonly ReadableInterface $source,
        /**
         * Offset of the first byte of the failed fragment in the source
         *
         * @var int<0, max>
         */
        public readonly int $offset,
        /**
         * Size of the failed fragment in bytes, `0` in case of it is not known
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
            $contents = $this->source->content;
            // @phpstan-ignore-next-line the contract of the property tells it throws in its text only
        } catch (SourceExceptionInterface) {
            return parent::__toString();
        }

        return (new ErrorPrinter())
            ->print($this)
            ->withSource($this->createSnippetSource($contents))
            ->withMessage($this->message)
            ->withClass(static::class)
            ->withInterval($this->offset, $this->length)
            ->render();
    }

    /**
     * Creates the source of the snippet, with the pathname of a file.
     */
    private function createSnippetSource(string $contents): ReadableInterface
    {
        if ($this->source instanceof FileInterface) {
            return VirtualSource::createFromString($this->source->pathname, $contents);
        }

        return new StringSource($contents);
    }
}
