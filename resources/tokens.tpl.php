<?php
/** @var \Phplrt\Compiler\CompilerResult $result */
echo "<?php\n";
?>

declare(strict_types=1);

namespace FFI\Preprocessor\Lexer;

/**
 * The compiled pattern of the preprocessing tokens and the identifiers of
 * the token kinds it marks, generated from `resources/tokens.pp3`.
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Lexer
 */
final class CompiledTokenLexer
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
}
