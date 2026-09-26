<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Html::header(
    PluginAuchanassettrackerEquipment::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'],
    'assets',
    PluginAuchanassettrackerMenu::MENU_EQUIPMENT
);

\Glpi\Search\SearchEngine::show(PluginAuchanassettrackerEquipment::class);

Html::footer();
