<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Html::header(
    __('Bulk add accessories', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    'assets',
    PluginAuchanassettrackerMenu::MENU_BULK
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

$type_choices = [];
foreach (PluginAuchanassettrackerEquipment::getAllowedAssetTypes() as $class) {
    $type_choices[$class] = $class::getTypeName(1);
}
$default_type = isset($type_choices['Peripheral']) ? 'Peripheral' : array_key_first($type_choices);

$header_rand = mt_rand();
$title = sprintf(
    __('%1$s - %2$s'),
    __('New item'),
    __('Bulk add accessories', 'auchanassettracker')
);
$entity_id = (int) ($_SESSION['glpiactive_entity'] ?? 0);
$entity_name = '';
if (Session::isMultiEntitiesMode()) {
    $entity_name = Dropdown::getDropdownName('glpi_entities', $entity_id);
    if ($entity_name === '' || $entity_name === '-1' || $entity_name === '&nbsp;') {
        $entity_name = '';
    }
}

// Same outer structure as native GLPI item forms (.asset → blue main-header).
echo '<div class="asset">';

echo '<div id="header_' . $header_rand . '" class="card-header main-header d-flex flex-wrap flex-md-nowrap me-2 mt-n2 align-items-stretch flex-grow-1" style="min-width: 100px;">';
echo '<h3 class="card-title d-flex align-items-center ps-0 ps-sm-4">';
echo '<div class="ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1">';
echo '<i class="ti ti-stack-2 fa-2x"></i>';
echo '</div>';
echo '<span>' . Html::entities_deep($title) . '</span>';
echo '</h3>';
if ($entity_name !== '') {
    echo '<div class="badge entity-name mx-1 px-2 ms-auto align-items-center col" title="'
        . Html::entities_deep($entity_name) . '" style="min-width: 100px; max-width: fit-content;">';
    echo '<i class="ti ti-stack me-2"></i>';
    echo '<div class="overflow-hidden text-truncate text-nowrap">';
    echo '<span class="float-end ps-1">' . Html::entities_deep($entity_name) . '</span>';
    echo '</div></div>';
}
echo '</div>';

echo "<div class='card-body'>";
echo "<form method='post' action='' class='aat-bulk-form'>";
echo "<div class='row g-3'>";

echo "<div class='col-md-6'>";
echo "<label class='form-label'>" . __('Name') . "</label>";
echo Html::input('name', [
    'value' => '',
    'class' => 'form-control',
]);
echo "</div>";

echo "<div class='col-md-6'>";
echo "<label class='form-label'>" . __('Equipment type', 'auchanassettracker') . $req . "</label>";
Dropdown::showFromArray('itemtype', $type_choices, [
    'value' => $default_type,
    'width' => '100%',
]);
echo "</div>";

echo "<div class='col-md-6'>";
echo "<label class='form-label'>" . __('Manufacturer') . $req . "</label>";
Manufacturer::dropdown([
    'name'  => 'manufacturers_id',
    'width' => '100%',
]);
echo "</div>";

echo "<div class='col-md-6'>";
echo "<label class='form-label'>" . __('Model') . $req . "</label>";
echo Html::input('model', [
    'required' => true,
    'class'    => 'form-control',
]);
echo "</div>";

echo "<div class='col-md-6'>";
echo "<label class='form-label'>" . __('Quantity', 'auchanassettracker') . $req . "</label>";
echo Html::input('quantity', [
    'type'     => 'number',
    'min'      => 1,
    'max'      => 500,
    'value'    => 1,
    'required' => true,
    'class'    => 'form-control',
]);
echo "</div>";

echo "<div class='col-md-6'>";
echo "<label class='form-label'>" . __('Location') . $req . "</label>";
if ($scope !== null) {
    echo "<div class='form-control-plaintext fw-semibold'>"
        . Dropdown::getDropdownName('glpi_locations', $scope)
        . "</div>";
    echo Html::hidden('locations_id', ['value' => $scope]);
    echo "<div class='form-text'>"
        . Html::entities_deep(__('Fixed from your profile location.', 'auchanassettracker'))
        . "</div>";
    $loc = $scope;
} else {
    Location::dropdown([
        'name'  => 'locations_id',
        'width' => '100%',
    ]);
    $loc = 0;
}
echo "</div>";

echo "<div class='col-12'>";
echo "<label class='form-label'>" . __('Physical container', 'auchanassettracker') . $req . "</label>";
$cond = ['is_active' => 1, 'is_deleted' => 0];
if ($loc > 0) {
    $cond['locations_id'] = $loc;
} else {
    $cond['locations_id'] = -1;
}
echo "<div class='aat-container-field'>";
PluginAuchanassettrackerContainer::dropdownWithActions([
    'name'          => 'plugin_auchanassettracker_containers_id',
    'condition'     => $cond,
    'width'         => '100%',
    'sync_location' => ($scope === null),
]);
echo "</div>";
echo "</div>";

echo "<div class='col-12'>";
echo "<label class='form-label'>" . __('Notes') . "</label>";
echo "<textarea name='notes' class='form-control' rows='3'></textarea>";
echo "</div>";

echo "</div>"; // row

echo "<div class='d-flex justify-content-end mt-4'>";
echo Html::submit(__('Create', 'auchanassettracker'), [
    'name'  => 'bulk_add',
    'class' => 'btn btn-primary',
]);
echo "</div>";

Html::closeForm();
echo "</div>"; // card-body
echo "</div>"; // asset

Html::footer();
