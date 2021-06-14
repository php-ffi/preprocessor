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
use FFI\Preprocessor\Directive\DirectiveInterface;
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
     * @param iterable<string> $stream
     * @param DirectivesRepositoryInterface $directives
     * @param DirectoriesRepositoryInterface $directories
     * @param SourcesRepositoryInterface $sources
     * @param int-mask-of<OptionEnum> $options
     */
    public function __construct(
        private iterable $stream,
        private DirectivesRepositoryInterface $directives,
        private DirectoriesRepositoryInterface $directories,
        private SourcesRepositoryInterface $sources,
        #[ExpectedValues(flagsFromClass: Option::class)]
        private int $options = 0
    ) {
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
        if (! Option::contains($this->options, Option::IGNORE_BUILTIN_DIRECTIVES)) {
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
        return match ($property) {
            'sources' => $this->getSources(),
            'directives' => $this->getDirectives(),
            'directories' => $this->getDirectories(),
            default => throw new \LogicException(
                \sprintf('Undefined property: %s::$%s', self::class, $property)
            )
        };
    }
}
