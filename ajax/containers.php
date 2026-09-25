<?php

/**
 * Containers for a location — JSON list or full dropdown HTML for GLPI Select2 refresh.
 */
include_once dirname(__DIR__) . '/front/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!Session::getLoginUserID()) {
    http_response_code(403);
    exit;
}

$locations_id = (int) ($_GET['locations_id'] ?? 0);
$display = (string) ($_GET['display'] ?? 'json');
$value = (int) ($_GET['value'] ?? 0);

// Central admin (scope null) may list all locations; others only their location.
$scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
if ($scope !== null) {
    $locations_id = $scope;
}

$condition = [
    'is_deleted' => 0,
    'is_active'  => 1,
];
if ($locations_id > 0) {
    if (!PluginAuchanassettrackerRighthelper::canAccessLocation($locations_id)) {
        $condition['locations_id'] = -1;
        $value = 0;
    } else {
        $condition['locations_id'] = $locations_id;
        if ($value > 0) {
            $tmp = new PluginAuchanassettrackerContainer();
            if (!$tmp->getFromDB($value)
                || (int) ($tmp->fields['locations_id'] ?? 0) !== $locations_id) {
                $value = 0;
            }
        }
    }
}
// locations_id == 0 and central admin → no location filter (all containers)

if ($display === 'dropdown') {
    header('Content-Type: text/html; charset=UTF-8');
    PluginAuchanassettrackerContainer::dropdownWithActions([
        'name'          => 'plugin_auchanassettracker_containers_id',
        'value'         => $value,
        'condition'     => $condition,
        'width'         => '280px',
        'sync_location' => false, // parent page already bound the listener
    ]);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

$results = [
    [
        'id'   => 0,
        'text' => Dropdown::EMPTY_VALUE,
    ],
];

$rows = [];
if ($locations_id > 0) {
    $rows = PluginAuchanassettrackerContainer::listForLocation($locations_id);
} elseif ($scope === null) {
    // Central admin, all locations
    global $DB;
    foreach ($DB->request([
        'FROM'  => PluginAuchanassettrackerContainer::getTable(),
        'WHERE' => [
            'is_deleted' => 0,
            'is_active'  => 1,
        ],
        'ORDER' => 'name ASC',
    ]) as $row) {
        $rows[] = $row;
    }
}

foreach ($rows as $row) {
    if (!(int) ($row['is_active'] ?? 1) || (int) ($row['is_deleted'] ?? 0) === 1) {
        continue;
    }
    $label = (string) ($row['name'] ?? '');
    $code = trim((string) ($row['code'] ?? ''));
    if ($code !== '') {
        $label .= ' (' . $code . ')';
    }
    $results[] = [
        'id'   => (int) $row['id'],
        'text' => $label,
    ];
}

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
