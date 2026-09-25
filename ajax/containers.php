<?php

/**
 * JSON list of containers for a location (equipment form location sync).
 */
include_once dirname(__DIR__) . '/front/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

header('Content-Type: application/json; charset=UTF-8');

if (!Session::getLoginUserID()) {
    echo json_encode(['results' => []]);
    exit;
}

$locations_id = (int) ($_GET['locations_id'] ?? 0);
$results = [
    [
        'id'   => 0,
        'text' => Dropdown::EMPTY_VALUE,
    ],
];

if ($locations_id > 0 && PluginAuchanassettrackerRighthelper::canAccessLocation($locations_id)) {
    foreach (PluginAuchanassettrackerContainer::listForLocation($locations_id) as $row) {
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
}

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
