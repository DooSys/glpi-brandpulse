<?php

declare(strict_types=1);

defined('GLPI_ROOT') or die('No direct access allowed');

function plugin_brandpulse_require_autoload(): void
{
    $autoload = __DIR__ . '/vendor/autoload.php';

    if (file_exists($autoload)) {
        require_once $autoload;
    }
}

function plugin_brandpulse_install(): bool
{
    plugin_brandpulse_require_autoload();

    if (!class_exists(GlpiPlugin\Brandpulse\Migrator::class)) {
        return false;
    }

    return GlpiPlugin\Brandpulse\Migrator::migrate(PLUGIN_BRANDPULSE_VERSION);
}

function plugin_brandpulse_uninstall(): bool
{
    plugin_brandpulse_require_autoload();

    if (class_exists(GlpiPlugin\Brandpulse\Config::class)) {
        GlpiPlugin\Brandpulse\Config::uninstall();
    }

    return true;
}
