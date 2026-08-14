<?php

declare(strict_types=1);

$AJAX_INCLUDE = 1;
defined('GLPI_ROOT') or die('No direct access allowed');

$baseDirectory = realpath(__DIR__ . '/../public/icons/pulse');
$file = str_replace('\\', '/', (string) ($_GET['file'] ?? ''));
$file = ltrim($file, '/');
$parts = array_filter(explode('/', $file), static fn (string $part): bool => $part !== '');

$isValid = $baseDirectory !== false
    && $file !== ''
    && str_ends_with(strtolower($file), '.svg')
    && !in_array('..', $parts, true)
    && !str_contains($file, "\0");

$path = $isValid ? realpath($baseDirectory . '/' . $file) : false;
if ($path === false || !is_file($path) || !str_starts_with($path, $baseDirectory . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit;
}

$mtime = (int) filemtime($path);
$size = (int) filesize($path);
$etag = '"' . sha1($file . '|' . $mtime . '|' . $size) . '"';
$lastModified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';

if (
    trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag
    || strtotime((string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '')) >= $mtime
) {
    http_response_code(304);
    header('ETag: ' . $etag);
    header('Last-Modified: ' . $lastModified);
    header('Cache-Control: public, max-age=31536000, immutable');
    exit;
}

header('Content-Type: image/svg+xml; charset=UTF-8');
header('ETag: ' . $etag);
header('Last-Modified: ' . $lastModified);
header('Cache-Control: public, max-age=31536000, immutable');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
header('Content-Length: ' . $size);
readfile($path);
