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
            // New asset: wait until native Location is chosen (JS reloads options).
            $condition['locations_id'] = -1;
        }

        echo "<div class='aat-native-container-field mt-3 mb-2'>";
        echo "<div class='form-field row col-12 col-sm-6 mb-2'>";
        echo "<label class='col-form-label col-xxl-5 text-xxl-end'>";
        echo Html::entities_deep(__('Physical container', 'auchanassettracker'));
        echo '</label>';
        echo "<div class='col-xxl-7 field-container'>";
        echo "<div class='aat-container-field'>";
        // No sync_location HTML swap — that dumps the menu at the top of the page.
        PluginAuchanassettrackerContainer::dropdownWithActions([
            'name'          => 'plugin_auchanassettracker_containers_id',
            'value'         => $container_id,
            'condition'     => $condition,
            'width'         => '100%',
            'sync_location' => false,
        ]);
        echo '</div>';
        echo "<div class='form-text'>";
        echo Html::entities_deep(__(
            'Auchan Asset Tracker shelf / box for this asset. Choose a location first.',
            'auchanassettracker'
        ));
        echo '</div></div></div></div>';

        if ($scope === null) {
            self::scriptSyncNativeLocationContainers();
        }
    }

    /**
     * Refresh container <select> options in place (JSON) when Location changes.
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

  function aatNativeSelect() {
    return $('.aat-native-container-field select[name="plugin_auchanassettracker_containers_id"]').first();
  }

  function aatReloadNativeContainerOptions(locId, keepValue) {
    var \$sel = aatNativeSelect();
    if (!\$sel.length) {
      return;
    }
    locId = parseInt(locId, 10) || 0;
    var current = keepValue ? (parseInt(\$sel.val(), 10) || 0) : 0;
    $.ajax({
      url: {$ajax},
      data: {
        display: 'json',
        locations_id: locId,
        value: current
      },
      dataType: 'json'
    }).done(function (data) {
      var results = (data && data.results) ? data.results : [];
      var html = '';
      for (var i = 0; i < results.length; i++) {
        var r = results[i];
        var id = r.id != null ? r.id : 0;
        var text = r.text != null ? r.text : '';
        html += '<option value="' + id + '">' + \$('<div/>').text(text).html() + '</option>';
      }
      \$sel.html(html);
      if (current > 0) {
        \$sel.val(String(current));
      } else {
        \$sel.val('0');
      }
      // Refresh Select2 without replacing the whole control (avoids menu jumping to page top).
      if (\$sel.hasClass('select2-hidden-accessible')) {
        \$sel.trigger('change.select2');
      } else {
        \$sel.trigger('change');
      }
    });
  }

  function aatReadNativeLocationId() {
    var \$sel = $('form select[name="locations_id"]').filter(':visible').last();
    if (!\$sel.length) {
      \$sel = $('form select[name="locations_id"]').last();
    }
    return \$sel.val();
  }

  $(document)
    .off('change.aatNativeLoc select2:select.aatNativeLoc select2:clear.aatNativeLoc')
    .on(
      'change.aatNativeLoc select2:select.aatNativeLoc select2:clear.aatNativeLoc',
      'select[name="locations_id"]',
      function () {
        // Ignore the container's own select if it were ever named the same.
        if ($(this).attr('name') !== 'locations_id') {
          return;
        }
        aatReloadNativeContainerOptions($(this).val(), false);
      }
    );

  var initial = aatReadNativeLocationId();
  if (parseInt(initial, 10) > 0) {
    aatReloadNativeContainerOptions(initial, true);
  }
});
JS);
    }

    public static function onItemAdd(CommonDBTM $item): void
    {
        if (PluginAuchanassettrackerEquipment::isNativeHookSuppressed()) {
            return;
        }
        self::syncFromNativeAsset($item);
    }

    public static function onItemUpdate(CommonDBTM $item): void
    {
        if (PluginAuchanassettrackerEquipment::isNativeHookSuppressed()) {
            return;
        }
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
        $existing = (int) ((PluginAuchanassettrackerEquipment::findByGlpiAsset(
            $itemtype,
            $items_id
        )['id'] ?? 0));

        // Creating/updating a native asset from GLPI without our field → do nothing
        // unless we already track it (keep metadata in sync) or container was posted.
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
}
