<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class FileSystemArchitectureTest
{
    public function testFileSystemKnowsNothingOfThePreprocessor(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('FFI\Preprocessor\FileSystem'))
            ->canOnly()
            ->dependOn()
            ->classes(
                Selector::inNamespace('FFI\Preprocessor\FileSystem'),
                Selector::classname('FFI\Preprocessor\Exception\PreprocessorException'),
                Selector::inNamespace('Phplrt\Contracts\Source'),
                Selector::inNamespace('Phplrt\Source'),
            )
            ->because('the file system only finds the files by their pathnames');
    }
}
