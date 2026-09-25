<?php
/**
 * Shared bootstrap for front controllers.
 */
function plugin_auchanassettracker_front_bootstrap(): void
{
    // GLPI 11 loads plugin fronts via LegacyFileLoadController — core is
    // already bootstrapped. Re-including includes.php can break the session.
    if (!defined('GLPI_ROOT')) {
        include_once dirname(__DIR__, 3) . '/inc/includes.php';
    }

    plugin_auchanassettracker_bootstrap();
    Session::checkLoginUser();
}

/**
 * Open native-looking page chrome (grey shell, white content).
 * Call right after Html::header().
 */
function plugin_auchanassettracker_page_begin(): void
{
    echo Html::scriptBlock("document.body.classList.add('aat-plugin-page');");
    echo '<div class="aat-page">';
}

/**
 * Close page chrome. Call right before Html::footer().
 */
function plugin_auchanassettracker_page_end(): void
{
    echo '</div>';
}
