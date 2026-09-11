<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro\Executor\MacroExpander;

use Phplrt\Contracts\Lexer\TokenInterface;

/**
 * The tokens that are yet to be rescanned and the macros that must not be
 * expanded in them, the state of one expansion.
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Macro\Executor
 */
final class Expansion
{
    /**
     * The tokens left, the next one is the last
     *
     * @var list<TokenInterface|Restoration>
     */
    private array $stack;

    /**
     * @param list<TokenInterface> $tokens
     * @param array<string, int<1, max>> $disabled how many replacements of a macro name are being rescanned
     */
    public function __construct(array $tokens, private array $disabled = [])
    {
        $this->stack = \array_reverse($tokens);
    }

    /**
     * Takes the next token, {@see null} in case of there are none left.
     */
    public function pop(): ?TokenInterface
    {
        while ($this->stack !== []) {
            $next = \array_pop($this->stack);

            if (!$next instanceof Restoration) {
                return $next;
            }

            $this->enable($next->name);
        }

        return null;
    }

    /**
     * Puts the tokens back so that the first of them is the next one.
     *
     * @param list<TokenInterface> $tokens
     */
    public function unshift(array $tokens): void
    {
        foreach (\array_reverse($tokens) as $token) {
            $this->stack[] = $token;
        }
    }

    /**
     * Replaces a macro name with the tokens of its replacement, the name
     * is not expanded until they are rescanned.
     *
     * @param list<TokenInterface> $replacement
     */
    public function enter(string $name, array $replacement): void
    {
        $this->disabled[$name] = ($this->disabled[$name] ?? 0) + 1;
        $this->stack[] = new Restoration($name);

        $this->unshift($replacement);
    }

    public function isDisabled(string $name): bool
    {
        return isset($this->disabled[$name]);
    }

    /**
     * @return array<string, int<1, max>>
     */
    public function getDisabled(): array
    {
        return $this->disabled;
    }

    private function enable(string $name): void
    {
        $count = $this->disabled[$name] - 1;

        if ($count > 0) {
            $this->disabled[$name] = $count;

            return;
        }

        unset($this->disabled[$name]);
    }
}
