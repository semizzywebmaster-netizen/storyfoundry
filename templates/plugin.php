<?php
/**
 * Generic shell for pages built by addons (they return HTML fragments).
 * $html is assembled by the addon itself and already escaped there.
 */
$html = isset($html) ? (string) $html : '';
echo $html;
