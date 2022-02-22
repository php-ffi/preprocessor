<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression;

use FFI\Preprocessor\Internal\Expression\Ast\ExpressionInterface;
use Phplrt\Contracts\Parser\ParserInterface;
use Phplrt\Lexer\Buffer\ArrayBuffer;
use Phplrt\Lexer\Lexer;
use Phplrt\Parser\BuilderInterface;
use Phplrt\Parser\ContextInterface;
use Phplrt\Parser\Parser as Runtime;

/**
 * @internal
 */
final class Parser implements ParserInterface, BuilderInterface
{
    /**
     * @var ParserInterface
     */
    private ParserInterface $runtime;

    /**
     * @var array|\Closure[]
     */
    private array $reducers;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $lexer = new Lexer($config['tokens']['default'], $config['skip']);

        $this->reducers = $config['reducers'];

        $this->runtime = new Runtime($lexer, $config['grammar'], [
            Runtime::CONFIG_AST_BUILDER  => $this,
            Runtime::CONFIG_INITIAL_RULE => $config['initial'],
            Runtime::CONFIG_BUFFER       => ArrayBuffer::class,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function build(ContextInterface $context, $result)
    {
        $state = $context->getState();

        if (isset($this->reducers[$state])) {
            return $this->reducers[$state]($context, $result);
        }

        return null;
    }

    /**
     * @param string $pathname
     * @return static
     */
    public static function fromFile(string $pathname): self
    {
        return new self(require $pathname);
    }

    /**
     * {@inheritDoc}
     *
     * @return ExpressionInterface
     * @throws \Throwable
     */
    public function parse($source, array $options = []): iterable
    {
        return $this->runtime->parse($source, $options);
    }
}
