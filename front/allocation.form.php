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
    'PluginAuchanassettrackerMenu',
    PluginAuchanassettrackerMenu::MENU_ALLOCATION
);

$base = plugin_auchanassettracker_web_dir();
$scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();
$uid_me = (int) Session::getLoginUserID();

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

echo "<div class='asset aat-page'>";

// In-app notices for this allocator
$notices = PluginAuchanassettrackerNotice::getUnreadForUser($uid_me);
if ($notices !== []) {
    echo "<div class='alert alert-info aat-alert-block'>";
    echo "<div class='aat-alert-title'>" . __('Your notices', 'auchanassettracker') . "</div><ul class='mb-0'>";
    foreach ($notices as $n) {
        $msg = Html::entities_deep((string) ($n['message'] ?? ''));
        $link = trim((string) ($n['link'] ?? ''));
        echo "<li>" . ($link !== '' ? "<a href='" . Html::entities_deep($link) . "'>$msg</a>" : $msg) . "</li>";
    }
    echo "</ul></div>";
    PluginAuchanassettrackerNotice::markReadForUser($uid_me);
}

PluginAuchanassettrackerAllocation::displayActiveAlerts($scope);

echo "<div class='card card-sm mb-3'>";
echo "<div class='card-header main-header'>" . __('New allocation', 'auchanassettracker') . "</div>";
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

/**
 * @param string $text
 * @param string $href
 */
$aat_link = static function (string $text, string $href): string {
    $safe = Html::entities_deep($text);
    if ($href === '') {
        return $safe;
    }
    return "<a href='" . Html::entities_deep($href) . "'>$safe</a>";
};

if ($preview_user > 0) {
    $current = PluginAuchanassettrackerAllocation::getCurrentGearForUser($preview_user);
    echo "<h3 class='fs-5 mt-3'>" . __('Equipment already with this user', 'auchanassettracker') . "</h3>";
    if ($current === []) {
        echo "<p class='text-muted'>" . __('None.', 'auchanassettracker') . "</p>";
    } else {
        echo "<div class='table-responsive'><table class='table table-sm table-hover table-striped'>";
        echo "<thead><tr><th>" . __('Name') . "</th><th>" . __('Type') . "</th><th>"
            . __('Serial number') . "</th><th>" . __('Status') . "</th><th>"
            . __('Source', 'auchanassettracker') . "</th></tr></thead><tbody>";
        foreach ($current as $e) {
            $src = (string) ($e['source'] ?? 'plugin');
            $type = (string) ($e['itemtype'] ?? '');
            $type_label = ($type !== '' && class_exists($type)) ? $type::getTypeName(1) : $type;
            $status = (string) ($e['status'] ?? '');
            $status_label = $status === 'glpi'
                ? __('GLPI asset', 'auchanassettracker')
                : PluginAuchanassettrackerEquipment::getStatusLabel($status);
            $href = '';
            if ($src === 'plugin' && (int) ($e['id'] ?? 0) > 0) {
                $href = $base . '/front/equipment.form.php?id=' . (int) $e['id'];
            } elseif ($src === 'glpi' && $type !== '' && (int) ($e['items_id'] ?? 0) > 0) {
                $href = $type::getFormURLWithID((int) $e['items_id']);
            }
            echo "<tr>";
            echo "<td>" . $aat_link((string) ($e['name'] ?? ''), $href) . "</td>";
            echo "<td>" . $aat_link($type_label, $href) . "</td>";
            echo "<td>" . $aat_link((string) ($e['serial'] ?? ''), $href) . "</td>";
            echo "<td>" . $aat_link($status_label, $href) . "</td>";
            echo "<td>" . $aat_link(
                $src === 'glpi' ? __('GLPI', 'auchanassettracker') : __('Plugin', 'auchanassettracker'),
                $href
            ) . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table></div>";
    }

    $available = PluginAuchanassettrackerEquipment::findByStatus(
        PluginAuchanassettrackerEquipment::STATUS_AVAILABLE,
        $scope
    );

    echo "<form method='post' action='' class='mt-3'>";
    echo Html::hidden('users_id', ['value' => $preview_user]);
    echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
    echo "<div class='table-responsive'><table class='table table-sm table-hover' id='aat-alloc-stock'>";
    echo "<thead><tr><th><input type='checkbox' class='form-check-input' id='aat-check-all' title='"
        . __('Select all') . "'></th><th>" . __('Name') . "</th><th>" . __('Type') . "</th><th>"
        . __('Serial number') . "</th><th>" . __('Container', 'auchanassettracker') . "</th></tr></thead><tbody>";
    $shown = 0;
    foreach ($available as $e) {
        if ((int) ($e['plugin_auchanassettracker_containers_id'] ?? 0) <= 0) {
            continue;
        }
        $shown++;
        $type = (string) ($e['itemtype'] ?? '');
        $type_label = ($type !== '' && class_exists($type)) ? $type::getTypeName(1) : $type;
        $href = $base . '/front/equipment.form.php?id=' . (int) $e['id'];
        $cname = Dropdown::getDropdownName(
            PluginAuchanassettrackerContainer::getTable(),
            (int) $e['plugin_auchanassettracker_containers_id']
        );
        echo "<tr><td>"
            . "<input type='checkbox' class='form-check-input aat-eq-check' name='equipment_ids[]' value='"
            . (int) $e['id'] . "'></td><td>" . $aat_link((string) ($e['name'] ?? ''), $href)
            . "</td><td>" . $aat_link($type_label, $href)
            . "</td><td>" . $aat_link((string) ($e['serial'] ?? ''), $href)
            . "</td><td>" . $aat_link((string) $cname, $href) . "</td></tr>";
    }
    if ($shown === 0) {
        echo "<tr><td colspan='5' class='text-muted'>"
            . __('No available stock with a container at this location.', 'auchanassettracker')
            . "</td></tr>";
    }
    echo "</tbody></table></div>";
    echo Html::scriptBlock(<<<'JS'
$(function () {
  $('#aat-check-all').on('change', function () {
    $('.aat-eq-check').prop('checked', this.checked);
  });
});
JS);
    echo "<div class='mt-3'>";
    echo Html::submit(__('Allocate', 'auchanassettracker'), ['name' => 'allocate', 'class' => 'btn btn-primary']);
    echo "</div>";
    Html::closeForm();
}

echo "</div></div>";

// Allocation history
$history = PluginAuchanassettrackerAllocation::getHistory($scope, 40);
echo "<div class='card card-sm aat-history-card'>";
echo "<div class='card-header main-header'>" . __('Allocation history', 'auchanassettracker') . "</div>";
echo "<div class='card-body table-responsive'>";
if ($history === []) {
    echo "<p class='text-muted mb-0'>" . __('None.', 'auchanassettracker') . "</p>";
} else {
    echo "<table class='table table-sm table-striped mb-0'><thead><tr>";
    echo "<th>" . __('Equipment') . "</th><th>" . __('Type') . "</th><th>" . __('Serial number') . "</th>";
    echo "<th>" . __('Recipient user', 'auchanassettracker') . "</th>";
    echo "<th>" . __('Technician', 'auchanassettracker') . "</th>";
    echo "<th>" . __('Allocated on', 'auchanassettracker') . "</th>";
    echo "<th>" . __('Status') . "</th><th></th></tr></thead><tbody>";
    foreach ($history as $row) {
        $eid = (int) ($row['equipments_id'] ?? $row['plugin_auchanassettracker_equipments_id'] ?? 0);
        $aid = (int) ($row['id'] ?? 0);
        $href = $eid > 0 ? $base . '/front/equipment.form.php?id=' . $eid : '';
        $type = (string) ($row['itemtype'] ?? '');
        $type_label = ($type !== '' && class_exists($type)) ? $type::getTypeName(1) : $type;
        $st = (string) ($row['allocation_status'] ?? '');
        $confirm_href = $base . '/front/confirm.php?allocation_id=' . $aid;
        echo "<tr><td>" . $aat_link((string) ($row['equipment_name'] ?? ''), $href)
            . "</td><td>" . $aat_link($type_label, $href)
            . "</td><td>" . $aat_link((string) ($row['serial'] ?? ''), $href)
            . "</td><td>" . ($href !== ''
                ? "<a href='" . Html::entities_deep($href) . "'>"
                    . getUserName((int) ($row['users_id_recipient'] ?? 0)) . "</a>"
                : getUserName((int) ($row['users_id_recipient'] ?? 0)))
            . "</td><td>" . ($href !== ''
                ? "<a href='" . Html::entities_deep($href) . "'>"
                    . getUserName((int) ($row['users_id_allocator'] ?? 0)) . "</a>"
                : getUserName((int) ($row['users_id_allocator'] ?? 0)))
            . "</td><td>" . $aat_link((string) ($row['allocation_date'] ?? ''), $href)
            . "</td><td>" . $aat_link(
                PluginAuchanassettrackerAllocation::getStatusLabel($st),
                $href
            ) . "</td><td>";
        if ($st === PluginAuchanassettrackerAllocation::STATUS_PENDING && $aid > 0) {
            echo "<a class='btn btn-sm btn-outline-primary' href='"
                . Html::entities_deep($confirm_href) . "'>"
                . __('Open confirmation', 'auchanassettracker') . "</a>";
        }
        echo "</td></tr>";
    }
    echo "</tbody></table>";
}
echo "</div></div>";

echo "</div>";
Html::footer();
