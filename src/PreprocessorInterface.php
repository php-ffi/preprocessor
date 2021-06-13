<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor;

use FFI\Preprocessor\Directives\DirectivesProviderInterface;
use FFI\Preprocessor\Environment\EnvironmentProviderInterface;
use FFI\Preprocessor\Exception\PreprocessorException;
use FFI\Preprocessor\Includes\DirectoriesProviderInterface;
use FFI\Preprocessor\Includes\FilesProviderInterface;
use Phplrt\Contracts\Source\ReadableInterface;

/**
 * @psalm-type SourceEntry = string|resource|ReadableInterface|\SplFileInfo
 *
 * @link ReadableInterface
 * @link StreamInterface
 */
interface PreprocessorInterface extends
    FilesProviderInterface,
    DirectivesProviderInterface,
    DirectoriesProviderInterface,
    EnvironmentProviderInterface
{
    /**
     * @psalm-param SourceEntry $source
     *
     * @param mixed $source
     * @return ResultInterface
     * @throws PreprocessorException
     */
    public function process(mixed $source): ResultInterface;
}
