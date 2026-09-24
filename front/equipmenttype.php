<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Html::header(
    PluginAuchanassettrackerEquipmenttype::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'],
    'assets',
    'PluginAuchanassettrackerMenu',
    'equipmenttype'
);

\Glpi\Search\SearchEngine::show(PluginAuchanassettrackerEquipmenttype::class);

Html::footer();
