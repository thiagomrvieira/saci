<?php

namespace ThiagoVieira\Saci;

class SaciInfo
{
    /**
     * Package version.
     */
    public const VERSION = '2.0.0';

    /**
     * Package author.
     */
    public const AUTHOR = 'Thiago Vieira';

    /**
     * Package name.
     */
    public const NAME = 'Saci';

    /**
     * Package description.
     */
    public const DESCRIPTION = 'A modern, elegant Laravel debugger that shows loaded views and their data in a floating bar';

    /**
     * Get package version.
     */
    public static function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Get package author.
     */
    public static function getAuthor(): string
    {
        return self::AUTHOR;
    }

    /**
     * Get package name.
     */
    public static function getName(): string
    {
        return self::NAME;
    }

    /**
     * Get package description.
     */
    public static function getDescription(): string
    {
        return self::DESCRIPTION;
    }
}