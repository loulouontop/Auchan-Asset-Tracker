<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Html::header(
    PluginAuchanassettrackerEquipment::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'],
    'assets',
    'PluginAuchanassettrackerMenu',
    'equipment'
);
plugin_auchanassettracker_page_begin();

\Glpi\Search\SearchEngine::show(PluginAuchanassettrackerEquipment::class);

plugin_auchanassettracker_page_end();
Html::footer();
