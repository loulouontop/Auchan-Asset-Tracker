<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!PluginAuchanassettrackerRighthelper::isSupportTech()
    && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
    Html::displayRightError();
    exit;
}

Html::header(
    __('Reports', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    'assets',
    'PluginAuchanassettrackerMenu'
);

$type = (string) ($_GET['type'] ?? $_POST['type'] ?? 'inventory_location');
$filters = [
    'locations_id'  => (int) ($_REQUEST['locations_id'] ?? 0),
    'users_id'      => (int) ($_REQUEST['users_id'] ?? 0),
    'containers_id' => (int) ($_REQUEST['containers_id'] ?? 0),
    'date_from'     => (string) ($_REQUEST['date_from'] ?? date('Y-m-d', strtotime('-30 days'))),
    'date_to'       => (string) ($_REQUEST['date_to'] ?? date('Y-m-d')),
];

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $report = PluginAuchanassettrackerReport::run($type, $filters);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="aat-report-' . $type . '.csv"');
    echo PluginAuchanassettrackerReport::toCsv($report);
    exit;
}

echo "<form method='get' action=''>";
echo "<table class='tab_cadre_fixe'><tr><th colspan='2'>" . __('Reports', 'auchanassettracker') . "</th></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Report type', 'auchanassettracker') . "</td><td>";
Dropdown::showFromArray('type', PluginAuchanassettrackerReport::getTypes(), ['value' => $type]);
echo "</td></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Location') . "</td><td>";
Location::dropdown(['name' => 'locations_id', 'value' => $filters['locations_id'], 'display_emptychoice' => true]);
echo "</td></tr>";
echo "<tr class='tab_bg_1'><td>" . __('User') . "</td><td>";
User::dropdown(['name' => 'users_id', 'value' => $filters['users_id'], 'right' => 'all', 'display_emptychoice' => true]);
echo "</td></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Container', 'auchanassettracker') . "</td><td>";
PluginAuchanassettrackerContainer::dropdown([
    'name' => 'containers_id',
    'value' => $filters['containers_id'],
    'display_emptychoice' => true,
]);
echo "</td></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Period') . "</td><td>";
echo Html::input('date_from', ['type' => 'date', 'value' => $filters['date_from']]);
echo " → ";
echo Html::input('date_to', ['type' => 'date', 'value' => $filters['date_to']]);
echo "</td></tr>";
echo "<tr class='tab_bg_2'><td colspan='2' class='center'>";
echo Html::submit(__('Run', 'auchanassettracker'), ['class' => 'btn btn-primary']);
echo " <a class='btn btn-secondary' href='?type=" . urlencode($type)
    . "&locations_id={$filters['locations_id']}&users_id={$filters['users_id']}"
    . "&containers_id={$filters['containers_id']}&date_from={$filters['date_from']}"
    . "&date_to={$filters['date_to']}&export=csv'>" . __('Export CSV', 'auchanassettracker') . "</a>";
echo "</td></tr></table>";
Html::closeForm();

$report = PluginAuchanassettrackerReport::run($type, $filters);
echo "<table class='tab_cadre_fixe'><tr>";
foreach ($report['headers'] as $h) {
    echo "<th>" . Html::entities_deep($h) . "</th>";
}
echo "</tr>";
foreach ($report['rows'] as $row) {
    echo "<tr class='tab_bg_1'>";
    foreach ($row as $cell) {
        echo "<td>" . Html::entities_deep((string) $cell) . "</td>";
    }
    echo "</tr>";
}
if ($report['rows'] === []) {
    echo "<tr><td colspan='" . max(1, count($report['headers'])) . "'>"
        . __('No data.', 'auchanassettracker') . "</td></tr>";
}
echo "</table>";

Html::footer();
