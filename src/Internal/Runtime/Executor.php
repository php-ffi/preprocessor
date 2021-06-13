<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Runtime;

use FFI\Preprocessor\Directives\Directive\FunctionLikeDirective;
use FFI\Preprocessor\Directives\Directive\ObjectLikeDirective;
use FFI\Preprocessor\Directives\Executor as DirectivesExecutor;
use FFI\Preprocessor\Directives\ExecutorInterface;
use FFI\Preprocessor\Directives\RepositoryProviderInterface as Directives;
use FFI\Preprocessor\Exception\NotReadableException;
use FFI\Preprocessor\Exception\PreprocessException;
use FFI\Preprocessor\Exception\PreprocessorException;
use FFI\Preprocessor\Includes\RepositoryProviderInterface as IncludeDirectories;
use FFI\Preprocessor\Internal\Expression\Parser;
use FFI\Preprocessor\Internal\Lexer;
use Phplrt\Contracts\Exception\RuntimeExceptionInterface;
use Phplrt\Contracts\Lexer\TokenInterface;
use Phplrt\Contracts\Source\FileInterface;
use Phplrt\Contracts\Source\ReadableInterface;
use Phplrt\Lexer\Token\Composite;
use Phplrt\Position\Position;
use Phplrt\Source\File;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\NullLogger;

/**
 * @internal
 */
final class Executor implements LoggerAwareInterface, LoggerInterface
{
    use LoggerAwareTrait;
    use LoggerTrait;

    /**
     * @var string
     */
    private const GRAMMAR_PATHNAME = __DIR__ . '/../../../resources/expression.php';

    /**
     * @var string[]
     */
    private const RENDERABLE_DIRECTIVES = [
        'FFI_SCOPE',
        'FFI_LIB',
    ];

    /**
     * @var Directives
     */
    public Directives $directives;

    /**
     * @var IncludeDirectories
     */
    public IncludeDirectories $includes;

    /**
     * @var OutputStack
     */
    private OutputStack $stack;

    /**
     * @var ExecutorInterface
     */
    private ExecutorInterface $executor;

    /**
     * @var Lexer
     */
    private Lexer $lexer;

    /**
     * @var Parser
     */
    private Parser $expressions;

    /**
     * @var int
     */
    private int $config;

    /**
     * @param int $config
     * @param Directives $directives
     * @param IncludeDirectories $includes
     */
    public function __construct(int $config, Directives $directives, IncludeDirectories $includes)
    {
        $this->lexer = new Lexer();
        $this->expressions = Parser::fromFile(self::GRAMMAR_PATHNAME);

        $this->config = $config;
        $this->includes = $includes;
        $this->directives = $directives;

        $this->stack = new OutputStack();
        $this->executor = new DirectivesExecutor($directives);
        $this->logger = new NullLogger();
    }

    /**
     * {@inheritDoc}
     */
    public function log($level, $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }

    /**
     * @param ReadableInterface $source
     * @return \Traversable|string[]
     * @throws PreprocessorException
     */
    public function execute(ReadableInterface $source): \Traversable
    {
        try {
            $stream = $this->lexer->lex($this->read($source));
        } catch (RuntimeExceptionInterface $e) {
            throw PreprocessException::fromSource($e->getMessage(), $source, $e->getToken(), $e);
        }

        foreach ($stream as $token) {
            try {
                switch ($token->getName()) {
                    case Lexer::T_ERROR:
                        $this->doError($token, $source);
                        break;

                    case Lexer::T_WARNING:
                        $this->doWarning($token, $source);
                        break;

                    case Lexer::T_QUOTED_INCLUDE:
                    case Lexer::T_ANGLE_BRACKET_INCLUDE:
                        yield from $this->doInclude($token, $source);
                        break;

                    case Lexer::T_IFDEF:
                        $this->doIfDefined($token);
                        break;

                    case Lexer::T_IFNDEF:
                        $this->doIfNotDefined($token);
                        break;

                    case Lexer::T_ENDIF:
                        $this->doEndIf();
                        break;

                    case Lexer::T_IF:
                        $this->doIf($token);
                        break;

                    case Lexer::T_ELSE_IF:
                        $this->doElseIf($token, $source);
                        break;

                    case Lexer::T_ELSE:
                        $this->doElse();
                        break;

                    case Lexer::T_OBJECT_MACRO:
                        $this->doObjectLikeDirective($token);
                        break;

                    case Lexer::T_FUNCTION_MACRO:
                        $this->doFunctionLikeDirective($token);
                        break;

                    case Lexer::T_UNDEF:
                        $this->doRemoveDefine($token);
                        break;

                    case Lexer::T_SOURCE:
                        yield $this->doRenderCode($token);
                        break;

                    default:
                        throw new \LogicException(\sprintf('Non implemented token "%s"', $token->getName()));
                }
            } catch (PreprocessException $e) {
                throw $e;
            } catch (RuntimeExceptionInterface $e) {
                throw new PreprocessException($e->getMessage(), $e->getCode(), $e);
            } catch (\Throwable $e) {
                throw PreprocessException::fromSource($e->getMessage(), $source, $token, $e);
            }
        }
    }

    /**
     * @param ReadableInterface $source
     * @return string
     */
    private function read(ReadableInterface $source): string
    {
        $content = $source->getContents();

        return \str_replace("\r", '', $content);
    }

    /**
     * @param Composite $tok
     * @param ReadableInterface $src
     */
    private function doError(Composite $tok, ReadableInterface $src): void
    {
        if (! $this->stack->isEnabled()) {
            return;
        }

        $message = $this->escape(\trim($tok[0]->getValue()));

        $this->logger->error($message, [
            'position' => Position::fromOffset($tok->getOffset()),
            'source'   => $src,
        ]);
    }

    /**
     * Replaces all occurrences of \ + \ n with normal line break.
     *
     * A backslash "\" + "\n" means the continuation of an expression, which
     * means it is not a significant character.
     *
     * <code>
     *  #if some\
     *      any
     * </code>
     *
     * Contain this value:
     *
     * <code>
     *  "some\
     *      any"
     * </code>
     *
     * And should replace into:
     *
     * <code>
     *  "some
     *      any"
     * </code>
     *
     * @param string $body
     * @return string
     */
    private function escape(string $body): string
    {
        return \str_replace("\\\n", "\n", $body);
    }

    /**
     * @param Composite $tok
     * @param ReadableInterface $src
     */
    private function doWarning(Composite $tok, ReadableInterface $src): void
    {
        if (! $this->stack->isEnabled()) {
            return;
        }

        $message = $this->escape(\trim($tok[0]->getValue()));

        $this->logger->warning($message, [
            'position' => Position::fromOffset($tok->getOffset()),
            'source'   => $src,
        ]);
    }

    /**
     * @param Composite $tok
     * @param ReadableInterface $src
     * @return iterable|string[]
     * @throws \Throwable
     */
    private function doInclude(Composite $tok, ReadableInterface $src): iterable
    {
        if (! $this->stack->isEnabled()) {
            return [];
        }

        $isQuotedInclude = $tok->getName() === Lexer::T_QUOTED_INCLUDE;

        $filename = $isQuotedInclude
            ? \str_replace('\"', '"', $tok[0]->getValue())
            : $tok[0]->getValue();

        try {
            $inclusion = $this->lookup($src, $filename, $isQuotedInclude);
        } catch (\Throwable $e) {
            throw NotReadableException::fromSource($e->getMessage(), $src, $tok[0]);
        }

        yield from $this->execute($inclusion);
    }


    /**
     * @param ReadableInterface $source
     * @param string $file
     * @param bool $withLocal
     * @return ReadableInterface
     */
    private function lookup(ReadableInterface $source, string $file, bool $withLocal): ReadableInterface
    {
        if ($source instanceof FileInterface && $withLocal) {
            $pathname = \dirname($source->getPathname()) . '/' . $file;

            if (\is_file($pathname)) {
                return File::fromPathname($pathname);
            }
        }

        foreach ($this->includes->getIncludedDirectories() as $directory) {
            $pathname = $directory . '/' . $file;

            if (\is_file($pathname)) {
                return File::fromPathname($pathname);
            }
        }

        foreach ($this->includes->getIncludedFiles() as $name => $result) {
            if ($name === $file) {
                return $result;
            }
        }

        throw new \LogicException(\sprintf('"%s": No such file or directory', $file));
    }

    /**
     * @param Composite $token
     */
    private function doIfDefined(Composite $token): void
    {
        if (! $this->stack->isEnabled()) {
            $this->stack->push(false);

            return;
        }

        $body = $this->escape($token[0]->getValue());

        $defined = $this->directives->defined($body);

        $this->stack->push($defined);
    }

    /**
     * @param Composite $token
     */
    private function doIfNotDefined(Composite $token): void
    {
        if (! $this->stack->isEnabled()) {
            $this->stack->push(false);

            return;
        }

        $body = $this->escape($token[0]->getValue());

        $defined = $this->directives->defined($body);

        $this->stack->push(! $defined);
    }

    /**
     * @return void
     */
    private function doEndIf(): void
    {
        try {
            $this->stack->pop();
        } catch (\LogicException $e) {
            throw new \LogicException('#endif directive without #if');
        }
    }

    /**
     * @param Composite $token
     * @throws RuntimeExceptionInterface
     * @throws \Throwable
     */
    private function doIf(Composite $token): void
    {
        if (! $this->stack->isEnabled()) {
            $this->stack->push(false);

            return;
        }

        $this->stack->push($this->eval($token));
    }

    /**
     * @param Composite $token
     * @return bool
     * @throws RuntimeExceptionInterface
     * @throws \Throwable
     */
    private function eval(Composite $token): bool
    {
        $body = $this->escape($token[0]->getValue());

        $processed = $this->replace($body, DirectivesExecutor::CTX_EXPRESSION);

        $ast = $this->expressions->parse($processed);

        return (bool)$ast->eval();
    }

    /**
     * @param string $body
     * @param int $ctx
     * @return string
     */
    private function replace(string $body, int $ctx): string
    {
        return $this->executor->replace($body, $ctx);
    }

    /**
     * @param Composite $token
     * @param ReadableInterface $source
     * @throws \Throwable
     */
    private function doElseIf(Composite $token, ReadableInterface $source): void
    {
        if (! $this->stack->isCompleted() && $this->eval($token)) {
            $this->stack->complete();
            return;
        }

        $this->stack->update(false, $this->stack->isCompleted());
    }

    /**
     * @return void
     */
    private function doElse(): void
    {
        try {
            if (! $this->stack->isCompleted()) {
                $this->stack->inverse();
            } else {
                $this->stack->update(false);
            }
        } catch (\LogicException $e) {
            throw new \LogicException('#else directive without #if');
        }
    }

    /**
     * @param Composite $token
     */
    private function doObjectLikeDirective(Composite $token): void
    {
        if (! $this->stack->isEnabled()) {
            return;
        }

        // Name
        $name = \trim($token[0]->getValue());

        // Value
        $value = \count($token) === 1 ? ObjectLikeDirective::DEFAULT_VALUE : \trim($token[1]->getValue());
        $value = $this->replace($value, ExecutorInterface::CTX_EXPRESSION);

        $this->directives->define($name, new ObjectLikeDirective($value));
    }

    /**
     * @param Composite $token
     */
    private function doFunctionLikeDirective(Composite $token): void
    {
        if (! $this->stack->isEnabled()) {
            return;
        }

        // Name
        $name = \trim($token[0]->getValue());

        // Arguments
        $args = \explode(',', $token[1]->getValue());

        // Value
        $value = \count($token) === 2 ? FunctionLikeDirective::DEFAULT_VALUE : \trim($token[2]->getValue());
        $value = $this->replace($value, ExecutorInterface::CTX_EXPRESSION);

        $this->directives->define($name, new FunctionLikeDirective($args, $value));
    }

    /**
     * @param Composite $token
     */
    private function doRemoveDefine(Composite $token): void
    {
        if (! $this->stack->isEnabled()) {
            return;
        }

        $body = $this->escape($token[0]->getValue());

        $name = $this->replace($body, ExecutorInterface::CTX_SOURCE);

        $this->directives->undef($name);
    }

    /**
     * @param TokenInterface $token
     * @return string
     */
    private function doRenderCode(TokenInterface $token): string
    {
        if (! $this->stack->isEnabled()) {
            return '';
        }

        $body = $this->escape($token->getValue());

        return $this->replace($body, ExecutorInterface::CTX_SOURCE);
    }
}
