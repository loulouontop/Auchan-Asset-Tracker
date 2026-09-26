<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Html::header(
    __('Confirm receipt', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    'assets',
    'PluginAuchanassettrackerMenu',
    PluginAuchanassettrackerMenu::MENU_CONFIRM
);

$uid = (int) Session::getLoginUserID();
$base = plugin_auchanassettracker_web_dir();
$focus_id = (int) ($_GET['allocation_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aid = (int) ($_POST['allocation_id'] ?? 0);
    if (isset($_POST['confirm'])) {
        PluginAuchanassettrackerAllocation::confirm($aid);
        Session::addMessageAfterRedirect(__('Receipt confirmed.', 'auchanassettracker'), true, INFO);
    } elseif (isset($_POST['reject'])) {
        PluginAuchanassettrackerAllocation::reject($aid);
        Session::addMessageAfterRedirect(
            __('Marked as not received. Item returned to its previous container when possible.', 'auchanassettracker'),
            true,
            WARNING
        );
    }
    Html::redirect($base . '/front/confirm.php');
    exit;
}

$pending = PluginAuchanassettrackerAllocation::getPendingForUser($uid);

// Central admin (or allocator opening a deep link) can focus a specific pending allocation.
if ($focus_id > 0) {
    $focus = new PluginAuchanassettrackerAllocation();
    if ($focus->getFromDB($focus_id)
        && ($focus->fields['allocation_status'] ?? '') === PluginAuchanassettrackerAllocation::STATUS_PENDING
    ) {
        $is_recipient = (int) ($focus->fields['users_id_recipient'] ?? 0) === $uid;
        $can_see = $is_recipient || PluginAuchanassettrackerRighthelper::isCentralAdmin()
            || PluginAuchanassettrackerRighthelper::canAllocate();
        if ($can_see) {
            $already = false;
            foreach ($pending as $p) {
                if ((int) ($p['id'] ?? 0) === $focus_id) {
                    $already = true;
                    break;
                }
            }
            if (!$already) {
                array_unshift($pending, $focus->fields);
            }
        }
    }
}

$mine = PluginAuchanassettrackerAllocation::getCurrentGearForUser($uid);

$aat_link = static function (string $text, string $href): string {
    $safe = Html::entities_deep($text);
    if ($href === '') {
        return $safe;
    }
    return "<a href='" . Html::entities_deep($href) . "'>$safe</a>";
};

echo "<div class='asset aat-page'>";

echo "<div class='card card-sm mb-3'>";
echo "<div class='card-header main-header'>" . __('Equipment awaiting your confirmation', 'auchanassettracker') . "</div>";
echo "<div class='card-body'>";

if ($pending === []) {
    echo "<p class='text-muted mb-0'>" . __('Nothing to confirm.', 'auchanassettracker') . "</p>";
} else {
    echo "<div class='table-responsive'><table class='table table-sm table-hover mb-0'>";
    echo "<thead><tr><th>" . __('Equipment') . "</th><th>" . __('Type') . "</th><th>"
        . __('Serial number') . "</th><th>" . __('Allocated on', 'auchanassettracker')
        . "</th><th>" . __('Actions') . "</th></tr></thead><tbody>";
    foreach ($pending as $a) {
        $aid = (int) ($a['id'] ?? 0);
        $eq = new PluginAuchanassettrackerEquipment();
        $eq->getFromDB((int) $a['plugin_auchanassettracker_equipments_id']);
        $type = (string) ($eq->fields['itemtype'] ?? '');
        $type_label = ($type !== '' && class_exists($type)) ? $type::getTypeName(1) : $type;
        $href = $base . '/front/equipment.form.php?id=' . (int) $eq->getID();
        $row_class = ($focus_id > 0 && $aid === $focus_id) ? " class='table-primary'" : '';
        echo "<tr$row_class><td>" . $aat_link((string) ($eq->fields['name'] ?? ''), $href)
            . "</td><td>" . $aat_link($type_label, $href)
            . "</td><td>" . $aat_link((string) ($eq->fields['serial'] ?? ''), $href)
            . "</td><td>" . $aat_link((string) ($a['allocation_date'] ?? ''), $href)
            . "</td><td><form method='post' action='' class='d-inline'>";
        echo Html::hidden('allocation_id', ['value' => $aid]);
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        // Only recipient or central admin may confirm/reject.
        $can_act = ((int) ($a['users_id_recipient'] ?? 0) === $uid)
            || PluginAuchanassettrackerRighthelper::isCentralAdmin();
        if ($can_act) {
            echo Html::submit(__('Confirm receipt', 'auchanassettracker'), [
                'name' => 'confirm',
                'class' => 'btn btn-success btn-sm',
            ]);
            echo " ";
            echo Html::submit(__('Did not receive', 'auchanassettracker'), [
                'name' => 'reject',
                'class' => 'btn btn-outline-danger btn-sm',
            ]);
        } else {
            echo "<span class='text-muted'>"
                . __('Waiting for the recipient to confirm.', 'auchanassettracker')
                . "</span>";
        }
        Html::closeForm();
        echo "</td></tr>";
    }
    echo "</tbody></table></div>";
}

echo "</div></div>";

echo "<div class='card card-sm aat-history-card'>";
echo "<div class='card-header main-header'>" . __('My equipment', 'auchanassettracker') . "</div>";
echo "<div class='card-body'>";
if ($mine === []) {
    echo "<p class='text-muted mb-0'>" . __('None.', 'auchanassettracker') . "</p>";
} else {
    echo "<div class='table-responsive'><table class='table table-sm table-striped mb-0'>";
    echo "<thead><tr><th>" . __('Name') . "</th><th>" . __('Type') . "</th><th>"
        . __('Serial number') . "</th><th>" . __('Status') . "</th><th>"
        . __('Source', 'auchanassettracker') . "</th></tr></thead><tbody>";
    foreach ($mine as $e) {
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
        echo "<tr><td>" . $aat_link((string) ($e['name'] ?? ''), $href)
            . "</td><td>" . $aat_link($type_label, $href)
            . "</td><td>" . $aat_link((string) ($e['serial'] ?? ''), $href)
            . "</td><td>" . $aat_link($status_label, $href)
            . "</td><td>" . $aat_link(
                $src === 'glpi' ? __('GLPI', 'auchanassettracker') : __('Plugin', 'auchanassettracker'),
                $href
            )
            . "</td></tr>";
    }
    echo "</tbody></table></div>";
}
echo "</div></div>";

echo "</div>";
Html::footer();
