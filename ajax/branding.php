<?php

declare(strict_types=1);

$AJAX_INCLUDE = 1;
$brandpulse_buffer_level = ob_get_level();
ob_start();
set_error_handler(static fn (): bool => true);

$payload = [
    'branding' => [
        'enabled' => false,
    ],
];

try {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    $config = GlpiPlugin\Brandpulse\Config::values();
    $payload = [
        'branding' => $config['branding'],
    ];
} catch (Throwable) {
    $payload['branding']['enabled'] = false;
} finally {
    restore_error_handler();
    while (ob_get_level() > $brandpulse_buffer_level) {
        ob_end_clean();
    }
}

Html::header_nocache();
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
