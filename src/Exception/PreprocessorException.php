<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Exception;

/**
 * Base of every exception thrown by the preprocessor. Nothing else leaves
 * it, so catching this class is enough to handle any failure.
 *
 * The kind of failure is told by the class of the exception.
 */
abstract class PreprocessorException extends \Exception {}
