<?php

/**
 * Bulk accessories receipt form (native GLPI display chrome).
 */
class PluginAuchanassettrackerBulk extends CommonDBTM
{
    public static $rightname = 'plugin_auchanassettracker';

    public static function getTypeName($nb = 0): string
    {
        return __('Bulk add accessories', 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_equipments';
    }

    public static function getIcon(): string
    {
        return 'ti ti-stack-2';
    }

    public static function getSectorizedDetails(): array
    {
        return ['assets', PluginAuchanassettrackerMenu::MENU_BULK];
    }

    public static function getFormURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/equipment.bulk.php';
    }

    public static function getSearchURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/equipment.php';
    }

    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        return $ong;
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm(-1, $options);

        $options['formtitle'] = self::getTypeName(1);
        $options['target']    = self::getFormURL();
        $options['candel']    = false;
        $options['canedit']   = true;

        $this->showFormHeader($options);

        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        $req = " <span class='aat-required'>*</span>";

        echo "<tr class='tab_bg_1'><td>" . __('Equipment type', 'auchanassettracker') . $req . "</td><td>";
        PluginAuchanassettrackerEquipmenttype::dropdown([
            'name' => 'plugin_auchanassettracker_equipmenttypes_id',
            'condition' => ['category' => 'B', 'is_active' => 1],
        ]);
        echo "</td><td>" . __('Manufacturer', 'auchanassettracker') . $req . "</td><td>";
        PluginAuchanassettrackerManufacturer::dropdown([
            'name' => 'plugin_auchanassettracker_manufacturers_id',
            'condition' => ['is_active' => 1],
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Model') . $req . "</td><td>";
        echo Html::input('model', ['required' => true, 'class' => 'form-control aat-input-sm']);
        echo "</td><td>" . __('Quantity', 'auchanassettracker') . $req . "</td><td>";
        echo Html::input('quantity', [
            'type' => 'number',
            'min' => 1,
            'max' => 500,
            'value' => 1,
            'required' => true,
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
            $loc = $scope;
        } else {
            Location::dropdown(['name' => 'locations_id']);
            $loc = 0;
        }
        echo "</td><td>" . __('Physical container', 'auchanassettracker') . $req . "</td><td>";
        $cond = ['is_active' => 1, 'is_deleted' => 0];
        if ($loc > 0) {
            $cond['locations_id'] = $loc;
        } else {
            $cond['locations_id'] = -1;
        }
        echo "<span class='aat-container-field'>";
        PluginAuchanassettrackerContainer::dropdownWithActions([
            'name'          => 'plugin_auchanassettracker_containers_id',
            'condition'     => $cond,
            'width'         => '280px',
            'sync_location' => ($scope === null),
        ]);
        echo "</span>";
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Notes') . "</td><td colspan='3'>";
        echo "<textarea name='notes' class='form-control' rows='2'></textarea></td></tr>";

        // Use add button name expected by native form chrome.
        $options['addbuttons'] = [
            'bulk_add' => [
                'value' => __('Create', 'auchanassettracker'),
                'class' => 'btn btn-primary',
            ],
        ];

        $this->showFormButtons($options);
        return true;
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
        return self::canCreate();
    }

    public function canUpdateItem(): bool
    {
        return $this->canCreateItem();
    }

    public function canViewItem(): bool
    {
        return true;
    }
}
