<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Node;

/**
 * Node of the controlling expression of a conditional directive after its
 * identifiers have been replaced (6.10.2p13).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.6 Constant expressions
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor
 *
 * @property-read int<0, max> $offset offset of the first byte of the node in
 *                the text of the expression
 */
interface ExpressionInterface {}
