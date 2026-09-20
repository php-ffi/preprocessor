<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro;

use FFI\Preprocessor\Macro\Exception\InvalidMacroException;
use FFI\Preprocessor\Macro\FunctionLikeMacro\ClosureFunctionLikeMacro;
use FFI\Preprocessor\Macro\ObjectLikeMacro\ClosureObjectLikeMacro;
use FFI\Preprocessor\Macro\ObjectLikeMacro\ContextObjectLikeMacro;
use FFI\Preprocessor\Macro\ObjectLikeMacro\StringObjectLikeMacro;
use FFI\Preprocessor\Runtime\SystemClock;
use Psr\Clock\ClockInterface;

/**
 * Macros defined by the configuration or by preprocessing. A name not
 * defined here is looked up in the previous repository, if there is one,
 * unless it has been undefined here: undefining a macro of the previous
 * repository hides it without changing that repository.
 */
final class MacroRepository implements MacroRepositoryInterface
{
    /**
     * The spelling of an identifier a macro can be named by (6.4.3)
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

    /**
     * Creates the repository with the macros every implementation defines.
     *
     * @param ClockInterface|null $clock tells the date and the time of translation, the current ones by default
     */
    public static function createDefault(?ClockInterface $clock = null): self
    {
        return self::createWithDefault(null, $clock);
    }

    /**
     * Creates the repository over the previous one with the macros every
     * implementation defines, those the previous repository has already
     * defined are left as they are.
     *
     * @param ClockInterface|null $clock tells the date and the time of translation, the current ones by default
     * @throws InvalidMacroException
     */
    public static function createWithDefault(?MacroRepositoryInterface $prev, ?ClockInterface $clock = null): self
    {
        $repository = new self($prev);

        foreach (self::createPredefinedMacros($clock ?? new SystemClock()) as $name => $macro) {
            if ($repository->find($name) === null) {
                $repository->defineByMacro($name, $macro);
            }
        }

        return $repository;
    }

    /**
     * Returns the mandatory macros that do not depend on the file being
     * preprocessed (6.10.10.2), by their names.
     *
     * @return array<non-empty-string, MacroInterface>
     */
    private static function createPredefinedMacros(ClockInterface $clock): array
    {
        return [
            '__DATE__' => new ClosureObjectLikeMacro(static function () use ($clock): string {
                $now = $clock->now();

                return \sprintf('"%s %2d %d"', $now->format('M'), (int) $now->format('j'), (int) $now->format('Y'));
            }),
            '__TIME__' => new ClosureObjectLikeMacro(
                static fn(): string => \sprintf('"%s"', $clock->now()->format('H:i:s')),
            ),
            '__FILE__' => new ContextObjectLikeMacro(
                static fn(MacroContext $context): string => '"' . \addcslashes($context->file->pathname, '"\\') . '"',
            ),
            '__LINE__' => new ContextObjectLikeMacro(
                static fn(MacroContext $context): string => (string) $context->getLine(),
            ),
            '__STDC__' => new StringObjectLikeMacro('1'),
            '__STDC_VERSION__' => new StringObjectLikeMacro('202311L'),
            '__STDC_HOSTED__' => new StringObjectLikeMacro('1'),
            '__STDC_UTF_16__' => new StringObjectLikeMacro('1'),
            '__STDC_UTF_32__' => new StringObjectLikeMacro('1'),
            '__STDC_EMBED_NOT_FOUND__' => new StringObjectLikeMacro('0'),
            '__STDC_EMBED_FOUND__' => new StringObjectLikeMacro('1'),
            '__STDC_EMBED_EMPTY__' => new StringObjectLikeMacro('2'),
        ];
    }

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
     * A helper method for registering a macro: the kind of the macro depends
     * on what `$replacement` is. An existing macro of the same name is
     * replaced.
     *
     * - If no `$replacement` is passed, registers a macro with the value `1`.
     *   ```php
     *   // It's the same as if `#define NAME 1` was written
     *   $macros->define('NAME');
     *
     *   // or the same thing as
     *   $macros->define('NAME', '1');
     *   // or the same thing as
     *   $macros->defineByString('NAME', '1');
     *   ```
     * - If `$replacement` is passed as a string, registers an object-like macro
     *   the name of which is replaced by this string.
     *   ```php
     *   // #define VERSION 2
     *   $macros->define('VERSION', '2');
     *
     *   // #define EMPTY
     *   $macros->define('EMPTY', '');
     *   ```
     * - If `$replacement` is passed as a closure without parameters, registers
     *   an object-like macro, the name of which is replaced by the value
     *   the closure returns each time the name is replaced.
     *   ```php
     *   // `NOW` is replaced by the actual time of each replacement
     *   $macros->define('NOW', static fn(): string => (string) \time());
     *   ```
     * - If `$replacement` is passed as a closure with parameters, registers
     *   a function-like macro, the invocation of which is replaced by the value
     *   the closure returns. The closure gets the arguments as they are
     *   written in the invocation, a variadic parameter takes the rest of them.
     *   ```php
     *   // `MAX(1, 2)` is replaced by `((1) > (2) ? (1) : (2))`
     *   $macros->define('MAX', static fn(string $a, string $b): string => "(($a) > ($b) ? ($a) : ($b))");
     *   ```
     * - If `$replacement` is passed as a {@see MacroInterface}, registers
     *   this macro as it is.
     *   ```php
     *   $macros->define('SIZE', new StringObjectLikeMacro('4096'));
     *   ```
     * - Any other value (`int`, `bool`, `null`, an object) is not converted
     *   to a string and throws an exception.
     *   ```php
     *   $macros->define('SIZE', 4096); // InvalidMacroException
     *   ```
     *
     * @param non-empty-string $name
     * @throws InvalidMacroException in case of the name is not an identifier or the replacement is of another type
     */
    public function define(string $name, mixed $replacement = '1'): void
    {
        switch (true) {
            case \is_string($replacement):
                $this->defineByString($name, $replacement);
                break;

            case $replacement instanceof \Closure:
                $this->defineByClosure($name, $replacement);
                break;

            case $replacement instanceof MacroInterface:
                $this->defineByMacro($name, $replacement);
                break;

            default:
                throw InvalidMacroException::becauseReplacementIsInvalid(\get_debug_type($replacement));
        }
    }

    /**
     * Registers an object-like macro the name of which is replaced by the
     * given text. The result is a {@see StringObjectLikeMacro}.
     *
     * ```php
     * // #define BUFFER_SIZE 4096
     * $macros->defineByString('BUFFER_SIZE', '4096');
     *
     * // #define GREETING "hello"
     * $macros->defineByString('GREETING', '"hello"');
     *
     * // #define EMPTY
     * $macros->defineByString('EMPTY', '');
     * ```
     *
     * The text is a replacement list as it is written after the name in
     * `#define`, the macros it contains are replaced after the replacement.
     *
     * @param non-empty-string $name
     * @throws InvalidMacroException in case of the name is not an identifier
     */
    public function defineByString(string $name, string $replacement): void
    {
        $this->defineByMacro($name, new StringObjectLikeMacro($replacement));
    }

    /**
     * Registers a macro implemented by the closure, the number of the
     * closure parameters selects the kind of the macro. The closure result
     * is converted to a string, `null` is an empty replacement.
     *
     * - A closure without parameters gives an {@see ClosureObjectLikeMacro}:
     *   the name is replaced by the result, the closure is called each time
     *   the name is replaced.
     *   ```php
     *   $macros->defineByClosure('NOW', static fn(): string => (string) \time());
     *
     *   // NOW + NOW   =>   1700000000 + 1700000001
     *   ```
     * - A closure with parameters gives a {@see ClosureFunctionLikeMacro}:
     *   the name followed by `(` is replaced by the result, the closure gets
     *   the arguments as they are written in the invocation. The name
     *   without `(` is left as it is.
     *   ```php
     *   $macros->defineByClosure('ADD', static fn(string $a, string $b): string => "($a + $b)");
     *
     *   // ADD(1, 2)   =>   (1 + 2)
     *   // ADD         =>   ADD
     *   ```
     * - A variadic closure gives a function-like macro that takes any number
     *   of the arguments.
     *   ```php
     *   $macros->defineByClosure('SUM', static fn(string ...$args): string => \implode(' + ', $args));
     *
     *   // SUM(1, 2, 3)   =>   1 + 2 + 3
     *   ```
     *
     * A function-like macro without parameters (`NAME()`) is not defined by
     * a closure, use {@see TemplateFunctionLikeMacro} with {@see defineByMacro()}.
     *
     * @param non-empty-string $name
     * @param \Closure(string ...): (scalar|\Stringable|null) $replacement
     * @throws InvalidMacroException in case of the name is not an identifier
     */
    public function defineByClosure(string $name, \Closure $replacement): void
    {
        $macro = ClosureObjectLikeMacro::isSupported($replacement)
            ? ClosureObjectLikeMacro::createFromClosure($replacement)
            : ClosureFunctionLikeMacro::createFromClosure($replacement)
        ;

        $this->defineByMacro($name, $macro);
    }

    /**
     * Registers the given macro as it is.
     *
     * ```php
     * // #define MAX(a, b) ((a) > (b) ? (a) : (b))
     * $macros->defineByMacro('MAX', new TemplateFunctionLikeMacro('((%1$s) > (%2$s) ? (%1$s) : (%2$s))', 2));
     *
     * // A macro of your own class implementing MacroInterface
     * $macros->defineByMacro('COUNTER', new CounterMacro());
     * ```
     *
     * An existing macro of the same name is replaced, a macro of the previous
     * repository is hidden by the new one without being changed.
     *
     * @param non-empty-string $name
     * @throws InvalidMacroException in case of the name is not an identifier
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
