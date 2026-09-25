<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

Html::header(
    PluginAuchanassettrackerContainer::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'],
    'assets',
    'PluginAuchanassettrackerMenu',
    'container'
);
plugin_auchanassettracker_page_begin();

\Glpi\Search\SearchEngine::show(PluginAuchanassettrackerContainer::class);

plugin_auchanassettracker_page_end();
Html::footer();
