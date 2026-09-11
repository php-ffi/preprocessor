<?php
/** @var \Phplrt\Compiler\CompilerResult $result */
echo "<?php\n";
?>

declare(strict_types=1);

namespace FFI\Preprocessor\Macro\Executor\MacroExpander;

use Phplrt\Lexer\Lexer;

/**
 * The lexer of the preprocessing tokens (6.4) and the identifiers of the
 * token kinds it marks, generated from `resources/tokens.pp3`.
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Macro\Executor
 */
final class CompiledTokenLexer extends Lexer
{
<?php foreach ($result->lexer->names as $id => $token): ?>
<?php if (\str_starts_with($token, 'T_')): ?>
    /** @var int */
    public const <?=$token?> = <?=$id?>;

<?php endif; ?>
<?php endforeach; ?>
    /**
     * Every token at the current offset, the identifier of its kind is the
     * mark of the match
     *
     * @var non-empty-string
     */
    public const PATTERN = <?=var_export($result->lexer->pattern, true)?>;

    public function __construct()
    {
        parent::__construct(pattern: self::PATTERN);
    }
}
