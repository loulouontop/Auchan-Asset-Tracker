<?php

class PluginAuchanassettrackerMenu extends CommonGLPI
{
    public static $rightname = 'plugin_auchanassettracker';

    /** Menu content keys under Assets (must match Html::header 4th argument). */
    public const MENU_EQUIPMENT  = 'aat_equipment';
    public const MENU_CONTAINER  = 'aat_container';
    public const MENU_BULK       = 'aat_bulk';
    public const MENU_ALLOCATION = 'aat_allocation';
    public const MENU_CONFIRM    = 'aat_confirm';
    public const MENU_CONFIG     = 'aat_config';

    public static function getIcon(): string
    {
        return 'ti ti-packages';
    }

    public static function getMenuName(): string
    {
        return __('Auchan Asset Tracker', 'auchanassettracker');
    }

    public static function getMenuContent(): array|false
    {
        if (!Session::getLoginUserID()) {
            return false;
        }

        $base = plugin_auchanassettracker_web_dir(true);
        $menu = [
            'is_multi_entries' => true,
        ];

        $can_stock = PluginAuchanassettrackerRighthelper::canManageStock()
            || PluginAuchanassettrackerRighthelper::isCentralAdmin();

        if ($can_stock
            || PluginAuchanassettrackerRighthelper::canAllocate()
            || Session::haveRight('config', UPDATE)
            || Session::haveRight(self::$rightname, READ)) {
            $menu[self::MENU_EQUIPMENT] = [
                'title' => PluginAuchanassettrackerEquipment::getTypeName(Session::getPluralNumber()),
                'page'  => "$base/front/equipment.php",
                'icon'  => PluginAuchanassettrackerEquipment::getIcon(),
                'links' => [
                    'search' => "$base/front/equipment.php",
                    'add'    => "$base/front/equipment.form.php",
                ],
            ];
            $menu[self::MENU_CONTAINER] = [
                'title' => PluginAuchanassettrackerContainer::getTypeName(Session::getPluralNumber()),
                'page'  => "$base/front/container.php",
                'icon'  => PluginAuchanassettrackerContainer::getIcon(),
                'links' => [
                    'search' => "$base/front/container.php",
                    'add'    => "$base/front/container.form.php",
                ],
            ];
        }

        if ($can_stock) {
            $menu[self::MENU_BULK] = [
                'title' => PluginAuchanassettrackerBulk::getTypeName(1),
                'page'  => "$base/front/equipment.bulk.php",
                'icon'  => PluginAuchanassettrackerBulk::getIcon(),
            ];
        }

        if (PluginAuchanassettrackerRighthelper::canAllocate()) {
            $menu[self::MENU_ALLOCATION] = [
                'title' => __('New allocation', 'auchanassettracker'),
                'page'  => "$base/front/allocation.form.php",
                'icon'  => 'ti ti-user-plus',
            ];
        }

        // End users + managers: confirm receipt / see pending.
        $menu[self::MENU_CONFIRM] = [
            'title' => __('Confirm receipt', 'auchanassettracker'),
            'page'  => "$base/front/confirm.php",
            'icon'  => 'ti ti-check',
        ];

        if (PluginAuchanassettrackerRighthelper::isCentralAdmin()
            || Session::haveRight('config', UPDATE)) {
            $menu[self::MENU_CONFIG] = [
                'title' => __('Configuration'),
                'page'  => "$base/front/config.form.php",
                'icon'  => 'ti ti-settings',
            ];
        }

        // Only multi-entry flag → hide empty menu for users with no rights.
        if (count($menu) <= 1) {
            return false;
        }

        return $menu;
    }
}
