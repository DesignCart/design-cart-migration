<?php
/**
 * Design Cart Migration
 * Code: design-cart-migration
 * Autor: Paweł Nosko — https://www.designcart.pl/pawel-nosko.html
 * Firma: Design Cart — https://www.designcart.pl/
 *
 * Plugin Name:       Design Cart Migration
 * Plugin URI:        https://www.designcart.pl/
 * Description:       Migrate a WordPress database to a new URL: export a ready SQL dump with replaced addresses, or run live search/replace on the current database (serialized data safe).
 * Version:           1.0.4
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Paweł Nosko
 * Author URI:        https://www.designcart.pl/pawel-nosko.html
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       design-cart-migration
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DCCM_VERSION', '1.0.4');
define('DCCM_FILE', __FILE__);
define('DCCM_DIR', plugin_dir_path(__FILE__));
define('DCCM_URL', plugin_dir_url(__FILE__));
define('DCCM_BASENAME', plugin_basename(__FILE__));

require_once DCCM_DIR . 'includes/class-replacer.php';
require_once DCCM_DIR . 'includes/class-exporter.php';
require_once DCCM_DIR . 'includes/admin/class-admin-helpers.php';
require_once DCCM_DIR . 'includes/admin/class-admin-handlers.php';
require_once DCCM_DIR . 'includes/admin/class-admin-dashboard.php';
require_once DCCM_DIR . 'includes/admin/class-admin-export.php';
require_once DCCM_DIR . 'includes/admin/class-admin-replace.php';
require_once DCCM_DIR . 'includes/admin/class-admin.php';
require_once DCCM_DIR . 'includes/class-plugin.php';

DCCM_Plugin::instance()->boot();
