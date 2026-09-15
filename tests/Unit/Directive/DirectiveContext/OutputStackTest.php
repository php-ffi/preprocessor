<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Directive\DirectiveContext;

use FFI\Preprocessor\Directive\DirectiveContext\OutputStack;
use FFI\Preprocessor\Directive\Exception\OutputStackException;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(OutputStack::class)]
#[CoversClass(OutputStackException::class)]
final class OutputStackTest extends TestCase
{
    public function testOutputIsEnabledWithoutSections(): void
    {
        $stack = new OutputStack();

        self::assertTrue($stack->isEnabled());
        self::assertFalse($stack->hasSection());
    }

    public function testSectionIsEnabledByTrueCondition(): void
    {
        $stack = new OutputStack();

        $stack->push(true);

        self::assertTrue($stack->isEnabled());
        self::assertTrue($stack->hasSection());
        self::assertTrue($stack->hasBranchTaken());
    }

    public function testSectionIsDisabledByFalseCondition(): void
    {
        $stack = new OutputStack();

        $stack->push(false);

        self::assertFalse($stack->isEnabled());
        self::assertFalse($stack->hasBranchTaken());
    }

    public function testElseIsEnabledAfterDisabledBranch(): void
    {
        $stack = new OutputStack();
        $stack->push(false);

        $stack->swap();

        self::assertTrue($stack->isEnabled());
        self::assertTrue($stack->hasBranchTaken());
    }

    public function testElseIsDisabledAfterEnabledBranch(): void
    {
        $stack = new OutputStack();
        $stack->push(true);

        $stack->swap();

        self::assertFalse($stack->isEnabled());
        self::assertTrue($stack->hasBranchTaken());
    }

    public function testElifIsTakenOnlyOnce(): void
    {
        $stack = new OutputStack();
        $stack->push(false);

        $stack->swap(false);
        self::assertFalse($stack->isEnabled());
        self::assertFalse($stack->hasBranchTaken());

        $stack->swap(true);
        self::assertTrue($stack->isEnabled());

        $stack->swap(true);
        self::assertFalse($stack->isEnabled());

        $stack->swap();
        self::assertFalse($stack->isEnabled());
    }

    public function testSectionInDisabledBranchIsNeverEnabled(): void
    {
        $stack = new OutputStack();
        $stack->push(false);

        $stack->push(true);
        self::assertFalse($stack->isEnabled());
        self::assertTrue($stack->hasBranchTaken());

        $stack->swap();
        self::assertFalse($stack->isEnabled());

        $stack->swap(true);
        self::assertFalse($stack->isEnabled());
    }

    public function testOuterBranchIsRestoredByClosingSection(): void
    {
        $stack = new OutputStack();
        $stack->push(true);
        $stack->push(false);

        $stack->pop();

        self::assertTrue($stack->isEnabled());
        self::assertTrue($stack->hasSection());

        $stack->pop();

        self::assertFalse($stack->hasSection());
    }

    public function testNestedSectionsFollowTheirOwnBranches(): void
    {
        $stack = new OutputStack();

        $stack->push(true);
        $stack->push(false);
        self::assertFalse($stack->isEnabled());

        $stack->swap();
        self::assertTrue($stack->isEnabled());

        $stack->pop();
        $stack->swap();
        self::assertFalse($stack->isEnabled());

        $stack->pop();
        self::assertTrue($stack->isEnabled());
    }

    public function testBranchTakenWithoutSectionIsRejected(): void
    {
        $this->expectException(OutputStackException::class);
        $this->expectExceptionMessage('The output stack has no open conditional section');

        (new OutputStack())->hasBranchTaken();
    }

    public function testSwappingWithoutSectionIsRejected(): void
    {
        $this->expectException(OutputStackException::class);

        (new OutputStack())->swap();
    }

    public function testClosingWithoutSectionIsRejected(): void
    {
        $this->expectException(OutputStackException::class);

        (new OutputStack())->pop();
    }
}
