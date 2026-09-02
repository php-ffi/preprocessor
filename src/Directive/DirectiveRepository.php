<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive;

use FFI\Preprocessor\Directive\Exception\InvalidDirectiveNameException;

/**
 * Directive handlers of the configuration by the names of the directives.
 */
final class DirectiveRepository implements MutableDirectiveRepositoryInterface
{
    /**
     * An identifier, the form of a directive name
     */
    private const NAME_PATTERN = '/\A[A-Za-z_\x80-\xFF][0-9A-Za-z_\x80-\xFF]*\z/';

    /**
     * @var array<non-empty-string, DirectiveHandlerInterface>
     */
    private array $handlers = [];

    public function find(string $name): ?DirectiveHandlerInterface
    {
        return $this->handlers[$name] ?? null;
    }

    public function add(string $name, DirectiveHandlerInterface $handler): void
    {
        if (isset($this->handlers[$name])) {
            throw InvalidDirectiveNameException::becauseNameIsTaken($name);
        }

        $this->replace($name, $handler);
    }

    public function replace(string $name, DirectiveHandlerInterface $handler): void
    {
        if (\preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw InvalidDirectiveNameException::becauseNameIsNotIdentifier($name);
        }

        $this->handlers[$name] = $handler;
    }

    public function remove(string $name): void
    {
        unset($this->handlers[$name]);
    }
}
