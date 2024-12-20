<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Environment;

use FFI\Contracts\Preprocessor\PreprocessorInterface;

final class PhpEnvironment implements EnvironmentInterface
{
    /**
     * @var list<non-empty-string>
     */
    private const EXPORT_DIRECTIVE_NAMES = [
        'PHP_',
        'ZEND_',
    ];

    public function applyTo(PreprocessorInterface $pre): void
    {
        foreach (self::EXPORT_DIRECTIVE_NAMES as $prefix) {
            foreach ($this->getCoreConstants() as $constant => $value) {
                $isValidType = \is_scalar($value) || $value === null;

                if (!$isValidType || !\str_starts_with($constant, $prefix)) {
                    continue;
                }

                $this->define($pre, $constant, $value);
            }
        }
    }

    private function getCoreConstants(): array
    {
        $constants = \get_defined_constants(true);

        return (array) ($constants['Core'] ?? $constants['core'] ?? []);
    }

    private function define(PreprocessorInterface $pre, string $name, $value): void
    {
        $pre->directives->define('__' . $name . '__', $this->toCLiteral($value));
    }

    private function toCLiteral($value): string
    {
        switch (true) {
            case \is_string($value) && \strlen($value) === 1:
                return "'" . \addcslashes($value, "'") . "'";

            case \is_string($value):
                return '"' . \addcslashes($value, '"') . '"';

            case \is_float($value):
                return \sprintf('%g', $value);

            case \is_int($value):
                return (string) $value;

            case \is_bool($value):
                return $value ? '1' : '0';

            case $value === null:
                return 'NULL';

            default:
                throw new \LogicException('Non-serializable C literal of PHP type ' . \get_debug_type($value));
        }
    }
}
