<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!PluginAuchanassettrackerRighthelper::canAllocate()) {
    Html::displayRightError();
    exit;
}

Html::header(
    __('New allocation', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    'assets',
    PluginAuchanassettrackerMenu::MENU_ALLOCATION
);

$base = plugin_auchanassettracker_web_dir();
$scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['allocate'])) {
    $uid = (int) ($_POST['users_id'] ?? 0);
    $eids = array_map('intval', (array) ($_POST['equipment_ids'] ?? []));
    $ok = 0;
    foreach ($eids as $eid) {
        if ($eid > 0 && PluginAuchanassettrackerAllocation::initiate($eid, $uid)) {
            $ok++;
        }
    }
    Session::addMessageAfterRedirect(
        sprintf(__('%d allocation(s) created.', 'auchanassettracker'), $ok),
        true,
        INFO
    );
    Html::redirect($base . '/front/allocation.form.php' . ($uid > 0 ? '?users_id=' . $uid : ''));
    exit;
}

$preview_user = (int) ($_GET['users_id'] ?? $_POST['users_id'] ?? 0);

echo "<div class='aat-form-page'>";

// Overdue allocation alerts (managers / allocators)
$overdue = PluginAuchanassettrackerAllocation::getOverduePending($scope);
$needs_container = PluginAuchanassettrackerEquipment::findNeedsContainer($scope);
if ($overdue !== [] || $needs_container !== []) {
    echo "<div class='alert alert-warning'>";
    echo "<strong>" . __('Active alerts', 'auchanassettracker') . "</strong>";
    if ($overdue !== []) {
        echo "<ul class='mb-1 mt-2'>";
        foreach ($overdue as $row) {
            echo "<li>" . Html::entities_deep(sprintf(
                __('No confirmation after threshold: %s (%s)', 'auchanassettracker'),
                (string) ($row['equipment_name'] ?? ''),
                (string) ($row['serial'] ?? '')
            )) . "</li>";
        }
        echo "</ul>";
    }
    if ($needs_container !== []) {
        echo "<ul class='mb-0 mt-2'>";
        foreach ($needs_container as $eq) {
            echo "<li><a href='" . $base . "/front/equipment.form.php?id=" . (int) $eq['id'] . "'>"
                . Html::entities_deep(sprintf(
                    __('Needs container: %s (%s)', 'auchanassettracker'),
                    (string) ($eq['name'] ?? ''),
                    (string) ($eq['serial'] ?? '')
                ))
                . "</a></li>";
        }
        echo "</ul>";
    }
    echo "</div>";
}

echo "<div class='card'>";
echo "<div class='card-header'>" . __('New allocation', 'auchanassettracker') . "</div>";
echo "<div class='card-body'>";

echo "<form method='get' action='' class='mb-3'>";
echo "<div class='row g-2 align-items-end'>";
echo "<div class='col-md-6'><label class='form-label'>"
    . __('Recipient user', 'auchanassettracker') . "</label>";
User::dropdown(['name' => 'users_id', 'value' => $preview_user, 'right' => 'all']);
echo "</div><div class='col-md-auto'>";
echo Html::submit(__('Show current gear', 'auchanassettracker'), ['class' => 'btn btn-secondary']);
echo "</div></div>";
Html::closeForm();

if ($preview_user > 0) {
    $current = PluginAuchanassettrackerAllocation::getCurrentGearForUser($preview_user);
    echo "<h3 class='h5 mt-3'>" . __('Equipment already with this user', 'auchanassettracker') . "</h3>";
    if ($current === []) {
        echo "<p class='text-muted'>" . __('None.', 'auchanassettracker') . "</p>";
    } else {
        echo "<ul>";
        foreach ($current as $e) {
            echo "<li>" . Html::entities_deep(($e['name'] ?? '') . ' [' . ($e['serial'] ?? '') . '] — '
                . PluginAuchanassettrackerEquipment::getStatusLabel((string) $e['status'])) . "</li>";
        }
        echo "</ul>";
    }

    $available = PluginAuchanassettrackerEquipment::findByStatus(
        PluginAuchanassettrackerEquipment::STATUS_AVAILABLE,
        $scope
    );

    echo "<form method='post' action='' class='mt-3'>";
    echo Html::hidden('users_id', ['value' => $preview_user]);
    echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
    echo "<div class='table-responsive'><table class='table table-sm table-hover'>";
    echo "<thead><tr><th></th><th>" . __('Name') . "</th><th>" . __('Serial number') . "</th><th>"
        . __('Container', 'auchanassettracker') . "</th></tr></thead><tbody>";
    $shown = 0;
    foreach ($available as $e) {
        if ((int) ($e['plugin_auchanassettracker_containers_id'] ?? 0) <= 0) {
            continue;
        }
        $shown++;
        echo "<tr><td>"
            . "<input type='checkbox' class='form-check-input' name='equipment_ids[]' value='"
            . (int) $e['id'] . "'></td><td>"
            . Html::entities_deep($e['name'] ?? '') . "</td><td>"
            . Html::entities_deep($e['serial'] ?? '') . "</td><td>"
            . Html::entities_deep(Dropdown::getDropdownName(
                PluginAuchanassettrackerContainer::getTable(),
                (int) $e['plugin_auchanassettracker_containers_id']
            )) . "</td></tr>";
    }
    if ($shown === 0) {
        echo "<tr><td colspan='4' class='text-muted'>"
            . __('No available stock with a container at this location.', 'auchanassettracker')
            . "</td></tr>";
    }
    echo "</tbody></table></div>";
    echo "<div class='mt-3'>";
    echo Html::submit(__('Allocate', 'auchanassettracker'), ['name' => 'allocate', 'class' => 'btn btn-primary']);
    echo "</div>";
    Html::closeForm();
}

echo "</div></div>";

// Allocation history
$history = PluginAuchanassettrackerAllocation::getHistory($scope, 40);
echo "<div class='card mt-4'>";
echo "<div class='card-header'>" . __('Allocation history', 'auchanassettracker') . "</div>";
echo "<div class='card-body table-responsive'>";
if ($history === []) {
    echo "<p class='text-muted mb-0'>" . __('None.', 'auchanassettracker') . "</p>";
} else {
    echo "<table class='table table-sm table-striped mb-0'><thead><tr>";
    echo "<th>" . __('Equipment') . "</th><th>" . __('Serial number') . "</th>";
    echo "<th>" . __('Recipient user', 'auchanassettracker') . "</th>";
    echo "<th>" . __('Allocated on', 'auchanassettracker') . "</th>";
    echo "<th>" . __('Status') . "</th></tr></thead><tbody>";
    foreach ($history as $row) {
        echo "<tr><td>" . Html::entities_deep((string) ($row['equipment_name'] ?? ''))
            . "</td><td>" . Html::entities_deep((string) ($row['serial'] ?? ''))
            . "</td><td>" . getUserName((int) ($row['users_id_recipient'] ?? 0))
            . "</td><td>" . Html::entities_deep((string) ($row['allocation_date'] ?? ''))
            . "</td><td>" . Html::entities_deep(
                PluginAuchanassettrackerAllocation::getStatusLabel((string) ($row['allocation_status'] ?? ''))
            ) . "</td></tr>";
    }
    echo "</tbody></table>";
}
echo "</div></div>";

echo "</div>";
Html::footer();
