<?php

declare(strict_types=1);

$AJAX_INCLUDE = 1;
include('../../../inc/includes.php');

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

Session::checkLoginUser();
Html::header_nocache();
header('Content-Type: application/json; charset=UTF-8');

$service = new GlpiPlugin\Brandpulse\CounterService();
echo json_encode($service->getPayload(), JSON_UNESCAPED_SLASHES);
