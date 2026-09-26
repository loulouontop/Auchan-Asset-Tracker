<?php

/**
 * Plugin configuration (Sprint 2: allocation confirm threshold only).
 */
class PluginAuchanassettrackerConfig extends CommonDBTM
{
    public static $rightname = 'config';

    public const KEY_ALLOC_DAYS = 'allocation_confirm_days';

    public const DEFAULT_ALLOC_DAYS = 5;

    public static function getTypeName($nb = 0): string
    {
        return __('Auchan Asset Tracker - configuration', 'auchanassettracker');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_auchanassettracker_configs';
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        global $DB;

        if (!$DB->tableExists(self::getTable())) {
            return $default;
        }

        foreach ($DB->request([
            'SELECT' => ['value'],
            'FROM'   => self::getTable(),
            'WHERE'  => ['name' => $key],
            'LIMIT'  => 1,
        ]) as $row) {
            return (string) ($row['value'] ?? $default);
        }

        return $default;
    }

    public static function set(string $key, string $value): bool
    {
        global $DB;

        if (!$DB->tableExists(self::getTable())) {
            return false;
        }

        $existing = 0;
        foreach ($DB->request([
            'SELECT' => ['id'],
            'FROM'   => self::getTable(),
            'WHERE'  => ['name' => $key],
            'LIMIT'  => 1,
        ]) as $row) {
            $existing = (int) ($row['id'] ?? 0);
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        if ($existing > 0) {
            return (bool) $DB->update(
                self::getTable(),
                ['value' => $value, 'date_mod' => $now],
                ['id' => $existing]
            );
        }

        return (bool) $DB->insert(self::getTable(), [
            'name'          => $key,
            'value'         => $value,
            'date_creation' => $now,
            'date_mod'      => $now,
        ]);
    }

    public static function getInt(string $key, int $default): int
    {
        $raw = self::get($key, (string) $default);
        $v = (int) $raw;
        return $v > 0 ? $v : $default;
    }

    public static function getAllocationConfirmDays(): int
    {
        return self::getInt(self::KEY_ALLOC_DAYS, self::DEFAULT_ALLOC_DAYS);
    }

    public static function saveThresholds(int $alloc): void
    {
        self::set(self::KEY_ALLOC_DAYS, (string) max(1, $alloc));
    }

    public static function seedDefaults(): void
    {
        if (self::get(self::KEY_ALLOC_DAYS) === null) {
            self::set(self::KEY_ALLOC_DAYS, (string) self::DEFAULT_ALLOC_DAYS);
        }
    }
}
