<?php

class PluginAuchanassettrackerMenu extends CommonGLPI
{
    public static $rightname = 'plugin_auchanassettracker';

    /** Top-level menu sector (not under Assets). */
    public const SECTOR = 'auchanassettracker';

    /** Option keys under the Auchan Asset Tracker menu. */
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

    /**
     * @return array{title: string, page: string, icon: string, content: array<string, array<string, mixed>>}|false
     */
    public static function getMenuContent(): array|false
    {
        if (!Session::getLoginUserID()) {
            return false;
        }

        $base = plugin_auchanassettracker_web_dir(true);
        $can_stock = PluginAuchanassettrackerRighthelper::canManageStock();
        $can_alloc = PluginAuchanassettrackerRighthelper::canAllocate();
        $is_admin  = PluginAuchanassettrackerRighthelper::isCentralAdmin();

        $content = [];

        if ($can_stock || $can_alloc || $is_admin) {
            $content[self::MENU_EQUIPMENT] = [
                'title' => PluginAuchanassettrackerEquipment::getTypeName(Session::getPluralNumber()),
                'page'  => "$base/front/equipment.php",
                'icon'  => PluginAuchanassettrackerEquipment::getIcon(),
                'links' => [
                    'search' => "$base/front/equipment.php",
                    'add'    => "$base/front/equipment.form.php",
                ],
            ];
            $content[self::MENU_CONTAINER] = [
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
            $content[self::MENU_BULK] = [
                'title' => PluginAuchanassettrackerBulk::getTypeName(1),
                'page'  => "$base/front/equipment.bulk.php",
                'icon'  => PluginAuchanassettrackerBulk::getIcon(),
            ];
        }

        if ($can_alloc) {
            $content[self::MENU_ALLOCATION] = [
                'title' => __('New allocation', 'auchanassettracker'),
                'page'  => "$base/front/allocation.form.php",
                'icon'  => 'ti ti-user-plus',
            ];
        }

        $content[self::MENU_CONFIRM] = [
            'title' => __('Confirm receipt', 'auchanassettracker'),
            'page'  => "$base/front/confirm.php",
            'icon'  => 'ti ti-check',
        ];

        if ($is_admin) {
            $content[self::MENU_CONFIG] = [
                'title' => __('Configuration'),
                'page'  => "$base/front/config.form.php",
                'icon'  => 'ti ti-settings',
            ];
        }

        if ($content === []) {
            return false;
        }

        $default = "$base/front/confirm.php";
        if ($can_alloc) {
            $default = "$base/front/allocation.form.php";
        } elseif ($can_stock) {
            $default = "$base/front/equipment.php";
        }

        return [
            'title'   => self::getMenuName(),
            'page'    => $default,
            'icon'    => self::getIcon(),
            'content' => $content,
        ];
    }
}
