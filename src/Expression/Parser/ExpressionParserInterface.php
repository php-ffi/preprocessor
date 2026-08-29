<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Parser;

use FFI\Preprocessor\Expression\Node\ExpressionInterface;
use Phplrt\Contracts\Parser\ParserInterface;

/**
 * @template-extends ParserInterface<ExpressionInterface>
 */
interface ExpressionParserInterface extends ParserInterface {}
