<?php

class PluginAuchanassettrackerMenu extends CommonGLPI
{
    public static $rightname = 'plugin_auchanassettracker';

    /** Menu content keys under Assets (must match Html::header 4th argument). */
    public const MENU_EQUIPMENT = 'aat_equipment';
    public const MENU_CONTAINER = 'aat_container';
    public const MENU_BULK      = 'aat_bulk';

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

        // Super-Admin / config editors always see the menu; stock roles too.
        $can = PluginAuchanassettrackerRighthelper::canManageStock()
            || PluginAuchanassettrackerRighthelper::isCentralAdmin()
            || Session::haveRight('config', UPDATE)
            || Session::haveRight(self::$rightname, READ);

        if (!$can) {
            return false;
        }

        $base = plugin_auchanassettracker_web_dir(true);

        // Separate Assets sidebar entries (GLPI does not always show options as a top bar).
        $menu = [
            'is_multi_entries'   => true,
            self::MENU_EQUIPMENT => [
                'title' => PluginAuchanassettrackerEquipment::getTypeName(Session::getPluralNumber()),
                'page'  => "$base/front/equipment.php",
                'icon'  => PluginAuchanassettrackerEquipment::getIcon(),
                'links' => [
                    'search' => "$base/front/equipment.php",
                    'add'    => "$base/front/equipment.form.php",
                ],
            ],
            self::MENU_CONTAINER => [
                'title' => PluginAuchanassettrackerContainer::getTypeName(Session::getPluralNumber()),
                'page'  => "$base/front/container.php",
                'icon'  => PluginAuchanassettrackerContainer::getIcon(),
                'links' => [
                    'search' => "$base/front/container.php",
                    'add'    => "$base/front/container.form.php",
                ],
            ],
            self::MENU_BULK => [
                'title' => __('Bulk add accessories', 'auchanassettracker'),
                'page'  => "$base/front/equipment.bulk.php",
                'icon'  => 'ti ti-stack-2',
            ],
        ];

        return $menu;
    }
}
