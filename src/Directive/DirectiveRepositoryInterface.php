<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive;

/**
 * Directive handlers by the names of the directives.
 */
interface DirectiveRepositoryInterface
{
    /**
     * Finds the handler of the directive name, {@see null} in case of there is
     * none.
     */
    public function find(string $name): ?DirectiveHandlerInterface;
}
