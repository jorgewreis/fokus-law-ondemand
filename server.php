<?php

$publicPath = getcwd();

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

$prefix = '/novologin';
if ($uri === $prefix || str_starts_with($uri, $prefix.'/')) {
    $stripped = substr($uri, strlen($prefix));
    $stripped = ($stripped === '' || $stripped === false) ? '/' : $stripped;
    $candidate = $publicPath.$stripped;

    if ($stripped !== '/' && is_file($candidate)) {
        $extension = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
        $types = [
            'css' => 'text/css; charset=UTF-8',
            'js' => 'text/javascript; charset=UTF-8',
            'mjs' => 'text/javascript; charset=UTF-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'txt' => 'text/plain; charset=UTF-8',
            'map' => 'application/json',
            'json' => 'application/json',
            'ico' => 'image/x-icon',
        ];

        if (isset($types[$extension])) {
            header('Content-Type: '.$types[$extension]);
        }

        header('X-Robots-Tag: noindex, nofollow');
        readfile($candidate);

        return true;
    }
}

if ($uri !== '/' && is_file($publicPath.$uri)) {
    return false;
}

$formattedDateTime = date('D M j H:i:s Y');
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$remoteAddress = ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1').':'.($_SERVER['REMOTE_PORT'] ?? '0');

file_put_contents('php://stdout', "[$formattedDateTime] $remoteAddress [$requestMethod] URI: $uri\n");

require_once $publicPath.'/index.php';
