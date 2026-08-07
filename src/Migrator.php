<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse;

final class Migrator
{
    public static function migrate(string $targetVersion): bool
    {
        if (!class_exists(\Config::class)) {
            return false;
        }

        $installedVersion = Config::schemaVersion();

        if ($installedVersion === '') {
            $installedVersion = '0.0.0';
        }

        if (version_compare($installedVersion, '0.1.0', '<=')) {
            self::migrateTo010();
        }

        Config::setSchemaVersion($targetVersion);

        return true;
    }

    private static function migrateTo010(): void
    {
        Config::installDefaults();
    }
}
