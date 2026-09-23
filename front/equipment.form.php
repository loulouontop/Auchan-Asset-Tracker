<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

$item = new PluginAuchanassettrackerEquipment();

if (isset($_POST['add'])) {
    $item->check(-1, CREATE, $_POST);
    if ($newID = $item->add($_POST)) {
        if ($_SESSION['glpibackcreated'] ?? false) {
            Html::redirect($item->getFormURL() . '?id=' . $newID);
        }
        Html::back();
    }
    Html::back();
} elseif (isset($_POST['update'])) {
    $item->check($_POST['id'], UPDATE);
    $item->update($_POST);
    Html::back();
} elseif (isset($_POST['mark_final'])) {
    $item->check((int) $_POST['id'], UPDATE);
    if ($item->getFromDB((int) $_POST['id'])) {
        $item->markFinal(
            (string) ($_POST['final_status'] ?? ''),
            (string) ($_POST['final_reason'] ?? ''),
            (string) ($_POST['final_document'] ?? '')
        );
    }
    Html::back();
} elseif (isset($_POST['reintroduce'])) {
    $item->check((int) $_POST['id'], UPDATE);
    if ($item->getFromDB((int) $_POST['id'])) {
        $item->reintroduceToStock((int) ($_POST['plugin_auchanassettracker_containers_id'] ?? 0));
    }
    Html::back();
} elseif (isset($_POST['delete']) || isset($_POST['purge'])) {
    $item->check($_POST['id'], DELETE);
    $item->delete($_POST);
    $item->redirectToList();
}

$id = (int) ($_GET['id'] ?? 0);
$item->display(['id' => $id]);

// Extra actions for final status / reintroduce
if ($id > 0 && $item->getFromDB($id)) {
    $status = (string) ($item->fields['status'] ?? '');
    $base = Plugin::getWebDir(plugin_auchanassettracker_dir());

    if (PluginAuchanassettrackerRighthelper::canWriteOff()
        && !PluginAuchanassettrackerEquipment::isFinalStatus($status)
        && !in_array($status, [
            PluginAuchanassettrackerEquipment::STATUS_IN_TRANSIT,
            PluginAuchanassettrackerEquipment::STATUS_AWAITING_VALIDATION,
        ], true)) {
        echo "<div class='spaced'><form method='post' action='" . $base . "/front/equipment.form.php'>";
        echo Html::hidden('id', ['value' => $id]);
        echo "<table class='tab_cadre_fixe'><tr><th colspan='2'>"
            . __('Write-off / Lost / Stolen', 'auchanassettracker') . "</th></tr>";
        echo "<tr class='tab_bg_1'><td>" . __('Action') . "</td><td>";
        Dropdown::showFromArray('final_status', [
            PluginAuchanassettrackerEquipment::STATUS_WRITTEN_OFF => __('Written off', 'auchanassettracker'),
            PluginAuchanassettrackerEquipment::STATUS_LOST => __('Lost', 'auchanassettracker'),
            PluginAuchanassettrackerEquipment::STATUS_STOLEN => __('Stolen', 'auchanassettracker'),
        ]);
        echo "</td></tr><tr class='tab_bg_1'><td>" . __('Reason', 'auchanassettracker') . " *</td><td>";
        echo "<textarea name='final_reason' class='form-control' required rows='3'></textarea></td></tr>";
        echo "<tr class='tab_bg_1'><td>" . __('Document (optional)', 'auchanassettracker') . "</td><td>";
        echo Html::input('final_document', ['value' => '']);
        echo "</td></tr><tr class='tab_bg_2'><td colspan='2' class='center'>";
        echo Html::submit(__('Apply', 'auchanassettracker'), ['name' => 'mark_final', 'class' => 'btn btn-warning']);
        echo "</td></tr></table>";
        Html::closeForm();
        echo "</div>";
    }

    if (PluginAuchanassettrackerRighthelper::canChangeFinalStatus()
        && PluginAuchanassettrackerEquipment::isFinalStatus($status)) {
        echo "<div class='spaced'><form method='post' action='" . $base . "/front/equipment.form.php'>";
        echo Html::hidden('id', ['value' => $id]);
        echo "<table class='tab_cadre_fixe'><tr><th colspan='2'>"
            . __('Reintroduce into stock', 'auchanassettracker') . "</th></tr>";
        echo "<tr class='tab_bg_1'><td>" . __('Container', 'auchanassettracker') . " *</td><td>";
        PluginAuchanassettrackerContainer::dropdown([
            'name' => 'plugin_auchanassettracker_containers_id',
            'condition' => [
                'locations_id' => (int) $item->fields['locations_id'],
                'is_active' => 1,
                'is_deleted' => 0,
            ],
        ]);
        echo "</td></tr><tr class='tab_bg_2'><td colspan='2' class='center'>";
        echo Html::submit(__('Reintroduce', 'auchanassettracker'), ['name' => 'reintroduce', 'class' => 'btn btn-primary']);
        echo "</td></tr></table>";
        Html::closeForm();
        echo "</div>";
    }
}
