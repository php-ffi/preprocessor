<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression;

use Phplrt\Parser\Context;

class Tracer
{
    /**
     * @var int
     */
    private int $depth = 0;

    /**
     * @return string
     */
    private function prefix(): string
    {
        return \str_repeat('  ', $this->depth % 50);
    }

    /**
     * @param Context $ctx
     * @return string
     */
    private function ctx(Context $ctx): string
    {
        return $ctx->getState() . ' : ' . $ctx->getToken();
    }

    /**
     * @param Context $ctx
     */
    private function write(Context $ctx): void
    {
        if (\is_int($ctx->getState())) {
            return;
        }

        \fwrite(\STDERR, $this->prefix() . $this->ctx($ctx) . "\n");
    }

    /**
     * @param Context $ctx
     * @param \Closure $then
     * @return mixed
     */
    public function __invoke(Context $ctx, \Closure $then)
    {
        $this->write($ctx);
        $this->depth++;

        try {
            return $then($ctx);
        } finally {
            $this->depth--;
        }
    }
}
