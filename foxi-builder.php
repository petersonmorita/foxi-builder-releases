<?php
/**
 * Plugin Name:       Foxi Builder
 * Plugin URI:        https://foxibuilder.com
 * Description:       A visual page builder for WordPress. Click-to-edit pages, a Brand Kit that re-themes whole sites, Swap Mode for re-skinning starter sites, built-in SEO and schema, and ready-made starter sites.
 * Version:           1.2.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Foxi
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       foxi-builder
 *
 * @package FoxiBuilder
 */

defined( 'ABSPATH' ) || exit;

define( 'FOXI_VERSION', '1.2.0' );
define( 'FOXI_FILE', __FILE__ );
define( 'FOXI_DIR', plugin_dir_path( __FILE__ ) );
define( 'FOXI_URL', plugin_dir_url( __FILE__ ) );

/**
 * Tiny PSR-4 style autoloader for the Foxi\ namespace.
 * Foxi\Seo\Schema -> includes/Seo/Schema.php
 */
spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'Foxi\\' ) ) {
			return;
		}
		$relative = str_replace( array( 'Foxi\\', '\\' ), array( '', '/' ), $class );
		$file     = FOXI_DIR . 'includes/' . $relative . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

require_once FOXI_DIR . 'includes/functions.php';

add_action( 'plugins_loaded', array( 'Foxi\\Plugin', 'instance' ) );

register_activation_hook( __FILE__, array( 'Foxi\\Plugin', 'activate' ) );
