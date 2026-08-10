<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse;

final class Config
{
    public const CONTEXT = 'plugin:brandpulse';
    public const SCHEMA_VERSION_KEY = 'schema_version';

    public static function installDefaults(): void
    {
        if (!class_exists(\Config::class)) {
            return;
        }

        $current = \Config::getConfigurationValues(self::CONTEXT);
        $current = is_array($current) ? $current : [];
        $missing = array_diff_key(self::defaults(), $current);

        if ($missing !== []) {
            \Config::setConfigurationValues(self::CONTEXT, $missing);
        }
    }

    public static function uninstall(): void
    {
        if (class_exists(\Config::class) && method_exists(\Config::class, 'deleteConfigurationValues')) {
            \Config::deleteConfigurationValues(self::CONTEXT);
        }
    }

    public static function schemaVersion(): string
    {
        return (string) self::rawValues()[self::SCHEMA_VERSION_KEY];
    }

    public static function setSchemaVersion(string $version): void
    {
        if (!class_exists(\Config::class)) {
            return;
        }

        \Config::setConfigurationValues(self::CONTEXT, [
            self::SCHEMA_VERSION_KEY => $version,
        ]);
    }

    public static function values(): array
    {
        $values = self::rawValues();

        $values['enabled'] = (bool) (int) $values['enabled'];
        $values['compact_search_enabled'] = (bool) (int) $values['compact_search_enabled'];
        $values['refresh_interval'] = max(15, (int) $values['refresh_interval']);
        $values['branding'] = self::decodeJson($values['branding_json'], self::defaultBranding());
        $values['counters'] = self::decodeJson($values['counters_json'], self::defaultCounters());

        return $values;
    }

    public static function rawValues(): array
    {
        $values = self::defaults();

        if (class_exists(\Config::class)) {
            $current = \Config::getConfigurationValues(self::CONTEXT);
            $values = array_replace($values, is_array($current) ? $current : []);
        }

        return $values;
    }

    public static function save(array $values): void
    {
        if (!class_exists(\Config::class)) {
            return;
        }

        \Config::setConfigurationValues(self::CONTEXT, array_intersect_key($values, self::defaults()));
    }


    public static function isPulseAllowedInCurrentContext(): bool
    {
        if (!class_exists(\Session::class)) {
            return false;
        }

        return \Session::getCurrentInterface() === 'central';
    }

    public static function isValidJsonArray(string $json): bool
    {
        $decoded = json_decode($json, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded);
    }

    public static function defaults(): array
    {
        return [
            'enabled' => '1',
            'refresh_interval' => '60',
            'compact_search_enabled' => '0',
            'pulse_interface' => 'central',
            'branding_json' => self::encodeJson(self::defaultBranding()),
            'counters_json' => self::encodeJson(self::defaultCounters()),
            self::SCHEMA_VERSION_KEY => '',
        ];
    }

    public static function defaultBranding(): array
    {
        return [
            'enabled' => false,
            'title' => 'GLPI BrandPulse',
            'login_logo' => '',
            'header_logo' => '',
            'login_background' => '',
        ];
    }

    public static function defaultCounters(): array
    {
        return [
            [
                'key' => 'my_tasks',
                'label' => 'My pending tasks',
                'icon' => 'pulse:tasks',
                'color' => '#27ab3c',
                'enabled' => true,
            ],
            [
                'key' => 'my_waiting_tickets',
                'label' => 'Waiting tickets',
                'icon' => 'pulse:waiting',
                'color' => '#f59f00',
                'enabled' => true,
            ],
            [
                'key' => 'ls_microbio',
                'label' => 'LS-Microbio tickets',
                'icon' => 'pulse:medical',
                'color' => '#ff3d2a',
                'enabled' => true,
            ],
            [
                'key' => 'my_open_tickets',
                'label' => 'My open tickets',
                'icon' => 'pulse:ticket',
                'color' => '#3b82f6',
                'enabled' => true,
            ],
            [
                'key' => 'it_tickets',
                'label' => 'IT tickets',
                'icon' => 'pulse:it',
                'color' => '#ff3d2a',
                'enabled' => true,
            ],
            [
                'key' => 'unassigned_tickets',
                'label' => 'Unassigned tickets',
                'icon' => 'pulse:unassigned',
                'color' => '#ffdc64',
                'enabled' => true,
            ],
        ];
    }

    private static function encodeJson(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private static function decodeJson(string $json, array $fallback): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : $fallback;
    }
}
