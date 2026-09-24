<?php

include_once __DIR__ . '/_bootstrap.php';
plugin_auchanassettracker_front_bootstrap();

\Glpi\Search\SearchEngine::show(PluginAuchanassettrackerEquipment::class);
