<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Runtime;

use Psr\Clock\ClockInterface;

/**
 * Clock of the system the preprocessor runs on, tells the date and the time of translation (6.10.10.2).
 */
final class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
