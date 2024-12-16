<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Runtime;

/**
 * This class is responsible for a set of preprocessor states.
 *
 * If all elements of the stack contain {@see true}, then the result of the
 * preprocessor should be sent to the output stream. Otherwise, if one of the
 * states contains {@see false}, then the output is not available.
 *
 * Each boolean value in stack is responsible for its own branch of
 * results of if/else expression, for example:
 *
 * <code>
 *  #if 1       // IF: $stack->push(true)
 *    A         //  >> Stack contains [true]: Output available
 *  #else       // ELSE: $stack->push(! $stack->pop());
 *    B         //  >> Stack contains [false]: Output NOT available
 *  #endif      // END: $stack->pop();
 * </code>
 *
 * In a more complex example, it will look like this:
 *
 * <code>
 *  #if 1       // IF: $stack->push(true)
 *    A         //  >> Stack contains [true]: Output available
 *    #if 0     // IF: $stack->push(false)
 *      B       //  >> Stack contains [true, false]: Output NOT available
 *    #else     // ELSE: $stack->push(! $stack->pop());
 *      C       //  >> Stack contains [true, true]: Output available
 *    #endif    // END: $stack->pop();
 *  #else       // ELSE: $stack->push(! $stack->pop());
 *    D         //  >> Stack contains [false]: Output NOT available
 *  #endif      // END: $stack->pop();
 * </code>
 *
 * @internal
 */
final class OutputStack implements \Countable
{
    private bool $state = true;

    /**
     * @var bool[]
     */
    private array $stack = [];

    /**
     * @var bool[]
     */
    private array $completed = [];

    public function isEnabled(): bool
    {
        return $this->state;
    }

    public function push(bool $state): void
    {
        $this->stack[] = $state;
        $this->completed[] = $state;

        if ($this->state && !$state) {
            $this->state = false;
        }
    }

    public function complete(): void
    {
        $this->assertSize();

        $this->pop();
        $this->push(true);
    }

    public function update(bool $status, bool $completed = false): void
    {
        \array_pop($this->stack);
        \array_pop($this->completed);

        $this->stack[] = $status;
        $this->completed[] = $completed;

        $this->refresh();
    }

    public function isCompleted(): bool
    {
        return \end($this->completed);
    }

    private function assertSize(): void
    {
        if (\count($this->stack) <= 0) {
            throw new \LogicException('Output stack is an empty');
        }
    }

    public function inverse(): void
    {
        $this->assertSize();

        try {
            $this->stack[] = !\array_pop($this->stack);
        } finally {
            $this->refresh();
        }
    }

    public function pop(): bool
    {
        $this->assertSize();

        try {
            \array_pop($this->completed);

            return \array_pop($this->stack);
        } finally {
            $this->refresh();
        }
    }

    private function refresh(): bool
    {
        foreach ($this->stack as $state) {
            if (!$state) {
                return $this->state = false;
            }
        }

        return $this->state = true;
    }

    public function count(): int
    {
        return \count($this->stack);
    }
}
