#!/usr/bin/env php
<?php
/**
 * Offline structure checks (no GLPI required).
 */
$root = dirname(__DIR__);
$errors = 0;

function fail(string $msg): void
{
    global $errors;
    echo "FAIL: $msg\n";
    $errors++;
}

function ok(string $msg): void
{
    echo "OK: $msg\n";
}

foreach ([
    'setup.php', 'hook.php', 'install/install.sql',
    'inc/equipment.class.php', 'inc/container.class.php', 'inc/allocation.class.php',
    'inc/assetform.class.php', 'inc/bulk.class.php', 'inc/mailhelper.class.php', 'inc/config.class.php',
    'front/allocation.form.php', 'front/confirm.php', 'front/config.form.php',
    'ajax/containers.php', 'css/assettracker.css', 'public/css/assettracker.css',
    'locales/en_GB.php', 'locales/ro_RO.php',
] as $rel) {
    if (!is_readable("$root/$rel")) {
        fail("missing $rel");
    } else {
        ok($rel);
    }
}

foreach ([
    'inc/transfer.class.php', 'inc/dashboard.class.php', 'inc/report.class.php',
    'inc/tickethook.class.php', 'inc/qrhelper.class.php',
    'front/dashboard.php', 'front/transfer.php', 'front/report.php',
    'public/qr.php', 'docs/README.md',
] as $rel) {
    if (file_exists("$root/$rel")) {
        fail("Sprint 3+ file still present: $rel");
    } else {
        ok("absent $rel");
    }
}

$sql = file_get_contents("$root/install/install.sql");
foreach ([
    'glpi_plugin_auchanassettracker_equipments',
    'glpi_plugin_auchanassettracker_containers',
    'glpi_plugin_auchanassettracker_allocations',
    'glpi_plugin_auchanassettracker_auditlogs',
    'glpi_plugin_auchanassettracker_profiles',
    'glpi_plugin_auchanassettracker_configs',
] as $table) {
    if (!str_contains($sql, $table)) {
        fail("SQL missing $table");
    } else {
        ok("SQL has $table");
    }
}

foreach ([
    'glpi_plugin_auchanassettracker_transfers',
    'service_tickets_id',
] as $forbidden) {
    if (str_contains($sql, $forbidden)) {
        fail("SQL must not include $forbidden in Sprint 2");
    } else {
        ok("SQL clean of $forbidden");
    }
}

$setup = file_get_contents("$root/setup.php");
if (!str_contains($setup, "plugin_init_auchanassettracker")) {
    fail('setup missing init');
} else {
    ok('plugin init present');
}

if (!str_contains($setup, 'AuchanAssetTracker') && !str_contains($setup, 'Auchan Asset Tracker')) {
    fail('plugin name missing');
} else {
    ok('plugin name present');
}

if (!str_contains($setup, '0.3.1')) {
    fail('expected version 0.3.1');
} else {
    ok('version 0.3.1');
}

foreach (['transfer', 'tickethook', 'qrhelper', 'dashboard', 'report'] as $bad) {
    if (preg_match("/['\"]" . preg_quote($bad, '/') . "['\"]/", $setup)) {
        fail("setup still bootstraps $bad");
    } else {
        ok("setup omits $bad");
    }
}

if (str_contains($sql, 'auchanequipment')) {
    fail('SQL must not reference auchanequipment');
} else {
    ok('no coupling to plugin 1 tables');
}

echo $errors === 0 ? "\nAll structure checks passed.\n" : "\n$errors failure(s).\n";
exit($errors === 0 ? 0 : 1);
