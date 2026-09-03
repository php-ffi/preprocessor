<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Reader\BlockReader;

/**
 * Lets the regular expressions match a logical line of any length: a line
 * of hundreds of megabytes is a single token.
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Stream\Reader
 */
final class BacktrackLimit
{
    /**
     * The limit of the backtracking of PCRE while a callback runs, enough
     * for a logical line of hundreds of megabytes
     *
     * @var non-empty-string
     */
    private const LIMIT = '1000000000';

    /**
     * @var non-empty-string
     */
    private const OPTION = 'pcre.backtrack_limit';

    /**
     * Calls the function with the raised limit, the previous one is back
     * right after it.
     *
     * @template TResult of mixed
     * @param \Closure(): TResult $then
     * @return TResult
     * @throws \Throwable in case of the function throws
     */
    public static function run(\Closure $then): mixed
    {
        $limit = \ini_get(self::OPTION);
        \ini_set(self::OPTION, self::LIMIT);

        try {
            return $then();
        } finally {
            if ($limit !== false) {
                \ini_set(self::OPTION, $limit);
            }
        }
    }
}
