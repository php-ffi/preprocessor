<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Chunk\TaggedChunk;

use FFI\Preprocessor\Stream\Chunk\TaggedChunk;

/**
 * Source text between the directives, the new-lines included: one or more
 * text lines or lines of a skipped group, which the reader does not tell
 * apart.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.1 General
 */
final class SourceChunk extends TaggedChunk {}
