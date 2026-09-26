<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Html::header(
    PluginAuchanassettrackerEquipment::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'],
    'assets',
    'PluginAuchanassettrackerMenu',
    PluginAuchanassettrackerMenu::MENU_EQUIPMENT
);

$scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();

echo "<div class='aat-page aat-equip-list'>";
if (PluginAuchanassettrackerRighthelper::canManageStock()
    || PluginAuchanassettrackerRighthelper::canAllocate()
    || PluginAuchanassettrackerRighthelper::isCentralAdmin()
) {
    // Managers see late confirmations here too (not only on New allocation).
    PluginAuchanassettrackerAllocation::displayActiveAlerts($scope);

    $uid_me = (int) Session::getLoginUserID();
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
}
echo "</div>";

\Glpi\Search\SearchEngine::show(PluginAuchanassettrackerEquipment::class);

Html::footer();
