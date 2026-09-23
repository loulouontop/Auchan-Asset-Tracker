<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Html::header(
    __('Auchan Asset Tracker', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    'assets',
    'PluginAuchanassettrackerMenu'
);

$role = PluginAuchanassettrackerRighthelper::getCurrentRole();
$base = Plugin::getWebDir(plugin_auchanassettracker_dir());

echo "<div class='assettracker-dashboard'>";
echo "<h1>" . __('Auchan Asset Tracker', 'auchanassettracker') . "</h1>";
echo "<p class='text-muted'>" . sprintf(
    __('Signed in as: %s', 'auchanassettracker'),
    PluginAuchanassettrackerRighthelper::getRoles()[$role] ?? $role
) . "</p>";

if ($role === PluginAuchanassettrackerRighthelper::ROLE_CENTRAL_ADMIN) {
    $data = PluginAuchanassettrackerDashboard::getAdminData();
    $ind = $data['indicators'];

    echo "<div class='aat-indicators'>";
    $cards = [
        'total' => [__('Total equipment', 'auchanassettracker'), $ind['total'], ''],
        'available' => [__('Available', 'auchanassettracker'), $ind['available'], 'status=' . PluginAuchanassettrackerEquipment::STATUS_AVAILABLE],
        'allocated' => [__('Allocated', 'auchanassettracker'), $ind['allocated'], 'status=' . PluginAuchanassettrackerEquipment::STATUS_ALLOCATED],
        'in_service' => [__('In service', 'auchanassettracker'), $ind['in_service'], 'status=' . PluginAuchanassettrackerEquipment::STATUS_IN_SERVICE],
        'in_transit' => [__('In transit', 'auchanassettracker'), $ind['in_transit'], 'status=' . PluginAuchanassettrackerEquipment::STATUS_IN_TRANSIT],
        'written_off_30' => [__('Written off (30 days)', 'auchanassettracker'), $ind['written_off_30'], 'status=' . PluginAuchanassettrackerEquipment::STATUS_WRITTEN_OFF],
    ];
    foreach ($cards as $key => [$label, $val, $qs]) {
        $href = $base . '/front/equipment.php' . ($qs !== '' ? '?' . $qs : '');
        echo "<a class='aat-card' href='" . Html::entities_deep($href) . "'><div class='aat-card-value'>"
            . (int) $val . "</div><div class='aat-card-label'>" . Html::entities_deep($label) . "</div></a>";
    }
    echo "</div>";

    echo "<h2>" . __('Active alerts', 'auchanassettracker') . "</h2>";
    echo "<div class='aat-alerts'>";
    foreach ($data['alerts']['service'] as $r) {
        echo "<div class='aat-alert'>" . sprintf(
            __('In service overtime: %s (%s) — %d days', 'auchanassettracker'),
            Html::entities_deep($r['name'] ?? ''),
            Html::entities_deep(Dropdown::getDropdownName('glpi_locations', (int) $r['locations_id'])),
            PluginAuchanassettrackerDashboard::daysSince($r['service_since'] ?? null)
        ) . "</div>";
    }
    foreach ($data['alerts']['allocations'] as $r) {
        echo "<div class='aat-alert'>" . sprintf(
            __('Unconfirmed allocation: %s — %d days', 'auchanassettracker'),
            Html::entities_deep($r['equipment_name'] ?? ''),
            PluginAuchanassettrackerDashboard::daysSince($r['allocation_date'] ?? null)
        ) . "</div>";
    }
    foreach ($data['alerts']['transfers'] as $r) {
        echo "<div class='aat-alert'>" . sprintf(
            __('Unvalidated transfer #%d to %s — %d days', 'auchanassettracker'),
            (int) $r['id'],
            Html::entities_deep(Dropdown::getDropdownName('glpi_locations', (int) $r['locations_id_dest'])),
            PluginAuchanassettrackerDashboard::daysSince($r['date_initiated'] ?? null)
        ) . "</div>";
    }
    if ($data['alerts']['service'] === [] && $data['alerts']['allocations'] === [] && $data['alerts']['transfers'] === []) {
        echo "<p>" . __('No active alerts.', 'auchanassettracker') . "</p>";
    }
    echo "</div>";

    echo "<h2>" . __('Stock by location', 'auchanassettracker') . "</h2>";
    echo "<table class='tab_cadre_fix'><tr><th>" . __('Location') . "</th><th>"
        . __('Available', 'auchanassettracker') . "</th><th>"
        . __('Allocated', 'auchanassettracker') . "</th><th>"
        . __('In service', 'auchanassettracker') . "</th><th>"
        . __('In transit', 'auchanassettracker') . "</th></tr>";
    foreach ($data['stock_by_location'] as $row) {
        echo "<tr class='tab_bg_1'><td>"
            . Html::entities_deep(Dropdown::getDropdownName('glpi_locations', (int) $row['locations_id']))
            . "</td><td>" . (int) $row['available']
            . "</td><td>" . (int) $row['allocated']
            . "</td><td>" . (int) $row['in_service']
            . "</td><td>" . (int) $row['in_transit'] . "</td></tr>";
    }
    echo "</table>";

    echo "<h2>" . __('Recent activity', 'auchanassettracker') . "</h2>";
    echo "<table class='tab_cadre_fix'><tr><th>" . __('Date') . "</th><th>"
        . __('User') . "</th><th>" . __('Action') . "</th><th>" . __('Details') . "</th></tr>";
    foreach ($data['recent'] as $r) {
        echo "<tr class='tab_bg_1'><td>" . Html::entities_deep($r['date_creation'] ?? '')
            . "</td><td>" . Html::entities_deep(getUserName((int) ($r['users_id'] ?? 0)))
            . "</td><td>" . Html::entities_deep($r['action'] ?? '')
            . "</td><td>" . Html::entities_deep($r['details'] ?? '') . "</td></tr>";
    }
    echo "</table>";

} elseif (in_array($role, [
    PluginAuchanassettrackerRighthelper::ROLE_LOCATION_MANAGER,
    PluginAuchanassettrackerRighthelper::ROLE_SUPPORT_TECH,
], true)) {
    $loc = PluginAuchanassettrackerRighthelper::getScopedLocationId() ?? 0;
    $data = PluginAuchanassettrackerDashboard::getManagerData((int) $loc);

    echo "<div class='aat-manager-grid'>";
    echo "<div class='aat-col'><h2>" . __('Technical room stock', 'auchanassettracker') . "</h2>";
    echo "<table class='tab_cadre_fix'><tr><th>" . __('Container', 'auchanassettracker')
        . "</th><th>" . __('Available', 'auchanassettracker') . "</th></tr>";
    foreach ($data['containers'] as $c) {
        echo "<tr class='tab_bg_1'><td><a href='" . $base . "/front/container.form.php?id=" . (int) $c['id'] . "'>"
            . Html::entities_deep(($c['code'] ?? '') . ' — ' . ($c['name'] ?? ''))
            . "</a></td><td>" . (int) ($c['available_count'] ?? 0) . "</td></tr>";
    }
    if ($data['containers'] === []) {
        echo "<tr><td colspan='2'>" . __('No containers yet. Create one before receiving equipment.', 'auchanassettracker') . "</td></tr>";
    }
    echo "</table></div>";

    echo "<div class='aat-col'><h2>" . __('To resolve today', 'auchanassettracker') . "</h2>";
    foreach ($data['to_resolve']['transfers'] as $t) {
        echo "<div class='aat-alert'><a href='" . $base . "/front/transfer.form.php?id=" . (int) $t['id'] . "'>"
            . sprintf(__('Transfer #%d awaiting validation', 'auchanassettracker'), (int) $t['id'])
            . "</a></div>";
    }
    foreach ($data['to_resolve']['allocations'] as $a) {
        echo "<div class='aat-alert'>" . sprintf(
            __('Unconfirmed: %s (%d days)', 'auchanassettracker'),
            Html::entities_deep($a['equipment_name'] ?? ''),
            PluginAuchanassettrackerDashboard::daysSince($a['allocation_date'] ?? null)
        ) . "</div>";
    }
    foreach ($data['to_resolve']['needs_container'] as $e) {
        echo "<div class='aat-alert'><a href='" . $base . "/front/equipment.form.php?id=" . (int) $e['id'] . "'>"
            . sprintf(__('Needs container: %s', 'auchanassettracker'), Html::entities_deep($e['name'] ?? ''))
            . "</a></div>";
    }
    echo "<h2>" . __('In movement', 'auchanassettracker') . "</h2>";
    foreach ($data['in_movement']['in_service'] as $e) {
        echo "<div>" . Html::entities_deep($e['name'] ?? '') . " — "
            . __('In service', 'auchanassettracker') . " ("
            . PluginAuchanassettrackerDashboard::daysSince($e['service_since'] ?? null) . "d)</div>";
    }
    foreach ($data['in_movement']['in_transit_out'] as $e) {
        echo "<div>" . Html::entities_deep($e['name'] ?? '') . " — "
            . __('In transit', 'auchanassettracker') . "</div>";
    }
    echo "</div></div>";

} else {
    $data = PluginAuchanassettrackerDashboard::getUserData((int) Session::getLoginUserID());

    if ($data['pending'] !== []) {
        echo "<div class='aat-pending'><h2>" . __('Pending actions', 'auchanassettracker') . "</h2>";
        echo "<p>" . sprintf(
            __('You have %d equipment item(s) awaiting confirmation.', 'auchanassettracker'),
            count($data['pending'])
        ) . " <a class='btn btn-primary' href='" . $base . "/front/confirm.php'>"
            . __('Confirm receipt', 'auchanassettracker') . "</a></p></div>";
    }

    echo "<h2>" . __('My equipment', 'auchanassettracker') . "</h2>";
    echo "<table class='tab_cadre_fix'><tr><th>" . __('Type') . "</th><th>"
        . __('Model') . "</th><th>" . __('Serial number') . "</th><th>"
        . __('Status') . "</th></tr>";
    foreach ($data['my_equipment'] as $e) {
        if (($e['status'] ?? '') === PluginAuchanassettrackerEquipment::STATUS_AWAITING_VALIDATION) {
            continue;
        }
        echo "<tr class='tab_bg_1'><td>"
            . Html::entities_deep(Dropdown::getDropdownName(
                PluginAuchanassettrackerEquipmenttype::getTable(),
                (int) $e['plugin_auchanassettracker_equipmenttypes_id']
            ))
            . "</td><td>" . Html::entities_deep($e['model'] ?? '')
            . "</td><td>" . Html::entities_deep($e['serial'] ?? '')
            . "</td><td>" . Html::entities_deep(PluginAuchanassettrackerEquipment::getStatusLabel((string) $e['status']))
            . "</td></tr>";
    }
    echo "</table>";
}

echo "</div>";
Html::footer();
