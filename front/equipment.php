<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Html::header(
    PluginAuchanassettrackerEquipment::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'],
    PluginAuchanassettrackerMenu::SECTOR,
    PluginAuchanassettrackerMenu::MENU_EQUIPMENT
);

$scope = PluginAuchanassettrackerRighthelper::getScopedLocationId();

if (PluginAuchanassettrackerRighthelper::canManageStock()
    || PluginAuchanassettrackerRighthelper::canAllocate()
    || PluginAuchanassettrackerRighthelper::isCentralAdmin()
) {
    echo "<div class='aat-page aat-equip-list'>";
    PluginAuchanassettrackerAllocation::displayActiveAlerts($scope);
    echo "</div>";
}

\Glpi\Search\SearchEngine::show(PluginAuchanassettrackerEquipment::class);

Html::footer();
