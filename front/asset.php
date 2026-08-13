<?php

declare(strict_types=1);

$AJAX_INCLUDE = 1;
defined('GLPI_ROOT') or die('No direct access allowed');

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

function plugin_brandpulse_asset_filename_from_url(string $value): string
{
    $parts = parse_url($value);
    if (!is_array($parts) || !str_ends_with((string) ($parts['path'] ?? ''), '/plugins/brandpulse/front/asset.php')) {
        return '';
    }

    parse_str((string) ($parts['query'] ?? ''), $query);

    return basename(str_replace('\\', '/', (string) ($query['file'] ?? '')));
}

function plugin_brandpulse_asset_field_value(string $field): string
{
    global $CFG_GLPI;

    $values = GlpiPlugin\Brandpulse\Config::values();
    $branding = is_array($values['branding'] ?? null) ? $values['branding'] : [];
    if (empty($branding['enabled'])) {
        $branding = [];
    }

    $fallbacks = [
        'logo_sidebar_expanded_light' => ['logo_sidebar_expanded_light', 'logo_sidebar_expanded_grey', 'logo_sidebar_expanded_dark', 'menu_logo'],
        'logo_sidebar_expanded_dark' => ['logo_sidebar_expanded_dark', 'logo_sidebar_expanded_grey', 'logo_sidebar_expanded_light', 'menu_logo'],
        'logo_sidebar_collapsed_light' => ['logo_sidebar_collapsed_light', 'logo_sidebar_collapsed_grey', 'logo_sidebar_collapsed_dark', 'logo_sidebar_expanded_light', 'logo_sidebar_expanded_grey', 'menu_logo'],
        'logo_sidebar_collapsed_dark' => ['logo_sidebar_collapsed_dark', 'logo_sidebar_collapsed_grey', 'logo_sidebar_collapsed_light', 'logo_sidebar_expanded_dark', 'logo_sidebar_expanded_grey', 'logo_sidebar_expanded_light', 'menu_logo'],
        'login_logo_light' => ['login_logo_light', 'login_logo_grey', 'login_logo_dark', 'login_logo', 'logo_sidebar_expanded_light', 'logo_sidebar_expanded_grey', 'menu_logo'],
        'login_logo_dark' => ['login_logo_dark', 'login_logo_grey', 'login_logo_light', 'login_logo', 'logo_sidebar_expanded_dark', 'logo_sidebar_expanded_grey', 'logo_sidebar_expanded_light', 'menu_logo'],
        'login_background' => ['login_background'],
        'favicon' => ['favicon'],
    ];

    foreach ($fallbacks[$field] ?? [] as $candidate) {
        $value = trim((string) ($branding[$candidate] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    $defaultAssets = [
        'logo_sidebar_expanded_light' => '/pics/logos/logo-GLPI-100-white.png',
        'logo_sidebar_expanded_dark' => '/pics/logos/logo-GLPI-100-black.png',
        'logo_sidebar_collapsed_light' => '/pics/logos/logo-G-100-white.png',
        'logo_sidebar_collapsed_dark' => '/pics/logos/logo-G-100-black.png',
        'login_logo_light' => '/pics/logos/logo-GLPI-250-white.png',
        'login_logo_dark' => '/pics/logos/logo-GLPI-250-black.png',
        'favicon' => '/pics/favicon.ico',
    ];

    return isset($defaultAssets[$field])
        ? rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/') . $defaultAssets[$field]
        : '';
}

$field = (string) ($_GET['field'] ?? '');
if ($field !== '') {
    global $CFG_GLPI;

    $value = plugin_brandpulse_asset_field_value($field);
    if ($value === '') {
        if ($field === 'login_background') {
            $transparentSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>';
            Html::header_nocache();
            header('Content-Type: image/svg+xml');
            header('Content-Length: ' . strlen($transparentSvg));
            echo $transparentSvg;
            exit;
        }

        http_response_code(404);
        exit;
    }

    $filename = plugin_brandpulse_asset_filename_from_url($value);
    if ($filename === '') {
        if (preg_match('/^(https?:)?\/\//', $value) || str_starts_with($value, 'data:') || str_starts_with($value, '/')) {
            Html::redirect($value);
        }

        Html::redirect(rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/') . '/' . ltrim($value, '/'));
    }

    $_GET['file'] = $filename;
}

$filename = (string) ($_GET['file'] ?? '');
$path = GlpiPlugin\Brandpulse\BrandAssetStore::assetPath($filename);

if ($path === null) {
    http_response_code(404);
    exit;
}

$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$contentTypes = [
    'svg' => 'image/svg+xml',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'ico' => 'image/x-icon',
];

Html::header_nocache();
header('Content-Type: ' . ($contentTypes[$extension] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
readfile($path);
