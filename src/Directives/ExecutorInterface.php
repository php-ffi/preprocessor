<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Directives;

use FFI\Preprocessor\Exception\DirectiveEvaluationException;

interface ExecutorInterface
{
    /**
     * Source body context.
     *
     * @var int
     */
    public const CTX_SOURCE = 0x00;

    /**
     * Directive expression body context.
     *
     * @var int
     */
    public const CTX_EXPRESSION = 0x01;

    /**
     * Accepts the name of a directive and its arguments, and returns the
     * result of executing that directive.
     *
     * @param string $name
     * @param array $arguments
     * @return string
     * @throws DirectiveEvaluationException
     */
    public function execute(string $name, array $arguments = []): string;

    /**
     * Executes all directives in the passed body and returns the result
     * of all replacements.
     *
     * The second argument is responsible for the execution context.
     * Substitutions can be performed both in the body of the source code
     * and in directive expressions.
     *
     * @psalm-param ExecutorInterface::CTX_* $ctx
     *
     * @param string $body
     * @param int $ctx
     * @return string
     * @throws DirectiveEvaluationException
     */
    public function replace(string $body, int $ctx = self::CTX_SOURCE): string;
}
