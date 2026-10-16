<?php

namespace App\Support;

/**
 * URL of a file in public/ with its modification time as version ("?v=..."): the browser can keep it
 * for a year (see server.php and public/.htaccess) and fetches it again only when the file changes.
 */
class Asset
{
    /** @var array<string, string> */
    private static array $urls = [];

    public static function url(string $path): string
    {
        return self::$urls[$path] ??= asset($path) . '?v=' . (@filemtime(public_path($path)) ?: '0');
    }
}
