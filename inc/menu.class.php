<?php

class PluginAuchanassettrackerMenu extends CommonGLPI
{
    public static $rightname = 'plugin_auchanassettracker';

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

        $menu = [
            'title' => self::getMenuName(),
            'page'  => "$base/front/equipment.php",
            'icon'  => self::getIcon(),
        ];

        $menu['options']['equipment'] = [
            'title' => PluginAuchanassettrackerEquipment::getTypeName(Session::getPluralNumber()),
            'page'  => "$base/front/equipment.php",
            'links' => [
                'search' => "$base/front/equipment.php",
                'add'    => "$base/front/equipment.form.php",
            ],
            'icon'  => PluginAuchanassettrackerEquipment::getIcon(),
        ];

        $menu['options']['container'] = [
            'title' => PluginAuchanassettrackerContainer::getTypeName(Session::getPluralNumber()),
            'page'  => "$base/front/container.php",
            'links' => [
                'search' => "$base/front/container.php",
                'add'    => "$base/front/container.form.php",
            ],
            'icon'  => PluginAuchanassettrackerContainer::getIcon(),
        ];

        $menu['options']['bulk'] = [
            'title' => __('Bulk add accessories', 'auchanassettracker'),
            'page'  => "$base/front/equipment.bulk.php",
            'icon'  => 'ti ti-stack-2',
        ];

        return $menu;
    }
}
