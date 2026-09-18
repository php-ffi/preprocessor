<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Runtime;

use FFI\Preprocessor\Runtime\SystemClock;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SystemClock::class)]
final class SystemClockTest extends TestCase
{
    public function testNowIsTheCurrentTime(): void
    {
        $before = new \DateTimeImmutable();
        $now = (new SystemClock())->now();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $now);
        self::assertLessThanOrEqual($after, $now);
    }
}
