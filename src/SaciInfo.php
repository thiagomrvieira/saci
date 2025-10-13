<?php

namespace ThiagoVieira\Saci;

use Composer\InstalledVersions;

class SaciInfo
{
    /**
     * Package version.
     */
    public const VERSION = '2.2.0';

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
        try {
            if (class_exists(InstalledVersions::class)) {
                $v = InstalledVersions::getPrettyVersion('thiago-vieira/saci');
                if (is_string($v) && $v !== '') {
                    return ltrim($v, 'vV ');
                }
            }
        } catch (\Throwable $e) {
            // ignore and fallback
        }
        return ltrim(self::VERSION, 'vV ');
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