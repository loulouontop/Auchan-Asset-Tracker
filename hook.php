<?php

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

    plugin_auchanassettracker_clear_translation_cache();

    if (defined('PLUGIN_AUCHANASSETTRACKER_VERSION') && $DB->tableExists('glpi_plugins')) {
        $DB->updateOrInsert(
            'glpi_plugins',
            ['version' => PLUGIN_AUCHANASSETTRACKER_VERSION],
            ['directory' => 'auchanassettracker']
        );
    }

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

    if (defined('PLUGIN_AUCHANASSETTRACKER_VERSION') && $DB->tableExists('glpi_plugins')) {
        $DB->updateOrInsert(
            'glpi_plugins',
            [
                'version' => PLUGIN_AUCHANASSETTRACKER_VERSION,
                'state'   => 1,
            ],
            ['directory' => 'auchanassettracker']
        );
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
    // Keep tables so reinstall preserves data (same approach as first plugin).
    plugin_auchanassettracker_clear_translation_cache();
    return true;
}

function plugin_auchanassettracker_getDatabaseRelations(): array
{
    return [];
}
