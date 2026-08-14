<?php

declare(strict_types=1);

$AJAX_INCLUDE = 1;
defined('GLPI_ROOT') or die('No direct access allowed');

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

function plugin_brandpulse_branding_css_url(string $value): string
{
    global $CFG_GLPI;

    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^(https?:)?\/\//', $value) || str_starts_with($value, 'data:') || str_starts_with($value, '/')) {
        $url = $value;
    } else {
        $url = rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/') . '/' . ltrim($value, '/');
    }

    return 'url("' . str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\"', '', ''], $url) . '")';
}

function plugin_brandpulse_branding_asset_value(array $branding, string $field): string
{
    global $CFG_GLPI;

    $fallbacks = [
        'logo_sidebar_expanded_light' => ['logo_sidebar_expanded_light', 'logo_sidebar_expanded_grey', 'logo_sidebar_expanded_dark', 'menu_logo'],
        'logo_sidebar_expanded_dark' => ['logo_sidebar_expanded_dark', 'logo_sidebar_expanded_grey', 'logo_sidebar_expanded_light', 'menu_logo'],
        'logo_sidebar_collapsed_light' => ['logo_sidebar_collapsed_light', 'logo_sidebar_collapsed_grey', 'logo_sidebar_collapsed_dark', 'logo_sidebar_expanded_light', 'logo_sidebar_expanded_grey', 'menu_logo'],
        'logo_sidebar_collapsed_dark' => ['logo_sidebar_collapsed_dark', 'logo_sidebar_collapsed_grey', 'logo_sidebar_collapsed_light', 'logo_sidebar_expanded_dark', 'logo_sidebar_expanded_grey', 'logo_sidebar_expanded_light', 'menu_logo'],
        'login_logo_light' => ['login_logo_light', 'login_logo_grey', 'login_logo_dark', 'login_logo', 'logo_sidebar_expanded_light', 'logo_sidebar_expanded_grey', 'menu_logo'],
        'login_logo_dark' => ['login_logo_dark', 'login_logo_grey', 'login_logo_light', 'login_logo', 'logo_sidebar_expanded_dark', 'logo_sidebar_expanded_grey', 'logo_sidebar_expanded_light', 'menu_logo'],
        'login_background' => ['login_background'],
    ];

    foreach ($fallbacks[$field] ?? [] as $candidate) {
        $value = trim((string) ($branding[$candidate] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    $rootDoc = rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/');
    $defaultAssets = [
        'logo_sidebar_expanded_light' => $rootDoc . '/pics/logos/logo-GLPI-100-white.png',
        'logo_sidebar_expanded_dark' => $rootDoc . '/pics/logos/logo-GLPI-100-black.png',
        'logo_sidebar_collapsed_light' => $rootDoc . '/pics/logos/logo-G-100-white.png',
        'logo_sidebar_collapsed_dark' => $rootDoc . '/pics/logos/logo-G-100-black.png',
        'login_logo_light' => $rootDoc . '/pics/logos/logo-GLPI-250-white.png',
        'login_logo_dark' => $rootDoc . '/pics/logos/logo-GLPI-250-black.png',
    ];

    return (string) ($defaultAssets[$field] ?? '');
}

function plugin_brandpulse_branding_css_var(string $name, string $value): string
{
    $url = plugin_brandpulse_branding_css_url($value);

    return $url !== '' ? '  ' . $name . ': ' . $url . ' !important;' . "\n" : '';
}

function plugin_brandpulse_branding_css(): string
{
    try {
        $config = GlpiPlugin\Brandpulse\Config::values();
        $branding = is_array($config['branding'] ?? null) ? $config['branding'] : [];
    } catch (Throwable) {
        $branding = [];
    }

    if (empty($branding['enabled'])) {
        return "/* BrandPulse branding disabled. GLPI native logo variables remain active. */\n";
    }

    $expandedLight = plugin_brandpulse_branding_asset_value($branding, 'logo_sidebar_expanded_light');
    $expandedDark = plugin_brandpulse_branding_asset_value($branding, 'logo_sidebar_expanded_dark');
    $collapsedLight = plugin_brandpulse_branding_asset_value($branding, 'logo_sidebar_collapsed_light');
    $collapsedDark = plugin_brandpulse_branding_asset_value($branding, 'logo_sidebar_collapsed_dark');
    $loginLight = plugin_brandpulse_branding_asset_value($branding, 'login_logo_light');
    $loginDark = plugin_brandpulse_branding_asset_value($branding, 'login_logo_dark');
    $loginBackground = plugin_brandpulse_branding_asset_value($branding, 'login_background');

    $css = "/* BrandPulse dynamic branding. Loaded before first page paint by GLPI plugin CSS hooks. */\n";
    $css .= ":root {\n";
    $css .= plugin_brandpulse_branding_css_var('--glpi-logo-light', $expandedLight);
    $css .= plugin_brandpulse_branding_css_var('--glpi-logo-dark', $expandedDark);
    $css .= plugin_brandpulse_branding_css_var('--glpi-logo-light-reduced', $collapsedLight);
    $css .= plugin_brandpulse_branding_css_var('--glpi-logo-dark-reduced', $collapsedDark);
    $css .= plugin_brandpulse_branding_css_var('--glpi-logo-light-login', $loginLight);
    $css .= plugin_brandpulse_branding_css_var('--glpi-logo-dark-login', $loginDark);
    $css .= "  --glpi-logo: var(--glpi-logo-light) !important;\n";
    $css .= "  --glpi-logo-reduced: var(--glpi-logo-light-reduced) !important;\n";
    $css .= "  --brandpulse-sidebar-logo-width: 180px;\n";
    $css .= "  --brandpulse-sidebar-logo-height: 68px;\n";
    $css .= "  --brandpulse-sidebar-logo-reduced-size: 52px;\n";
    $css .= "}\n\n";
    $css .= ":root[data-glpi-theme-dark=\"1\"] {\n";
    $css .= "  --glpi-logo: var(--glpi-logo-dark) !important;\n";
    $css .= "  --glpi-logo-reduced: var(--glpi-logo-dark-reduced) !important;\n";
    $css .= "}\n\n";
    $css .= ".page .glpi-logo {\n";
    $css .= "  background-image: var(--glpi-logo) !important;\n";
    $css .= "  background-repeat: no-repeat !important;\n";
    $css .= "  background-size: contain !important;\n";
    $css .= "}\n\n";
    $css .= "#navbar-menu .navbar-brand,\naside.navbar .navbar-brand,\n.navbar-vertical .navbar-brand {\n";
    $css .= "  min-height: var(--brandpulse-sidebar-logo-height) !important;\n";
    $css .= "}\n\n";
    $css .= "#navbar-menu .navbar-brand .glpi-logo,\naside.navbar .navbar-brand .glpi-logo,\n.navbar-vertical .navbar-brand .glpi-logo {\n";
    $css .= "  width: min(var(--brandpulse-sidebar-logo-width), calc(100% - 1rem)) !important;\n";
    $css .= "  height: var(--brandpulse-sidebar-logo-height) !important;\n";
    $css .= "  background-position: center !important;\n";
    $css .= "}\n\n";
    $css .= "body.navbar-collapsed #navbar-menu .navbar-brand .glpi-logo,\nbody.navbar-collapsed aside.navbar .navbar-brand .glpi-logo,\nbody.navbar-collapsed .navbar-vertical .navbar-brand .glpi-logo {\n";
    $css .= "  background-image: var(--glpi-logo-reduced) !important;\n";
    $css .= "  width: var(--brandpulse-sidebar-logo-reduced-size) !important;\n";
    $css .= "  height: var(--brandpulse-sidebar-logo-reduced-size) !important;\n";
    $css .= "}\n\n";
    $css .= ".page-anonymous .glpi-logo {\n";
    $css .= "  --logo: var(--glpi-logo-dark-login) !important;\n";
    $css .= "  background: none !important;\n";
    $css .= "  content: var(--logo) !important;\n";
    $css .= "  width: 200px !important;\n";
    $css .= "  height: 110px !important;\n";
    $css .= "}\n\n";
    $css .= ":root[data-glpi-theme-dark=\"1\"] .page-anonymous .glpi-logo {\n";
    $css .= "  --logo: var(--glpi-logo-light-login) !important;\n";
    $css .= "}\n";

    $backgroundUrl = plugin_brandpulse_branding_css_url($loginBackground);
    if ($backgroundUrl !== '') {
        $css .= "\n.page-anonymous {\n";
        $css .= "  background-image: " . $backgroundUrl . " !important;\n";
        $css .= "  background-position: center !important;\n";
        $css .= "  background-size: cover !important;\n";
        $css .= "}\n";
    }

    return $css;
}

Html::header_nocache();
header('Content-Type: text/css; charset=UTF-8');
echo plugin_brandpulse_branding_css();
