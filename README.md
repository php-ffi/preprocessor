# Simple C-lang Preprocessor

This implementation of a preprocessor based in part on
[ISO/IEC 9899:TC2](http://www.open-std.org/jtc1/sc22/wg14/www/docs/n1124.pdf).

## Requirements

- PHP >= 8.0

## Installation

Library is available as composer repository and can be installed using the 
following command in a root of your project.

```sh
$ composer require ffi/preprocessor
```

## Usage

```php
use FFI\Preprocessor\Preprocessor;

$pre = new Preprocessor();

echo $pre->process('
    #define VK_DEFINE_HANDLE(object) typedef struct object##_T* object;

    #if !defined(VK_DEFINE_NON_DISPATCHABLE_HANDLE)
        #if defined(__LP64__) || defined(_WIN64) || (defined(__x86_64__) && !defined(__ILP32__) ) || defined(_M_X64) || defined(__ia64) || defined (_M_IA64) || defined(__aarch64__) || defined(__powerpc64__)
            #define VK_DEFINE_NON_DISPATCHABLE_HANDLE(object) typedef struct object##_T *object;
        #else
            #define VK_DEFINE_NON_DISPATCHABLE_HANDLE(object) typedef uint64_t object;
        #endif
    #endif

    VK_DEFINE_HANDLE(VkInstance)
    VK_DEFINE_NON_DISPATCHABLE_HANDLE(VkSemaphore)
');

//
// Expected Output:
//
//  typedef struct VkInstance_T* VkInstance;
//  typedef uint64_t VkSemaphore;
//
```

## Directives

### Supported Directives

- [x] `#include "file.h"` local-first include
- [x] `#include <file.h>` global-first include
- [x] `#define name` defining directives
    - [x] `#define name value` object-like macro
    - [x] `#define name(arg) value` function-like macro
    - [x] `#define name(arg) xxx##arg` concatenation
    - [x] `#define name(arg) #arg` stringizing
- [x] `#undef name` removing directives
- [x] `#ifdef name` "if directive defined" condition
- [x] `#ifndef name` "if directive not defined" condition
- [x] `#if EXPRESSION` if condition
- [x] `#elif EXPRESSION` else if condition
- [x] `#else` else condition
- [x] `#endif` completion of a condition
- [x] `#error message` error message directive
- [x] `#warning message` warning message directive
- [ ] `#line 66 "filename"` line and file override
- [ ] `#pragma XXX` compiler control
    - [ ] `#pragma once`
- [ ] `#assert XXX` compiler assertion
    - [ ] `#unassert XXX` compiler assertion
- [ ] `#ident XXX`
    - [ ] `#sccs XXX`

### Expression Grammar

#### Comparison Operators

- [x] `A > B` greater than
- [x] `A < B` less than
- [x] `A == B` equal
- [x] `A != B` not equal
- [x] `A >= B` greater than or equal
- [x] `A <= B` less than or equal

#### Logical Operators

- [x] `! A` logical NOT
- [x] `A && B` conjunction
- [x] `A || B` disjunction
- [x] `(...)` grouping

#### Arithmetic Operators

- [x] `A + B` math addition
- [x] `A - B` math subtraction
- [x] `A * B` math multiplication
- [x] `A / B` math division
- [x] `A % B` modulo
- [ ] `A++` increment
    - [x] `++A` prefix form
- [ ] `A--` decrement
    - [x] `--A` prefix form
- [x] `+A` unary plus
- [x] `-A` unary minus
- [ ] `&A` unary addr
- [ ] `*A` unary pointer

#### Bitwise Operators

- [x] `~A` bitwise NOT
- [x] `A & B` bitwise AND
- [x] `A | B` bitwise OR
- [x] `A ^ B` bitwise XOR
- [x] `A << B` bitwise left shift
- [x] `A >> B` bitwise right shift

#### Other Operators

- [x] `defined(X)` defined macro
- [ ] `A ? B : C` ternary
- [ ] `sizeof VALUE` sizeof
    - [ ] `sizeof(TYPE)` sizeof type

### Literals

- [x] `true`, `false` boolean
- [x] `42` decimal integer literal
    - [x] `42u`, `42U` unsigned int
    - [x] `42l`, `42L` long int
    - [x] `42ul`, `42UL` unsigned long int
    - [x] `42ll`, `42LL` long long int
    - [x] `42ull`, `42ULL` unsigned long long int
- [x] `042` octal integer literal
- [x] `0x42` hexadecimal integer literal
- [x] `0b42` binary integer literal
- [x] `"string"` string (char array)
    - [x] `L"string"` string (wide char array)
    - [x] `"\•"` escape sequences in strings
    - [ ] `"\•••"` arbitrary octal value in strings
    - [ ] `"\X••"` arbitrary hexadecimal value in strings
- [ ] `'x'` char literal
    - [ ] `'\•'` escape sequences
    - [ ] `'\•••'` arbitrary octal value
    - [ ] `'\X••'` arbitrary hexadecimal value
    - [ ] `L'x'` wide character literal
- [ ] `42.0` double
    - [ ] `42f`, `42F` float
    - [ ] `42l`, `42L` long double
    - [ ] `42E` exponential form
    - [ ] `0.42e23` exponential form
- [ ] `NULL` null macro

### Type Casting

- [x] `(char)42`
- [x] `(short)42`
- [x] `(int)42`
- [x] `(long)42`
- [x] `(float)42`
- [x] `(double)42`
- [x] `(bool)42` (Out of ISO/IEC 9899:TC2 specification)
- [x] `(string)42` (Out of ISO/IEC 9899:TC2 specification)
- [ ] `(void)42`
- [ ] `(long type)42` Casting to a long type (`long int`, `long double`, etc)
- [ ] `(const type)42` Casting to a constant type (`const char`, etc)
- [ ] `(unsigned type)42` Casting to unsigned type (`unsigned int`, `unsigned long`, etc)
- [ ] `(signed type)42` Casting to signed type (`signed int`, `signed long`, etc)
- [ ] Pointers (`void *`, etc)
- [ ] References (`unsigned int`, `unsigned long`, etc)

### Object Like Directive

```php
use FFI\Preprocessor\Preprocessor;
use FFI\Preprocessor\Directive\ObjectLikeDirective;

$pre = new Preprocessor();

// #define A
$pre->define('A');

// #define B 42
$pre->define('B', '42');

// #define С 42
$pre->define('С', new ObjectLikeDirective('42'));
```

## Function Like Directive

```php
use FFI\Preprocessor\Preprocessor;
use FFI\Preprocessor\Directive\FunctionLikeDirective;

$pre = new Preprocessor();

// #define C(object) object##_T* object;
$pre->define('C', function (string $arg) {
    return "${arg}_T* ${arg};";
});

// #define D(object) object##_T* object;
$pre->define('D', new FunctionLikeDirective(['object'], 'object##_T* object'));
```

## Include Directories

```php
use FFI\Preprocessor\Preprocessor;

$pre = new Preprocessor();

$pre->include('/path/to/directory');
$pre->exclude('some');
```

## Message Handling

```php
use FFI\Preprocessor\Preprocessor;

$logger = new Psr3LoggerImplementation();

$pre = new Preprocessor($logger);

$pre->process('
    #error Error message
    // Will be sent to the logger:
    //  - LoggerInterface::error("Error message")
    
    #warning Warning message
    // Will be sent to the logger: 
    //  - LoggerInterface::warning("Warning message")
');
```
