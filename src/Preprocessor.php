<?php

declare(strict_types=1);

namespace FFI\Preprocessor;

use FFI\Contracts\Preprocessor\Directive\DirectiveInterface;
use FFI\Contracts\Preprocessor\Directive\RepositoryInterface as DirectivesRepositoryInterface;
use FFI\Contracts\Preprocessor\Io\Directory\RepositoryInterface as DirectoriesRepositoryInterface;
use FFI\Contracts\Preprocessor\Io\Source\RepositoryInterface as SourcesRepositoryInterface;
use FFI\Contracts\Preprocessor\PreprocessorInterface;
use FFI\Preprocessor\Directive\Repository as DirectivesRepository;
use FFI\Preprocessor\Environment\EnvironmentInterface;
use FFI\Preprocessor\Internal\Runtime\SourceExecutor;
use FFI\Preprocessor\Io\DirectoriesRepository;
use FFI\Preprocessor\Io\SourceRepository;
use Phplrt\Source\File;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Preprocessor implements PreprocessorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @psalm-readonly
     */
    public DirectivesRepository $directives;

    /**
     * @psalm-readonly
     */
    public DirectoriesRepository $directories;

    /**
     * @psalm-readonly
     */
    public SourceRepository $sources;

    /**
     * @var list<class-string<EnvironmentInterface>>
     */
    private array $environments = [
        Environment\PhpEnvironment::class,
        Environment\StandardEnvironment::class,
    ];

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->directives = new DirectivesRepository();
        $this->directories = new DirectoriesRepository();
        $this->sources = new SourceRepository();

        $this->logger = $logger ?? new NullLogger();

        foreach ($this->environments as $environment) {
            $this->load(new $environment($this));
        }
    }

    public function load(EnvironmentInterface $env): void
    {
        $env->applyTo($this);
    }

    /**
     * @param int-mask-of<Option::*> $options
     *
     * @psalm-suppress MissingParamType : PHP 7.4 does not allow mixed type hint.
     */
    public function process($source, int $options = Option::NOTHING): Result
    {
        [$directives, $directories, $sources] = [
            clone $this->directives,
            clone $this->directories,
            clone $this->sources,
        ];

        $logger = $this->logger ?? new NullLogger();
        $context = new SourceExecutor($directives, $directories, $sources, $logger, $options);
        $stream = $context->execute(File::new($source));

        return new Result($stream, $directives, $directories, $sources, $options);
    }

    public function getDirectives(): DirectivesRepositoryInterface
    {
        return $this->directives;
    }

    public function getSources(): SourcesRepositoryInterface
    {
        return $this->sources;
    }

    public function getDirectories(): DirectoriesRepositoryInterface
    {
        return $this->directories;
    }

    public function define(string $directive, $value = DirectiveInterface::DEFAULT_VALUE): void
    {
        $this->directives->define($directive, $value);
    }

    public function undef(string $directive): bool
    {
        return $this->directives->undef($directive);
    }

    public function add(string $file, $source, bool $overwrite = false): bool
    {
        return $this->sources->add($file, $source, $overwrite);
    }

    public function remove(string $file): bool
    {
        return $this->sources->remove($file);
    }

    public function include(string $directory): void
    {
        $this->directories->include($directory);
    }

    public function exclude(string $directory): void
    {
        $this->directories->exclude($directory);
    }

    public function __clone()
    {
        $this->sources = clone $this->sources;
        $this->directories = clone $this->directories;
        $this->directives = clone $this->directives;
    }
}
