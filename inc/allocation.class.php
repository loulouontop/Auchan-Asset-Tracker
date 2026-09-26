<?php

/**
 * Allocation history + confirmation workflow.
 */
class PluginAuchanassettrackerAllocation extends CommonDBTM
{
    public static $rightname = 'plugin_auchanassettracker';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_RETURNED  = 'returned';

    public static function getTypeName($nb = 0): string
    {
        return _n('Allocation', 'Allocations', $nb, 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_allocations';
    }

    public static function getSectorizedDetails(): array
    {
        return ['assets', 'PluginAuchanassettrackerMenu', PluginAuchanassettrackerMenu::MENU_ALLOCATION];
    }

    public static function getFormURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/allocation.form.php';
    }

    /**
     * Start allocation: equipment Available → Awaiting validation.
     */
    public static function initiate(int $equipment_id, int $users_id_recipient): bool
    {
        if (!PluginAuchanassettrackerRighthelper::canAllocate()) {
            Session::addMessageAfterRedirect(__('Insufficient rights.'), false, ERROR);
            return false;
        }

        $eq = new PluginAuchanassettrackerEquipment();
        if (!$eq->getFromDB($equipment_id)) {
            return false;
        }

        $loc = (int) ($eq->fields['locations_id'] ?? 0);
        if (!PluginAuchanassettrackerRighthelper::canAccessLocation($loc)) {
            Session::addMessageAfterRedirect(
                __('You cannot allocate equipment from another location.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        if (($eq->fields['status'] ?? '') !== PluginAuchanassettrackerEquipment::STATUS_AVAILABLE) {
            Session::addMessageAfterRedirect(
                __('Only available equipment can be allocated.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        $prev_container = (int) ($eq->fields['plugin_auchanassettracker_containers_id'] ?? 0);
        if ($prev_container <= 0) {
            Session::addMessageAfterRedirect(
                __('A physical container is mandatory for equipment in stock.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        if (self::hasPendingForEquipment($equipment_id)) {
            Session::addMessageAfterRedirect(
                __('Cancel the current pending allocation first.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        $alloc = new self();
        $aid = $alloc->add([
            'plugin_auchanassettracker_equipments_id' => $equipment_id,
            'users_id_recipient'  => $users_id_recipient,
            'users_id_allocator'  => (int) Session::getLoginUserID(),
            'plugin_auchanassettracker_containers_id_previous' => $prev_container,
            'allocation_date'     => $now,
            'allocation_status'   => self::STATUS_PENDING,
            'date_creation'       => $now,
            'date_mod'            => $now,
        ]);

        if (!$aid) {
            return false;
        }

        $ok = $eq->update([
            'id'     => $equipment_id,
            'status' => PluginAuchanassettrackerEquipment::STATUS_AWAITING_VALIDATION,
            'users_id' => $users_id_recipient,
            'plugin_auchanassettracker_containers_id' => 0,
            '_aat_skip_container_check' => 1,
        ]);

        if ($ok) {
            PluginAuchanassettrackerAuditlog::record(
                'allocation_initiate',
                self::class,
                (int) $aid,
                sprintf('equipment=%d user=%d', $equipment_id, $users_id_recipient)
            );
            PluginAuchanassettrackerMailhelper::notifyAllocationPending($users_id_recipient, $equipment_id);
        }

        return (bool) $ok;
    }

    public static function confirm(int $allocation_id): bool
    {
        $alloc = new self();
        if (!$alloc->getFromDB($allocation_id)) {
            return false;
        }

        $uid = (int) Session::getLoginUserID();
        if ((int) ($alloc->fields['users_id_recipient'] ?? 0) !== $uid
            && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            Session::addMessageAfterRedirect(__('Insufficient rights.'), false, ERROR);
            return false;
        }

        if (($alloc->fields['allocation_status'] ?? '') !== self::STATUS_PENDING) {
            return false;
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $eq_id = (int) $alloc->fields['plugin_auchanassettracker_equipments_id'];
        $recipient = (int) $alloc->fields['users_id_recipient'];

        $alloc->update([
            'id'                  => $allocation_id,
            'allocation_status'   => self::STATUS_CONFIRMED,
            'confirmation_date'   => $now,
            'date_mod'            => $now,
        ]);

        $eq = new PluginAuchanassettrackerEquipment();
        $ok = $eq->update([
            'id'     => $eq_id,
            'status' => PluginAuchanassettrackerEquipment::STATUS_ALLOCATED,
            'users_id' => $recipient,
            'plugin_auchanassettracker_containers_id' => 0,
            '_aat_skip_container_check' => 1,
        ]);

        if ($ok && $eq->getFromDB($eq_id)) {
            // Ensure a linked native GLPI asset exists, then set its owner.
            if ((int) ($eq->fields['items_id'] ?? 0) <= 0) {
                $asset_id = PluginAuchanassettrackerEquipment::createLinkedGlpiAsset($eq->fields);
                if ($asset_id > 0) {
                    global $DB;
                    $DB->update(PluginAuchanassettrackerEquipment::getTable(), [
                        'items_id' => $asset_id,
                    ], ['id' => $eq_id]);
                    $eq->fields['items_id'] = $asset_id;
                }
            }
            PluginAuchanassettrackerEquipment::syncGlpiAssetOwner($eq->fields, $recipient);
        }

        PluginAuchanassettrackerAuditlog::record(
            'allocation_confirm',
            self::class,
            $allocation_id,
            'equipment=' . $eq_id
        );

        return (bool) $ok;
    }

    /**
     * Did not receive → Available and restore previous container.
     */
    public static function reject(int $allocation_id, int $container_id = 0): bool
    {
        $alloc = new self();
        if (!$alloc->getFromDB($allocation_id)) {
            return false;
        }

        $uid = (int) Session::getLoginUserID();
        $is_recipient = (int) ($alloc->fields['users_id_recipient'] ?? 0) === $uid;
        if (!$is_recipient && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            Session::addMessageAfterRedirect(__('Insufficient rights.'), false, ERROR);
            return false;
        }

        if (($alloc->fields['allocation_status'] ?? '') !== self::STATUS_PENDING) {
            return false;
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $eq_id = (int) $alloc->fields['plugin_auchanassettracker_equipments_id'];

        $eq = new PluginAuchanassettrackerEquipment();
        if (!$eq->getFromDB($eq_id)) {
            return false;
        }

        $loc = (int) ($eq->fields['locations_id'] ?? 0);
        $prev = (int) ($alloc->fields['plugin_auchanassettracker_containers_id_previous'] ?? 0);
        if ($container_id <= 0) {
            $container_id = $prev;
        }

        $update = [
            'id'       => $eq_id,
            'status'   => PluginAuchanassettrackerEquipment::STATUS_AVAILABLE,
            'users_id' => 0,
            '_aat_skip_container_check' => 1,
        ];

        if ($container_id > 0) {
            if (!PluginAuchanassettrackerEquipment::containerBelongsToLocation($container_id, $loc)) {
                // Previous shelf missing/inactive — leave empty for manager.
                $update['plugin_auchanassettracker_containers_id'] = 0;
            } else {
                $update['plugin_auchanassettracker_containers_id'] = $container_id;
            }
        } else {
            $update['plugin_auchanassettracker_containers_id'] = 0;
        }

        $alloc->update([
            'id'                => $allocation_id,
            'allocation_status' => self::STATUS_REJECTED,
            'confirmation_date' => $now,
            'date_mod'          => $now,
        ]);

        $ok = $eq->update($update);

        if ($ok && $eq->getFromDB($eq_id)) {
            PluginAuchanassettrackerEquipment::syncGlpiAssetOwner($eq->fields, 0);
        }

        PluginAuchanassettrackerAuditlog::record(
            'allocation_reject',
            self::class,
            $allocation_id,
            'equipment=' . $eq_id
        );

        $allocator = (int) ($alloc->fields['users_id_allocator'] ?? 0);
        if ($allocator > 0) {
            PluginAuchanassettrackerMailhelper::notifyAllocationRejected($allocator, $eq_id);
        }

        return (bool) $ok;
    }

    public static function hasPendingForEquipment(int $equipment_id): bool
    {
        global $DB;
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'plugin_auchanassettracker_equipments_id' => $equipment_id,
                'allocation_status' => self::STATUS_PENDING,
            ],
            'LIMIT' => 1,
        ]) as $_) {
            return true;
        }
        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getPendingForUser(int $users_id): array
    {
        global $DB;
        $rows = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'users_id_recipient' => $users_id,
                'allocation_status'  => self::STATUS_PENDING,
            ],
            'ORDER' => 'allocation_date ASC',
        ]) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getOverduePending(?int $locations_id = null): array
    {
        global $DB;

        $days = PluginAuchanassettrackerConfig::getAllocationConfirmDays();
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . max(1, $days) . ' days'));

        $rows = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'allocation_status' => self::STATUS_PENDING,
                ['allocation_date' => ['<', $cutoff]],
            ],
            'ORDER' => 'allocation_date ASC',
        ]) as $row) {
            $eq = new PluginAuchanassettrackerEquipment();
            if (!$eq->getFromDB((int) $row['plugin_auchanassettracker_equipments_id'])) {
                continue;
            }
            if ($locations_id !== null && (int) $eq->fields['locations_id'] !== $locations_id) {
                continue;
            }
            $row['equipment_name'] = $eq->fields['name'] ?? '';
            $row['locations_id'] = (int) ($eq->fields['locations_id'] ?? 0);
            $row['serial'] = $eq->fields['serial'] ?? '';
            $row['itemtype'] = $eq->fields['itemtype'] ?? '';
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getHistory(?int $locations_id = null, int $limit = 50): array
    {
        global $DB;
        $rows = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'ORDER' => 'allocation_date DESC',
            'LIMIT' => $limit,
        ]) as $row) {
            $eq = new PluginAuchanassettrackerEquipment();
            if (!$eq->getFromDB((int) $row['plugin_auchanassettracker_equipments_id'])) {
                continue;
            }
            if ($locations_id !== null && (int) $eq->fields['locations_id'] !== $locations_id) {
                continue;
            }
            $row['equipment_name'] = $eq->fields['name'] ?? '';
            $row['serial'] = $eq->fields['serial'] ?? '';
            $row['itemtype'] = $eq->fields['itemtype'] ?? '';
            $row['equipments_id'] = (int) $eq->getID();
            $rows[] = $row;
        }
        return $rows;
    }

    public static function getStatusLabel(string $status): string
    {
        $map = [
            self::STATUS_PENDING   => __('Pending confirmation', 'auchanassettracker'),
            self::STATUS_CONFIRMED => __('Confirmed', 'auchanassettracker'),
            self::STATUS_REJECTED  => __('Did not receive', 'auchanassettracker'),
            self::STATUS_RETURNED  => __('Returned', 'auchanassettracker'),
        ];
        return $map[$status] ?? $status;
    }

    /**
     * Plugin equipment currently with a user.
     *
     * @return list<array<string, mixed>>
     */
    public static function getUserEquipment(int $users_id): array
    {
        return array_merge(
            PluginAuchanassettrackerEquipment::findByStatus(
                PluginAuchanassettrackerEquipment::STATUS_ALLOCATED,
                null,
                $users_id
            ),
            PluginAuchanassettrackerEquipment::findByStatus(
                PluginAuchanassettrackerEquipment::STATUS_AWAITING_VALIDATION,
                null,
                $users_id
            )
        );
    }

    /**
     * Plugin gear + native GLPI assets assigned to the user.
     *
     * @return list<array<string, mixed>>
     */
    public static function getCurrentGearForUser(int $users_id): array
    {
        $plugin = self::getUserEquipment($users_id);
        foreach ($plugin as &$row) {
            $row['source'] = 'plugin';
        }
        unset($row);

        $native = PluginAuchanassettrackerEquipment::findGlpiAssetsForUser($users_id);
        // Avoid duplicate display when the same asset is linked from plugin stock.
        $linked = [];
        foreach ($plugin as $p) {
            $it = (string) ($p['itemtype'] ?? '');
            $iid = (int) ($p['items_id'] ?? 0);
            if ($it !== '' && $iid > 0) {
                $linked[$it . ':' . $iid] = true;
            }
        }

        $out = $plugin;
        foreach ($native as $n) {
            $key = ($n['itemtype'] ?? '') . ':' . (int) ($n['items_id'] ?? 0);
            if (isset($linked[$key])) {
                continue;
            }
            $n['source'] = 'glpi';
            $out[] = $n;
        }
        return $out;
    }

    /**
     * User IDs of managers who should see location-scoped alerts.
     *
     * @return list<int>
     */
    public static function getManagerUserIds(?int $locations_id = null): array
    {
        global $DB;

        if (!$DB->tableExists(PluginAuchanassettrackerProfile::getTable())
            || !$DB->tableExists('glpi_profiles_users')) {
            return [];
        }

        $profile_ids = [];
        foreach ($DB->request([
            'FROM'  => PluginAuchanassettrackerProfile::getTable(),
            'WHERE' => [
                'role' => [
                    PluginAuchanassettrackerRighthelper::ROLE_CENTRAL_ADMIN,
                    PluginAuchanassettrackerRighthelper::ROLE_LOCATION_MANAGER,
                ],
            ],
        ]) as $row) {
            $role = (string) ($row['role'] ?? '');
            $loc  = (int) ($row['locations_id'] ?? 0);
            if ($role === PluginAuchanassettrackerRighthelper::ROLE_CENTRAL_ADMIN) {
                $profile_ids[(int) $row['profiles_id']] = true;
                continue;
            }
            if ($locations_id === null || $loc === 0 || $loc === $locations_id) {
                $profile_ids[(int) $row['profiles_id']] = true;
            }
        }

        if ($profile_ids === []) {
            return [];
        }

        $uids = [];
        foreach ($DB->request([
            'SELECT' => ['users_id'],
            'FROM'   => 'glpi_profiles_users',
            'WHERE'  => ['profiles_id' => array_keys($profile_ids)],
        ]) as $row) {
            $uid = (int) ($row['users_id'] ?? 0);
            if ($uid > 0) {
                $uids[$uid] = true;
            }
        }
        return array_keys($uids);
    }

    /**
     * Create in-app notices for managers/allocators about late confirmations.
     */
    public static function notifyOverdueManagers(?int $locations_id = null): void
    {
        $overdue = self::getOverduePending($locations_id);
        if ($overdue === []) {
            return;
        }

        $base = plugin_auchanassettracker_web_dir();
        foreach ($overdue as $row) {
            $eq_id = (int) ($row['plugin_auchanassettracker_equipments_id'] ?? 0);
            $label = trim(($row['equipment_name'] ?? '') . ' [' . ($row['serial'] ?? '') . ']');
            $msg = sprintf(
                __('Late confirmation (calendar days): %s', 'auchanassettracker'),
                $label !== ' []' ? $label : ('#' . $eq_id)
            );
            $link = $base . '/front/allocation.form.php';
            $loc = (int) ($row['locations_id'] ?? 0);

            $targets = self::getManagerUserIds($loc > 0 ? $loc : null);
            $allocator = (int) ($row['users_id_allocator'] ?? 0);
            if ($allocator > 0) {
                $targets[] = $allocator;
            }
            $targets = array_values(array_unique(array_filter($targets)));

            foreach ($targets as $uid) {
                if (!PluginAuchanassettrackerNotice::hasSimilarUnread($uid, $msg)) {
                    PluginAuchanassettrackerNotice::addForUser($uid, $msg, $link);
                }
            }
        }
    }

    /**
     * Render Active alerts block (late confirmations + needs container).
     */
    public static function displayActiveAlerts(?int $locations_id = null): void
    {
        $base = plugin_auchanassettracker_web_dir();
        self::notifyOverdueManagers($locations_id);

        $overdue = self::getOverduePending($locations_id);
        $needs_container = PluginAuchanassettrackerEquipment::findNeedsContainer($locations_id);
        if ($overdue === [] && $needs_container === []) {
            return;
        }

        echo "<div class='alert alert-warning aat-alert-block'>";
        echo "<div class='aat-alert-title'>" . __('Active alerts', 'auchanassettracker') . "</div>";
        if ($overdue !== []) {
            echo "<p class='mb-1 fw-semibold'>"
                . __('Late confirmations (calendar days)', 'auchanassettracker') . "</p>";
            echo "<ul>";
            foreach ($overdue as $row) {
                $eid = (int) ($row['plugin_auchanassettracker_equipments_id'] ?? 0);
                echo "<li><a href='" . $base . "/front/equipment.form.php?id=$eid'>"
                    . Html::entities_deep(($row['equipment_name'] ?? '') . ' [' . ($row['serial'] ?? '') . ']')
                    . "</a></li>";
            }
            echo "</ul>";
        }
        if ($needs_container !== []) {
            echo "<p class='mb-1 fw-semibold'>"
                . __('Needs a container (rejected)', 'auchanassettracker') . "</p>";
            echo "<p class='text-muted small mb-1'>"
                . __('These items were marked as not received and need a physical container.', 'auchanassettracker')
                . "</p>";
            echo "<ul class='mb-0'>";
            foreach ($needs_container as $eq) {
                echo "<li><a href='" . $base . "/front/equipment.form.php?id=" . (int) $eq['id'] . "'>"
                    . Html::entities_deep(($eq['name'] ?? '') . ' [' . ($eq['serial'] ?? '') . ']')
                    . "</a></li>";
            }
            echo "</ul>";
        }
        echo "</div>";
    }
}
