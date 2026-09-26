<?php

/**
 * Tracked equipment (Sprint 2: stock → allocation → user confirm).
 */
class PluginAuchanassettrackerEquipment extends CommonDBTM
{
    public static $rightname = 'plugin_auchanassettracker';

    public const STATUS_AVAILABLE           = 'available';
    public const STATUS_AWAITING_VALIDATION = 'awaiting_validation';
    public const STATUS_ALLOCATED           = 'allocated';

    public static function getTypeName($nb = 0): string
    {
        return _n('Equipment', 'Equipment', $nb, 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_equipments';
    }

    public static function getIcon(): string
    {
        return 'ti ti-device-desktop';
    }

    public static function getSectorizedDetails(): array
    {
        return ['assets', PluginAuchanassettrackerMenu::MENU_EQUIPMENT];
    }

    public static function getFormURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/equipment.form.php';
    }

    public static function getSearchURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/equipment.php';
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_AVAILABLE           => __('Available', 'auchanassettracker'),
            self::STATUS_AWAITING_VALIDATION => __('Awaiting validation', 'auchanassettracker'),
            self::STATUS_ALLOCATED           => __('Allocated', 'auchanassettracker'),
        ];
    }

    public static function getStatusLabel(string $status): string
    {
        $all = self::getStatuses();
        return $all[$status] ?? $status;
    }

    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        return $ong;
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = ['id' => 'common', 'name' => self::getTypeName(1)];

        $tab[] = [
            'id'            => 1,
            'table'         => self::getTable(),
            'field'         => 'name',
            'name'          => __('Name'),
            'datatype'      => 'itemlink',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'       => 2,
            'table'    => self::getTable(),
            'field'    => 'serial',
            'name'     => __('Serial number'),
            'datatype' => 'string',
        ];
        $tab[] = [
            'id'       => 3,
            'table'    => self::getTable(),
            'field'    => 'model',
            'name'     => __('Model'),
            'datatype' => 'string',
        ];
        $tab[] = [
            'id'       => 4,
            'table'    => self::getTable(),
            'field'    => 'status',
            'name'     => __('Status'),
            'datatype' => 'specific',
            'searchtype' => ['equals', 'notequals'],
        ];
        $tab[] = [
            'id'        => 5,
            'table'     => 'glpi_locations',
            'field'     => 'completename',
            'name'      => __('Location'),
            'datatype'  => 'dropdown',
            'linkfield' => 'locations_id',
        ];
        $tab[] = [
            'id'        => 6,
            'table'     => 'glpi_users',
            'field'     => 'name',
            'name'      => __('Allocated user', 'auchanassettracker'),
            'datatype'  => 'dropdown',
            'linkfield' => 'users_id',
        ];
        $tab[] = [
            'id'        => 7,
            'table'     => PluginAuchanassettrackerContainer::getTable(),
            'field'     => 'name',
            'name'      => __('Physical container', 'auchanassettracker'),
            'datatype'  => 'dropdown',
            'linkfield' => 'plugin_auchanassettracker_containers_id',
        ];
        $tab[] = [
            'id'        => 8,
            'table'     => PluginAuchanassettrackerEquipmenttype::getTable(),
            'field'     => 'name',
            'name'      => __('Equipment type', 'auchanassettracker'),
            'datatype'  => 'dropdown',
            'linkfield' => 'plugin_auchanassettracker_equipmenttypes_id',
        ];

        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        if ($field === 'status') {
            return self::getStatusLabel((string) ($values[$field] ?? ''));
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if ($field === 'status') {
            return Dropdown::showFromArray($name, self::getStatuses(), [
                'value'   => is_array($values) ? ($values[$field] ?? '') : $values,
                'display' => false,
            ]);
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    public function prepareInputForAdd($input)
    {
        if (!PluginAuchanassettrackerRighthelper::canManageStock()
            && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            Session::addMessageAfterRedirect(__('Insufficient rights.'), false, ERROR);
            return false;
        }

        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        if ($scope !== null) {
            $input['locations_id'] = $scope;
        }

        $locations_id = (int) ($input['locations_id'] ?? 0);
        if ($locations_id <= 0) {
            Session::addMessageAfterRedirect(
                __('Location is required.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        if (!PluginAuchanassettrackerRighthelper::canAccessLocation($locations_id)) {
            Session::addMessageAfterRedirect(
                __('You cannot add equipment in another location.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        $type_id = (int) ($input['plugin_auchanassettracker_equipmenttypes_id'] ?? 0);
        $mfr_id  = (int) ($input['plugin_auchanassettracker_manufacturers_id'] ?? 0);
        $model   = trim((string) ($input['model'] ?? ''));
        $serial  = trim((string) ($input['serial'] ?? ''));

        if ($type_id <= 0 || $mfr_id <= 0 || $model === '') {
            Session::addMessageAfterRedirect(
                __('Type, manufacturer and model are mandatory.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        if (PluginAuchanassettrackerEquipmenttype::isCategoryA($type_id) && $serial === '') {
            Session::addMessageAfterRedirect(
                __('Serial number is required for this equipment type.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        if ($serial !== '' && self::serialExists($serial)) {
            Session::addMessageAfterRedirect(
                __('Serial number must be unique.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        // New receipt always Available + mandatory container.
        $input['status'] = self::STATUS_AVAILABLE;
        $container_id = (int) ($input['plugin_auchanassettracker_containers_id'] ?? 0);
        if ($container_id <= 0) {
            Session::addMessageAfterRedirect(
                __('A physical container is mandatory for equipment in stock.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        if (!self::containerBelongsToLocation($container_id, $locations_id)) {
            Session::addMessageAfterRedirect(
                __('Selected container does not belong to this location.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        $input['users_id'] = 0;
        $input['serial'] = $serial !== '' ? $serial : null;
        $input['is_deleted'] = 0;
        $input['entities_id'] = $input['entities_id'] ?? ($_SESSION['glpiactive_entity'] ?? 0);

        $type = new PluginAuchanassettrackerEquipmenttype();
        $typeName = $type->getFromDB($type_id) ? ($type->fields['name'] ?? 'Equipment') : 'Equipment';
        $input['name'] = trim((string) ($input['name'] ?? ''));
        if ($input['name'] === '') {
            $input['name'] = $typeName . ($serial !== '' ? ' - ' . $serial : ' - ' . $model);
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $input['date_creation'] = $now;
        $input['date_mod'] = $now;

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        $current_status = (string) ($this->fields['status'] ?? '');
        $new_status = isset($input['status']) ? (string) $input['status'] : $current_status;

        // Enforce container when Available — except reject path (awaiting → available, no shelf yet).
        if ($new_status === self::STATUS_AVAILABLE) {
            $container_id = (int) ($input['plugin_auchanassettracker_containers_id']
                ?? $this->fields['plugin_auchanassettracker_containers_id']
                ?? 0);
            $from_reject = ($current_status === self::STATUS_AWAITING_VALIDATION);
            if ($container_id <= 0 && !$from_reject) {
                Session::addMessageAfterRedirect(
                    __('A physical container is mandatory for equipment in stock.', 'auchanassettracker'),
                    false,
                    ERROR
                );
                return false;
            }
        }

        if (isset($input['serial'])) {
            $serial = trim((string) $input['serial']);
            if ($serial !== '' && self::serialExists($serial, (int) $this->getID())) {
                Session::addMessageAfterRedirect(
                    __('Serial number must be unique.', 'auchanassettracker'),
                    false,
                    ERROR
                );
                return false;
            }
            $input['serial'] = $serial !== '' ? $serial : null;
        }

        $loc = (int) ($input['locations_id'] ?? $this->fields['locations_id'] ?? 0);
        if (!PluginAuchanassettrackerRighthelper::canAccessLocation($loc)
            && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            Session::addMessageAfterRedirect(
                __('You cannot modify equipment from another location.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        $input['date_mod'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        return $input;
    }

    public function post_addItem()
    {
        PluginAuchanassettrackerAuditlog::record(
            'equipment_receipt',
            self::class,
            (int) $this->getID(),
            sprintf(
                'serial=%s location=%d container=%d',
                $this->fields['serial'] ?? '',
                (int) ($this->fields['locations_id'] ?? 0),
                (int) ($this->fields['plugin_auchanassettracker_containers_id'] ?? 0)
            )
        );
    }

    public function post_updateItem($history = true)
    {
        PluginAuchanassettrackerAuditlog::record(
            'equipment_update',
            self::class,
            (int) $this->getID(),
            json_encode([
                'updates' => array_keys($this->updates ?? []),
                'status'  => $this->fields['status'] ?? '',
            ])
        );
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        $is_new = $ID <= 0;
        $status = (string) ($this->fields['status'] ?? self::STATUS_AVAILABLE);
        $req = " <span class='aat-required'>*</span>";

        echo "<tr class='tab_bg_1'><td>" . __('Equipment type', 'auchanassettracker') . $req . "</td><td>";
        PluginAuchanassettrackerEquipmenttype::dropdown([
            'name'  => 'plugin_auchanassettracker_equipmenttypes_id',
            'value' => (int) ($this->fields['plugin_auchanassettracker_equipmenttypes_id'] ?? 0),
            'condition' => ['is_active' => 1],
        ]);
        echo "</td><td>" . __('Manufacturer', 'auchanassettracker') . $req . "</td><td>";
        PluginAuchanassettrackerManufacturer::dropdown([
            'name'  => 'plugin_auchanassettracker_manufacturers_id',
            'value' => (int) ($this->fields['plugin_auchanassettracker_manufacturers_id'] ?? 0),
            'condition' => ['is_active' => 1],
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Model') . $req . "</td><td>";
        echo Html::input('model', [
            'value' => $this->fields['model'] ?? '',
            'required' => true,
            'class' => 'form-control aat-input-sm',
        ]);
        echo "</td><td>" . __('Serial number') . "</td><td>";
        echo Html::input('serial', [
            'value' => $this->fields['serial'] ?? '',
            'class' => 'form-control aat-input-sm',
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Location') . $req . "</td><td>";
        if ($scope !== null) {
            echo Dropdown::getDropdownName('glpi_locations', $scope);
            echo Html::hidden('locations_id', ['value' => $scope]);
            echo "<br><small class='text-muted'>"
                . __('Fixed from your profile location.', 'auchanassettracker')
                . "</small>";
            $loc_for_container = $scope;
        } else {
            Location::dropdown([
                'name'  => 'locations_id',
                'value' => (int) ($this->fields['locations_id'] ?? 0),
            ]);
            $loc_for_container = (int) ($this->fields['locations_id'] ?? 0);
        }
        echo "</td><td>" . __('Status') . "</td><td>";
        if ($is_new) {
            echo self::getStatusLabel(self::STATUS_AVAILABLE);
            echo Html::hidden('status', ['value' => self::STATUS_AVAILABLE]);
        } else {
            echo self::getStatusLabel($status);
        }
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Physical container', 'auchanassettracker');
        if ($is_new || $status === self::STATUS_AVAILABLE) {
            echo $req;
        }
        echo "</td><td>";
        $container_condition = ['is_deleted' => 0, 'is_active' => 1];
        $container_value = (int) ($this->fields['plugin_auchanassettracker_containers_id'] ?? 0);
        if ($loc_for_container > 0) {
            $container_condition['locations_id'] = $loc_for_container;
            if ($container_value > 0) {
                $tmp = new PluginAuchanassettrackerContainer();
                if (!$tmp->getFromDB($container_value)
                    || (int) ($tmp->fields['locations_id'] ?? 0) !== $loc_for_container
                    || (int) ($tmp->fields['is_deleted'] ?? 0) === 1) {
                    $container_value = 0;
                }
            }
        } else {
            $container_condition['locations_id'] = -1;
            $container_value = 0;
        }
        echo "<span class='aat-container-field'>";
        PluginAuchanassettrackerContainer::dropdownWithActions([
            'name'          => 'plugin_auchanassettracker_containers_id',
            'value'         => $container_value,
            'condition'     => $container_condition,
            'width'         => '280px',
            'sync_location' => ($scope === null),
        ]);
        echo "</span>";
        echo "</td><td>" . __('Allocated user', 'auchanassettracker') . "</td><td>";
        $uid = (int) ($this->fields['users_id'] ?? 0);
        echo $uid > 0 ? getUserName($uid) : '—';
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Notes') . "</td><td colspan='3'>";
        echo "<textarea name='notes' class='form-control' rows='3'>"
            . Html::entities_deep($this->fields['notes'] ?? '')
            . "</textarea>";
        echo "</td></tr>";

        $this->showFormButtons($options);
        return true;
    }

    public static function serialExists(string $serial, int $except_id = 0): bool
    {
        global $DB;
        $where = ['serial' => $serial];
        if ($except_id > 0) {
            $where[] = ['NOT' => ['id' => $except_id]];
        }
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => $where,
            'LIMIT' => 1,
        ]) as $_) {
            return true;
        }
        return false;
    }

    public static function containerBelongsToLocation(int $container_id, int $locations_id): bool
    {
        $c = new PluginAuchanassettrackerContainer();
        if (!$c->getFromDB($container_id)) {
            return false;
        }
        return (int) ($c->fields['locations_id'] ?? 0) === $locations_id
            && (int) ($c->fields['is_deleted'] ?? 0) === 0
            && (int) ($c->fields['is_active'] ?? 0) === 1;
    }

    /**
     * Bulk create Category B accessories (type + quantity).
     *
     * @return int number created
     */
    public static function bulkAddAccessories(array $input, int $quantity): int
    {
        $quantity = max(0, min(500, $quantity));
        if ($quantity <= 0) {
            return 0;
        }

        $created = 0;
        for ($i = 1; $i <= $quantity; $i++) {
            $row = $input;
            $row['serial'] = $row['serial'] ?? null;
            if (!empty($row['serial'])) {
                $row['serial'] = $row['serial'] . '-' . $i;
            }
            $eq = new self();
            if ($eq->add($row)) {
                $created++;
            }
        }
        return $created;
    }

    public function canViewItem(): bool
    {
        if (PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            return true;
        }
        $role = PluginAuchanassettrackerRighthelper::getCurrentRole();
        if ($role === PluginAuchanassettrackerRighthelper::ROLE_USER) {
            return (int) ($this->fields['users_id'] ?? 0) === (int) Session::getLoginUserID();
        }
        $loc = (int) ($this->fields['locations_id'] ?? 0);
        return PluginAuchanassettrackerRighthelper::canAccessLocation($loc);
    }

    public function canUpdateItem(): bool
    {
        if (PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            return true;
        }
        if (!PluginAuchanassettrackerRighthelper::canManageStock()
            && !PluginAuchanassettrackerRighthelper::canAllocate()) {
            return false;
        }
        return PluginAuchanassettrackerRighthelper::canAccessLocation(
            (int) ($this->fields['locations_id'] ?? 0)
        );
    }

    public function canCreateItem(): bool
    {
        return PluginAuchanassettrackerRighthelper::canManageStock()
            || PluginAuchanassettrackerRighthelper::isCentralAdmin();
    }

    public static function canCreate(): bool
    {
        return Session::getLoginUserID()
            && (PluginAuchanassettrackerRighthelper::canManageStock()
                || PluginAuchanassettrackerRighthelper::isCentralAdmin()
                || Session::haveRight(self::$rightname, CREATE));
    }

    public static function canView(): bool
    {
        return (bool) Session::getLoginUserID();
    }

    public static function canUpdate(): bool
    {
        return Session::getLoginUserID()
            && (PluginAuchanassettrackerRighthelper::isSupportTech()
                || PluginAuchanassettrackerRighthelper::isCentralAdmin()
                || Session::haveRight(self::$rightname, UPDATE));
    }

    public function delete(array $input, $force = 0, $history = 1)
    {
        $input['is_deleted'] = 1;
        return $this->update($input);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function findByStatus(string $status, ?int $locations_id = null, ?int $users_id = null): array
    {
        global $DB;
        $where = [
            'status'     => $status,
            'is_deleted' => 0,
        ];
        if ($locations_id !== null) {
            $where['locations_id'] = $locations_id;
        }
        if ($users_id !== null) {
            $where['users_id'] = $users_id;
        }
        $rows = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => $where,
            'ORDER' => 'date_mod DESC',
        ]) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    public static function countByStatus(?int $locations_id = null): array
    {
        global $DB;
        $counts = [];
        foreach (array_keys(self::getStatuses()) as $st) {
            $counts[$st] = 0;
        }
        $where = ['is_deleted' => 0];
        if ($locations_id !== null) {
            $where['locations_id'] = $locations_id;
        }
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => $where,
        ]) as $row) {
            $st = (string) ($row['status'] ?? '');
            if (!isset($counts[$st])) {
                $counts[$st] = 0;
            }
            $counts[$st]++;
        }
        return $counts;
    }

    public static function listInContainer(int $container_id): array
    {
        global $DB;
        $rows = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'plugin_auchanassettracker_containers_id' => $container_id,
                'status'     => self::STATUS_AVAILABLE,
                'is_deleted' => 0,
            ],
            'ORDER' => 'plugin_auchanassettracker_equipmenttypes_id ASC, model ASC',
        ]) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Available stock missing a container (e.g. after user reject).
     *
     * @return list<array<string, mixed>>
     */
    public static function findNeedsContainer(?int $locations_id = null): array
    {
        global $DB;
        $where = [
            'status'     => self::STATUS_AVAILABLE,
            'is_deleted' => 0,
            'plugin_auchanassettracker_containers_id' => 0,
        ];
        if ($locations_id !== null) {
            $where['locations_id'] = $locations_id;
        }
        $rows = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => $where,
            'ORDER' => 'date_mod DESC',
        ]) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }
}
