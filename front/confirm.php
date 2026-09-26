<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Html::header(
    __('Confirm receipt', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    'assets',
    PluginAuchanassettrackerMenu::MENU_CONFIRM
);

$uid = (int) Session::getLoginUserID();
$base = plugin_auchanassettracker_web_dir();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aid = (int) ($_POST['allocation_id'] ?? 0);
    if (isset($_POST['confirm'])) {
        PluginAuchanassettrackerAllocation::confirm($aid);
        Session::addMessageAfterRedirect(__('Receipt confirmed.', 'auchanassettracker'), true, INFO);
    } elseif (isset($_POST['reject'])) {
        PluginAuchanassettrackerAllocation::reject($aid);
        Session::addMessageAfterRedirect(
            __('Marked as not received. The manager will put it back in a container.', 'auchanassettracker'),
            true,
            WARNING
        );
    }
    Html::redirect($base . '/front/confirm.php');
    exit;
}

$pending = PluginAuchanassettrackerAllocation::getPendingForUser($uid);
$mine = PluginAuchanassettrackerAllocation::getUserEquipment($uid);

echo "<div class='aat-form-page'>";
echo "<div class='card'>";
echo "<div class='card-header'>" . __('Equipment awaiting your confirmation', 'auchanassettracker') . "</div>";
echo "<div class='card-body'>";

if ($pending === []) {
    echo "<p class='text-muted mb-0'>" . __('Nothing to confirm.', 'auchanassettracker') . "</p>";
} else {
    echo "<div class='table-responsive'><table class='table table-sm table-hover mb-0'>";
    echo "<thead><tr><th>" . __('Equipment') . "</th><th>"
        . __('Serial number') . "</th><th>" . __('Allocated on', 'auchanassettracker')
        . "</th><th>" . __('Actions') . "</th></tr></thead><tbody>";
    foreach ($pending as $a) {
        $eq = new PluginAuchanassettrackerEquipment();
        $eq->getFromDB((int) $a['plugin_auchanassettracker_equipments_id']);
        echo "<tr><td>" . Html::entities_deep($eq->fields['name'] ?? '')
            . "</td><td>" . Html::entities_deep($eq->fields['serial'] ?? '')
            . "</td><td>" . Html::entities_deep($a['allocation_date'] ?? '')
            . "</td><td><form method='post' action='' class='d-inline'>";
        echo Html::hidden('allocation_id', ['value' => (int) $a['id']]);
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        echo Html::submit(__('Confirm receipt', 'auchanassettracker'), [
            'name' => 'confirm',
            'class' => 'btn btn-success btn-sm',
        ]);
        echo " ";
        echo Html::submit(__('Did not receive', 'auchanassettracker'), [
            'name' => 'reject',
            'class' => 'btn btn-outline-danger btn-sm',
        ]);
        Html::closeForm();
        echo "</td></tr>";
    }
    echo "</tbody></table></div>";
}

echo "</div></div>";

echo "<div class='card mt-4'>";
echo "<div class='card-header'>" . __('My equipment', 'auchanassettracker') . "</div>";
echo "<div class='card-body'>";
if ($mine === []) {
    echo "<p class='text-muted mb-0'>" . __('None.', 'auchanassettracker') . "</p>";
} else {
    echo "<ul class='mb-0'>";
    foreach ($mine as $e) {
        echo "<li>" . Html::entities_deep(($e['name'] ?? '') . ' [' . ($e['serial'] ?? '') . '] — '
            . PluginAuchanassettrackerEquipment::getStatusLabel((string) ($e['status'] ?? '')))
            . "</li>";
    }
    echo "</ul>";
}
echo "</div></div>";

echo "</div>";
Html::footer();
