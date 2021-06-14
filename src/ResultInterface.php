<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor;

use FFI\Preprocessor\Directive\ProviderInterface as DirectivesProviderInterface;
use FFI\Preprocessor\Io\Directory\ProviderInterface as DirectoriesProviderInterface;
use FFI\Preprocessor\Io\Source\ProviderInterface as SourcesProviderInterface;

interface ResultInterface extends
    DirectivesProviderInterface,
    DirectoriesProviderInterface,
    SourcesProviderInterface,
    \Stringable
{
}
