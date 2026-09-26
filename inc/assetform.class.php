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

        $condition = ['is_deleted' => 0];
        if ($loc_for_containers > 0) {
            $condition['locations_id'] = $loc_for_containers;
        } else {
            $condition['locations_id'] = -1;
        }

        // Hidden marker so we always know the field was rendered (for save logic).
        echo Html::hidden('_aat_container_field', ['value' => '1']);

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
            'sync_location' => ($scope === null),
        ]);
        echo '</div>';
        echo "<div class='form-text'>";
        echo Html::entities_deep(__(
            'Auchan Asset Tracker shelf / box for this asset. Choose a location first.',
            'auchanassettracker'
        ));
        echo '</div></div></div></div>';

        self::scriptEnsureInsideFormAndSync($scope === null);
    }

    /**
     * Keep the field inside the asset <form> (so save posts the value),
     * and sync options when Location changes.
     */
    public static function scriptEnsureInsideFormAndSync(bool $sync_location): void
    {
        $sync_js = $sync_location ? 'true' : 'false';
        $ajax = json_encode(
            plugin_auchanassettracker_web_dir() . '/ajax/containers.php',
            JSON_UNESCAPED_SLASHES
        );

        echo Html::scriptBlock(<<<JS
$(function () {
  function aatMoveContainerIntoForm() {
    var \$block = $('.aat-native-container-field').first();
    if (!\$block.length) {
      return;
    }
    if (\$block.closest('form').length) {
      return;
    }
    var \$form = $('form').has('select[name="locations_id"]').first();
    if (!\$form.length) {
      \$form = $('form[method="post"]').filter(':visible').last();
    }
    if (!\$form.length) {
      return;
    }
    var \$anchor = \$form.find('.card-footer, .form-buttons, button[type="submit"], input[type="submit"]').first();
    if (\$anchor.length) {
      \$block.insertBefore(\$anchor.closest('.card-footer, .form-buttons, .row, div').length
        ? \$anchor.closest('.card-footer, .form-buttons, .row, div')
        : \$anchor);
    } else {
      \$form.append(\$block);
    }
    // Ensure hidden marker is also inside the form.
    if (!\$form.find('input[name="_aat_container_field"]').length) {
      \$form.append($('<input>', { type: 'hidden', name: '_aat_container_field', value: '1' }));
    }
  }

  aatMoveContainerIntoForm();
  // Twig forms may finish rendering slightly later.
  setTimeout(aatMoveContainerIntoForm, 100);
  setTimeout(aatMoveContainerIntoForm, 400);

  if (!{$sync_js} || window.aatNativeContainerSyncBound) {
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
        var text = r.text != null ? String(r.text) : '';
        html += '<option value="' + id + '">' + \$('<div/>').text(text).html() + '</option>';
      }
      var wasSelect2 = \$sel.hasClass('select2-hidden-accessible');
      if (wasSelect2 && \$sel.data('select2')) {
        try { \$sel.select2('destroy'); } catch (e) {}
      }
      \$sel.html(html);
      if (current > 0 && \$sel.find('option[value="' + current + '"]').length) {
        \$sel.val(String(current));
      } else {
        \$sel.val('0');
      }
      if (wasSelect2 && typeof \$sel.select2 === 'function') {
        \$sel.select2({ width: 'style' });
      }
      \$sel.trigger('change');
    });
  }

  $(document)
    .off('change.aatNativeLoc select2:select.aatNativeLoc select2:clear.aatNativeLoc')
    .on(
      'change.aatNativeLoc select2:select.aatNativeLoc select2:clear.aatNativeLoc',
      'select[name="locations_id"]',
      function () {
        aatReloadNativeContainerOptions($(this).val(), false);
      }
    );

  var \$loc = $('form select[name="locations_id"]').filter(':visible').last();
  if (!\$loc.length) {
    \$loc = $('form select[name="locations_id"]').last();
  }
  var initial = \$loc.val();
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

        $field_present = array_key_exists('plugin_auchanassettracker_containers_id', $_POST)
            || array_key_exists('_aat_container_field', $_POST);

        $existing = PluginAuchanassettrackerEquipment::findByGlpiAsset($itemtype, $items_id);
        $existing_id = (int) ($existing['id'] ?? 0);

        if (!$field_present && $existing_id <= 0) {
            return;
        }

        $container_id = null;
        if (array_key_exists('plugin_auchanassettracker_containers_id', $_POST)) {
            $container_id = (int) ($_POST['plugin_auchanassettracker_containers_id'] ?? 0);
        } elseif (array_key_exists('_aat_container_field', $_POST)) {
            // Field rendered but empty select may omit key in some browsers — treat as 0.
            $container_id = 0;
        }

        PluginAuchanassettrackerEquipment::ensureFromGlpiAsset(
            $itemtype,
            $items_id,
            $container_id
        );
    }
}
