<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Includes;

/**
 * @internal
 */
final class Repository implements RepositoryProviderInterface
{
    use RepositoryTrait;

    /**
     * @param string[] $directories
     */
    public function __construct(iterable $directories = [])
    {
        foreach ($directories as $directory) {
            $this->include($directory);
        }
    }
}
