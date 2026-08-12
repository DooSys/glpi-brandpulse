<?php

declare(strict_types=1);

$AJAX_INCLUDE = 1;
defined('GLPI_ROOT') or die('No direct access allowed');

$brandpulse_buffer_level = ob_get_level();
ob_start();
set_error_handler(static fn (): bool => true);

$payload = [
    'count' => 0,
    'icons' => [],
];

try {
    $manifestPath = __DIR__ . '/../public/icons/pulse/index.json';
    if (is_readable($manifestPath)) {
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $icons = is_array($manifest['icons'] ?? null) ? $manifest['icons'] : [];
        $payload = [
            'count' => count($icons),
            'icons' => $icons,
        ];
    }
} catch (Throwable) {
    $payload = [
        'count' => 0,
        'icons' => [],
    ];
} finally {
    restore_error_handler();
    while (ob_get_level() > $brandpulse_buffer_level) {
        ob_end_clean();
    }
}

Html::header_nocache();
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
