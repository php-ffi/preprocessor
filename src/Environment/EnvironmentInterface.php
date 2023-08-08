<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Environment;

use FFI\Contracts\Preprocessor\PreprocessorInterface;

interface EnvironmentInterface
{
    public function applyTo(PreprocessorInterface $pre): void;
}
