<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor;

use FFI\Preprocessor\Directive\ProviderInterface as DirectivesProviderInterface;
use FFI\Preprocessor\Directive\RegistrarInterface as DirectiveRegistrarInterface;
use FFI\Preprocessor\Environment\EnvironmentInterface;
use FFI\Preprocessor\Exception\PreprocessorException;
use FFI\Preprocessor\Io\Directory\ProviderInterface as DirectoriesProviderInterface;
use FFI\Preprocessor\Io\Directory\RegistrarInterface as DirectoryRegistrarInterface;
use FFI\Preprocessor\Io\Source\ProviderInterface as SourcesProviderInterface;
use FFI\Preprocessor\Io\Source\RegistrarInterface as SourceRegistrarInterface;
use JetBrains\PhpStorm\ExpectedValues;
use Phplrt\Contracts\Source\ReadableInterface;

/**
 * @psalm-import-type OptionEnum from Option
 * @psalm-type SourceEntry = string|resource|ReadableInterface|\SplFileInfo
 * @see ReadableInterface
 */
interface PreprocessorInterface extends
    DirectiveRegistrarInterface,
    DirectivesProviderInterface,
    SourceRegistrarInterface,
    SourcesProviderInterface,
    DirectoryRegistrarInterface,
    DirectoriesProviderInterface
{
    /**
     * @param SourceEntry $source
     * @param int-mask-of<OptionEnum> $options
     * @return ResultInterface
     * @throws PreprocessorException
     */
    public function process(
        $source,
        #[ExpectedValues(flagsFromClass: Option::class)]
        int $options = Option::NOTHING
    ): ResultInterface;

    /**
     * @param EnvironmentInterface $env
     */
    public function load(EnvironmentInterface $env): void;
}
