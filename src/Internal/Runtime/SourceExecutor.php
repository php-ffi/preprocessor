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
use FFI\Preprocessor\Option;
use FFI\Preprocessor\Preprocessor;
use JetBrains\PhpStorm\ExpectedValues;
use Phplrt\Contracts\Exception\RuntimeExceptionInterface;
use Phplrt\Contracts\Lexer\TokenInterface;
use Phplrt\Contracts\Source\FileInterface;
use Phplrt\Contracts\Source\ReadableInterface;
use Phplrt\Exception\RuntimeException;
use Phplrt\Lexer\Token\Composite;
use Phplrt\Position\Position;
use Phplrt\Source\File;
use Psr\Log\LoggerInterface;

/**
 * @internal SourceExecutor is an internal library class, please do not use it in your code.
 * @psalm-internal FFI\Preprocessor\Internal
 *
 * @psalm-import-type OptionEnum from Option
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
     * @param int-mask-of<OptionEnum> $options
     */
    public function __construct(
        private DirectivesRepository $directives,
        private DirectoriesRepository $directories,
        private SourcesRepository $sources,
        private LoggerInterface $logger,
        #[ExpectedValues(flagsFromClass: Option::class)]
        private int $options,
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
                        yield from $this->doError($token, $source);
                        break;

                    case Lexer::T_WARNING:
                        yield from $this->doWarning($token, $source);
                        break;

                    case Lexer::T_QUOTED_INCLUDE:
                    case Lexer::T_ANGLE_BRACKET_INCLUDE:
                        yield from $this->doInclude($token, $source);
                        break;

                    case Lexer::T_IFDEF:
                        yield from $this->doIfDefined($token, $source);
                        break;

                    case Lexer::T_IFNDEF:
                        yield from $this->doIfNotDefined($token, $source);
                        break;

                    case Lexer::T_ENDIF:
                        yield from $this->doEndIf($token, $source);
                        break;

                    case Lexer::T_IF:
                        yield from $this->doIf($token, $source);
                        break;

                    case Lexer::T_ELSE_IF:
                        yield from $this->doElseIf($token, $source);
                        break;

                    case Lexer::T_ELSE:
                        yield from $this->doElse($token, $source);
                        break;

                    case Lexer::T_OBJECT_MACRO:
                        yield from $this->doObjectLikeDirective($token, $source);
                        break;

                    case Lexer::T_FUNCTION_MACRO:
                        yield from $this->doFunctionLikeDirective($token, $source);
                        break;

                    case Lexer::T_UNDEF:
                        yield from $this->doRemoveDefine($token, $source);
                        break;

                    case Lexer::T_SOURCE:
                        yield $this->doRenderCode($token);
                        break;

                    default:
                        throw new \LogicException(\sprintf('Non implemented token "%s"', $token->getName()));
                }
            } catch (RuntimeExceptionInterface $e) {
                $message = $e instanceof RuntimeException
                    ? $e->getOriginalMessage()
                    : $e->getMessage();

                $exception = new PreprocessException($message, (int)$e->getCode(), $e);
                $exception->setSource($source);
                $exception->setToken($token);

                throw $exception;
            } catch (\Throwable $e) {
                throw PreprocessException::fromSource($e->getMessage(), $source, $token, $e);
            }
        }
    }

    /**
     * @param ReadableInterface $source
     * @param TokenInterface $token
     * @param array<string> $comments
     * @return iterable<string>
     */
    private function debug(ReadableInterface $source, TokenInterface $token, array $comments = []): iterable
    {
        if (Option::contains($this->options, Option::KEEP_DEBUG_COMMENTS)) {
            $map = static fn(string $line): string => "//   $line\n";
            $line = Position::fromOffset($source, $token->getOffset())
                ->getLine();
            return [
                '// ' . $this->sourceToString($source) . ':' . $line . "\n",
                ...\array_map($map, $comments)
            ];
        }

        return [];
    }

    /**
     * @param ReadableInterface $source
     * @return string
     */
    private function sourceToString(ReadableInterface $source): string
    {
        return $source instanceof FileInterface
            ? $source->getPathname()
            : '{' . $source->getHash() . '}'
        ;
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
     * @return iterable<string>
     */
    private function doError(Composite $tok, ReadableInterface $src): iterable
    {
        if (! $this->stack->isEnabled()) {
            return [];
        }

        $message = $this->escape(\trim($tok[0]->getValue()));

        $this->logger->error($message, [
            'position' => Position::fromOffset($tok->getOffset()),
            'source'   => $src,
        ]);

        return $this->debug($src, $tok, ['error ' . $message]);
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
     * @return iterable<string>
     */
    private function doWarning(Composite $tok, ReadableInterface $src): iterable
    {
        if (! $this->stack->isEnabled()) {
            return [];
        }

        $message = $this->escape(\trim($tok[0]->getValue()));

        $this->logger->warning($message, [
            'position' => Position::fromOffset($tok->getOffset()),
            'source'   => $src,
        ]);

        return $this->debug($src, $tok, ['warning ' . $message]);
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

        yield from $this->debug($src, $token, ['include ' . $this->sourceToString($inclusion)]);
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
        $file = $this->normalizeRelativePathname($file);

        if ($source instanceof FileInterface && $withLocal) {
            $pathname = \dirname($source->getPathname()) . \DIRECTORY_SEPARATOR . $file;

            if (\is_file($pathname)) {
                return File::fromPathname($pathname);
            }
        }

        foreach ($this->directories as $directory) {
            $pathname = $directory . \DIRECTORY_SEPARATOR . $file;

            if (\is_file($pathname)) {
                return File::fromPathname($pathname);
            }
        }

        foreach ($this->sources as $name => $source) {
            if ($this->normalizeRelativePathname($name) === $file) {
                return $source;
            }
        }

        throw new \LogicException(\sprintf('"%s": No such file or directory', $file));
    }

    /**
     * @param string $file
     * @return string
     */
    private function normalizeRelativePathname(string $file): string
    {
        $file = \trim($file, " \t\n\r\0\x0B/\\");

        return \str_replace(['\\', '/'], \DIRECTORY_SEPARATOR, $file);
    }

    /**
     * @param Composite $token
     * @param ReadableInterface $source
     * @return iterable<string>
     */
    private function doIfDefined(Composite $token, ReadableInterface $source): iterable
    {
        if (! $this->stack->isEnabled()) {
            $this->stack->push(false);

            return [];
        }

        $body = $this->escape($token[0]->getValue());

        $defined = $this->directives->defined($body);

        $this->stack->push($defined);

        return $this->debug($source, $token, ['if defined ' . $body]);
    }

    /**
     * @param Composite $token
     * @param ReadableInterface $source
     * @return iterable<string>
     */
    private function doIfNotDefined(Composite $token, ReadableInterface $source): iterable
    {
        if (! $this->stack->isEnabled()) {
            $this->stack->push(false);

            return [];
        }

        $body = $this->escape($token[0]->getValue());

        $defined = $this->directives->defined($body);

        $this->stack->push(! $defined);

        return $this->debug($source, $token, ['if not defined ' . $body]);
    }

    /**
     * @param TokenInterface $token
     * @param ReadableInterface $source
     * @return iterable<string>
     */
    private function doEndIf(TokenInterface $token, ReadableInterface $source): iterable
    {
        try {
            $this->stack->pop();
        } catch (\LogicException $e) {
            throw new \LogicException('#endif directive without #if');
        }

        return $this->debug($source, $token, ['endif']);
    }

    /**
     * @param Composite $token
     * @param ReadableInterface $source
     * @return iterable<string>
     * @throws RuntimeExceptionInterface
     * @throws \Throwable
     */
    private function doIf(Composite $token, ReadableInterface $source): iterable
    {
        if (! $this->stack->isEnabled()) {
            $this->stack->push(false);

            return [];
        }

        $this->stack->push($this->eval($token));

        return $this->debug($source, $token, ['if ' . $token[0]->getValue()]);
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
     * @return iterable<string>
     * @throws RuntimeExceptionInterface
     * @throws \Throwable
     */
    private function doElseIf(Composite $token, ReadableInterface $source): iterable
    {
        if (! $this->stack->isCompleted() && $this->eval($token)) {
            $this->stack->complete();

            return [];
        }

        $this->stack->update(false, $this->stack->isCompleted());

        return $this->debug($source, $token, ['else if ' . $token->getValue()]);
    }

    /**
     * @param TokenInterface $token
     * @param ReadableInterface $source
     * @return iterable<string>
     */
    private function doElse(TokenInterface $token, ReadableInterface $source): iterable
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

        return $this->debug($source, $token, ['else']);
    }

    /**
     * @param Composite $token
     * @param ReadableInterface $source
     * @return iterable<string>
     */
    private function doObjectLikeDirective(Composite $token, ReadableInterface $source): iterable
    {
        if (! $this->stack->isEnabled()) {
            return [];
        }

        // Name
        $name = \trim($token[0]->getValue());

        // Value
        $value = \count($token) === 1 ? DirectiveInterface::DEFAULT_VALUE : \trim($token[1]->getValue());
        $value = $this->replace($value, DirectiveExecutor::CTX_EXPRESSION);

        $this->directives->define($name, new ObjectLikeDirective($value));

        return $this->debug($source, $token, ['define ' . $name . ' = ' . ($value ?: '""')]);
    }

    /**
     * @param Composite $token
     * @param ReadableInterface $source
     * @return iterable<string>
     */
    private function doFunctionLikeDirective(Composite $token, ReadableInterface $source): iterable
    {
        if (! $this->stack->isEnabled()) {
            return [];
        }

        // Name
        $name = \trim($token[0]->getValue());

        // Arguments
        $args = \explode(',', $token[1]->getValue());

        // Value
        $value = \count($token) === 2 ? DirectiveInterface::DEFAULT_VALUE : \trim($token[2]->getValue());
        $value = $this->replace($value, DirectiveExecutor::CTX_EXPRESSION);

        $this->directives->define($name, new FunctionLikeDirective($args, $value));

        return $this->debug($source, $token, [
            'define ' . $name . '(' . $token[1]->getValue() . ') = ' . ($value ?: '""')
        ]);
    }

    /**
     * @param Composite $token
     * @param ReadableInterface $source
     * @return iterable<string>
     */
    private function doRemoveDefine(Composite $token, ReadableInterface $source): iterable
    {
        if (! $this->stack->isEnabled()) {
            return [];
        }

        $body = $this->escape($token[0]->getValue());

        $name = $this->replace($body, DirectiveExecutor::CTX_SOURCE);

        $this->directives->undef($name);

        return $this->debug($source, $token, ['undef ' . $name]);
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
