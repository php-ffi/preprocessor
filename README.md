# C23 Preprocessor for PHP

<p align="center">
    <a href="https://packagist.org/packages/ffi/preprocessor"><img src="https://poser.pugx.org/ffi/preprocessor/require/php?style=for-the-badge" alt="PHP 8.1+"></a>
    <a href="https://packagist.org/packages/ffi/preprocessor"><img src="https://poser.pugx.org/ffi/preprocessor/version?style=for-the-badge" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/ffi/preprocessor"><img src="https://poser.pugx.org/ffi/preprocessor/v/unstable?style=for-the-badge" alt="Latest Unstable Version"></a>
    <a href="https://packagist.org/packages/ffi/preprocessor"><img src="https://poser.pugx.org/ffi/preprocessor/downloads?style=for-the-badge" alt="Total Downloads"></a>
    <a href="https://raw.githubusercontent.com/php-ffi/preprocessor/master/LICENSE"><img src="https://poser.pugx.org/ffi/preprocessor/license?style=for-the-badge" alt="License MIT"></a>
</p>
<p align="center">
    <a href="https://github.com/php-ffi/preprocessor/actions"><img src="https://github.com/php-ffi/preprocessor/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
</p>

This implementation of a C-preprocessor based on
[ISO/IEC 9899:2024 - N3220](https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf).

You can also use a [PHP C parser](https://github.com/php-ffi/c-parser) to subsequently
parse the source code into an AST after preprocessing.

## Requirements

- PHP ^8.1

## Installation

Library is available as a Composer repository and can be installed using the 
following command in the root of your project.

```sh
$ composer require ffi/preprocessor
```

## Usage

`Preprocessor::process()` expands the macros and executes the directives of a C source,
the result is a stringable `Result`.

```php
$pre = new FFI\Preprocessor\Preprocessor();

echo $pre->process(<<<'C'
    #define SQUARE(x) ((x) * (x))

    int area = SQUARE(4);
    C);

// int area = ((4) * (4));
```

Method `process()` accepts:
- `SplFileInfo` - file
- `string` - the source text
- `resource` - stream
- `Phplrt\Contracts\Source\ReadableInterface` - any other source object


### Result

Besides the output, the result tells what the run has learned. Each getter reads
the output to the end first, and the result can be read more than once.

```php
$result = $pre->process("#define A 1\n#warning careful\n");

$result->hasMacro('A');        // true, the macros as they are when the run ends
$result->findMacro('A');       // MacroInterface|null
$result->getMacros();          // iterable<MacroInterface>

$result->getFiles();           // the first file and the included ones, each once
$result->getDiagnostics();     // the messages of #error and #warning


echo $result;                  // preprocessed result
```


### Macros

The `$pre->macros` holds the macros every run starts with.

```php
$pre->macros->define('DEBUG');                       // #define DEBUG 1
$pre->macros->define('LEVEL', '3');                  // #define LEVEL 3
$pre->macros->remove('DEBUG');                       // #undef DEBUG


// A closure with no parameters is an object-like macro, evaluated at each replacement
$pre->macros->define('REQUEST_ID', fn() => uniqid());

// A closure with parameters is a function-like macro, it gets the arguments as written
$pre->macros->define('MAX', fn(string $a, string $b): string
    => "(($a) > ($b) ? ($a) : ($b))"
);
```

> The `__DATE__` and `__TIME__` are taken from a [PSR-20](https://www.php-fig.org/psr/psr-20/)
> clock, the system one by default.
>
> ```php
> $pre = new Preprocessor(clock: $myClock);
> ```


### File System

Any `#include` and `#embed` search for files in the file system of the preprocessor,
`$pre->files`.

```php
$pre = new FFI\Preprocessor\Preprocessor();

// Define a file by its contents
$pre->files->add('config.h', '#define VERSION 2');
$pre->files->add('example.h', new SplFileInfo('/path/to/file.h'));
$pre->files->add('some.h', fopen('/path/to/stream.h'));

// Make a real file visible under a virtual name
$pre->files->link('gl.h', '/usr/include/GL/gl.h');

// Mount a real directory, `#include <sys/types.h>` is searched in it
$pre->files->mount('/usr/include');

// Mount it under a virtual directory, `#include <sdk/a.h>`
$pre->files->mount('/opt/sdk/include', 'sdk');
```


### Diagnostics

Ane `#error` and `#warning` are reported to a listener. By default, an error 
stops the run with `DiagnosticException` and a warning is skipped.

```php
use FFI\Preprocessor\Diagnostic\DiagnosticListener\StreamDiagnosticListener;

$pre = new Preprocessor(diagnostic: new StreamDiagnosticListener(STDERR));

// {entrypoint}:1:1: warning: #warning careful
```

The ready listeners are:
- `ThrowingDiagnosticListener` - throws errors
- `StreamDiagnosticListener` - writes diagnostic into the stream
- `LoggerDiagnosticListener` - A PSR-3 logger 
- `NullDiagnosticListener` - does nothing
- `CompoundListener` - combines several into one


### Directives

Every directive has a handler, `$pre->directives` holds them. Add a
directive of your own or replace a standard one.

```php
use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\DirectiveHandlerInterface;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\DirectiveChunk;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\GeneratedChunk;

$pre->directives->add('note', new class implements DirectiveHandlerInterface {
    public function handle(DirectiveChunk $directive, DirectiveContext $context): iterable
    {
        yield new GeneratedChunk("/* {$directive->body} */\n", $directive->source, 0);
    }
});

// #note hi   =>   /* hi */
```

The context gives the handler the macros, the file system, the diagnostic 
listener and the executor that preprocesses an included file.


#### Pragmas

`#pragma once` is executed, any other pragma is passed to the output. A pragma 
is executed by a processor, add one to react to a pragma of your own.

```php
use FFI\Preprocessor\Directive\Handler\PragmaDirectiveHandler;
use FFI\Preprocessor\Directive\Handler\PragmaDirectiveHandler\OncePragmaProcessor;
use FFI\Preprocessor\Directive\Handler\PragmaDirectiveHandler\PragmaProcessorInterface;

final class PackPragmaProcessor implements PragmaProcessorInterface
{
    public function supports(string $body): bool
    {
        return \str_starts_with($body, 'pack');
    }

    public function apply(DirectiveChunk $directive, DirectiveContext $context): iterable
    {
        return []; // `#pragma pack(...)` leaves nothing in the output
    }
}

$pre->directives->replace('pragma', new PragmaDirectiveHandler([
    new OncePragmaProcessor(),
    new PackPragmaProcessor(),
]));
```

## Limitations

List of preprocessor limitations. May be added to in the future.

### Directives

- `#line` is not supported, `__FILE__` and `__LINE__` always tell the real file and line.
- `#embed` has no parameters (`limit`, `prefix`, `suffix`, `if_empty`), and `__has_embed` is not supported.
- `#pragma once` recognizes a file by its pathname, the same file included by different paths is not detected.

### Other Features

- `_Pragma` operator is not supported.
- `__VA_OPT__` is not supported.
- `__COUNTER__` is not defined.
- `__DATE__` and `__TIME__` are taken from the clock at each replacement, not once per run.
