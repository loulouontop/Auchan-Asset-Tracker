<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

$item = new PluginAuchanassettrackerContainer();
$in_modal = !empty($_REQUEST['_in_modal']);

if (isset($_POST['add'])) {
    $item->check(-1, CREATE, $_POST);
    if ($newID = $item->add($_POST)) {
        $url = $item->getFormURL() . '?id=' . $newID;
        if ($in_modal || !empty($_POST['_in_modal'])) {
            $url .= '&_in_modal=1';
        }
        Html::redirect($url);
    }
    Html::back();
} elseif (isset($_POST['update'])) {
    $item->check($_POST['id'], UPDATE);
    $item->update($_POST);
    Html::back();
} elseif (isset($_POST['purge'])) {
    $item->check($_POST['id'], PURGE);
    $item->delete($_POST, 1);
    if ($in_modal || !empty($_POST['_in_modal'])) {
        echo Html::scriptBlock('window.top.location.reload();');
        Html::popFooter();
        exit;
    }
    $item->redirectToList();
} elseif (isset($_POST['delete'])) {
    $item->check($_POST['id'], DELETE);
    $item->delete($_POST, 0);
    if ($in_modal || !empty($_POST['_in_modal'])) {
        echo Html::scriptBlock('window.top.location.reload();');
        Html::popFooter();
        exit;
    }
    $item->redirectToList();
}

$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $item->check($id, READ);
} else {
    $item->check(-1, CREATE);
}

// Popup/iframe must NOT render full GLPI chrome (sidebar) — form only.
if ($in_modal) {
    Html::popHeader(
        PluginAuchanassettrackerContainer::getTypeName(1),
        $_SERVER['PHP_SELF']
    );
    $item->showForm($id, ['in_modal' => true]);
    Html::popFooter();
    exit;
}

Html::header(
    PluginAuchanassettrackerContainer::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'],
    PluginAuchanassettrackerMenu::SECTOR,
    PluginAuchanassettrackerMenu::MENU_CONTAINER
);

$item->display(['id' => $id]);

Html::footer();
