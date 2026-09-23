<?php

class PluginAuchanassettrackerManufacturer extends CommonDropdown
{
    public static $rightname = 'plugin_auchanassettracker';

    public static function getTypeName($nb = 0): string
    {
        return _n('Manufacturer', 'Manufacturers', $nb, 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_manufacturers';
    }

    public function getAdditionalFields()
    {
        return [
            [
                'name'  => 'is_active',
                'label' => __('Active'),
                'type'  => 'bool',
                'list'  => true,
            ],
        ];
    }

    public static function seedDefaults(): void
    {
        global $DB;

        if (!$DB->tableExists(self::getTable())) {
            return;
        }

        $defaults = ['Lenovo', 'Dell', 'HP', 'Apple', 'Logitech', 'Zebra', 'Samsung', 'Microsoft', 'Acer', 'ASUS'];
        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        foreach ($defaults as $name) {
            $exists = false;
            foreach ($DB->request([
                'FROM'  => self::getTable(),
                'WHERE' => ['name' => $name],
                'LIMIT' => 1,
            ]) as $_) {
                $exists = true;
            }
            if (!$exists) {
                $DB->insert(self::getTable(), [
                    'name'          => $name,
                    'is_active'     => 1,
                    'date_creation' => $now,
                    'date_mod'      => $now,
                ]);
            }
        }
    }
}
