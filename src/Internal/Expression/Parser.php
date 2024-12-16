<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression;

use FFI\Preprocessor\Internal\Expression\Ast\ExpressionInterface;
use Phplrt\Buffer\ArrayBuffer;
use Phplrt\Contracts\Parser\ParserInterface;
use Phplrt\Lexer\Lexer;
use Phplrt\Parser\BuilderInterface;
use Phplrt\Parser\ContextInterface;
use Phplrt\Parser\Parser as Runtime;

/**
 * @internal
 */
final class Parser implements ParserInterface, BuilderInterface
{
    private ParserInterface $runtime;

    /**
     * @var list<\Closure>
     */
    private array $reducers;

    public function __construct(array $config)
    {
        $lexer = new Lexer($config['tokens']['default'], $config['skip']);

        $this->reducers = $config['reducers'];

        $this->runtime = new Runtime($lexer, $config['grammar'], [
            Runtime::CONFIG_AST_BUILDER => $this,
            Runtime::CONFIG_INITIAL_RULE => $config['initial'],
            Runtime::CONFIG_BUFFER => ArrayBuffer::class,
        ]);
    }

    public function build(ContextInterface $context, $result)
    {
        $state = $context->getState();

        if (isset($this->reducers[$state])) {
            return $this->reducers[$state]($context, $result);
        }

        return null;
    }

    public static function fromFile(string $pathname): self
    {
        return new self(require $pathname);
    }

    /**
     * @throws \Throwable
     */
    public function parse($source, array $options = []): ExpressionInterface
    {
        return $this->runtime->parse($source, $options);
    }
}
