<?php
/** @var \Phplrt\Compiler\CompilerResult $result */
echo "<?php\n";
?>

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Reader\BlockReader;

use Phplrt\Contracts\Lexer\LexerInterface;
use Phplrt\Contracts\Source\ReadableInterface;
use Phplrt\Lexer\Lexer;

/**
 * @readonly
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Stream\Reader
 */
final class CompiledMarkupLexer implements LexerInterface
{
<?php foreach ($result->lexer->names as $id => $token): ?>
    /** @var int */
    public const <?=$token?> = <?=$id?>;
<?php endforeach; ?>

    protected readonly LexerInterface $lexer;

    /** @var non-empty-string */
    private const COMPILED_PCRE = <?=var_export($result->lexer->pattern, true)?>;

    /** @var array<int, non-empty-string> */
    private const COMPILED_CHANNELS = [
<?php foreach ($result->lexer->channels as $id => $channel): ?>
        <?=$id?> => <?=var_export($channel, true)?>,
<?php endforeach; ?>
    ];

    /** @var array<int, non-empty-string> */
    private const COMPILED_TOKEN_NAMES_MAPPING = [
<?php foreach ($result->lexer->names as $id => $name): ?>
        <?=$id?> => <?=var_export($name, true)?>,
<?php endforeach; ?>
    ];

    /** @var array<int, non-empty-string|null> */
    private const COMPILED_TOKEN_TRANSITIONS = [
<?php foreach ($result->lexer->transitions as $id => $target): ?>
        <?=$id?> => <?=var_export($target, true)?>,
<?php endforeach; ?>
    ];

    /** @var array<int, int<0, max>> */
    private const COMPILED_TOKEN_SUBGROUPS = [
<?php foreach ($result->lexer->subgroups as $id => $count): ?>
        <?=$id?> => <?=$count?>,
<?php endforeach; ?>
    ];

    public function __construct()
    {
        $this->lexer = new Lexer(
            pattern: self::COMPILED_PCRE,
            channels: self::COMPILED_CHANNELS,
            names: self::COMPILED_TOKEN_NAMES_MAPPING,
            transitions: self::COMPILED_TOKEN_TRANSITIONS,
            subgroups: self::COMPILED_TOKEN_SUBGROUPS,
        );
    }

    public function lex(ReadableInterface $source, int $offset = 0): iterable
    {
        return $this->lexer->lex($source, $offset);
    }
}
