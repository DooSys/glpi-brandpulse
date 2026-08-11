<?php

declare(strict_types=1);

use Glpi\Http\Firewall;
use Glpi\Plugin\Hooks;

defined('GLPI_ROOT') or die('No direct access allowed');

const PLUGIN_BRANDPULSE_VERSION = '0.1.9';
const PLUGIN_BRANDPULSE_MIN_GLPI = '11.0.0';
const PLUGIN_BRANDPULSE_MAX_GLPI = '12.0.0';
const PLUGIN_BRANDPULSE_MIN_PHP = '8.2.0';

function plugin_brandpulse_autoload(): void
{
    $autoload = __DIR__ . '/vendor/autoload.php';

    if (file_exists($autoload)) {
        require_once $autoload;
    }
}

function plugin_init_brandpulse(): void
{
    global $PLUGIN_HOOKS;

    plugin_brandpulse_autoload();

    $PLUGIN_HOOKS['csrf_compliant']['brandpulse'] = true;
    $PLUGIN_HOOKS['config_page']['brandpulse'] = 'front/config.php';

    if (class_exists(Firewall::class)) {
        Firewall::addPluginStrategyForLegacyScripts('brandpulse', '#^/front/config\.php$#', Firewall::STRATEGY_CENTRAL_ACCESS);
        Firewall::addPluginStrategyForLegacyScripts('brandpulse', '#^/ajax/counters\.php$#', Firewall::STRATEGY_CENTRAL_ACCESS);
        Firewall::addPluginStrategyForLegacyScripts('brandpulse', '#^/ajax/branding\.php$#', Firewall::STRATEGY_NO_CHECK);
    }

    if (class_exists(Plugin::class) && Plugin::isPluginActive('brandpulse')) {
        $PLUGIN_HOOKS[Hooks::ADD_CSS]['brandpulse'][] = 'css/brandpulse.css';
        $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['brandpulse'][] = 'js/brandpulse.js';
    }
}

function plugin_version_brandpulse(): array
{
    return [
        'name'           => __('GLPI BrandPulse', 'brandpulse'),
        'version'        => PLUGIN_BRANDPULSE_VERSION,
        'author'         => 'DooSys',
        'license'        => 'GPLv3+',
        'homepage'       => 'https://github.com/DooSys/glpi-brandpulse',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_BRANDPULSE_MIN_GLPI,
                'max' => PLUGIN_BRANDPULSE_MAX_GLPI,
            ],
            'php' => [
                'min' => PLUGIN_BRANDPULSE_MIN_PHP,
            ],
        ],
    ];
}

function plugin_brandpulse_check_prerequisites(): bool
{
    if (version_compare(PHP_VERSION, PLUGIN_BRANDPULSE_MIN_PHP, '<')) {
        echo 'This plugin requires PHP ' . PLUGIN_BRANDPULSE_MIN_PHP . ' or newer.';
        return false;
    }

    if (defined('GLPI_VERSION') && version_compare(GLPI_VERSION, PLUGIN_BRANDPULSE_MIN_GLPI, '<')) {
        echo 'This plugin requires GLPI ' . PLUGIN_BRANDPULSE_MIN_GLPI . ' or newer.';
        return false;
    }

    return true;
}

function plugin_brandpulse_check_config(bool $verbose = false): bool
{
    return true;
}
