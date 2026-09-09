<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro;

/**
 * Macros currently defined, by their names (6.10.5.5).
 */
interface MacroRepositoryInterface
{
    /**
     * Finds the macro of the name, {@see null} in case of the name is not
     * defined as a macro.
     */
    public function find(string $name): ?MacroInterface;

    /**
     * Returns every macro currently defined.
     *
     * @return iterable<non-empty-string, MacroInterface>
     */
    public function getAll(): iterable;
}
