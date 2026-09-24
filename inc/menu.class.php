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

        if (!PluginAuchanassettrackerRighthelper::canManageStock()
            && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            return false;
        }

        $base = Plugin::getWebDir(plugin_auchanassettracker_dir());

        $menu = [
            'title' => self::getMenuName(),
            'page'  => $base . '/front/equipment.php',
            'icon'  => self::getIcon(),
        ];

        $menu['options']['equipment'] = [
            'title' => PluginAuchanassettrackerEquipment::getTypeName(Session::getPluralNumber()),
            'page'  => $base . '/front/equipment.php',
            'links' => [
                'search' => $base . '/front/equipment.php',
                'add'    => $base . '/front/equipment.form.php',
            ],
            'icon'  => PluginAuchanassettrackerEquipment::getIcon(),
        ];

        $menu['options']['container'] = [
            'title' => PluginAuchanassettrackerContainer::getTypeName(Session::getPluralNumber()),
            'page'  => $base . '/front/container.php',
            'links' => [
                'search' => $base . '/front/container.php',
                'add'    => $base . '/front/container.form.php',
            ],
            'icon'  => PluginAuchanassettrackerContainer::getIcon(),
        ];

        $menu['options']['bulk'] = [
            'title' => __('Bulk add accessories', 'auchanassettracker'),
            'page'  => $base . '/front/equipment.bulk.php',
            'icon'  => 'ti ti-stack-2',
        ];

        if (PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
            $menu['options']['equipmenttype'] = [
                'title' => PluginAuchanassettrackerEquipmenttype::getTypeName(Session::getPluralNumber()),
                'page'  => $base . '/front/equipmenttype.php',
                'icon'  => 'ti ti-list',
            ];
            $menu['options']['manufacturer'] = [
                'title' => PluginAuchanassettrackerManufacturer::getTypeName(Session::getPluralNumber()),
                'page'  => $base . '/front/manufacturer.php',
                'icon'  => 'ti ti-building-factory',
            ];
        }

        return $menu;
    }
}
