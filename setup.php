<?php
/**
 * Auchan Asset Tracker — GLPI 11 plugin (Sprint 1).
 *
 * Stock receipt, physical containers, rights, audit. RO + EN.
 *
 * @author    Lokmane Benaziza
 * @copyright 2026 Auchan Romania
 */

define('PLUGIN_AUCHANASSETTRACKER_VERSION', '0.1.11');
define('PLUGIN_AUCHANASSETTRACKER_MIN_GLPI', '11.0.0');
define('PLUGIN_AUCHANASSETTRACKER_MAX_GLPI', '11.9.99');
/**
 * Exact plugins/ folder name on disk (case-sensitive on Linux).
 * Must match glpi_plugins.directory — never hardcode a different casing.
 */
define('PLUGIN_AUCHANASSETTRACKER_DIR', basename(__DIR__));

/**
 * Single plugin directory key for hooks, menus, DB row, and every URL.
 */
function plugin_auchanassettracker_dir(): string
{
    return PLUGIN_AUCHANASSETTRACKER_DIR;
}

/**
 * Web base path for this plugin (/plugins/<exact-folder-name>).
 */
function plugin_auchanassettracker_web_dir(bool $full = false): string
{
    return Plugin::getWebDir(plugin_auchanassettracker_dir(), $full);
}

function plugin_auchanassettracker_bootstrap(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    foreach ([
        'pluginlog',
        'auditlog',
        'profile',
        'righthelper',
        'equipmenttype',
        'manufacturer',
        'container',
        'equipment',
        'menu',
    ] as $file) {
        $path = __DIR__ . '/inc/' . $file . '.class.php';
        if (is_readable($path)) {
            require_once $path;
        }
    }

    $loaded = true;
}

function plugin_auchanassettracker_load_translations(): void
{
    global $TRANSLATE;

    $lang = (string) ($_SESSION['glpilanguage'] ?? 'en_GB');
    if ($lang === '') {
        $lang = 'en_GB';
    }

    if (isset($TRANSLATE) && !str_starts_with($lang, 'en')) {
        $phpfile = __DIR__ . '/locales/' . $lang . '.php';
        if (is_readable($phpfile)) {
            $TRANSLATE->addTranslationFile('phparray', $phpfile, 'auchanassettracker', $lang);
        }
    }
}

function plugin_auchanassettracker_post_init(): void
{
    plugin_auchanassettracker_load_translations();
}

function plugin_init_auchanassettracker(): void
{
    global $PLUGIN_HOOKS, $DB;

    $plug = plugin_auchanassettracker_dir();

    $PLUGIN_HOOKS['csrf_compliant'][$plug] = true;
    $PLUGIN_HOOKS['post_init'][$plug] = 'plugin_auchanassettracker_post_init';

    plugin_auchanassettracker_bootstrap();

    if ($DB->tableExists('glpi_plugin_auchanassettracker_equipments')
        || $DB->tableExists('glpi_plugin_auchanassettracker_containers')) {
        plugin_auchanassettracker_ensure_schema();
    }

    if ($DB->tableExists('glpi_plugin_auchanassettracker_equipmenttypes')) {
        PluginAuchanassettrackerEquipmenttype::seedDefaults();
    }
    if ($DB->tableExists('glpi_plugin_auchanassettracker_manufacturers')) {
        PluginAuchanassettrackerManufacturer::seedDefaults();
    }

    if (!Session::getLoginUserID()) {
        return;
    }

    Plugin::registerClass('PluginAuchanassettrackerEquipmenttype');
    Plugin::registerClass('PluginAuchanassettrackerManufacturer');
    Plugin::registerClass('PluginAuchanassettrackerContainer');
    Plugin::registerClass('PluginAuchanassettrackerEquipment');
    Plugin::registerClass('PluginAuchanassettrackerProfile', [
        'addtabon' => ['Profile'],
    ]);

    $PLUGIN_HOOKS['menu_toadd'][$plug] = [
        'assets' => 'PluginAuchanassettrackerMenu',
    ];

    $PLUGIN_HOOKS['redefine_menus'][$plug] = 'plugin_auchanassettracker_redefine_menus';

    $PLUGIN_HOOKS['add_css'][$plug][] = 'css/assettracker.css';
}

function plugin_version_auchanassettracker(): array
{
    return [
        'name'           => 'Auchan Asset Tracker',
        'version'        => PLUGIN_AUCHANASSETTRACKER_VERSION,
        'author'         => 'Lokmane BENAZIZA',
        'license'        => 'Auchan RO',
        'homepage'       => '',
        'minGlpiVersion' => PLUGIN_AUCHANASSETTRACKER_MIN_GLPI,
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_AUCHANASSETTRACKER_MIN_GLPI,
                'max' => PLUGIN_AUCHANASSETTRACKER_MAX_GLPI,
            ],
            'php' => ['min' => '8.1'],
        ],
    ];
}

function plugin_auchanassettracker_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_AUCHANASSETTRACKER_MIN_GLPI, 'lt')) {
        echo 'GLPI ' . PLUGIN_AUCHANASSETTRACKER_MIN_GLPI . ' or higher is required.';
        return false;
    }
    return true;
}

function plugin_auchanassettracker_check_config(bool $verbose = false): bool
{
    return true;
}

if (is_readable(__DIR__ . '/hook.php')) {
    require_once __DIR__ . '/hook.php';
}
