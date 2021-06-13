<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Lexer;

use Phplrt\Contracts\Source\ReadableInterface;
use Phplrt\Source\File;

/**
 * The purpose of this class is:
 *
 * 1) Correct all line breaks
 * 2) Cut all comments from the code
 *
 * @internal Simplifier is an internal library class, please do not use it in your code.
 * @psalm-internal Bic\Preprocessor\Internal
 */
final class Simplifier
{
    /**
     * @var string
     */
    private const PCRE_BLOCK_COMMENT = '\h*\/\\*.*?\\*\/';

    /**
     * @var string
     */
    private const PCRE_INLINE_COMMENT = '\\h*\/\/[^\\n]*(?=\\n)';

    /**
     * @var string
     */
    private const PCRE_COMMENTS = '/(\G' .
        '|' . self::PCRE_BLOCK_COMMENT .
        '|' . self::PCRE_INLINE_COMMENT .
    ')/isum';

    /**
     * @param ReadableInterface $source
     * @return ReadableInterface
     */
    public function simplify(ReadableInterface $source): ReadableInterface
    {
        $content = $source->getContents();

        $content = $this->normalizeLineDelimiters($content);
        $content = $this->trimComments($content);

        return File::fromSources($content);
    }

    /**
     * @param string $source
     * @return string
     */
    private function trimComments(string $source): string
    {
        return \preg_replace(self::PCRE_COMMENTS, '', $source);
    }

    /**
     * Normalize windows-aware line breaks
     *
     * @param string $source
     * @return string
     */
    private function normalizeLineDelimiters(string $source): string
    {
        return \str_replace("\r\n", "\n", $source);
    }
}
