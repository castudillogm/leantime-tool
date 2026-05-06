<?php

// Define the public path
define('PUBLIC_PATH', __DIR__.'/public');

// Check if the requested file exists in public directory
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Fix for assets requested with extra path segments (e.g. /install/dist/...)
if (preg_match('/^\/(?:[^\/]+\/)*(dist|assets|images|theme|userfiles|favicon\.ico|robots\.txt)(.*)$/', $requestUri, $matches)) {
    $requestUri = '/' . $matches[1] . $matches[2];
}

$publicFile = PUBLIC_PATH . '/' . ltrim($requestUri, '/');
$publicFile = str_replace(['//', '\\\\'], ['/', '\\'], $publicFile);

// Re-parse query string if necessary
if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $queryStringArray);
    $_GET = array_merge($_GET, $queryStringArray);
}

if (file_exists($publicFile) && is_file($publicFile)) {
    // If it's a static file in public, serve it directly
    $extension = strtolower(pathinfo($publicFile, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
        'otf'  => 'font/otf',
        'json' => 'application/json',
        'xml'  => 'application/xml',
        'txt'  => 'text/plain',
    ];

    if (isset($mimeTypes[$extension])) {
        header('Content-Type: '.$mimeTypes[$extension]);
    }

    // Set cache headers for static assets
    header('Cache-Control: public, max-age=31536000');
    
    readfile($publicFile);
    exit;
}

// Otherwise, include the main application entry point
require PUBLIC_PATH.'/index.php';
