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
        $values['branding'] = self::normalizeBranding(
            self::decodeJson($values['branding_json'], self::defaultBranding())
        );
        $values['counters'] = self::normalizeCounters(
            self::decodeJson($values['counters_json'], self::defaultCounters())
        );

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

    public static function saveBranding(array $branding): void
    {
        self::save([
            'branding_json' => self::encodeJson(self::normalizeBranding($branding)),
        ]);
    }

    public static function savePulse(bool $enabled, int $refreshInterval, bool $compactSearchEnabled, array $counters): void
    {
        self::save([
            'enabled' => $enabled ? '1' : '0',
            'refresh_interval' => (string) max(15, $refreshInterval),
            'compact_search_enabled' => $compactSearchEnabled ? '1' : '0',
            'counters_json' => self::encodeJson(self::normalizeCounters($counters)),
        ]);
    }

    public static function isPulseAllowedInCurrentContext(): bool
    {
        if (!class_exists(\Session::class) || !method_exists(\Session::class, 'getCurrentInterface')) {
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
            'favicon' => '',
            'login_logo' => '',
            'menu_logo' => '',
            'login_background' => '',
            'login_alert_enabled' => false,
            'login_alert_type' => 'info',
            'login_alert_message' => '',
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
                'source_type' => 'preset',
                'savedsearches_id' => 0,
                'warning_threshold' => 0,
                'critical_threshold' => 0,
            ],
            [
                'key' => 'my_waiting_tickets',
                'label' => 'Waiting tickets',
                'icon' => 'pulse:waiting',
                'color' => '#f59f00',
                'enabled' => true,
                'source_type' => 'preset',
                'savedsearches_id' => 0,
                'warning_threshold' => 0,
                'critical_threshold' => 0,
            ],
            [
                'key' => 'my_open_tickets',
                'label' => 'My open tickets',
                'icon' => 'pulse:ticket',
                'color' => '#3b82f6',
                'enabled' => true,
                'source_type' => 'preset',
                'savedsearches_id' => 0,
                'warning_threshold' => 0,
                'critical_threshold' => 0,
            ],
            [
                'key' => 'all_open_tickets',
                'label' => 'All open tickets',
                'icon' => 'pulse:it',
                'color' => '#ff3d2a',
                'enabled' => true,
                'source_type' => 'preset',
                'savedsearches_id' => 0,
                'warning_threshold' => 0,
                'critical_threshold' => 0,
            ],
            [
                'key' => 'unassigned_tickets',
                'label' => 'Unassigned tickets',
                'icon' => 'pulse:unassigned',
                'color' => '#ffdc64',
                'enabled' => true,
                'source_type' => 'preset',
                'savedsearches_id' => 0,
                'warning_threshold' => 0,
                'critical_threshold' => 0,
            ],
        ];
    }

    public static function pulseIcons(): array
    {
        return [
            'pulse:tasks' => 'Tasks',
            'pulse:waiting' => 'Waiting',
            'pulse:medical' => 'Medical',
            'pulse:ticket' => 'Ticket',
            'pulse:it' => 'IT',
            'pulse:unassigned' => 'Unassigned',
            'pulse:bell' => 'Bell',
            'pulse:clock' => 'Clock',
            'pulse:warning' => 'Warning',
            'pulse:validation' => 'Validation',
            'pulse:asset' => 'Asset',
            'pulse:brand' => 'Brand',
            'pulse:search' => 'Search',
        ];
    }

    public static function presetCounters(): array
    {
        return [
            'my_tasks' => 'My pending tasks',
            'my_waiting_tickets' => 'Waiting tickets',
            'my_open_tickets' => 'My open tickets',
            'all_open_tickets' => 'All open tickets',
            'unassigned_tickets' => 'Unassigned tickets',
        ];
    }

    public static function normalizeBranding(array $branding): array
    {
        $branding = array_replace(self::defaultBranding(), $branding);
        $allowedAlertTypes = ['info', 'warning', 'danger', 'success'];

        return [
            'enabled' => !empty($branding['enabled']),
            'title' => trim((string) $branding['title']),
            'favicon' => trim((string) $branding['favicon']),
            'login_logo' => trim((string) $branding['login_logo']),
            'menu_logo' => trim((string) $branding['menu_logo']),
            'login_background' => trim((string) $branding['login_background']),
            'login_alert_enabled' => !empty($branding['login_alert_enabled']),
            'login_alert_type' => in_array($branding['login_alert_type'], $allowedAlertTypes, true)
                ? (string) $branding['login_alert_type']
                : 'info',
            'login_alert_message' => trim((string) $branding['login_alert_message']),
        ];
    }

    public static function normalizeCounters(array $counters): array
    {
        $normalized = [];

        foreach ($counters as $counter) {
            if (!is_array($counter)) {
                continue;
            }

            $key = trim((string) ($counter['key'] ?? ''));
            $label = trim((string) ($counter['label'] ?? ''));
            $sourceType = (string) ($counter['source_type'] ?? $counter['scope_type'] ?? 'preset');
            $savedSearchId = max(0, (int) ($counter['savedsearches_id'] ?? 0));

            if ($key === '' && $label !== '') {
                $key = self::slug($label);
            }

            if ($key === '' || $label === '') {
                continue;
            }

            if (!in_array($sourceType, ['preset', 'saved_search'], true)) {
                $sourceType = 'preset';
            }

            if ($sourceType === 'preset' && !array_key_exists($key, self::presetCounters())) {
                continue;
            }

            if ($sourceType === 'saved_search') {
                if ($savedSearchId <= 0) {
                    continue;
                }

                $key = 'saved_search_' . $savedSearchId;
            }

            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'icon' => trim((string) ($counter['icon'] ?? 'pulse:bell')) ?: 'pulse:bell',
                'color' => self::normalizeColor((string) ($counter['color'] ?? '#3b82f6')),
                'enabled' => !empty($counter['enabled']),
                'source_type' => $sourceType,
                'savedsearches_id' => $savedSearchId,
                'warning_threshold' => max(0, (int) ($counter['warning_threshold'] ?? 0)),
                'critical_threshold' => max(0, (int) ($counter['critical_threshold'] ?? 0)),
            ];
        }

        return $normalized;
    }

    private static function encodeJson(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private static function decodeJson(string $json, array $fallback): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : $fallback;
    }

    private static function normalizeColor(string $color): string
    {
        $color = trim($color);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? $color : '#3b82f6';
    }

    private static function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?: '';
        $value = trim($value, '_');

        return $value !== '' ? $value : 'counter';
    }
}
