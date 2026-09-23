<?php
/**
 * Auchan Asset Tracker — GLPI 11 plugin.
 *
 * Equipment tracking: stock, containers, allocation, transfers, service, QR, dashboards.
 *
 * @author    Lokmane Benaziza
 * @copyright 2026 Auchan Romania
 */

define('PLUGIN_AUCHANASSETTRACKER_VERSION', '1.0.0');
define('PLUGIN_AUCHANASSETTRACKER_MIN_GLPI', '11.0.0');
define('PLUGIN_AUCHANASSETTRACKER_MAX_GLPI', '11.9.99');

function plugin_auchanassettracker_bootstrap(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    foreach ([
        'pluginlog',
        'config',
        'auditlog',
        'profile',
        'righthelper',
        'equipmenttype',
        'manufacturer',
        'container',
        'equipment',
        'allocation',
        'transfer',
        'transferitem',
        'dashboard',
        'report',
        'tickethook',
        'qrhelper',
        'menu',
        'mailhelper',
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

    $PLUGIN_HOOKS['csrf_compliant']['auchanassettracker'] = true;
    $PLUGIN_HOOKS['post_init']['auchanassettracker'] = 'plugin_auchanassettracker_post_init';

    plugin_auchanassettracker_bootstrap();

    if ((!$DB->tableExists('glpi_plugin_auchanassettracker_equipments')
            || !$DB->tableExists('glpi_plugin_auchanassettracker_containers')
            || !$DB->tableExists('glpi_plugin_auchanassettracker_configs'))
        && is_readable(__DIR__ . '/install/install.sql')) {
        plugin_auchanassettracker_install();
    }

    if ($DB->tableExists('glpi_plugin_auchanassettracker_configs')) {
        PluginAuchanassettrackerConfig::seedDefaults();
    }
    if ($DB->tableExists('glpi_plugin_auchanassettracker_equipmenttypes')) {
        PluginAuchanassettrackerEquipmenttype::seedDefaults();
    }
    if ($DB->tableExists('glpi_plugin_auchanassettracker_manufacturers')) {
        PluginAuchanassettrackerManufacturer::seedDefaults();
    }

    if (!Session::getLoginUserID()) {
        // Public QR page still needs classes; ticket hooks only when logged in.
        return;
    }

    Plugin::registerClass('PluginAuchanassettrackerEquipmenttype');
    Plugin::registerClass('PluginAuchanassettrackerManufacturer');
    Plugin::registerClass('PluginAuchanassettrackerContainer');
    Plugin::registerClass('PluginAuchanassettrackerEquipment');
    Plugin::registerClass('PluginAuchanassettrackerAllocation');
    Plugin::registerClass('PluginAuchanassettrackerTransfer');
    Plugin::registerClass('PluginAuchanassettrackerProfile', [
        'addtabon' => ['Profile'],
    ]);

    $PLUGIN_HOOKS['menu_toadd']['auchanassettracker'] = [
        'assets' => 'PluginAuchanassettrackerMenu',
    ];

    if (Session::haveRight('config', UPDATE)
        || PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
        $PLUGIN_HOOKS['config_page']['auchanassettracker'] = 'front/config.form.php';
    }

    $PLUGIN_HOOKS['item_add']['auchanassettracker'] = [
        'Ticket'      => ['PluginAuchanassettrackerTickethook', 'postTicketAdd'],
        'Item_Ticket' => ['PluginAuchanassettrackerTickethook', 'postItemTicketAdd'],
    ];
    $PLUGIN_HOOKS['item_update']['auchanassettracker'] = [
        'Ticket' => ['PluginAuchanassettrackerTickethook', 'postTicketUpdate'],
    ];

    $PLUGIN_HOOKS['add_css']['auchanassettracker'][] = 'css/assettracker.css';
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
