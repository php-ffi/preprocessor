<?php

declare(strict_types=1);

use Phplrt\Compiler\Compiler;
use Phplrt\Source\File;

require __DIR__ . '/../vendor/autoload.php';

$input = __DIR__ . '/../resources/expression/grammar.pp2';
$output = __DIR__ . '/../resources/expression.php';

// Execute

$result = (new Compiler())
    ->load(File::fromPathname($input))
    ->build()
;

$result->withClassUsage('FFI\\Preprocessor\\Internal\\Expression\\Ast');

\file_put_contents($output, $result->generate());
