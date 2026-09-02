<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive;

use FFI\Preprocessor\Directive\Exception\InvalidDirectiveNameException;

/**
 * Directive handlers that are added, replaced and removed by the
 * configuration and the presets.
 */
interface MutableDirectiveRepositoryInterface extends DirectiveRepositoryInterface
{
    /**
     * Adds the handler of a directive name no handler is added for yet.
     *
     * @throws InvalidDirectiveNameException in case of the name is not an identifier or has a handler
     */
    public function add(string $name, DirectiveHandlerInterface $handler): void;

    /**
     * Sets the handler of a directive name, replacing the one added before.
     *
     * @throws InvalidDirectiveNameException in case of the name is not an identifier
     */
    public function replace(string $name, DirectiveHandlerInterface $handler): void;

    /**
     * Removes the handler of the directive name, nothing happens in case of
     * there is none.
     */
    public function remove(string $name): void;
}
