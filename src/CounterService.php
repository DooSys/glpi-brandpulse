<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse;

final class CounterService
{
    private int $userId;

    public function __construct(?int $userId = null)
    {
        $this->userId = $userId ?? (int) ($_SESSION['glpiID'] ?? 0);
    }

    public function getPayload(): array
    {
        $config = Config::values();

        if (!$config['enabled'] || !Config::isPulseAllowedInCurrentContext() || $this->userId <= 0) {
            return [
                'enabled' => false,
                'refresh_interval' => $config['refresh_interval'],
                'compact_search_enabled' => false,
                'counters' => [],
            ];
        }

        return [
            'enabled' => true,
            'refresh_interval' => $config['refresh_interval'],
            'compact_search_enabled' => $config['compact_search_enabled'],
            'counters' => $this->getCounters($config['counters']),
        ];
    }

    private function getCounters(array $definitions): array
    {
        $counters = [];

        foreach ($definitions as $definition) {
            if (empty($definition['enabled']) || empty($definition['key'])) {
                continue;
            }

            $key = (string) $definition['key'];
            $count = $this->count($definition);

            $icon = (string) ($definition['icon'] ?? 'pulse:Notifications/Bell.svg');

            $counters[] = [
                'key' => $key,
                'label' => __((string) ($definition['label'] ?? $key), 'brandpulse'),
                'icon' => $icon,
                'icon_url' => $this->iconUrl($icon),
                'color' => $this->colorForCount($definition, $count),
                'count' => $count,
                'href' => $this->href($definition, $count),
            ];
        }

        return $counters;
    }

    private function count(array $definition): int
    {
        if (($definition['source_type'] ?? 'preset') === 'saved_search') {
            return $this->countSavedSearch((int) ($definition['savedsearches_id'] ?? 0));
        }

        return $this->countSearch('Ticket', $this->presetSearchParams((string) ($definition['key'] ?? '')));
    }

    private function href(array $definition, int $count): string
    {
        if ($count <= 0) {
            return '#';
        }

        if (($definition['source_type'] ?? 'preset') === 'saved_search') {
            return $this->savedSearchUrl((int) ($definition['savedsearches_id'] ?? 0));
        }

        return $this->searchUrl('Ticket', $this->presetSearchParams((string) ($definition['key'] ?? '')));
    }

    private function countSavedSearch(int $savedSearchId): int
    {
        $search = $this->savedSearchData($savedSearchId);

        if ($search === null) {
            return 0;
        }

        return $this->countSearch($search['itemtype'], $search['params']);
    }

    private function savedSearchUrl(int $savedSearchId): string
    {
        $search = $this->savedSearchData($savedSearchId);

        if ($search === null) {
            return '#';
        }

        return $this->searchUrl($search['itemtype'], $search['params']);
    }

    private function savedSearchData(int $savedSearchId): ?array
    {
        if ($savedSearchId <= 0 || !class_exists(\SavedSearch::class)) {
            return null;
        }

        $savedSearch = new \SavedSearch();
        if (!$savedSearch->getFromDB($savedSearchId) || !$this->canUseSavedSearch($savedSearch)) {
            return null;
        }

        $itemtype = (string) ($savedSearch->fields['itemtype'] ?? 'Ticket');
        if ($itemtype === '' || !class_exists($itemtype)) {
            return null;
        }

        return [
            'itemtype' => $itemtype,
            'params' => $this->paramsFromQuery((string) ($savedSearch->fields['query'] ?? '')),
        ];
    }

    private function countSearch(string $itemtype, array $params): int
    {
        if ($params === [] || !class_exists(\Search::class)) {
            return 0;
        }

        $bufferLevel = ob_get_level();
        ob_start();
        set_error_handler(static fn (): bool => true);

        try {
            $params = array_replace([
                'is_deleted' => 0,
                'start' => 0,
                'list_limit' => 1,
                'display_type' => \Search::HTML_OUTPUT,
                'reset' => 'reset',
            ], $params);

            if (method_exists(\Search::class, 'manageParams')) {
                $managedParams = \Search::manageParams($itemtype, $params, false, false);
                if (is_array($managedParams)) {
                    $params = $managedParams;
                }
            }

            $params['start'] = 0;
            $params['list_limit'] = 1;
            $params['display_type'] = \Search::HTML_OUTPUT;

            $data = \Search::getDatas($itemtype, $params, [2]);

            return (int) ($data['data']['totalcount'] ?? 0);
        } catch (\Throwable) {
            return 0;
        } finally {
            restore_error_handler();
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }
    }

    private function presetSearchParams(string $key): array
    {
        return match ($key) {
            'my_tasks' => [
                'criteria' => [
                    ['field' => 12, 'searchtype' => 'equals', 'value' => 'notold'],
                    ['field' => 95, 'searchtype' => 'equals', 'value' => $this->userId],
                ],
            ],
            'my_waiting_tickets' => [
                'criteria' => [
                    ['field' => 5, 'searchtype' => 'equals', 'value' => $this->userId],
                    ['field' => 12, 'searchtype' => 'equals', 'value' => 4],
                ],
            ],
            'my_open_tickets' => [
                'criteria' => [
                    ['field' => 5, 'searchtype' => 'equals', 'value' => $this->userId],
                    ['field' => 12, 'searchtype' => 'equals', 'value' => 'notold'],
                ],
            ],
            'all_open_tickets' => [
                'criteria' => [
                    ['field' => 12, 'searchtype' => 'equals', 'value' => 'notold'],
                ],
            ],
            'unassigned_tickets' => [
                'criteria' => [
                    ['field' => 12, 'searchtype' => 'equals', 'value' => 'notold'],
                    ['field' => 5, 'searchtype' => 'equals', 'value' => 0],
                ],
            ],
            default => [],
        };
    }

    private function searchUrl(string $itemtype, array $params): string
    {
        global $CFG_GLPI;

        if ($params === []) {
            return '#';
        }

        $params = array_replace([
            'is_deleted' => 0,
            'as_map' => 0,
            'browse' => 0,
            'itemtype' => $itemtype,
            'start' => 0,
            'sort' => [0 => 19],
            'order' => [0 => 'DESC'],
        ], $params);

        $path = '/front/ticket.php';
        if (class_exists($itemtype) && method_exists($itemtype, 'getSearchURL')) {
            $path = (string) $itemtype::getSearchURL();
            $rootDoc = (string) ($CFG_GLPI['root_doc'] ?? '');
            if ($rootDoc !== '' && str_starts_with($path, $rootDoc)) {
                $path = substr($path, strlen($rootDoc));
            }
        }

        return ($CFG_GLPI['root_doc'] ?? '') . $path . '?' . http_build_query($params);
    }

    private function paramsFromQuery(string $query): array
    {
        $query = html_entity_decode(trim($query), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $query = ltrim($query, '?&');
        $params = [];
        parse_str($query, $params);

        if (!is_array($params)) {
            return [];
        }

        unset($params['_glpi_csrf_token'], $params['csrf_token']);

        return $params;
    }

    private function canUseSavedSearch(\SavedSearch $savedSearch): bool
    {
        $isPrivate = (int) ($savedSearch->fields['is_private'] ?? 1) === 1;
        $ownerId = (int) ($savedSearch->fields['users_id'] ?? 0);

        if ($isPrivate && $ownerId !== $this->userId) {
            return false;
        }

        return (int) ($savedSearch->fields['type'] ?? \SavedSearch::SEARCH) === \SavedSearch::SEARCH;
    }

    private function iconUrl(string $icon): string
    {
        if ($icon === '') {
            $icon = 'pulse:Notifications/Bell.svg';
        }

        if (str_starts_with($icon, 'pulse:')) {
            $path = substr($icon, 6);
            if ($path === '') {
                $path = 'Notifications/Bell.svg';
            }
            if (!str_ends_with($path, '.svg')) {
                $path .= '.svg';
            }

            return $this->pluginWebBase() . '/icons/pulse/' . implode('/', array_map('rawurlencode', explode('/', $path)));
        }

        return $icon;
    }

    private function pluginWebBase(): string
    {
        global $CFG_GLPI;

        if (class_exists(\Plugin::class) && method_exists(\Plugin::class, 'getWebDir')) {
            try {
                $webDir = (string) \Plugin::getWebDir('brandpulse');
                if ($webDir !== '') {
                    return rtrim($webDir, '/');
                }
            } catch (\Throwable) {
            }
        }

        return rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/') . '/plugins/brandpulse';
    }

    private function colorForCount(array $definition, int $count): string
    {
        $warning = (int) ($definition['warning_threshold'] ?? 0);
        $critical = (int) ($definition['critical_threshold'] ?? 0);

        if ($critical > 0 && $count >= $critical) {
            return '#ff3d2a';
        }

        if ($warning > 0 && $count >= $warning) {
            return '#f59f00';
        }

        return (string) ($definition['color'] ?? '#3b82f6');
    }
}
