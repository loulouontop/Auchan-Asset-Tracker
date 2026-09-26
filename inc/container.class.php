<?php

/**
 * Physical container (shelf / box) for stock.
 */
class PluginAuchanassettrackerContainer extends CommonDropdown
{
    public static $rightname = 'plugin_auchanassettracker';

    public static function getTypeName($nb = 0): string
    {
        if ((int) $nb === 1) {
            return __('Physical container', 'auchanassettracker');
        }
        return __('Physical containers', 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_containers';
    }

    public static function getIcon(): string
    {
        return 'ti ti-box';
    }

    public static function getSectorizedDetails(): array
    {
        return [PluginAuchanassettrackerMenu::SECTOR, PluginAuchanassettrackerMenu::MENU_CONTAINER];
    }

    public static function getFormURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/container.form.php';
    }

    public static function getSearchURL($full = true): string
    {
        return plugin_auchanassettracker_web_dir($full) . '/front/container.php';
    }

    public function getAdditionalFields()
    {
        return [
            [
                'name'  => 'code',
                'label' => __('Container code', 'auchanassettracker'),
                'type'  => 'text',
                'list'  => true,
            ],
            [
                'name'  => 'locations_id',
                'label' => __('Location'),
                'type'  => 'dropdownValue',
                'list'  => true,
            ],
            [
                'name'  => 'is_active',
                'label' => __('Active'),
                'type'  => 'bool',
                'list'  => true,
            ],
            [
                'name'  => 'description',
                'label' => __('Description'),
                'type'  => 'textarea',
                'list'  => false,
            ],
        ];
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

        $tab[] = [
            'id'   => 'common',
            'name' => self::getTypeName(1),
        ];

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
            'field'    => 'code',
            'name'     => __('Container code', 'auchanassettracker'),
            'datatype' => 'string',
        ];

        $tab[] = [
            'id'            => 3,
            'table'         => 'glpi_locations',
            'field'         => 'completename',
            'name'          => __('Location'),
            'datatype'      => 'itemlink',
            'itemlink_type' => 'Location',
            'linkfield'     => 'locations_id',
        ];

        $tab[] = [
            'id'       => 4,
            'table'    => self::getTable(),
            'field'    => 'is_active',
            'name'     => __('Active'),
            'datatype' => 'bool',
        ];

        $tab[] = [
            'id'       => 5,
            'table'    => self::getTable(),
            'field'    => 'description',
            'name'     => __('Description'),
            'datatype' => 'text',
        ];

        return $tab;
    }

    public function prepareInputForAdd($input)
    {
        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        if ($scope !== null) {
            $input['locations_id'] = $scope;
        }

        $locations_id = (int) ($input['locations_id'] ?? 0);
        if ($locations_id <= 0) {
            Session::addMessageAfterRedirect(
                __('Location is required for a container.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        if (!PluginAuchanassettrackerRighthelper::canAccessLocation($locations_id)) {
            Session::addMessageAfterRedirect(
                __('You cannot create a container in another location.', 'auchanassettracker'),
                false,
                ERROR
            );
            return false;
        }

        if (empty($input['name'])) {
            Session::addMessageAfterRedirect(__('Name is mandatory.'), false, ERROR);
            return false;
        }

        $input['code'] = $input['code'] ?? '';
        if (trim((string) $input['code']) === '') {
            $input['code'] = self::generateCode($locations_id);
        }

        // Heal UNIQUE qr_token from older installs (empty '' collides).
        global $DB;
        if ($DB->fieldExists(self::getTable(), 'qr_token')) {
            $token = trim((string) ($input['qr_token'] ?? ''));
            if ($token === '') {
                $input['qr_token'] = bin2hex(random_bytes(16));
            }
        }

        $input['is_active'] = isset($input['is_active']) ? (int) (bool) $input['is_active'] : 1;
        $input['is_deleted'] = 0;
        $input['entities_id'] = $input['entities_id'] ?? ($_SESSION['glpiactive_entity'] ?? 0);

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $input['date_creation'] = $now;
        $input['date_mod'] = $now;

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        if (isset($input['locations_id'])) {
            $loc = (int) $input['locations_id'];
            if (!PluginAuchanassettrackerRighthelper::canAccessLocation($loc)) {
                Session::addMessageAfterRedirect(
                    __('You cannot move a container to another location.', 'auchanassettracker'),
                    false,
                    ERROR
                );
                return false;
            }
        }

        // Soft-delete only — never hard delete from UI.
        if (isset($input['is_deleted']) && (int) $input['is_deleted'] === 1) {
            $input['is_active'] = 0;
        }

        $input['date_mod'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        return $input;
    }

    public function post_addItem()
    {
        PluginAuchanassettrackerAuditlog::record(
            'container_create',
            self::class,
            (int) $this->getID(),
            sprintf('code=%s location=%d', $this->fields['code'] ?? '', (int) ($this->fields['locations_id'] ?? 0))
        );
    }

    public function post_updateItem($history = true)
    {
        PluginAuchanassettrackerAuditlog::record(
            'container_update',
            self::class,
            (int) $this->getID(),
            json_encode(array_keys($this->updates ?? []))
        );
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        if (!empty($_REQUEST['_in_modal']) || !empty($options['in_modal'])) {
            echo Html::hidden('_in_modal', ['value' => 1]);
        }

        $scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
        $req = " <span class='aat-required'>*</span>";

        echo "<tr class='tab_bg_1'><td>" . __('Name') . $req . "</td><td>";
        echo Html::input('name', [
            'value'    => $this->fields['name'] ?? '',
            'required' => true,
            'class'    => 'form-control aat-input-sm',
        ]);
        echo "</td><td>" . __('Container code', 'auchanassettracker') . "</td><td>";
        $code_opts = [
            'value' => $this->fields['code'] ?? '',
            'class' => 'form-control aat-input-sm',
        ];
        if ($ID > 0) {
            $code_opts['readonly'] = true;
        } else {
            $code_opts['placeholder'] = __('Auto-generated if empty', 'auchanassettracker');
        }
        echo Html::input('code', $code_opts);
        if ($ID <= 0) {
            echo "<div class='form-text'>"
                . Html::entities_deep(__('Leave empty to auto-generate a code like BUC-A1.', 'auchanassettracker'))
                . "</div>";
        }
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Location') . $req . "</td><td>";
        if ($scope !== null) {
            echo Dropdown::getDropdownName('glpi_locations', $scope);
            echo Html::hidden('locations_id', ['value' => $scope]);
            echo "<div class='form-text'>"
                . Html::entities_deep(__('Fixed from your profile location.', 'auchanassettracker'))
                . "</div>";
        } else {
            Location::dropdown([
                'name'  => 'locations_id',
                'value' => (int) ($this->fields['locations_id'] ?? 0),
                'width' => '220px',
            ]);
        }
        echo "</td><td>" . __('Active') . "</td><td>";
        Dropdown::showYesNo('is_active', (int) ($this->fields['is_active'] ?? 1));
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Description') . "</td><td colspan='3'>";
        echo "<textarea name='description' class='form-control' rows='3'>"
            . Html::entities_deep($this->fields['description'] ?? '')
            . "</textarea>";
        echo "</td></tr>";

        $this->showFormButtons($options);
        return true;
    }

    public static function generateCode(int $locations_id): string
    {
        global $DB;

        $prefix = 'LOC';
        $loc = new Location();
        if ($loc->getFromDB($locations_id)) {
            $name = preg_replace('/[^A-Za-z0-9]/', '', (string) ($loc->fields['name'] ?? 'LOC'));
            $prefix = strtoupper(substr($name !== '' ? $name : 'LOC', 0, 3));
        }

        $n = 1;
        foreach ($DB->request([
            'COUNT' => 'cpt',
            'FROM'  => self::getTable(),
            'WHERE' => ['locations_id' => $locations_id],
        ]) as $row) {
            $n = (int) ($row['cpt'] ?? 0) + 1;
        }

        $code = sprintf('%s-%s%d', $prefix, chr(64 + min(26, $n)), $n);
        // Ensure uniqueness
        $try = 0;
        while (self::codeExists($code) && $try < 50) {
            $n++;
            $code = sprintf('%s-%s%d', $prefix, chr(64 + min(26, (($n - 1) % 26) + 1)), $n);
            $try++;
        }

        return $code;
    }

    public static function codeExists(string $code): bool
    {
        global $DB;
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['code' => $code],
            'LIMIT' => 1,
        ]) as $_) {
            return true;
        }
        return false;
    }

    public static function countAtLocation(int $locations_id): int
    {
        if ($locations_id <= 0) {
            return 0;
        }

        return (int) countElementsInTable(self::getTable(), [
            'locations_id' => $locations_id,
            'is_deleted'   => 0,
            'is_active'    => 1,
        ]);
    }

    /**
     * Dropdown with native + / i:
     * - i → open selected container form
     * - + click → GLPI popup (default)
     * - Ctrl/Cmd+click or middle-click on + → new browser tab
     * - external-link icon → always new tab
     */
    public static function dropdownWithActions(array $options = []): void
    {
        $rand = (int) ($options['rand'] ?? mt_rand());
        $sync_location = !empty($options['sync_location']);
        unset($options['sync_location']);
        $options['rand'] = $rand;
        $options['comments'] = $options['comments'] ?? true;
        $options['addicon'] = $options['addicon'] ?? true;

        echo "<span class='aat-container-dropdown'>";
        self::dropdown($options);
        echo "</span>";

        $base = plugin_auchanassettracker_web_dir();
        $add_url_js = json_encode($base . '/front/container.form.php', JSON_UNESCAPED_SLASHES);
        $view_url_js = json_encode($base . '/front/container.form.php?id=', JSON_UNESCAPED_SLASHES);
        $tip_js = json_encode(
            __('Click: popup · Ctrl+click or middle-click: new tab', 'auchanassettracker'),
            JSON_UNESCAPED_SLASHES
        );
        $newtab_js = json_encode(__('Open in new tab', 'auchanassettracker'), JSON_UNESCAPED_SLASHES);

        echo Html::scriptBlock(<<<JS
$(function () {
   var \$info = $('#comments_link_plugin_auchanassettracker_containers_id{$rand}');
   \$info.off('click').on('click', function (e) {
      e.preventDefault();
      e.stopImmediatePropagation();
      var id = $('#dropdown_plugin_auchanassettracker_containers_id{$rand}').val();
      if (id && parseInt(id, 10) > 0) {
         window.location.href = {$view_url_js} + id;
      }
      return false;
   });

   var \$add = $('#add_plugin_auchanassettracker_containers_id{$rand}');
   if (!\$add.length) {
      \$add = $('.aat-container-dropdown a[id^="add_plugin_auchanassettracker_containers_id{$rand}"]');
   }
   if (\$add.length) {
      // Real href so browser "Open in new tab" / Ctrl+click work;
      // plain left-click still uses GLPI popup (_in_modal).
      \$add.attr('href', {$add_url_js});
      \$add.attr('title', {$tip_js});
      \$add.on('click', function (e) {
         if (e.ctrlKey || e.metaKey || e.shiftKey || e.which === 2) {
            e.preventDefault();
            e.stopImmediatePropagation();
            window.open({$add_url_js}, '_blank');
            return false;
         }
      });
      \$add.on('auxclick', function (e) {
         if (e.button === 1) {
            e.preventDefault();
            e.stopImmediatePropagation();
            window.open({$add_url_js}, '_blank');
            return false;
         }
      });
      if (!\$add.siblings('.aat-container-newtab').length) {
         \$add.after(
            $('<a/>', {
               'class': 'btn btn-outline-secondary btn-sm ms-1 aat-container-newtab',
               'href': {$add_url_js},
               'target': '_blank',
               'rel': 'noopener',
               'title': {$newtab_js},
               'html': '<i class="ti ti-external-link"></i>'
            })
         );
      }
   }
});
JS);

        if ($sync_location) {
            self::scriptSyncLocationContainers($rand);
        }
    }

    /**
     * When location changes: rebuild the container dropdown (fresh GLPI Select2 condition).
     * Empty location → empty container list; location set → only that location's containers.
     */
    public static function scriptSyncLocationContainers(int $container_rand): void
    {
        $ajax = json_encode(
            plugin_auchanassettracker_web_dir() . '/ajax/containers.php',
            JSON_UNESCAPED_SLASHES
        );

        echo Html::scriptBlock(<<<JS
$(function () {
   var \$field = $('.aat-container-field').first();
   if (!\$field.length) {
      \$field = $('.aat-container-dropdown').first().parent();
   }

   function reloadForLocation(locId) {
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

   $(document).off('change.aatLoc sync.aatLoc')
      .on('change.aatLoc', 'select[name="locations_id"]', function () {
         reloadForLocation($(this).val());
      })
      .on('select2:select.aatLoc select2:clear.aatLoc', 'select[name="locations_id"]', function () {
         reloadForLocation($(this).val());
      });
});
JS);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForLocation(int $locations_id): array
    {
        global $DB;
        $rows = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'locations_id' => $locations_id,
                'is_deleted'   => 0,
            ],
            'ORDER' => 'name ASC',
        ]) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function canCreateItem(): bool
    {
        return PluginAuchanassettrackerRighthelper::canManageStock()
            || PluginAuchanassettrackerRighthelper::isCentralAdmin();
    }

    public static function canCreate(): bool
    {
        return (bool) Session::getLoginUserID()
            && (PluginAuchanassettrackerRighthelper::canManageStock()
                || PluginAuchanassettrackerRighthelper::isCentralAdmin());
    }

    public static function canView(): bool
    {
        return (bool) Session::getLoginUserID()
            && (PluginAuchanassettrackerRighthelper::canManageStock()
                || PluginAuchanassettrackerRighthelper::canAllocate()
                || PluginAuchanassettrackerRighthelper::isCentralAdmin());
    }

    public static function canUpdate(): bool
    {
        return (bool) Session::getLoginUserID()
            && (PluginAuchanassettrackerRighthelper::canManageStock()
                || PluginAuchanassettrackerRighthelper::isCentralAdmin());
    }

    public function canUpdateItem(): bool
    {
        if (!PluginAuchanassettrackerRighthelper::canManageStock()
            && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            return false;
        }
        $loc = (int) ($this->fields['locations_id'] ?? 0);
        return PluginAuchanassettrackerRighthelper::canAccessLocation($loc);
    }

    public function canPurgeItem(): bool
    {
        return $this->canUpdateItem();
    }

    public static function canPurge(): bool
    {
        return self::canUpdate();
    }

    public function canViewItem(): bool
    {
        $loc = (int) ($this->fields['locations_id'] ?? 0);
        return PluginAuchanassettrackerRighthelper::canAccessLocation($loc)
            || PluginAuchanassettrackerRighthelper::isCentralAdmin();
    }

    /**
     * Soft-delete by default; hard-delete when $force (purge / delete permanently).
     */
    public function delete(array $input, $force = 0, $history = 1)
    {
        if ($force) {
            return parent::delete($input, $force, $history);
        }

        $input['is_deleted'] = 1;
        $input['is_active'] = 0;
        return $this->update($input);
    }
}
