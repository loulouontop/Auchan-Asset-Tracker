<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Session::checkRight('profile', UPDATE);

if (isset($_POST['update_aat_profile']) || isset($_POST['update'])) {
    Session::checkCSRF($_POST);

    $ok = PluginAuchanassettrackerProfile::saveFromPost($_POST);
    if ($ok) {
        Session::addMessageAfterRedirect(
            __('Role mapping saved.', 'auchanassettracker'),
            true,
            INFO
        );
    } else {
        Session::addMessageAfterRedirect(
            __('Unable to save role mapping.', 'auchanassettracker'),
            false,
            ERROR
        );
    }

    $profiles_id = (int) ($_POST['profiles_id'] ?? 0);
    if ($profiles_id > 0) {
        global $CFG_GLPI;
        Html::redirect(
            $CFG_GLPI['root_doc'] . '/front/profile.form.php?id=' . $profiles_id
            . '&forcetab=PluginAuchanassettrackerProfile$1'
        );
    }
}

Html::back();
