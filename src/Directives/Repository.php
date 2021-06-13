<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Directives;

/**
 * @internal
 */
final class Repository implements RepositoryProviderInterface
{
    use RepositoryTrait;

    /**
     * @psalm-param iterable<string, DirectiveInterface>
     *
     * @param iterable|array $defines
     * @throws \ReflectionException
     */
    public function __construct(iterable $defines = [])
    {
        foreach ($defines as $name => $directive) {
            $this->define($name, $directive);
        }
    }

    /**
     * @param string $name
     * @return DirectiveInterface|null
     */
    public function find(string $name): ?DirectiveInterface
    {
        return $this->defines[$name] ?? null;
    }
}
