<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests;

use Phplrt\Contracts\Source\FileInterface;
use Phplrt\Source\VirtualSource;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates a file of the given content under a virtual pathname.
     *
     * @param non-empty-string $pathname
     */
    protected static function createFile(string $content, string $pathname = 'test.h'): FileInterface
    {
        return VirtualSource::createFromString($pathname, $content);
    }
}
