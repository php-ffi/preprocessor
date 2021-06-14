<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Runtime;

use FFI\Preprocessor\Directive\DirectiveInterface;
use FFI\Preprocessor\Directive\FunctionLikeDirective;
use FFI\Preprocessor\Directive\ObjectLikeDirective;
use FFI\Preprocessor\Directive\Repository as DirectivesRepository;
use FFI\Preprocessor\Exception\NotReadableException;
use FFI\Preprocessor\Exception\PreprocessException;
use FFI\Preprocessor\Exception\PreprocessorException;
use FFI\Preprocessor\Internal\Expression\Parser;
use FFI\Preprocessor\Internal\Lexer;
use FFI\Preprocessor\Io\Directory\Repository as DirectoriesRepository;
use FFI\Preprocessor\Io\Source\Repository as SourcesRepository;
use FFI\Preprocessor\Preprocessor;
use Phplrt\Contracts\Exception\RuntimeExceptionInterface;
use Phplrt\Contracts\Lexer\TokenInterface;
use Phplrt\Contracts\Source\FileInterface;
use Phplrt\Contracts\Source\ReadableInterface;
use Phplrt\Lexer\Token\Composite;
use Phplrt\Position\Position;
use Phplrt\Source\File;
use Psr\Log\LoggerInterface;

/**
 * @internal SourceExecutor is an internal library class, please do not use it in your code.
 * @psalm-internal FFI\Preprocessor\Internal
 */
final class SourceExecutor
{
    /**
     * @var string
     */
    private const GRAMMAR_PATHNAME = __DIR__ . '/../../../resources/expression.php';

    /**
     * @var OutputStack
     */
    private OutputStack $stack;

    /**
     * @var DirectiveExecutor
     */
    private DirectiveExecutor $executor;

    /**
     * @var Lexer
     */
    private Lexer $lexer;

    /**
     * @var Parser
     */
    private Parser $expressions;

    /**
     * @param DirectivesRepository $directives
     * @param DirectoriesRepository $directories
     * @param SourcesRepository $sources
     * @param LoggerInterface $logger
     */
    public function __construct(
        private DirectivesRepository $directives,
        private DirectoriesRepository $directories,
        private SourcesRepository $sources,
        private LoggerInterface $logger,
    ) {
        $this->lexer = new Lexer();
        $this->stack = new OutputStack();
        $this->executor = new DirectiveExecutor($this->directives);
        $this->expressions = Parser::fromFile(self::GRAMMAR_PATHNAME);
    }

    /**
     * @param ReadableInterface $source
     * @return \Traversable<string>
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
     * @param Composite $token
     * @param ReadableInterface $src
     * @return iterable<string>
     * @throws \Throwable
     */
    private function doInclude(Composite $token, ReadableInterface $src): iterable
    {
        if (! $this->stack->isEnabled()) {
            return [];
        }

        $isQuotedInclude = $token->getName() === Lexer::T_QUOTED_INCLUDE;

        $filename = $isQuotedInclude
            ? \str_replace('\"', '"', $token[0]->getValue())
            : $token[0]->getValue();

        try {
            $inclusion = $this->lookup($src, $filename, $isQuotedInclude);
        } catch (\Throwable $e) {
            throw NotReadableException::fromSource($e->getMessage(), $src, $token[0]);
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

        foreach ($this->directories as $directory) {
            $pathname = $directory . '/' . $file;

            if (\is_file($pathname)) {
                return File::fromPathname($pathname);
            }
        }

        foreach ($this->sources as $name => $source) {
            if ($name === $file) {
                return $source;
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

        $processed = $this->replace($body, DirectiveExecutor::CTX_EXPRESSION);

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
        $value = \count($token) === 1 ? DirectiveInterface::DEFAULT_VALUE : \trim($token[1]->getValue());
        $value = $this->replace($value, DirectiveExecutor::CTX_EXPRESSION);

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
        $value = \count($token) === 2 ? DirectiveInterface::DEFAULT_VALUE : \trim($token[2]->getValue());
        $value = $this->replace($value, DirectiveExecutor::CTX_EXPRESSION);

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

        $name = $this->replace($body, DirectiveExecutor::CTX_SOURCE);

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

        return $this->replace($body, DirectiveExecutor::CTX_SOURCE);
    }
}
