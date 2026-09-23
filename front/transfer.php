<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!PluginAuchanassettrackerRighthelper::canTransfer()
    && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
    Html::displayRightError();
    exit;
}

Html::header(
    __('Transfers', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    'assets',
    'PluginAuchanassettrackerMenu'
);

$base = Plugin::getWebDir('auchanassettracker');
$scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();

echo "<p><a class='btn btn-primary' href='" . $base . "/front/transfer.form.php'>"
    . __('New transfer', 'auchanassettracker') . "</a></p>";

if (PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
    $list = PluginAuchanassettrackerTransfer::getAll();
} else {
    $list = array_merge(
        PluginAuchanassettrackerTransfer::getPendingForLocation((int) $scope),
        // also show initiated from this location
        array_filter(
            PluginAuchanassettrackerTransfer::getAll(PluginAuchanassettrackerTransfer::STATUS_IN_TRANSIT),
            static fn($t) => (int) $t['locations_id_source'] === (int) $scope
                || (int) $t['locations_id_dest'] === (int) $scope
        )
    );
    // unique by id
    $uniq = [];
    foreach ($list as $t) {
        $uniq[(int) $t['id']] = $t;
    }
    $list = array_values($uniq);
}

echo "<table class='tab_cadre_fixe'><tr><th>" . __('ID') . "</th><th>"
    . __('Source') . "</th><th>" . __('Destination') . "</th><th>"
    . __('Status') . "</th><th>" . __('Date') . "</th><th></th></tr>";

foreach ($list as $t) {
    echo "<tr class='tab_bg_1'><td>" . (int) $t['id']
        . "</td><td>" . Html::entities_deep(Dropdown::getDropdownName('glpi_locations', (int) $t['locations_id_source']))
        . "</td><td>" . Html::entities_deep(Dropdown::getDropdownName('glpi_locations', (int) $t['locations_id_dest']))
        . "</td><td>" . Html::entities_deep($t['transfer_status'] ?? '')
        . "</td><td>" . Html::entities_deep($t['date_initiated'] ?? '')
        . "</td><td><a href='" . $base . "/front/transfer.form.php?id=" . (int) $t['id'] . "'>"
        . __('Open') . "</a></td></tr>";
}
echo "</table>";

Html::footer();
