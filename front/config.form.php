<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

if (!PluginAuchanassettrackerRighthelper::isCentralAdmin()) {
    Html::displayRightError();
    exit;
}

Html::header(
    __('Auchan Asset Tracker - configuration', 'auchanassettracker'),
    $_SERVER['PHP_SELF'],
    'assets',
    'PluginAuchanassettrackerMenu',
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

echo "<div class='asset aat-page'>";
echo "<div class='card card-sm'>";
echo "<div class='card-header main-header'>" . __('Alert thresholds', 'auchanassettracker') . "</div>";
echo "<div class='card-body'>";
echo "<form method='post' action=''>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo "<div class='mb-3'><label class='form-label'>"
    . __('Allocation confirmation (calendar days)', 'auchanassettracker') . "</label>";
echo Html::input('allocation_confirm_days', [
    'type'  => 'number',
    'min'   => 1,
    'class' => 'form-control aat-input-sm',
    'value' => PluginAuchanassettrackerConfig::getAllocationConfirmDays(),
]);
echo "<div class='form-text'>"
    . __('Default 5 calendar days. Late pending allocations appear under Active alerts on Equipment / New allocation, and as in-app notices for managers and the allocator.', 'auchanassettracker')
    . "</div></div>";
echo Html::submit(_sx('button', 'Save'), ['name' => 'save_thresholds', 'class' => 'btn btn-primary']);
Html::closeForm();
echo "</div></div></div>";

Html::footer();
