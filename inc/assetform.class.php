<?php

/**
 * Physical container field on native GLPI asset forms (Computer, Monitor, …).
 */
class PluginAuchanassettrackerAssetform
{
    /**
     * @param array{item?: CommonDBTM, options?: array<string, mixed>} $params
     */
    public static function postItemForm(array $params): void
    {
        $item = $params['item'] ?? null;
        if (!$item instanceof CommonDBTM) {
            return;
        }

        $itemtype = $item->getType();
        if (!PluginAuchanassettrackerEquipment::isAllowedAssetType($itemtype)) {
            return;
        }

        if (!PluginAuchanassettrackerRighthelper::canManageStock()
            && !PluginAuchanassettrackerRighthelper::canAllocate()
            && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            return;
        }

        $locations_id = (int) ($item->fields['locations_id'] ?? 0);
        if ($locations_id > 0
            && !PluginAuchanassettrackerRighthelper::canAccessLocation($locations_id)) {
            return;
        }

        $container_id = 0;
        if (!$item->isNewItem()) {
            $eq = PluginAuchanassettrackerEquipment::findByGlpiAsset($itemtype, (int) $item->getID());
            $container_id = (int) ($eq['plugin_auchanassettracker_containers_id'] ?? 0);
        }

        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        $loc_for_containers = $scope !== null ? (int) $scope : $locations_id;

        $condition = ['is_deleted' => 0, 'is_active' => 1];
        if ($loc_for_containers > 0) {
            $condition['locations_id'] = $loc_for_containers;
        } else {
            $condition['locations_id'] = -1;
        }

        echo "<div class='aat-native-container-field mt-3 mb-2'>";
        echo "<div class='form-field row col-12 col-sm-6 mb-2'>";
        echo "<label class='col-form-label col-xxl-5 text-xxl-end' for='aat_native_container'>";
        echo Html::entities_deep(__('Physical container', 'auchanassettracker'));
        echo '</label>';
        echo "<div class='col-xxl-7 field-container aat-container-field'>";
        PluginAuchanassettrackerContainer::dropdownWithActions([
            'name'          => 'plugin_auchanassettracker_containers_id',
            'value'         => $container_id,
            'condition'     => $condition,
            'width'         => '100%',
            'sync_location' => false,
        ]);
        echo "<div class='form-text'>";
        echo Html::entities_deep(__(
            'Auchan Asset Tracker shelf / box for this asset.',
            'auchanassettracker'
        ));
        echo '</div></div></div></div>';
    }

    public static function onItemAdd(CommonDBTM $item): void
    {
        self::syncFromNativeAsset($item);
    }

    public static function onItemUpdate(CommonDBTM $item): void
    {
        self::syncFromNativeAsset($item);
    }

    private static function syncFromNativeAsset(CommonDBTM $item): void
    {
        $itemtype = $item->getType();
        if (!PluginAuchanassettrackerEquipment::isAllowedAssetType($itemtype)) {
            return;
        }

        $items_id = (int) $item->getID();
        if ($items_id <= 0) {
            return;
        }

        $container_posted = array_key_exists(
            'plugin_auchanassettracker_containers_id',
            $_POST
        );
        $container_id = $container_posted
            ? (int) ($_POST['plugin_auchanassettracker_containers_id'] ?? 0)
            : null;

        PluginAuchanassettrackerEquipment::ensureFromGlpiAsset(
            $itemtype,
            $items_id,
            $container_id
        );
    }
}
