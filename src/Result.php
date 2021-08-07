<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor;

use FFI\Preprocessor\Directive\Directive;
use FFI\Preprocessor\Directive\RepositoryInterface as DirectivesRepositoryInterface;
use FFI\Preprocessor\Io\Directory\RepositoryInterface as DirectoriesRepositoryInterface;
use FFI\Preprocessor\Io\Source\RepositoryInterface as SourcesRepositoryInterface;
use JetBrains\PhpStorm\ExpectedValues;

/**
 * @psalm-import-type OptionEnum from Option
 *
 * @property-read DirectivesRepositoryInterface $directives
 * @property-read DirectoriesRepositoryInterface $directories
 * @property-read SourcesRepositoryInterface $sources
 */
final class Result implements ResultInterface
{
    /**
     * @var array<string>
     */
    private const BUILTIN_DIRECTIVES = [
        'FFI_SCOPE',
        'FFI_LIB',
    ];

    /**
     * @var string|null
     */
    private ?string $result = null;

    /**
     * @var iterable<string>
     */
    private iterable $stream;

    /**
     * @var DirectivesRepositoryInterface
     */
    private DirectivesRepositoryInterface $directives;

    /**
     * @var DirectoriesRepositoryInterface
     */
    private DirectoriesRepositoryInterface $directories;

    /**
     * @var SourcesRepositoryInterface
     */
    private SourcesRepositoryInterface $sources;

    /**
     * @var int-mask-of<OptionEnum>
     */
    private int $options;

    /**
     * @param iterable<string> $stream
     * @param DirectivesRepositoryInterface $directives
     * @param DirectoriesRepositoryInterface $directories
     * @param SourcesRepositoryInterface $sources
     * @param int-mask-of<OptionEnum> $options
     */
    public function __construct(
        iterable $stream,
        DirectivesRepositoryInterface $directives,
        DirectoriesRepositoryInterface $directories,
        SourcesRepositoryInterface $sources,
        #[ExpectedValues(flagsFromClass: Option::class)]
        int $options = 0
    ) {
        $this->stream = $stream;
        $this->directives = $directives;
        $this->directories = $directories;
        $this->sources = $sources;
        $this->options = $options;
    }

    /**
     * {@inheritDoc}
     */
    public function getDirectives(): DirectivesRepositoryInterface
    {
        $this->compileIfNotCompiled();

        return $this->directives;
    }

    /**
     * @return void
     */
    private function compileIfNotCompiled(): void
    {
        if ($this->result === null) {
            $this->result = '';

            foreach ($this->stream as $chunk) {
                $this->result .= $chunk;
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getDirectories(): DirectoriesRepositoryInterface
    {
        $this->compileIfNotCompiled();

        return $this->directories;
    }

    /**
     * {@inheritDoc}
     */
    public function getSources(): SourcesRepositoryInterface
    {
        $this->compileIfNotCompiled();

        return $this->sources;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        $this->compileIfNotCompiled();

        return $this->minify($this->injectBuiltinDirectives(
            $this->result
        ));
    }

    /**
     * @param string $result
     * @return string
     */
    private function minify(string $result): string
    {
        if (! Option::contains($this->options, Option::KEEP_EXTRA_LINE_FEEDS)) {
            $result = \preg_replace('/\n{2,}/ium', "\n", $result);
            $result = \trim($result, "\n");
        }

        return $result;
    }

    /**
     * @param string $result
     * @return string
     */
    private function injectBuiltinDirectives(string $result): string
    {
        if (! Option::contains($this->options, Option::SKIP_BUILTIN_DIRECTIVES)) {
            foreach (self::BUILTIN_DIRECTIVES as $name) {
                $directive = $this->directives->find($name);

                if ($directive !== null) {
                    $result = \sprintf('#define %s %s', $name, Directive::render($directive()))
                        . "\n" . $result;
                }
            }
        }

        return $result;
    }

    /**
     * @param string $property
     * @return \Traversable
     */
    public function __get(string $property): \Traversable
    {
        switch ($property) {
            case 'sources':
                return $this->getSources();
            case 'directives':
                return $this->getDirectives();
            case 'directories':
                return $this->getDirectories();
            default:
                throw new \LogicException(
                    \sprintf('Undefined property: %s::$%s', self::class, $property)
                );
        }
    }
}
