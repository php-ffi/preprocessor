<?php

declare(strict_types=1);

namespace FFI\Preprocessor;

use FFI\Contracts\Preprocessor\Directive\RepositoryInterface as DirectivesRepositoryInterface;
use FFI\Contracts\Preprocessor\Io\Directory\RepositoryInterface as DirectoriesRepositoryInterface;
use FFI\Contracts\Preprocessor\Io\Source\RepositoryInterface as SourcesRepositoryInterface;
use FFI\Contracts\Preprocessor\ResultInterface;
use FFI\Preprocessor\Directive\Directive;

/**
 * @property-read DirectivesRepositoryInterface $directives
 * @property-read DirectoriesRepositoryInterface $directories
 * @property-read SourcesRepositoryInterface $sources
 */
final class Result implements ResultInterface
{
    /**
     * @var list<non-empty-string>
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

    private DirectivesRepositoryInterface $directives;
    private DirectoriesRepositoryInterface $directories;
    private SourcesRepositoryInterface $sources;

    /**
     * @var int<0, max>
     */
    private int $options;

    /**
     * @psalm-type OptionEnumCase = Option::*
     *
     * @param iterable<string> $stream
     * @param int-mask-of<OptionEnumCase> $options
     */
    public function __construct(
        iterable $stream,
        DirectivesRepositoryInterface $directives,
        DirectoriesRepositoryInterface $directories,
        SourcesRepositoryInterface $sources,
        int $options = 0
    ) {
        $this->stream = $stream;
        $this->directives = $directives;
        $this->directories = $directories;
        $this->sources = $sources;
        $this->options = $options;
    }

    public function getDirectives(): DirectivesRepositoryInterface
    {
        $this->compileIfNotCompiled();

        return $this->directives;
    }

    private function compileIfNotCompiled(): void
    {
        if ($this->result === null) {
            $this->result = $this->withoutRecursionDepth(function (): string {
                $result = '';

                foreach ($this->stream as $chunk) {
                    $result .= $chunk;
                }

                return $result;
            });
        }
    }

    /**
     * @template TResult of mixed
     *
     * @param callable():TResult $context
     *
     * @return TResult
     */
    private function withoutRecursionDepth(callable $context)
    {
        if (($beforeRecursionDepth = \ini_get('xdebug.max_nesting_level')) !== false) {
            \ini_set('xdebug.max_nesting_level', '-1');
        }

        if (($beforeMode = \ini_get('xdebug.mode')) !== false) {
            \ini_set('xdebug.mode', 'off');
        }

        try {
            return $context();
        } finally {
            if ($beforeRecursionDepth !== false) {
                \ini_set('xdebug.max_nesting_level', $beforeRecursionDepth);
            }

            if ($beforeMode !== false) {
                \ini_set('xdebug.mode', $beforeMode);
            }
        }
    }

    public function getDirectories(): DirectoriesRepositoryInterface
    {
        $this->compileIfNotCompiled();

        return $this->directories;
    }

    public function getSources(): SourcesRepositoryInterface
    {
        $this->compileIfNotCompiled();

        return $this->sources;
    }

    public function __toString(): string
    {
        $this->compileIfNotCompiled();

        /** @psalm-suppress PossiblyNullArgument An execution of "compileIfNotCompiled" fills the result */
        return $this->minify($this->injectBuiltinDirectives($this->result));
    }

    private function minify(string $result): string
    {
        if (! Option::contains($this->options, Option::KEEP_EXTRA_LINE_FEEDS)) {
            $result = \preg_replace('/\n{2,}/ium', "\n", $result) ?? $result;
            $result = \trim($result, "\n");
        }

        return $result;
    }

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
     * @param non-empty-string $property
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
