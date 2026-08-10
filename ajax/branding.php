<?php

declare(strict_types=1);

$AJAX_INCLUDE = 1;

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

Html::header_nocache();
header('Content-Type: application/json; charset=UTF-8');

$config = GlpiPlugin\Brandpulse\Config::values();
echo json_encode([
    'branding' => $config['branding'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
