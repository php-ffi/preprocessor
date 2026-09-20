<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro;

use Phplrt\Contracts\Source\FileInterface;

/**
 * The place a macro is replaced at: what `__FILE__` and `__LINE__` tell (6.10.10.2).
 */
final class MacroContext
{
    public function __construct(
        /**
         * The file the name of the macro is written in
         */
        public readonly FileInterface $file,
        /**
         * Offset of the name of the macro in the file
         *
         * @var int<0, max>
         */
        public readonly int $offset,
    ) {}

    /**
     * Returns the number of the line of the file the name of the macro is written on, from 1.
     *
     * @return int<1, max>
     */
    public function getLine(): int
    {
        return \substr_count($this->file->content, "\n", 0, $this->offset) + 1;
    }
}
