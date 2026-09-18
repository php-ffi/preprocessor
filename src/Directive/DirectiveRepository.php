<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive;

use FFI\Preprocessor\Directive\Exception\InvalidDirectiveNameException;
use FFI\Preprocessor\Directive\Handler\DefineDirectiveHandler;
use FFI\Preprocessor\Directive\Handler\ElseDirectiveHandler;
use FFI\Preprocessor\Directive\Handler\ElseIfDefinedDirectiveHandler;
use FFI\Preprocessor\Directive\Handler\ElseIfDirectiveHandler;
use FFI\Preprocessor\Directive\Handler\EmbedDirectiveHandler;
use FFI\Preprocessor\Directive\Handler\EndifDirectiveHandler;
use FFI\Preprocessor\Directive\Handler\ErrorDirectiveHandler;
use FFI\Preprocessor\Directive\Handler\IfDefinedDirectiveHandler;
use FFI\Preprocessor\Directive\Handler\IfDirectiveHandler;
use FFI\Preprocessor\Directive\Handler\IncludeDirectiveHandler;
use FFI\Preprocessor\Directive\Handler\PragmaDirectiveHandler;
use FFI\Preprocessor\Directive\Handler\UndefDirectiveHandler;
use FFI\Preprocessor\Directive\Handler\WarningDirectiveHandler;

/**
 * Directive handlers of the configuration by the names of the directives. A
 * name with no handler here is looked up in the previous repository, if there
 * is one, unless it has been removed here: removing a handler of the previous
 * repository hides it without changing that repository.
 */
final class DirectiveRepository implements MutableDirectiveRepositoryInterface
{
    /**
     * An identifier, the form of a directive name
     */
    private const NAME_PATTERN = '/\A[A-Za-z_\x80-\xFF][0-9A-Za-z_\x80-\xFF]*\z/';

    /**
     * @var array<non-empty-string, DirectiveHandlerInterface>
     */
    private array $handlers = [];

    /**
     * Names removed here, the handlers of the previous repository hidden by them
     *
     * @var array<string, true>
     */
    private array $removed = [];

    public function __construct(
        /**
         * The repository searched for the names not added here
         */
        private readonly ?DirectiveRepositoryInterface $prev = null,
    ) {}

    /**
     * Creates the repository of the directives of the standard, `#line`
     * excepted.
     */
    public static function createDefault(): self
    {
        $repository = new self();

        $repository->add('include', new IncludeDirectiveHandler());
        $repository->add('embed', new EmbedDirectiveHandler());
        $repository->add('define', new DefineDirectiveHandler());
        $repository->add('undef', new UndefDirectiveHandler());
        $repository->add('error', new ErrorDirectiveHandler());
        $repository->add('warning', new WarningDirectiveHandler());
        $repository->add('pragma', PragmaDirectiveHandler::createDefault());
        $repository->add('if', new IfDirectiveHandler());
        $repository->add('ifdef', $ifDefined = new IfDefinedDirectiveHandler());
        $repository->add('ifndef', $ifDefined);
        $repository->add('elif', new ElseIfDirectiveHandler());
        $repository->add('elifdef', $elseIfDefined = new ElseIfDefinedDirectiveHandler());
        $repository->add('elifndef', $elseIfDefined);
        $repository->add('else', new ElseDirectiveHandler());
        $repository->add('endif', new EndifDirectiveHandler());

        return $repository;
    }

    public function find(string $name): ?DirectiveHandlerInterface
    {
        if (isset($this->handlers[$name])) {
            return $this->handlers[$name];
        }

        if (isset($this->removed[$name])) {
            return null;
        }

        return $this->prev?->find($name);
    }

    /**
     * @throws InvalidDirectiveNameException in case of the name is not an identifier or has a handler
     */
    public function add(string $name, DirectiveHandlerInterface $handler): void
    {
        if ($this->find($name) !== null) {
            throw InvalidDirectiveNameException::becauseNameIsTaken($name);
        }

        $this->replace($name, $handler);
    }

    /**
     * @throws InvalidDirectiveNameException in case of the name is not an identifier
     */
    public function replace(string $name, DirectiveHandlerInterface $handler): void
    {
        if (\preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw InvalidDirectiveNameException::becauseNameIsNotIdentifier($name);
        }

        $this->handlers[$name] = $handler;
        unset($this->removed[$name]);
    }

    public function remove(string $name): void
    {
        unset($this->handlers[$name]);

        if ($this->prev?->find($name) !== null) {
            $this->removed[$name] = true;
        }
    }
}
