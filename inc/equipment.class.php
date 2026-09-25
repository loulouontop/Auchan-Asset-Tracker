<?php

/**
 * Tracked equipment (Sprint 1: stock receipt into physical containers).
 */
class PluginAuchanassettrackerEquipment extends CommonDBTM
{
    public static $rightname = 'plugin_auchanassettracker';

    public const STATUS_AVAILABLE = 'available';

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

    public static function getStatuses(): array
    {
        return [
            self::STATUS_AVAILABLE => __('Available', 'auchanassettracker'),
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
            'id'         => 4,
            'table'      => self::getTable(),
            'field'      => 'status',
            'name'       => __('Status'),
            'datatype'   => 'specific',
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
            'id'        => 7,
            'table'     => PluginAuchanassettrackerContainer::getTable(),
            'field'     => 'name',
            'name'      => __('Physical container', 'auchanassettracker'),
            'datatype'  => 'dropdown',
            'linkfield' => 'plugin_auchanassettracker_containers_id',
        ];
        $tab[] = [
            'id'        => 8,
            'table'     => self::getTable(),
            'field'     => 'itemtype',
            'name'      => __('Equipment type', 'auchanassettracker'),
            'datatype'  => 'itemtypename',
            'itemtype_list' => 'asset_types',
        ];
        $tab[] = [
            'id'        => 9,
            'table'     => 'glpi_manufacturers',
            'field'     => 'name',
            'name'      => __('Manufacturer'),
            'datatype'  => 'dropdown',
            'linkfield' => 'manufacturers_id',
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

        $itemtype = (string) ($input['itemtype'] ?? '');
        $mfr_id   = (int) ($input['manufacturers_id'] ?? 0);
        $model    = trim((string) ($input['model'] ?? ''));
        $serial   = trim((string) ($input['serial'] ?? ''));

        if ($itemtype === '' || !self::isAllowedAssetType($itemtype)) {
            Session::addMessageAfterRedirect(
                __('Equipment type is mandatory.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        if ($mfr_id <= 0 || $model === '') {
            Session::addMessageAfterRedirect(
                __('Type, manufacturer and model are mandatory.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        if (self::itemtypeRequiresSerial($itemtype) && $serial === '') {
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

        $input['itemtype'] = $itemtype;
        $input['items_id'] = 0;
        $input['manufacturers_id'] = $mfr_id;
        $input['plugin_auchanassettracker_equipmenttypes_id'] = 0;
        $input['plugin_auchanassettracker_manufacturers_id'] = 0;
        $input['serial'] = $serial !== '' ? $serial : null;
        $input['is_deleted'] = 0;
        $input['entities_id'] = $input['entities_id'] ?? ($_SESSION['glpiactive_entity'] ?? 0);

        $typeName = is_a($itemtype, CommonGLPI::class, true)
            ? $itemtype::getTypeName(1)
            : $itemtype;
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

        // Enforce container when status is / becomes Available.
        if ($new_status === self::STATUS_AVAILABLE) {
            $container_id = (int) ($input['plugin_auchanassettracker_containers_id']
                ?? $this->fields['plugin_auchanassettracker_containers_id']
                ?? 0);
            if ($container_id <= 0) {
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

        // Sprint 1: stock only — keep status Available.
        $input['status'] = self::STATUS_AVAILABLE;
        $input['date_mod'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        return $input;
    }

    public function post_addItem()
    {
        $asset_id = self::createLinkedGlpiAsset($this->fields);
        if ($asset_id > 0) {
            global $DB;
            $DB->update(self::getTable(), [
                'items_id' => $asset_id,
            ], ['id' => (int) $this->getID()]);
            $this->fields['items_id'] = $asset_id;
        }

        PluginAuchanassettrackerAuditlog::record(
            'equipment_receipt',
            self::class,
            (int) $this->getID(),
            sprintf(
                'serial=%s location=%d container=%d itemtype=%s items_id=%d',
                $this->fields['serial'] ?? '',
                (int) ($this->fields['locations_id'] ?? 0),
                (int) ($this->fields['plugin_auchanassettracker_containers_id'] ?? 0),
                (string) ($this->fields['itemtype'] ?? ''),
                (int) ($this->fields['items_id'] ?? 0)
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
        $req = " <span class='aat-required' title='"
            . Html::entities_deep(__('Mandatory field'))
            . "'>*</span>";
        $base = Plugin::getWebDir(plugin_auchanassettracker_dir());
        $current_itemtype = (string) ($this->fields['itemtype'] ?? 'Computer');
        if ($current_itemtype === '' || !self::isAllowedAssetType($current_itemtype)) {
            $current_itemtype = 'Computer';
        }

        echo "<tr class='tab_bg_1'><td>" . __('Equipment type', 'auchanassettracker') . $req . "</td><td>";
        $type_choices = [];
        foreach (self::getAllowedAssetTypes() as $class) {
            $type_choices[$class] = $class::getTypeName(1);
        }
        Dropdown::showFromArray('itemtype', $type_choices, [
            'value' => $current_itemtype,
            'width' => '220px',
        ]);
        echo "</td><td>" . __('Manufacturer') . $req . "</td><td>";
        Manufacturer::dropdown([
            'name'  => 'manufacturers_id',
            'value' => (int) ($this->fields['manufacturers_id'] ?? 0),
            'width' => '220px',
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Model') . $req . "</td><td>";
        echo Html::input('model', [
            'value'    => $this->fields['model'] ?? '',
            'required' => true,
            'class'    => 'form-control aat-input-sm',
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
            echo "<div class='form-text'>"
                . Html::entities_deep(__('Fixed from your profile location.', 'auchanassettracker'))
                . "</div>";
            $loc_for_container = $scope;
        } else {
            Location::dropdown([
                'name'  => 'locations_id',
                'value' => (int) ($this->fields['locations_id'] ?? 0),
                'width' => '220px',
            ]);
            $loc_for_container = (int) ($this->fields['locations_id'] ?? 0);
        }
        echo "</td><td>" . __('Status') . "</td><td>";
        echo self::getStatusLabel(self::STATUS_AVAILABLE);
        echo Html::hidden('status', ['value' => self::STATUS_AVAILABLE]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Physical container', 'auchanassettracker') . $req . "</td><td colspan='3'>";
        $container_condition = ['is_deleted' => 0, 'is_active' => 1];
        if ($loc_for_container > 0) {
            $container_condition['locations_id'] = $loc_for_container;
        }

        echo "<div class='aat-container-field d-flex flex-wrap align-items-center gap-1'>";
        PluginAuchanassettrackerContainer::dropdown([
            'name'      => 'plugin_auchanassettracker_containers_id',
            'value'     => (int) ($this->fields['plugin_auchanassettracker_containers_id'] ?? 0),
            'condition' => $container_condition,
            'comments'  => true,
            'addicon'   => true,
            'width'     => '280px',
        ]);
        echo " <a class='btn btn-sm btn-secondary' href='"
            . Html::entities_deep($base . '/front/container.form.php')
            . "' title='" . Html::entities_deep(__('Create container', 'auchanassettracker')) . "'>"
            . "<i class='ti ti-plus'></i></a>";
        echo " <a class='btn btn-sm btn-outline-secondary' href='"
            . Html::entities_deep($base . '/front/container.php')
            . "'>" . Html::entities_deep(__('Physical containers', 'auchanassettracker')) . "</a>";
        echo "</div>";
        echo "</td></tr>";

        $linked_id = (int) ($this->fields['items_id'] ?? 0);
        if ($ID > 0 && $linked_id > 0 && class_exists($current_itemtype)) {
            $link = $current_itemtype::getFormURLWithID($linked_id);
            echo "<tr class='tab_bg_1'><td>" . __('Linked GLPI asset', 'auchanassettracker') . "</td><td colspan='3'>";
            echo "<a href='" . Html::entities_deep($link) . "'>"
                . Html::entities_deep(sprintf('%s #%d', $current_itemtype::getTypeName(1), $linked_id))
                . "</a>";
            echo "</td></tr>";
        }

        echo "<tr class='tab_bg_1'><td>" . __('Notes') . "</td><td colspan='3'>";
        echo "<textarea name='notes' class='form-control' rows='3'>"
            . Html::entities_deep($this->fields['notes'] ?? '')
            . "</textarea>";
        echo "</td></tr>";

        $this->showFormButtons($options);
        return true;
    }

    /**
     * @return list<class-string>
     */
    public static function getAllowedAssetTypes(): array
    {
        global $CFG_GLPI;

        $types = $CFG_GLPI['asset_types'] ?? ['Computer'];
        $out = [];
        foreach ($types as $type) {
            if (is_string($type) && $type !== '' && class_exists($type)) {
                $out[] = $type;
            }
        }
        return $out !== [] ? $out : ['Computer'];
    }

    public static function isAllowedAssetType(string $itemtype): bool
    {
        return in_array($itemtype, self::getAllowedAssetTypes(), true);
    }

    public static function itemtypeRequiresSerial(string $itemtype): bool
    {
        $optional = ['Monitor', 'Peripheral', 'Printer', 'Phone', 'Rack', 'Enclosure', 'PDU', 'Cable'];
        foreach ($optional as $name) {
            if ($itemtype === $name || str_ends_with($itemtype, '\\' . $name)) {
                return false;
            }
        }
        // Computer, NetworkEquipment, custom assets → serial required.
        return true;
    }

    /**
     * Create the matching GLPI asset (Computer, custom asset, …).
     *
     * @param array<string, mixed> $fields
     */
    public static function createLinkedGlpiAsset(array $fields): int
    {
        $itemtype = (string) ($fields['itemtype'] ?? '');
        if ($itemtype === '' || !class_exists($itemtype) || !is_a($itemtype, CommonDBTM::class, true)) {
            return 0;
        }

        /** @var CommonDBTM $asset */
        $asset = new $itemtype();
        if (!$asset::canCreate()) {
            Session::addMessageAfterRedirect(
                sprintf(
                    __('Stock saved, but the GLPI %s could not be created (missing rights).', 'auchanassettracker'),
                    $itemtype::getTypeName(1)
                ),
                false,
                WARNING
            );
            return 0;
        }

        $input = [
            'name'             => (string) ($fields['name'] ?? ''),
            'serial'           => $fields['serial'] ?? '',
            'locations_id'     => (int) ($fields['locations_id'] ?? 0),
            'manufacturers_id' => (int) ($fields['manufacturers_id'] ?? 0),
            'entities_id'      => (int) ($fields['entities_id'] ?? ($_SESSION['glpiactive_entity'] ?? 0)),
            'comment'          => trim(
                (string) ($fields['model'] ?? '')
                . (isset($fields['notes']) && $fields['notes'] !== ''
                    ? "\n" . $fields['notes']
                    : '')
            ),
            'is_deleted'       => 0,
            'is_template'      => 0,
        ];

        $new_id = $asset->add($input);
        if (!$new_id) {
            Session::addMessageAfterRedirect(
                sprintf(
                    __('Stock saved, but creating the GLPI %s failed.', 'auchanassettracker'),
                    $itemtype::getTypeName(1)
                ),
                false,
                WARNING
            );
            return 0;
        }

        return (int) $new_id;
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
        $loc = (int) ($this->fields['locations_id'] ?? 0);
        return PluginAuchanassettrackerRighthelper::canAccessLocation($loc);
    }

    public function canUpdateItem(): bool
    {
        if (PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            return true;
        }
        if (!PluginAuchanassettrackerRighthelper::canManageStock()) {
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
            && (PluginAuchanassettrackerRighthelper::canManageStock()
                || PluginAuchanassettrackerRighthelper::isCentralAdmin()
                || Session::haveRight(self::$rightname, UPDATE));
    }

    public function delete(array $input, $force = 0, $history = 1)
    {
        $input['is_deleted'] = 1;
        return $this->update($input);
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
}
