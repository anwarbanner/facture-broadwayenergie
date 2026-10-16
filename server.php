<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * @package  Laravel
 * @author   Taylor Otwell <taylor@laravel.com>
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// This file allows us to emulate Apache's "mod_rewrite" functionality from the
// built-in PHP web server. This provides a convenient way to test a Laravel
// application without having installed a "real" web server software here.
if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    // The built-in server sends no cache headers: every page re-downloaded every CSS / JS file, one at a time.
    // Versioned files (?v=, App\Support\Asset) are kept a year; the others are revalidated (304 when unchanged).
    $file = __DIR__.'/public'.$uri;
    $types = [
        'css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
        'webp' => 'image/webp', 'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'eot' => 'application/vnd.ms-fontobject',
    ];
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if (!is_file($file) || !isset($types[$extension])) {
        return false;
    }

    $etag = '"'.dechex(filemtime($file)).'-'.dechex(filesize($file)).'"';
    header('Content-Type: '.$types[$extension].(in_array($extension, ['css', 'js', 'svg'], true) ? '; charset=UTF-8' : ''));
    header('ETag: '.$etag);
    // Icon fonts are loaded from the CSS (no version) and never change: one week.
    $isFont = in_array($extension, ['woff', 'woff2', 'ttf', 'eot'], true);
    header('Cache-Control: '.(isset($_GET['v']) ? 'public, max-age=31536000, immutable' : ($isFont ? 'public, max-age=604800' : 'public, max-age=0, must-revalidate')));

    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);

        return true;
    }

    // Text files are sent gzipped (style.css: 1.3 MB -> ~0.2 MB); the compressed copy is kept in storage/.
    if (in_array($extension, ['css', 'js', 'svg'], true) && str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip')) {
        $cacheDir = __DIR__.'/storage/framework/cache/assets';
        $gzip = $cacheDir.'/'.md5($file).'-'.trim($etag, '"').'.gz';
        if (!is_file($gzip)) {
            @mkdir($cacheDir, 0775, true);
            @file_put_contents($gzip, gzencode(file_get_contents($file), 6));
        }
        if (is_file($gzip)) {
            header('Content-Encoding: gzip');
            header('Vary: Accept-Encoding');
            header('Content-Length: '.filesize($gzip));
            readfile($gzip);

            return true;
        }
    }

    header('Content-Length: '.filesize($file));
    readfile($file);

    return true;
}

// Pages are compressed too (the built-in server does not do it).
ini_set('zlib.output_compression', '1');

require_once __DIR__.'/public/index.php';
