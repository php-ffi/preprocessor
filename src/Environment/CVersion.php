<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Environment;

final class CVersion
{
    /**
     * @var int<0, max>
     */
    public const VERSION_ISO_C94 = 199409;

    /**
     * @var int<0, max>
     */
    public const VERSION_ISO_C99 = 199901;

    /**
     * @var int<0, max>
     */
    public const VERSION_ISO_C11 = 201112;

    /**
     * @var int<0, max>
     */
    public const VERSION_ISO_C18 = 201710;
}
