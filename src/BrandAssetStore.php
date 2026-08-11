<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse;

final class BrandAssetStore
{
    public static function pluginDocumentDirectory(): string
    {
        if (defined('GLPI_PLUGIN_DOC_DIR')) {
            return rtrim((string) GLPI_PLUGIN_DOC_DIR, '/\\') . '/brandpulse';
        }

        if (defined('GLPI_DOC_DIR')) {
            return rtrim((string) GLPI_DOC_DIR, '/\\') . '/_plugins/brandpulse';
        }

        return dirname(__DIR__) . '/files/_plugins/brandpulse';
    }

    public static function brandDirectory(): string
    {
        return self::pluginDocumentDirectory() . '/brand';
    }

    public static function ensureBrandDirectory(): bool
    {
        $directory = self::brandDirectory();

        return is_dir($directory) || mkdir($directory, 0755, true) || is_dir($directory);
    }

    public static function assetUrl(string $filename): string
    {
        global $CFG_GLPI;

        return rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/')
            . '/plugins/brandpulse/front/asset.php?file='
            . rawurlencode($filename);
    }

    public static function assetPath(string $filename): ?string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return null;
        }

        $path = self::brandDirectory() . '/' . $filename;
        if (!is_file($path)) {
            return null;
        }

        $realPath = realpath($path);
        $realDirectory = realpath(self::brandDirectory());
        if ($realPath === false || $realDirectory === false || !str_starts_with($realPath, $realDirectory . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realPath;
    }
}
