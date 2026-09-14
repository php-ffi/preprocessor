<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Runtime\Chunk;

use FFI\Preprocessor\Runtime\Chunk;

/**
 * Base of the chunks whose meaning is known: source text, a directive or the
 * text the preprocessor produced. The class tells which.
 */
abstract class TaggedChunk extends Chunk {}
