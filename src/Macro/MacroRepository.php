<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro;

use FFI\Preprocessor\Macro\Exception\InvalidMacroException;
use FFI\Preprocessor\Macro\FunctionLikeMacro\ClosureFunctionLikeMacro;

/**
 * Macros defined by the configuration or by preprocessing. A name not
 * defined here is looked up in the previous repository, if there is one,
 * unless it has been undefined here: undefining a macro of the previous
 * repository hides it without changing that repository.
 */
final class MacroRepository implements MacroRepositoryInterface
{
    /**
     * The name of a macro with an optional parameter list, as `-D` takes it
     */
    private const NAME_PATTERN = '/\A[A-Za-z_\x80-\xFF][0-9A-Za-z_\x80-\xFF]*+\z/';

    /**
     * Macros defined here by their names
     *
     * @var array<non-empty-string, MacroInterface>
     */
    private array $macros = [];

    /**
     * Names undefined here, the macros of the previous repository hidden by them
     *
     * @var array<string, true>
     */
    private array $removed = [];

    public function __construct(
        /**
         * The repository searched for the names not defined here
         */
        private readonly ?MacroRepositoryInterface $prev = null,
    ) {}

    public function find(string $name): ?MacroInterface
    {
        if (isset($this->macros[$name])) {
            return $this->macros[$name];
        }

        if (isset($this->removed[$name])) {
            return null;
        }

        return $this->prev?->find($name);
    }

    public function getAll(): iterable
    {
        if ($this->prev !== null) {
            foreach ($this->prev->getAll() as $name => $macro) {
                if (!isset($this->macros[$name]) && !isset($this->removed[$name])) {
                    yield $name => $macro;
                }
            }
        }

        yield from $this->macros;
    }

    /**
     * @phpstan-assert non-empty-string $name
     *
     * @throws InvalidMacroException in case of the name is not an identifier
     */
    private function assertValidMacroName(string $name): void
    {
        if (\preg_match(self::NAME_PATTERN, $name) === 1) {
            return;
        }

        throw InvalidMacroException::becauseNameIsInvalid($name);
    }

    /**
     * @param non-empty-string $name
     * @throws \Throwable in case of internal error occurs
     */
    public function define(string $name, mixed $replacement = '1'): void
    {
        $this->defineByString($name, $replacement);
    }

    /**
     * @param non-empty-string $name
     * @throws \Throwable in case of internal error occurs
     */
    public function defineByString(string $name, string $replacement): void
    {
        $this->defineByMacro($name, new ObjectLikeMacro($replacement));
    }

    /**
     * @param non-empty-string $name
     * @param \Closure(string ...): string $replacement
     * @throws \Throwable in case of internal error occurs
     */
    public function defineByClosure(string $name, \Closure $replacement): void
    {
        $this->defineByMacro($name, ClosureFunctionLikeMacro::createFromClosure($replacement));
    }

    /**
     * @param non-empty-string $name
     * @throws \Throwable in case of internal error occurs
     */
    public function defineByMacro(string $name, MacroInterface $macro): void
    {
        $this->assertValidMacroName($name);

        $this->macros[$name] = $macro;
    }

    /**
     * Undefines the macro of the name, nothing happens in case of it is not
     * defined.
     *
     * @param non-empty-string $name
     */
    public function remove(string $name): void
    {
        unset($this->macros[$name]);

        if ($this->prev !== null) {
            $this->removed[$name] = true;
        }
    }
}
