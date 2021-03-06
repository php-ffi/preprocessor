<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor;

use FFI\Contracts\Preprocessor\Directive\DirectiveInterface;
use FFI\Contracts\Preprocessor\Directive\RepositoryInterface as DirectivesRepositoryInterface;
use FFI\Contracts\Preprocessor\Io\Directory\RepositoryInterface as DirectoriesRepositoryInterface;
use FFI\Contracts\Preprocessor\Io\Source\RepositoryInterface as SourcesRepositoryInterface;
use FFI\Contracts\Preprocessor\PreprocessorInterface;
use FFI\Preprocessor\Directive\Repository as DirectivesRepository;
use FFI\Preprocessor\Environment;
use FFI\Preprocessor\Environment\EnvironmentInterface;
use FFI\Preprocessor\Internal\Runtime\SourceExecutor;
use FFI\Preprocessor\Io\DirectoriesRepository as DirectoriesRepository;
use FFI\Preprocessor\Io\SourceRepository as SourcesRepository;
use Phplrt\Source\File;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Preprocessor implements PreprocessorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var DirectivesRepository
     * @psalm-readonly
     */
    public DirectivesRepository $directives;

    /**
     * @var DirectoriesRepository
     * @psalm-readonly
     */
    public DirectoriesRepository $directories;

    /**
     * @var SourcesRepository
     * @psalm-readonly
     */
    public SourcesRepository $sources;

    /**
     * @var array<class-string<EnvironmentInterface>>
     */
    private array $environments = [
        Environment\PhpEnvironment::class,
        Environment\StandardEnvironment::class,
    ];

    /**
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->directives = new DirectivesRepository();
        $this->directories = new DirectoriesRepository();
        $this->sources = new SourcesRepository();

        $this->logger = $logger ?? new NullLogger();

        foreach ($this->environments as $environment) {
            $this->load(new $environment($this));
        }
    }

    /**
     * @param EnvironmentInterface $env
     * @return void
     */
    public function load(EnvironmentInterface $env): void
    {
        $env->applyTo($this);
    }

    /**
     * {@inheritDoc}
     *
     * @psalm-type OptionEnumCase = Option::*
     * @param int-mask-of<OptionEnumCase> $options
     *
     * @psalm-suppress MissingParamType PHP 7.4 does not allow mixed type hint.
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

    /**
     * {@inheritDoc}
     */
    public function getDirectives(): DirectivesRepositoryInterface
    {
        return $this->directives;
    }

    /**
     * {@inheritDoc}
     */
    public function getSources(): SourcesRepositoryInterface
    {
        return $this->sources;
    }

    /**
     * {@inheritDoc}
     */
    public function getDirectories(): DirectoriesRepositoryInterface
    {
        return $this->directories;
    }

    /**
     * {@inheritDoc}
     */
    public function define(string $directive, $value = DirectiveInterface::DEFAULT_VALUE): void
    {
        $this->directives->define($directive, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function undef(string $directive): bool
    {
        return $this->directives->undef($directive);
    }

    /**
     * {@inheritDoc}
     */
    public function add(string $file, $source, bool $overwrite = false): bool
    {
        return $this->sources->add($file, $source, $overwrite);
    }

    /**
     * {@inheritDoc}
     */
    public function remove(string $file): bool
    {
        return $this->sources->remove($file);
    }

    /**
     * {@inheritDoc}
     */
    public function include(string $directory): void
    {
        $this->directories->include($directory);
    }

    /**
     * {@inheritDoc}
     */
    public function exclude(string $directory): void
    {
        $this->directories->exclude($directory);
    }

    /**
     * @return void
     */
    public function __clone()
    {
        $this->sources = clone $this->sources;
        $this->directories = clone $this->directories;
        $this->directives = clone $this->directives;
    }
}
