<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Html::header(
    PluginAuchanassettrackerManufacturer::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'],
    'assets',
    'PluginAuchanassettrackerMenu',
    'manufacturer'
);

\Glpi\Search\SearchEngine::show(PluginAuchanassettrackerManufacturer::class);

Html::footer();
