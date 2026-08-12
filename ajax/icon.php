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

Html::header_nocache();
header('Content-Type: image/svg+xml; charset=UTF-8');
readfile($path);
