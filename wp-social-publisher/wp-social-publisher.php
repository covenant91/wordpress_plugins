<?php
/**
 * Plugin Name:       WP Social Publisher
 * Plugin URI:        https://yoursite.com/wp-social-publisher
 * Description:       Cross-post WordPress posts to Facebook, Instagram, LinkedIn, and X from the editor.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Your Name
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-social-publisher
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || die();

define( 'WSP_VERSION',    '1.0.0' );
define( 'WSP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WSP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WSP_PLUGIN_FILE', __FILE__ );

// Autoload all classes from includes/ and admin/
foreach ( glob( WSP_PLUGIN_DIR . 'includes/class-wsp-*.php' ) as $file ) {
	require_once $file;
}
foreach ( glob( WSP_PLUGIN_DIR . 'admin/class-wsp-*.php' ) as $file ) {
	require_once $file;
}

register_activation_hook( __FILE__, array( 'WSP_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WSP_Deactivator', 'deactivate' ) );
register_uninstall_hook( __FILE__, 'wsp_uninstall' );

/**
 * Delegate uninstall to uninstall.php via the registered hook file.
 * WordPress calls this function — it simply requires uninstall.php.
 */
function wsp_uninstall() {
	require_once WSP_PLUGIN_DIR . 'uninstall.php';
}

/**
 * Bootstrap the plugin.
 */
function wsp_init() {
	$loader = new WSP_Loader();

	$publisher   = new WSP_Publisher( $loader );
	$meta_box    = new WSP_Meta_Box( $loader );
	$gutenberg   = new WSP_Gutenberg( $loader );
	$admin       = new WSP_Admin( $loader );
	$token_mgr   = new WSP_Token_Manager();
	$token_mgr->register_hooks( $loader );

	$loader->run();
}
add_action( 'plugins_loaded', 'wsp_init' );
