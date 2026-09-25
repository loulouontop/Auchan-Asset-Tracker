<?php

/**
 * Find glpi_plugins rows that belong to this plugin (any directory casing).
 *
 * @return list<array<string, mixed>>
 */
function plugin_auchanassettracker_find_plugin_rows(): array
{
    global $DB;

    $rows = [];
    if (!$DB->tableExists('glpi_plugins')) {
        return $rows;
    }

    foreach ($DB->request(['FROM' => 'glpi_plugins']) as $row) {
        if (strcasecmp((string) ($row['directory'] ?? ''), 'auchanassettracker') === 0) {
            $rows[] = $row;
        }
    }

    return $rows;
}

/**
 * Keep a single glpi_plugins row and force directory = exact disk folder name.
 *
 * @return array{id: int, version: string, state: int}|null
 */
function plugin_auchanassettracker_sync_plugin_directory(): ?array
{
    global $DB;

    $rows = plugin_auchanassettracker_find_plugin_rows();
    if ($rows === []) {
        return null;
    }

    // Exact on-disk folder (e.g. AuchanAssetTracker). Never invent another casing.
    $canonical = plugin_auchanassettracker_dir();

    // Prefer a row that already matches the disk name; otherwise rewrite the first.
    $keep = null;
    foreach ($rows as $row) {
        if ((string) $row['directory'] === $canonical) {
            $keep = $row;
            break;
        }
    }
    if ($keep === null) {
        $keep = $rows[0];
    }

    $keepId = (int) $keep['id'];

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        if ($id !== $keepId) {
            $DB->delete('glpi_plugins', ['id' => $id]);
        }
    }

    $DB->update('glpi_plugins', [
        'directory' => $canonical,
        'name'      => 'Auchan Asset Tracker',
    ], ['id' => $keepId]);

    // Re-read state/version after update in case another heal changed them.
    $version = (string) ($keep['version'] ?? '');
    $state = (int) ($keep['state'] ?? 2);
    foreach ($DB->request([
        'FROM'  => 'glpi_plugins',
        'WHERE' => ['id' => $keepId],
        'LIMIT' => 1,
    ]) as $fresh) {
        $version = (string) ($fresh['version'] ?? $version);
        $state = (int) ($fresh['state'] ?? $state);
    }

    return [
        'id'      => $keepId,
        'version' => $version,
        'state'   => $state,
    ];
}

/**
 * Heal directory/version drift as soon as setup.php is loaded.
 *
 * GLPI compares folder name vs glpi_plugins.directory with PHP (case-sensitive).
 * A wrong casing creates a ghost DB row ("unable to load") plus a real folder
 * marked "version changed". This heal collapses rows to the disk name and
 * finishes the upgrade so the plugin stays usable.
 */
function plugin_auchanassettracker_self_heal_on_load(): void
{
    global $DB;

    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (!isset($DB) || !is_object($DB) || !method_exists($DB, 'tableExists')) {
        return;
    }

    try {
        if (!$DB->tableExists('glpi_plugins')) {
            return;
        }
    } catch (Throwable) {
        return;
    }

    $info = plugin_auchanassettracker_sync_plugin_directory();
    if ($info === null) {
        return;
    }

    $canonical = plugin_auchanassettracker_dir();
    $needsVersion = $info['version'] !== PLUGIN_AUCHANASSETTRACKER_VERSION;
    // GLPI: ACTIVATED=1, NOTACTIVATED=2, TOBECONFIGURED=3, NOTINSTALLED=4, NOTUPDATED=6
    $state = (int) $info['state'];
    $wasPendingUpdate = ($state === 6);
    $wasActive = ($state === 1);
    $wasInstalled = in_array($state, [1, 2, 3, 6], true);

    // Directory sync already ran. Continue when version drifted or plugin is stuck
    // in "to update" / deactivated-after-update (no Assets menu until ACTIVATED).
    if (!$needsVersion && !$wasPendingUpdate && $wasActive) {
        return;
    }
    if (!$needsVersion && !$wasPendingUpdate && !$wasInstalled) {
        return;
    }

    static $upgrading = false;
    if ($upgrading) {
        return;
    }
    $upgrading = true;

    try {
        if (($needsVersion || $wasPendingUpdate) && function_exists('plugin_auchanassettracker_upgrade')) {
            plugin_auchanassettracker_upgrade($info['version']);
        }

        // Bring the plugin back online whenever it was already installed.
        $newState = $wasInstalled || $needsVersion || $wasPendingUpdate ? 1 : $state;
        if ($state === 4) {
            $newState = 4; // never auto-install
        }

        $DB->update('glpi_plugins', [
            'directory' => $canonical,
            'version'   => PLUGIN_AUCHANASSETTRACKER_VERSION,
            'name'      => 'Auchan Asset Tracker',
            'state'     => $newState,
        ], ['id' => $info['id']]);
    } catch (Throwable) {
        // Never break GLPI boot because of plugin self-heal.
    } finally {
        $upgrading = false;
    }
}

function plugin_auchanassettracker_install(array $params = []): bool
{
    global $DB;

    $sqlFile = __DIR__ . '/install/install.sql';
    if (is_readable($sqlFile)) {
        foreach (explode(';', (string) file_get_contents($sqlFile)) as $query) {
            $query = trim($query);
            if ($query !== '') {
                $DB->doQuery($query);
            }
        }
    }

    plugin_auchanassettracker_ensure_schema();

    plugin_auchanassettracker_bootstrap();

    if (class_exists('PluginAuchanassettrackerEquipmenttype', false)) {
        PluginAuchanassettrackerEquipmenttype::seedDefaults();
    }
    if (class_exists('PluginAuchanassettrackerManufacturer', false)) {
        PluginAuchanassettrackerManufacturer::seedDefaults();
    }
    if (class_exists('PluginAuchanassettrackerProfile', false)) {
        PluginAuchanassettrackerProfile::initProfile();
    }

    plugin_auchanassettracker_sync_plugin_directory();
    $rows = plugin_auchanassettracker_find_plugin_rows();
    if ($rows !== []) {
        $DB->update('glpi_plugins', [
            'version' => PLUGIN_AUCHANASSETTRACKER_VERSION,
        ], ['id' => (int) $rows[0]['id']]);
    }

    plugin_auchanassettracker_clear_translation_cache();

    return true;
}

function plugin_auchanassettracker_upgrade($version): bool
{
    global $DB;

    if (is_readable(__DIR__ . '/install/install.sql')) {
        foreach (explode(';', (string) file_get_contents(__DIR__ . '/install/install.sql')) as $query) {
            $query = trim($query);
            if ($query !== '') {
                $DB->doQuery($query);
            }
        }
    }

    plugin_auchanassettracker_ensure_schema();

    plugin_auchanassettracker_bootstrap();

    if (class_exists('PluginAuchanassettrackerEquipmenttype', false)) {
        PluginAuchanassettrackerEquipmenttype::seedDefaults();
    }
    if (class_exists('PluginAuchanassettrackerManufacturer', false)) {
        PluginAuchanassettrackerManufacturer::seedDefaults();
    }
    if (class_exists('PluginAuchanassettrackerProfile', false)) {
        PluginAuchanassettrackerProfile::initProfile();
    }

    plugin_auchanassettracker_sync_plugin_directory();
    $rows = plugin_auchanassettracker_find_plugin_rows();
    if ($rows !== []) {
        $DB->update('glpi_plugins', [
            'version' => PLUGIN_AUCHANASSETTRACKER_VERSION,
        ], ['id' => (int) $rows[0]['id']]);
    }

    plugin_auchanassettracker_clear_translation_cache();

    return true;
}

function plugin_auchanassettracker_clear_translation_cache(): void
{
    if (!class_exists(\Glpi\Cache\CacheManager::class, false)) {
        return;
    }

    try {
        (new \Glpi\Cache\CacheManager())->getTranslationsCacheInstance()->clear();
    } catch (Throwable) {
        // Ignore cache backend issues during upgrade.
    }
}

function plugin_auchanassettracker_uninstall(): bool
{
    // Intentionally do NOT drop plugin tables or purge stock data.
    // Disable/uninstall only deactivates the plugin so a later reinstall
    // can reuse existing containers, equipment and role mappings.
    plugin_auchanassettracker_clear_translation_cache();
    return true;
}

function plugin_auchanassettracker_getDatabaseRelations(): array
{
    return [];
}

/**
 * Add columns introduced after first install (CREATE TABLE IF NOT EXISTS won't alter).
 * Also heal leftover UNIQUE qr_token from older installs (empty '' collides).
 */
function plugin_auchanassettracker_ensure_schema(): void
{
    global $DB;

    $table = 'glpi_plugin_auchanassettracker_equipments';
    if ($DB->tableExists($table)) {
        $columns = [
            'itemtype'         => "VARCHAR(100) NOT NULL DEFAULT 'Computer'",
            'items_id'         => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'manufacturers_id' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        ];

        foreach ($columns as $name => $definition) {
            if (!$DB->fieldExists($table, $name)) {
                $DB->doQuery("ALTER TABLE `$table` ADD `$name` $definition");
            }
        }
    }

    // Older full-plugin installs kept UNIQUE qr_token DEFAULT ''.
    // Backfill empties so new inserts no longer hit duplicate-key 1062.
    $containers = 'glpi_plugin_auchanassettracker_containers';
    if ($DB->tableExists($containers) && $DB->fieldExists($containers, 'qr_token')) {
        foreach ($DB->request([
            'SELECT' => ['id', 'qr_token'],
            'FROM'   => $containers,
        ]) as $row) {
            $token = trim((string) ($row['qr_token'] ?? ''));
            if ($token !== '') {
                continue;
            }
            $DB->update($containers, [
                'qr_token' => bin2hex(random_bytes(16)),
            ], ['id' => (int) $row['id']]);
        }
    }
}

/**
 * Move Auchan Asset Tracker to the first position under Assets.
 *
 * @param array<string, mixed> $menu
 * @return array<string, mixed>
 */
function plugin_auchanassettracker_redefine_menus(array $menu): array
{
    if (!isset($menu['assets']['content']) || !is_array($menu['assets']['content'])) {
        return $menu;
    }

    $content = $menu['assets']['content'];
    $foundKey = null;

    foreach (array_keys($content) as $key) {
        if (stripos((string) $key, 'auchanassettracker') !== false) {
            $foundKey = $key;
            break;
        }
    }

    if ($foundKey === null) {
        return $menu;
    }

    $item = $content[$foundKey];
    unset($content[$foundKey]);
    $menu['assets']['content'] = [$foundKey => $item] + $content;

    return $menu;
}

// When GLPI includes setup.php during plugin state checks, heal DB drift first.
plugin_auchanassettracker_self_heal_on_load();
