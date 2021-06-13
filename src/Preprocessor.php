<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor;

use FFI\Preprocessor\Directives\Repository as DirectivesRepository;
use FFI\Preprocessor\Directives\RepositoryProviderInterface as DirectivesRepositoryInterface;
use FFI\Preprocessor\Environment\EnvironmentInterface;
use FFI\Preprocessor\Environment\PhpEnvironment;
use FFI\Preprocessor\Environment\StandardEnvironment;
use FFI\Preprocessor\Includes\Repository as IncludeDirectoriesRepository;
use FFI\Preprocessor\Includes\RepositoryProviderInterface as IncludeDirectoriesRepositoryInterface;
use FFI\Preprocessor\Internal\Runtime\Executor;
use Phplrt\Source\File;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Preprocessor implements PreprocessorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var DirectivesRepositoryInterface
     */
    private DirectivesRepositoryInterface $directives;

    /**
     * @var IncludeDirectoriesRepositoryInterface
     */
    private IncludeDirectoriesRepositoryInterface $includes;

    /**
     * @psalm-var array<array-key, class-string<EnvironmentInterface>>
     * @var array|EnvironmentInterface[]
     */
    private array $environments = [
        PhpEnvironment::class,
        StandardEnvironment::class
    ];

    /**
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->directives = new DirectivesRepository();
        $this->includes = new IncludeDirectoriesRepository();
        $this->logger = $logger ?? new NullLogger();

        $this->bootEnvironment();
    }

    /**
     * @return void
     */
    protected function bootEnvironment(): void
    {
        foreach ($this->environments as $environment) {
            $this->load(new $environment());
        }
    }

    /**
     * @param EnvironmentInterface $env
     */
    public function load(EnvironmentInterface $env): void
    {
        $env->applyTo($this);
    }

    /**
     * {@inheritDoc}
     * @throws \ReflectionException
     */
    public function define(string $directive, $value = self::DEFAULT_VALUE): void
    {
        $this->directives->define($directive, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function undef(string $directive): void
    {
        $this->directives->undef($directive);
    }

    /**
     * {@inheritDoc}
     */
    public function defined(string $directive): bool
    {
        return $this->directives->defined($directive);
    }

    /**
     * {@inheritDoc}
     */
    public function include(string $directory): void
    {
        $this->includes->include($directory);
    }

    /**
     * {@inheritDoc}
     */
    public function exclude(string $directory): void
    {
        $this->includes->exclude($directory);
    }

    /**
     * {@inheritDoc}
     */
    public function getIncludedDirectories(): iterable
    {
        return $this->includes->getIncludedDirectories();
    }

    /**
     * {@inheritDoc}
     */
    public function add($source, string $name): void
    {
        $this->includes->add($source, $name);
    }

    /**
     * {@inheritDoc}
     */
    public function remove(string $file): void
    {
        $this->includes->remove($file);
    }

    /**
     * {@inheritDoc}
     */
    public function getIncludedFiles(): iterable
    {
        return $this->includes->getIncludedFiles();
    }

    /**
     * {@inheritDoc}
     */
    public function process($source, int $options = 0): ResultInterface
    {
        $includes = clone $this->includes;
        $directives = clone $this->directives;

        $context = new Executor($options, $directives, $includes);
        $context->setLogger($this->logger);

        $stream = $context->execute(File::new($source));

        return new Result($stream, $directives, $includes, $options);
    }

    /**
     * @return void
     */
    public function __clone()
    {
        $this->includes = clone $this->includes;
        $this->directives = clone $this->directives;
        $this->logger = clone $this->logger;
    }
}
