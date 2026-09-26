<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!PluginAuchanassettrackerRighthelper::isCentralAdmin()
    && !Session::haveRight('config', UPDATE)) {
    Html::displayRightError();
    exit;
}

Html::header(
    __('Auchan Asset Tracker - configuration', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    'assets',
    PluginAuchanassettrackerMenu::MENU_CONFIG
);

$base = plugin_auchanassettracker_web_dir();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_thresholds'])) {
    PluginAuchanassettrackerConfig::saveThresholds(
        (int) ($_POST['allocation_confirm_days'] ?? 5)
    );
    Session::addMessageAfterRedirect(__('Thresholds saved.', 'auchanassettracker'), true, INFO);
    Html::redirect($base . '/front/config.form.php');
    exit;
}

echo "<div class='aat-form-page'>";
echo "<div class='card'>";
echo "<div class='card-header'>" . __('Alert thresholds', 'auchanassettracker') . "</div>";
echo "<div class='card-body'>";
echo "<form method='post' action=''>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo "<div class='mb-3'><label class='form-label'>"
    . __('Allocation confirmation (working days)', 'auchanassettracker') . "</label>";
echo Html::input('allocation_confirm_days', [
    'type'  => 'number',
    'min'   => 1,
    'class' => 'form-control aat-input-sm',
    'value' => PluginAuchanassettrackerConfig::getAllocationConfirmDays(),
]);
echo "<div class='form-text'>"
    . __('Default 5 working days. Alert managers when confirmation is late.', 'auchanassettracker')
    . "</div></div>";
echo Html::submit(_sx('button', 'Save'), ['name' => 'save_thresholds', 'class' => 'btn btn-primary']);
Html::closeForm();
echo "</div></div>";

echo "<p class='mt-3 text-muted'>"
    . __('Map GLPI profiles to Asset Tracker roles under Administration → Profiles → Auchan Asset Tracker tab.', 'auchanassettracker')
    . "</p>";

echo "<p><a href='" . $base . "/front/equipmenttype.php'>" . __('Equipment types', 'auchanassettracker') . "</a> · ";
echo "<a href='" . $base . "/front/manufacturer.php'>" . __('Manufacturers', 'auchanassettracker') . "</a></p>";

echo "</div>";
Html::footer();
