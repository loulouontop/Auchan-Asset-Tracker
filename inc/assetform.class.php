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
            // New asset: wait until native Location is chosen (JS reloads the list).
            $condition['locations_id'] = -1;
        }

        echo "<div class='aat-native-container-field mt-3 mb-2'>";
        echo "<div class='form-field row col-12 col-sm-6 mb-2'>";
        echo "<label class='col-form-label col-xxl-5 text-xxl-end'>";
        echo Html::entities_deep(__('Physical container', 'auchanassettracker'));
        echo '</label>';
        echo "<div class='col-xxl-7 field-container'>";
        echo "<div class='aat-container-field'>";
        PluginAuchanassettrackerContainer::dropdownWithActions([
            'name'          => 'plugin_auchanassettracker_containers_id',
            'value'         => $container_id,
            'condition'     => $condition,
            'width'         => '100%',
            // Reload containers when native locations_id changes (new asset flow).
            'sync_location' => ($scope === null),
        ]);
        echo '</div>';
        echo "<div class='form-text'>";
        echo Html::entities_deep(__(
            'Auchan Asset Tracker shelf / box for this asset. Choose a location first.',
            'auchanassettracker'
        ));
        echo '</div></div></div></div>';

        // Always bind location→container sync on native forms (Select2 + plain change).
        if ($scope === null) {
            self::scriptSyncNativeLocationContainers();
        }
    }

    /**
     * Native asset forms: refresh Physical container when Location changes.
     */
    public static function scriptSyncNativeLocationContainers(): void
    {
        $ajax = json_encode(
            plugin_auchanassettracker_web_dir() . '/ajax/containers.php',
            JSON_UNESCAPED_SLASHES
        );

        echo Html::scriptBlock(<<<JS
$(function () {
  if (window.aatNativeContainerSyncBound) {
    return;
  }
  window.aatNativeContainerSyncBound = true;

  function aatNativeContainerTarget() {
    return $('.aat-native-container-field .aat-container-field').first();
  }

  function aatReloadNativeContainers(locId) {
    var \$field = aatNativeContainerTarget();
    if (!\$field.length) {
      return;
    }
    locId = parseInt(locId, 10) || 0;
    $.ajax({
      url: {$ajax},
      data: {
        display: 'dropdown',
        locations_id: locId,
        value: 0
      },
      dataType: 'html'
    }).done(function (html) {
      \$field.html(html);
    });
  }

  function aatReadNativeLocationId() {
    var \$sel = $('select[name="locations_id"]').filter(':visible').last();
    if (!\$sel.length) {
      \$sel = $('select[name="locations_id"]').last();
    }
    return \$sel.val();
  }

  $(document)
    .off('change.aatNativeLoc select2:select.aatNativeLoc select2:clear.aatNativeLoc')
    .on(
      'change.aatNativeLoc select2:select.aatNativeLoc select2:clear.aatNativeLoc',
      'select[name="locations_id"]',
      function () {
        aatReloadNativeContainers($(this).val());
      }
    );

  // If location was already chosen before our field rendered, load once.
  var initial = aatReadNativeLocationId();
  if (parseInt(initial, 10) > 0) {
    aatReloadNativeContainers(initial);
  }
});
JS);
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

        // Only touch plugin rows when container was posted, or a link already exists.
        $container_posted = array_key_exists(
            'plugin_auchanassettracker_containers_id',
            $_POST
        );
        $existing = self::findExistingId($itemtype, $items_id);
        if (!$container_posted && $existing <= 0) {
            return;
        }

        $container_id = $container_posted
            ? (int) ($_POST['plugin_auchanassettracker_containers_id'] ?? 0)
            : null;

        PluginAuchanassettrackerEquipment::ensureFromGlpiAsset(
            $itemtype,
            $items_id,
            $container_id
        );
    }

    private static function findExistingId(string $itemtype, int $items_id): int
    {
        $row = PluginAuchanassettrackerEquipment::findByGlpiAsset($itemtype, $items_id);
        return (int) ($row['id'] ?? 0);
    }
}
