<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Environment;

use FFI\Contracts\Preprocessor\PreprocessorInterface;

/**
 * The compiler supports these predefined macros specified by the
 * ISO C99, C11, C17, and ISO C++17 standards.
 */
final class StandardEnvironment implements EnvironmentInterface
{
    private \DateTimeZone $zone;

    /**
     * Defined when compiled as C.
     *
     * @var int<0, max>
     */
    public int $version = CVersion::VERSION_ISO_C18;

    /**
     * Defined as {@see true} if the implementation is a hosted implementation,
     * one that supports the entire required standard library. Otherwise,
     * defined as {@see false}.
     */
    public bool $hosted = false;

    /**
     * Defined as {@see false} if the implementation doesn't support optional
     * standard atomics.
     */
    public bool $atomics = false;

    /**
     * Defined as {@see false} if the implementation doesn't support optional
     * standard threads.
     */
    public bool $threads = false;

    /**
     * Defined as {@see false} if the implementation doesn't support standard
     * variable length arrays.
     */
    public bool $vla = false;

    /**
     * Expands to an integer literal that starts at 0. The value is incremented
     * by 1 every time it's used in a source file, or in included headers of
     * the source file. __COUNTER__ remembers its state when you use precompiled
     * headers. This macro is always defined.
     *
     * @var int<0, max>
     */
    public int $counter = 0;

    /**
     * Standard env constructor.
     */
    public function __construct()
    {
        $this->zone = new \DateTimeZone('UTC');
    }

    /**
     * @throws \Throwable
     */
    public function applyTo(PreprocessorInterface $pre): void
    {
        $now = new \DateTime('now', $this->zone);

        $pre->directives->define('__DATE__', $now->format('M d Y'));
        $pre->directives->define('__TIME__', $now->format('h:i:s'));

        $pre->directives->define('__STDC__');
        $pre->directives->define('__STDC_VERSION__', (string) $this->version);
        $pre->directives->define('__STDC_HOSTED__', $this->hosted ? '1' : '0');

        if (!$this->atomics) {
            $pre->directives->define('__STDC_NO_ATOMICS__', '1');
        }

        if (!$this->hosted) {
            $pre->directives->define('__STDC_NO_COMPLEX__', '1');
        }

        if (!$this->threads) {
            $pre->directives->define('__STDC_NO_THREADS__', '1');
        }

        if (!$this->vla) {
            $pre->directives->define('__STDC_NO_VLA__', '1');
        }

        $pre->directives->define('__COUNTER__', fn() => $this->counter++);
    }
}
