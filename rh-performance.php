<?php

/**
 * Plugin Name:       RH Performance
 * Plugin URI:        https://github.com/herbeckrobin/rh-performance
 * Update URI:        https://github.com/herbeckrobin/rh-performance
 * Description:       LCP-Preload für CSS-Hintergrundbilder (die WordPress nicht selbst priorisiert). Teil der rh-blueprint Kollektion.
 * Version:           0.1.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Robin Herbeck
 * Author URI:        https://robinherbeck.de
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rh-performance
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('RHPERF_VERSION', '0.1.1');
define('RHPERF_PLUGIN_FILE', __FILE__);
define('RHPERF_PLUGIN_DIR', plugin_dir_path(__FILE__));

$rhperf_autoload = RHPERF_PLUGIN_DIR . 'vendor/autoload.php';

if (! is_readable($rhperf_autoload)) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p><strong>RH Performance:</strong> Composer-Dependencies fehlen. Bitte <code>composer install</code> im Plugin-Verzeichnis ausführen.</p></div>';
    });
    return;
}

require_once $rhperf_autoload;

RhPerformance\Plugin::boot();
