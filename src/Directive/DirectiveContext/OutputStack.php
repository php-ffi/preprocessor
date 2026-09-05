<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\DirectiveContext;

use FFI\Preprocessor\Directive\Exception\OutputStackException;

/**
 * The open conditional sections of one preprocessed file (6.10.2), one frame
 * per section. The output of the text is enabled while every frame is
 * enabled:
 *
 * ```
 * #if 1       // push(true)    [true]         enabled
 * #if 0       // push(false)   [true, false]  disabled
 * #else       // swap()        [true, true]   enabled
 * #endif      // pop()         [true]         enabled
 * #endif      // pop()         []             enabled
 * ```
 */
final class OutputStack
{
    /**
     * Whether the current branch is enabled, the enabled branches of the
     * outer sections included, the innermost last
     *
     * @var list<bool>
     */
    private array $enabled = [];

    /**
     * Whether a branch of the section has been taken, the innermost section last
     *
     * @var list<bool>
     */
    private array $taken = [];

    /**
     * Whether the last branch of the section has been started, the innermost
     * section last
     *
     * @var list<bool>
     */
    private array $ended = [];

    /**
     * Whether the text and the directives other than the ones that manage the
     * stack are put out.
     */
    public function isEnabled(): bool
    {
        if ($this->enabled === []) {
            return true;
        }

        if (\PHP_VERSION_ID >= 80500) {
            return \array_last($this->enabled);
        }

        return $this->enabled[\count($this->enabled) - 1];
    }

    /**
     * Whether a conditional section is open.
     */
    public function hasSection(): bool
    {
        return $this->enabled !== [];
    }

    /**
     * Whether a branch of the current section has been taken, so the next ones
     * are disabled without evaluating their conditions. Always {@see true}
     * in a section that is in a disabled branch.
     *
     * @throws OutputStackException in case of there is no open section
     */
    public function hasBranchTaken(): bool
    {
        $this->assertSection();

        return $this->taken[\count($this->taken) - 1];
    }

    /**
     * Whether the current section is in its last branch, which no branch
     * follows.
     *
     * @throws OutputStackException in case of there is no open section
     */
    public function hasLastBranch(): bool
    {
        $this->assertSection();

        return $this->ended[\count($this->ended) - 1];
    }

    /**
     * Opens a section with its first branch, enabled in case of the condition
     * is true and the output is enabled.
     */
    public function push(bool $isEnabled): void
    {
        $isOuterEnabled = $this->isEnabled();

        $this->enabled[] = $isOuterEnabled && $isEnabled;
        $this->taken[] = !$isOuterEnabled || $isEnabled;
        $this->ended[] = false;
    }

    /**
     * Starts the next branch of the current section, as `#elif` does.
     * It is enabled in case of the condition is true, no branch of the
     * section has been taken and the output is enabled outside the section.
     *
     * @throws OutputStackException in case of there is no open section or its last branch has been started
     */
    public function swap(bool $isEnabled = true): void
    {
        if ($this->hasLastBranch()) {
            throw OutputStackException::becauseBranchFollowsLastOne();
        }

        $isTaken = $this->hasBranchTaken();

        $this->pop();
        $this->push($isEnabled && !$isTaken);
        \array_pop($this->taken);
        $this->taken[] = $isTaken || $isEnabled;
    }

    /**
     * Starts the last branch of the current section, as `#else` does. No
     * branch can follow it.
     *
     * @throws OutputStackException in case of there is no open section or its last branch has been started
     */
    public function swapLast(): void
    {
        $this->swap();

        \array_pop($this->ended);
        $this->ended[] = true;
    }

    /**
     * Closes the current section.
     *
     * @throws OutputStackException in case of there is no open section
     */
    public function pop(): void
    {
        $this->assertSection();

        \array_pop($this->enabled);
        \array_pop($this->taken);
        \array_pop($this->ended);
    }

    /**
     * @throws OutputStackException in case of there is no open section
     */
    private function assertSection(): void
    {
        if ($this->enabled === []) {
            throw OutputStackException::becauseSectionIsMissing();
        }
    }
}
