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

            $counters[] = [
                'key' => $key,
                'label' => __((string) ($definition['label'] ?? $key), 'brandpulse'),
                'icon' => (string) ($definition['icon'] ?? 'pulse:bell'),
                'color' => $this->colorForCount($definition, $count),
                'count' => $count,
                'href' => $this->href($definition, $count),
            ];
        }

        return $counters;
    }

    private function count(array $definition): int
    {
        $scopeType = (string) ($definition['scope_type'] ?? 'preset');

        return match ($scopeType) {
            'category' => $this->countTicketsByCategory((int) ($definition['scope_id'] ?? 0)),
            'group' => $this->countTicketsByGroup((int) ($definition['scope_id'] ?? 0)),
            default => $this->countPreset((string) ($definition['key'] ?? '')),
        };
    }

    private function countPreset(string $key): int
    {
        return match ($key) {
            'my_tasks' => $this->countMyTasks(),
            'my_waiting_tickets' => $this->countMyWaitingTickets(),
            'my_open_tickets' => $this->countMyOpenTickets(),
            'all_open_tickets' => $this->countAllOpenTickets(),
            'unassigned_tickets' => $this->countUnassignedTickets(),
            default => 0,
        };
    }

    private function href(array $definition, int $count): string
    {
        if ($count <= 0) {
            return '#';
        }

        $scopeType = (string) ($definition['scope_type'] ?? 'preset');
        $scopeId = (int) ($definition['scope_id'] ?? 0);

        if ($scopeType === 'category' && $scopeId > 0) {
            return $this->ticketSearchUrl([
                ['field' => 12, 'searchtype' => 'equals', 'value' => 'notold'],
                ['field' => 7, 'searchtype' => 'equals', 'value' => $scopeId],
            ]);
        }

        if ($scopeType === 'group' && $scopeId > 0) {
            return $this->ticketSearchUrl([
                ['field' => 12, 'searchtype' => 'equals', 'value' => 'notold'],
                ['field' => 8, 'searchtype' => 'equals', 'value' => $scopeId],
            ]);
        }

        return match ((string) ($definition['key'] ?? '')) {
            'my_tasks' => $this->ticketSearchUrl([
                ['field' => 12, 'searchtype' => 'equals', 'value' => 'notold'],
                ['field' => 95, 'searchtype' => 'equals', 'value' => $this->userId],
            ]),
            'my_waiting_tickets' => $this->ticketSearchUrl([
                ['field' => 5, 'searchtype' => 'equals', 'value' => $this->userId],
                ['field' => 12, 'searchtype' => 'equals', 'value' => 4],
            ]),
            'my_open_tickets' => $this->ticketSearchUrl([
                ['field' => 5, 'searchtype' => 'equals', 'value' => $this->userId],
                ['field' => 12, 'searchtype' => 'equals', 'value' => 'notold'],
            ]),
            'all_open_tickets' => $this->ticketSearchUrl([
                ['field' => 12, 'searchtype' => 'equals', 'value' => 'notold'],
            ]),
            'unassigned_tickets' => $this->ticketSearchUrl([
                ['field' => 12, 'searchtype' => 'equals', 'value' => 'notold'],
                ['field' => 5, 'searchtype' => 'equals', 'value' => 0],
            ]),
            default => '#',
        };
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

    private function countMyTasks(): int
    {
        return $this->countSql("\n            SELECT COUNT(tt.id) AS counter\n            FROM glpi_tickettasks tt\n            INNER JOIN glpi_tickets t ON t.id = tt.tickets_id\n            WHERE tt.users_id_tech = {$this->userId}\n              AND tt.state <> 2\n              AND t.is_deleted = 0\n              AND t.status IN (1, 2, 3, 4)\n        ");
    }

    private function countMyWaitingTickets(): int
    {
        return $this->countAssignedTickets('t.status = 4');
    }

    private function countMyOpenTickets(): int
    {
        return $this->countAssignedTickets('t.status NOT IN (5, 6)');
    }

    private function countAllOpenTickets(): int
    {
        return $this->countSql("\n            SELECT COUNT(t.id) AS counter\n            FROM glpi_tickets t\n            WHERE t.is_deleted = 0\n              AND t.status IN (1, 2, 3, 4)\n        ");
    }

    private function countAssignedTickets(string $statusCondition): int
    {
        return $this->countSql("\n            SELECT COUNT(DISTINCT t.id) AS counter\n            FROM glpi_tickets t\n            INNER JOIN glpi_tickets_users tu ON tu.tickets_id = t.id\n            WHERE t.is_deleted = 0\n              AND {$statusCondition}\n              AND tu.type = 2\n              AND tu.users_id = {$this->userId}\n        ");
    }

    private function countTicketsByCategory(int $categoryId): int
    {
        if ($categoryId <= 0) {
            return 0;
        }

        return $this->countSql("\n            SELECT COUNT(t.id) AS counter\n            FROM glpi_tickets t\n            WHERE t.is_deleted = 0\n              AND t.status IN (1, 2, 3, 4)\n              AND t.itilcategories_id = {$categoryId}\n        ");
    }

    private function countTicketsByGroup(int $groupId): int
    {
        if ($groupId <= 0) {
            return 0;
        }

        return $this->countSql("\n            SELECT COUNT(DISTINCT t.id) AS counter\n            FROM glpi_tickets t\n            INNER JOIN glpi_groups_tickets gt ON gt.tickets_id = t.id\n            WHERE t.is_deleted = 0\n              AND t.status IN (1, 2, 3, 4)\n              AND gt.groups_id = {$groupId}\n        ");
    }

    private function countUnassignedTickets(): int
    {
        return $this->countSql("\n            SELECT COUNT(DISTINCT t.id) AS counter\n            FROM glpi_tickets t\n            LEFT JOIN glpi_tickets_users tu ON tu.tickets_id = t.id AND tu.type = 2\n            WHERE t.is_deleted = 0\n              AND t.status IN (1, 2, 3, 4)\n              AND tu.users_id IS NULL\n        ");
    }

    private function countSql(string $sql): int
    {
        global $DB;

        if (!isset($DB)) {
            return 0;
        }

        try {
            $result = $DB->query($sql);

            if (!$result) {
                return 0;
            }

            return (int) $DB->result($result, 0, 'counter');
        } catch (\Throwable) {
            return 0;
        }
    }

    private function ticketSearchUrl(array $criteria): string
    {
        global $CFG_GLPI;

        $normalized = [];
        foreach (array_values($criteria) as $index => $criterion) {
            $normalized[$index] = [
                'link' => $criterion['link'] ?? 'AND',
                'field' => $criterion['field'],
                'searchtype' => $criterion['searchtype'],
                'value' => $criterion['value'],
            ];
        }

        $query = http_build_query([
            'is_deleted' => 0,
            'as_map' => 0,
            'browse' => 0,
            'criteria' => $normalized,
            'itemtype' => 'Ticket',
            'start' => 0,
            'sort' => [0 => 19],
            'order' => [0 => 'DESC'],
        ]);

        return ($CFG_GLPI['root_doc'] ?? '') . '/front/ticket.php?' . $query;
    }
}
