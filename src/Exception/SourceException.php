<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Exception;

use Phplrt\Contracts\Source\Exception\SourceExceptionInterface;
use Phplrt\Contracts\Source\FileInterface;
use Phplrt\Exception\ErrorPrinter;
use Phplrt\Source\VirtualSource;

/**
 * Base of the failures caused by the preprocessed source itself, i.e. the
 * ones that point to a particular place in one of its files.
 */
abstract class SourceException extends PreprocessorException
{
    protected function __construct(
        string $message,
        /**
         * The file the failure occurred in, under its virtual pathname
         */
        public readonly FileInterface $source,
        /**
         * Offset of the first byte of the failed fragment in the file
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
    ) {
        parent::__construct($message);
    }

    /**
     * Prints the failure along with the snippet of the file that holds it,
     * or as any other exception in case of the file cannot be read again.
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
            ->withSource(VirtualSource::createFromString($this->source->pathname, $contents))
            ->withMessage($this->message)
            ->withClass(static::class)
            ->withInterval($this->offset, $this->length)
            ->render();
    }
}
