<?php

/**
 * QR helpers: public URL + simple printable HTML/PDF label.
 */
class PluginAuchanassettrackerQrhelper
{
    public static function getPublicUrl(string $token): string
    {
        global $CFG_GLPI;

        $root = rtrim((string) ($CFG_GLPI['url_base'] ?? $CFG_GLPI['root_doc'] ?? ''), '/');
        // Prefer url_base for external networks; fall back to relative plugin public path.
        if ($root !== '' && !str_contains($root, '/plugins/')) {
            return $root . '/plugins/' . plugin_auchanassettracker_dir() . '/public/qr.php?t=' . urlencode($token);
        }

        return Plugin::getWebDir(plugin_auchanassettracker_dir(), true) . '/qr.php?t=' . urlencode($token);
    }

    /**
     * Very small QR via Google Charts-compatible online API fallback,
     * plus inline SVG matrix using a pure-PHP encoder-free approach:
     * we embed an img to a local generator endpoint.
     */
    public static function getQrImageUrl(string $token): string
    {
        return Plugin::getWebDir(plugin_auchanassettracker_dir(), true) . '/qrimg.php?t=' . urlencode($token);
    }

    /**
     * Render a printable label (HTML that browsers can print to PDF).
     */
    public static function renderLabelHtml(array $container): string
    {
        $name = Html::entities_deep($container['name'] ?? '');
        $code = Html::entities_deep($container['code'] ?? '');
        $token = (string) ($container['qr_token'] ?? '');
        $url = self::getPublicUrl($token);
        $img = self::getQrImageUrl($token);
        $loc = Dropdown::getDropdownName('glpi_locations', (int) ($container['locations_id'] ?? 0));

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . $code . '</title>
<style>
body{font-family:Arial,sans-serif;text-align:center;padding:24px}
.box{border:2px solid #222;padding:20px;display:inline-block;min-width:280px}
h1{font-size:18px;margin:0 0 8px}
.code{font-size:22px;font-weight:bold;letter-spacing:1px}
.loc{color:#555;margin:8px 0}
img{width:180px;height:180px;margin:12px 0}
.url{font-size:10px;word-break:break-all;color:#333}
@media print{button{display:none}}
</style></head><body>
<div class="box">
<h1>' . $name . '</h1>
<div class="code">' . $code . '</div>
<div class="loc">' . Html::entities_deep($loc) . '</div>
<img src="' . Html::entities_deep($img) . '" alt="QR"/>
<div class="url">' . Html::entities_deep($url) . '</div>
</div>
<p><button onclick="window.print()">' . __('Print', 'auchanassettracker') . '</button></p>
</body></html>';
    }
}
