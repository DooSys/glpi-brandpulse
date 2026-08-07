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

        if (!$config['enabled'] || $this->userId <= 0) {
            return [
                'enabled' => false,
                'refresh_interval' => $config['refresh_interval'],
                'counters' => [],
            ];
        }

        return [
            'enabled' => true,
            'refresh_interval' => $config['refresh_interval'],
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
            $count = $this->count($key);

            $counters[] = [
                'key' => $key,
                'label' => (string) ($definition['label'] ?? $key),
                'icon' => (string) ($definition['icon'] ?? 'fa-solid fa-bell'),
                'color' => (string) ($definition['color'] ?? '#3b82f6'),
                'count' => $count,
                'href' => $this->href($key, $count),
            ];
        }

        return $counters;
    }

    private function count(string $key): int
    {
        return match ($key) {
            'my_tasks' => $this->countMyTasks(),
            'my_waiting_tickets' => $this->countMyWaitingTickets(),
            'ls_microbio' => $this->countLsMicrobioTickets(),
            'my_open_tickets' => $this->countMyOpenTickets(),
            'it_tickets' => $this->countItTickets(),
            'unassigned_tickets' => $this->countUnassignedTickets(),
            default => 0,
        };
    }

    private function href(string $key, int $count): string
    {
        if ($count <= 0) {
            return '#';
        }

        return match ($key) {
            'my_tasks' => $this->ticketSearchUrl([
                ['field' => 12, 'searchtype' => 'equals', 'value' => 'notold'],
                ['field' => 95, 'searchtype' => 'equals', 'value' => $this->userId],
            ]),
            'my_waiting_tickets' => $this->ticketSearchUrl([
                ['field' => 5, 'searchtype' => 'equals', 'value' => $this->userId],
                ['field' => 12, 'searchtype' => 'equals', 'value' => 4],
            ]),
            'ls_microbio' => $this->ticketSearchUrl([
                ['field' => 12, 'searchtype' => 'equals', 'value' => 'notold'],
                ['field' => 1, 'searchtype' => 'contains', 'value' => 'LS-Microbio'],
            ]),
            'my_open_tickets' => $this->ticketSearchUrl([
                ['field' => 5, 'searchtype' => 'equals', 'value' => $this->userId],
                ['field' => 12, 'searchtype' => 'equals', 'value' => 'notold'],
            ]),
            'it_tickets' => $this->ticketSearchUrl([
                ['field' => 12, 'searchtype' => 'equals', 'value' => 'notold'],
                ['link' => 'AND NOT', 'field' => 1, 'searchtype' => 'contains', 'value' => 'Demande HPA'],
                ['link' => 'AND NOT', 'field' => 1, 'searchtype' => 'contains', 'value' => 'Création Prescripteur'],
                ['link' => 'AND NOT', 'field' => 1, 'searchtype' => 'contains', 'value' => 'Création Préleveur'],
                ['link' => 'AND NOT', 'field' => 1, 'searchtype' => 'contains', 'value' => 'Creation de Correspondant'],
                ['link' => 'AND NOT', 'field' => 1, 'searchtype' => 'contains', 'value' => 'Travaux'],
                ['link' => 'AND NOT', 'field' => 1, 'searchtype' => 'contains', 'value' => 'Achats'],
                ['link' => 'AND NOT', 'field' => 1, 'searchtype' => 'contains', 'value' => 'LS-Microbio'],
            ]),
            'unassigned_tickets' => $this->ticketSearchUrl([
                ['field' => 12, 'searchtype' => 'equals', 'value' => 'notold'],
                ['field' => 5, 'searchtype' => 'equals', 'value' => 0],
            ]),
            default => '#',
        };
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

    private function countAssignedTickets(string $statusCondition): int
    {
        return $this->countSql("\n            SELECT COUNT(DISTINCT t.id) AS counter\n            FROM glpi_tickets t\n            INNER JOIN glpi_tickets_users tu ON tu.tickets_id = t.id\n            WHERE t.is_deleted = 0\n              AND {$statusCondition}\n              AND tu.type = 2\n              AND tu.users_id = {$this->userId}\n        ");
    }

    private function countLsMicrobioTickets(): int
    {
        return $this->countSql("\n            SELECT COUNT(t.id) AS counter\n            FROM glpi_tickets t\n            WHERE t.is_deleted = 0\n              AND t.status IN (1, 2, 3, 4)\n              AND t.name LIKE '%LS-Microbio%'\n        ");
    }

    private function countItTickets(): int
    {
        $excluded = [
            'Demande HPA',
            'Création Prescripteur',
            'Création Préleveur',
            'Creation de Correspondant',
            'Travaux',
            'Achats',
            'LS-Microbio',
        ];

        $clauses = array_map(
            static fn (string $term): string => "(t.name NOT LIKE '%" . addslashes($term) . "%' OR t.name IS NULL)",
            $excluded
        );

        return $this->countSql("\n            SELECT COUNT(t.id) AS counter\n            FROM glpi_tickets t\n            WHERE t.is_deleted = 0\n              AND t.status IN (1, 2, 3, 4)\n              AND " . implode("\n              AND ", $clauses) . "\n        ");
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
