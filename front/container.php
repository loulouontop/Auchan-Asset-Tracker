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

if (PluginAuchanassettrackerContainer::canCreate()) {
    $add = PluginAuchanassettrackerContainer::getFormURL();
    echo "<div class='aat-list-actions mb-2'>";
    echo "<a class='btn btn-primary' href='" . Html::entities_deep($add) . "'>";
    echo "<i class='ti ti-plus'></i> " . Html::entities_deep(__('Add'));
    echo "</a>";
    echo "</div>";
}

\Glpi\Search\SearchEngine::show(PluginAuchanassettrackerContainer::class);

Html::footer();
