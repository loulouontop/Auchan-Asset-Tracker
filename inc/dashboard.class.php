<?php

/**
 * Role-aware dashboard data builders.
 */
class PluginAuchanassettrackerDashboard
{
    public static function getAdminData(): array
    {
        $counts = PluginAuchanassettrackerEquipment::countByStatus(null);
        $written_off_30 = self::countWrittenOffLastDays(30);
        $total = array_sum($counts);

        return [
            'indicators' => [
                'total'          => $total,
                'available'      => $counts[PluginAuchanassettrackerEquipment::STATUS_AVAILABLE] ?? 0,
                'allocated'      => $counts[PluginAuchanassettrackerEquipment::STATUS_ALLOCATED] ?? 0,
                'in_service'     => $counts[PluginAuchanassettrackerEquipment::STATUS_IN_SERVICE] ?? 0,
                'in_transit'     => $counts[PluginAuchanassettrackerEquipment::STATUS_IN_TRANSIT] ?? 0,
                'written_off_30' => $written_off_30,
            ],
            'alerts' => [
                'service'     => PluginAuchanassettrackerTickethook::getOverdueInService(null),
                'allocations' => PluginAuchanassettrackerAllocation::getOverduePending(null),
                'transfers'   => PluginAuchanassettrackerTransfer::getOverdue(null),
            ],
            'stock_by_location' => self::stockByLocation(),
            'recent'            => PluginAuchanassettrackerAuditlog::getRecent(15),
        ];
    }

    public static function getManagerData(int $locations_id): array
    {
        $containers = PluginAuchanassettrackerContainer::listForLocation($locations_id);
        foreach ($containers as &$c) {
            $c['available_count'] = count(PluginAuchanassettrackerEquipment::listInContainer((int) $c['id']));
        }
        unset($c);

        return [
            'containers' => $containers,
            'to_resolve' => [
                'allocations' => PluginAuchanassettrackerAllocation::getOverduePending($locations_id),
                'transfers'   => PluginAuchanassettrackerTransfer::getPendingForLocation($locations_id),
                'needs_container' => self::availableWithoutContainer($locations_id),
            ],
            'in_movement' => [
                'in_service' => PluginAuchanassettrackerEquipment::findByStatus(
                    PluginAuchanassettrackerEquipment::STATUS_IN_SERVICE,
                    $locations_id
                ),
                'in_transit_out' => PluginAuchanassettrackerEquipment::findByStatus(
                    PluginAuchanassettrackerEquipment::STATUS_IN_TRANSIT,
                    $locations_id
                ),
            ],
            'alerts' => [
                'service'     => PluginAuchanassettrackerTickethook::getOverdueInService($locations_id),
                'allocations' => PluginAuchanassettrackerAllocation::getOverduePending($locations_id),
            ],
        ];
    }

    public static function getUserData(int $users_id): array
    {
        return [
            'pending'   => PluginAuchanassettrackerAllocation::getPendingForUser($users_id),
            'my_equipment' => PluginAuchanassettrackerAllocation::getCurrentGearForUser($users_id),
        ];
    }

    public static function countWrittenOffLastDays(int $days): int
    {
        global $DB;
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
        foreach ($DB->request([
            'COUNT' => 'cpt',
            'FROM'  => PluginAuchanassettrackerEquipment::getTable(),
            'WHERE' => [
                'status' => PluginAuchanassettrackerEquipment::STATUS_WRITTEN_OFF,
                ['final_date' => ['>=', $cutoff]],
            ],
        ]) as $row) {
            return (int) ($row['cpt'] ?? 0);
        }
        return 0;
    }

    public static function stockByLocation(): array
    {
        global $DB;
        $by = [];
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerEquipment::getTable(),
            'WHERE' => ['is_deleted' => 0],
        ]) as $row) {
            $lid = (int) $row['locations_id'];
            if (!isset($by[$lid])) {
                $by[$lid] = [
                    'locations_id' => $lid,
                    'available'    => 0,
                    'allocated'    => 0,
                    'in_service'   => 0,
                    'in_transit'   => 0,
                ];
            }
            $st = (string) $row['status'];
            if (isset($by[$lid][$st])) {
                $by[$lid][$st]++;
            }
        }
        return array_values($by);
    }

    public static function availableWithoutContainer(int $locations_id): array
    {
        global $DB;
        $rows = [];
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerEquipment::getTable(),
            'WHERE' => [
                'locations_id' => $locations_id,
                'status'       => PluginAuchanassettrackerEquipment::STATUS_AVAILABLE,
                'plugin_auchanassettracker_containers_id' => 0,
                'is_deleted'   => 0,
            ],
        ]) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    public static function daysSince(?string $datetime): int
    {
        if (!$datetime) {
            return 0;
        }
        $t = strtotime($datetime);
        if ($t === false) {
            return 0;
        }
        return (int) floor((time() - $t) / 86400);
    }
}
