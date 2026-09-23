<?php

/**
 * Align glpi_plugins.directory with the real folder name.
 *
 * A previous install wrote directory=auchanassettracker while the folder on disk
 * may be AuchanAssetTracker. MySQL matches case-insensitively, but PHP/Linux do
 * not — that produces "Unable to load plugin information" and "version changed".
 */
function plugin_auchanassettracker_sync_plugin_directory(): void
{
    global $DB;

    if (!$DB->tableExists('glpi_plugins')) {
        return;
    }

    $canonical = plugin_auchanassettracker_dir();
    $rows = [];

    foreach ($DB->request(['FROM' => 'glpi_plugins']) as $row) {
        if (strcasecmp((string) ($row['directory'] ?? ''), 'auchanassettracker') === 0) {
            $rows[] = $row;
        }
    }

    if ($rows === []) {
        return;
    }

    // Prefer an exact directory match; otherwise keep the first row.
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
        'version'   => PLUGIN_AUCHANASSETTRACKER_VERSION,
        'name'      => 'Auchan Asset Tracker',
    ], ['id' => $keepId]);
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

    plugin_auchanassettracker_bootstrap();

    if (class_exists('PluginAuchanassettrackerConfig', false)) {
        PluginAuchanassettrackerConfig::seedDefaults();
    }
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

    plugin_auchanassettracker_bootstrap();

    if (class_exists('PluginAuchanassettrackerConfig', false)) {
        PluginAuchanassettrackerConfig::seedDefaults();
    }
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
    // Keep tables so reinstall preserves data (same approach as first plugin).
    plugin_auchanassettracker_clear_translation_cache();
    return true;
}

function plugin_auchanassettracker_getDatabaseRelations(): array
{
    return [];
}
