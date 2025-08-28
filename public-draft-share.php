<?php
/**
 * Plugin Name: Public Draft Share
 * Plugin URI: https://example.com/public-draft-share
 * Description: Create secure, shareable links to view draft posts and pages without logging in.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Liew Cheon Fong
 * Author URI: https://github.com/liewcf
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: public-draft-share
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PDS_VERSION', '1.0.0' );
define( 'PDS_PLUGIN_FILE', __FILE__ );
define( 'PDS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PDS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once PDS_PLUGIN_DIR . 'includes/class-pds-core.php';
require_once PDS_PLUGIN_DIR . 'includes/class-pds-admin.php';

// Bootstrap
add_action( 'plugins_loaded', function () {
    // Load translations
    load_plugin_textdomain( 'public-draft-share', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    \PDS\Core::instance();
    if ( is_admin() ) {
        \PDS\Admin::instance();
    }
} );

// Activation: register rewrites and flush
register_activation_hook( __FILE__, function () {
    \PDS\Core::instance()->add_rewrite_rules();
    flush_rewrite_rules();
} );

// Deactivation: flush
register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );
