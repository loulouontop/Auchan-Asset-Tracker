<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Html::header(
    __('Bulk add accessories', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    'assets',
    'PluginAuchanassettrackerMenu',
    'bulk'
);

if (!PluginAuchanassettrackerRighthelper::canManageStock()
    && !PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
    Html::displayRightError();
    exit;
}

$base = plugin_auchanassettracker_web_dir();
$scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
$req = " <span class='aat-required'>*</span>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_add'])) {
    $qty = (int) ($_POST['quantity'] ?? 0);
    $input = [
        'itemtype'                                => (string) ($_POST['itemtype'] ?? 'Peripheral'),
        'manufacturers_id'                        => (int) ($_POST['manufacturers_id'] ?? 0),
        'plugin_auchanassettracker_containers_id' => (int) ($_POST['plugin_auchanassettracker_containers_id'] ?? 0),
        'name'                                    => (string) ($_POST['name'] ?? ''),
        'model'                                   => (string) ($_POST['model'] ?? ''),
        'notes'                                   => (string) ($_POST['notes'] ?? ''),
        'locations_id'                            => $scope ?? (int) ($_POST['locations_id'] ?? 0),
    ];
    $created = PluginAuchanassettrackerEquipment::bulkAddAccessories($input, $qty);
    Session::addMessageAfterRedirect(
        sprintf(__('%d accessory record(s) created.', 'auchanassettracker'), $created),
        true,
        INFO
    );
    Html::redirect($base . '/front/equipment.php');
    exit;
}

echo "<form method='post' action=''>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr><th colspan='2'>" . __('Bulk add accessories', 'auchanassettracker') . "</th></tr>";

$type_choices = [];
foreach (PluginAuchanassettrackerEquipment::getAllowedAssetTypes() as $class) {
    $type_choices[$class] = $class::getTypeName(1);
}
$default_type = isset($type_choices['Peripheral']) ? 'Peripheral' : array_key_first($type_choices);

echo "<tr class='tab_bg_1'><td>" . __('Name') . "</td><td>";
echo Html::input('name', [
    'value' => '',
    'class' => 'form-control aat-input-sm',
]);
echo "</td></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Equipment type', 'auchanassettracker') . $req . "</td><td>";
Dropdown::showFromArray('itemtype', $type_choices, [
    'value' => $default_type,
    'width' => '220px',
]);
echo "</td></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Manufacturer') . $req . "</td><td>";
Manufacturer::dropdown([
    'name'  => 'manufacturers_id',
    'width' => '220px',
]);
echo "</td></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Model') . $req . "</td><td>";
echo Html::input('model', [
    'required' => true,
    'class'    => 'form-control aat-input-sm',
]);
echo "</td></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Quantity', 'auchanassettracker') . $req . "</td><td>";
echo Html::input('quantity', [
    'type'     => 'number',
    'min'      => 1,
    'max'      => 500,
    'value'    => 1,
    'required' => true,
    'class'    => 'form-control aat-input-sm',
]);
echo "</td></tr>";

echo "<tr class='tab_bg_1'><td>" . __('Location') . $req . "</td><td>";
if ($scope !== null) {
    echo Dropdown::getDropdownName('glpi_locations', $scope);
    echo Html::hidden('locations_id', ['value' => $scope]);
    echo "<div class='form-text'>"
        . Html::entities_deep(__('Fixed from your profile location.', 'auchanassettracker'))
        . "</div>";
    $loc = $scope;
} else {
    Location::dropdown([
        'name'  => 'locations_id',
        'width' => '220px',
    ]);
    $loc = 0;
}
echo "</td></tr>";

echo "<tr class='tab_bg_1'><td>" . __('Physical container', 'auchanassettracker') . $req . "</td><td>";
$cond = ['is_active' => 1, 'is_deleted' => 0];
if ($loc > 0) {
    $cond['locations_id'] = $loc;
} elseif ($scope !== null) {
    $cond['locations_id'] = -1;
}
// Central admin + no location: all containers.
echo "<span class='aat-container-field'>";
PluginAuchanassettrackerContainer::dropdownWithActions([
    'name'          => 'plugin_auchanassettracker_containers_id',
    'condition'     => $cond,
    'width'         => '280px',
    'sync_location' => ($scope === null),
]);
echo "</span>";
echo "</td></tr>";

echo "<tr class='tab_bg_1'><td>" . __('Notes') . "</td><td>";
echo "<textarea name='notes' class='form-control' rows='2'></textarea></td></tr>";

echo "<tr class='tab_bg_2'><td colspan='2' class='center'>";
echo Html::submit(__('Create', 'auchanassettracker'), ['name' => 'bulk_add', 'class' => 'btn btn-primary']);
echo " <a class='btn btn-secondary' href='" . $base . "/front/equipment.form.php'>" . __('Single item', 'auchanassettracker') . "</a>";
echo "</td></tr></table>";
Html::closeForm();

Html::footer();
