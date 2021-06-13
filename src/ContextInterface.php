<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor;

use FFI\Preprocessor\Directives\RepositoryProviderInterface as DirectivesRepositoryInterface;
use FFI\Preprocessor\Includes\RepositoryProviderInterface as IncludeDirectoriesRepositoryInterface;

interface ContextInterface
{
    /**
     * @return DirectivesRepositoryInterface
     */
    public function getDirectives(): DirectivesRepositoryInterface;

    /**
     * @return IncludeDirectoriesRepositoryInterface
     */
    public function getIncludeDirectories(): IncludeDirectoriesRepositoryInterface;
}
