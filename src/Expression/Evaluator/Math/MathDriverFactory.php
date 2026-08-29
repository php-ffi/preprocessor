<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Evaluator\Math;

use FFI\Preprocessor\Expression\Evaluator\Math\Driver\BcMathDriver;
use FFI\Preprocessor\Expression\Evaluator\Math\Driver\GmpDriver;
use FFI\Preprocessor\Expression\Evaluator\Math\Driver\NativeX64Driver;
use FFI\Preprocessor\Expression\Evaluator\Math\Driver\NativeX86Driver;

/**
 * Picks the arithmetic the platform supports: the native integers of a
 * 64-bit PHP, then the GMP and the BCMath extensions.
 */
final class MathDriverFactory
{
    /**
     * Creates the first available math driver
     *
     * @return NativeX64Driver|GmpDriver|BcMathDriver|NativeX86Driver
     */
    public static function createDefault(): MathDriverInterface
    {
        return match (true) {
            \PHP_INT_SIZE >= 8 => new NativeX64Driver(),
            \extension_loaded('gmp') => new GmpDriver(),
            \extension_loaded('bcmath') => new BcMathDriver(),
            default => new NativeX86Driver(),
        };
    }
}
