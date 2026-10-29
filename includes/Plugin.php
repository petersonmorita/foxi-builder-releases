<?php
/**
 * Bootstrap. Each feature lives in its own module class so future features
 * (site export, page audits, accessibility checks…) can be added as new
 * modules without touching existing ones.
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/** @var array<string,object> */
	private $modules = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Module registry. Order matters only where noted.
	 *
	 * @return array<string,string>
	 */
	private function module_map() {
		$map = array(
			'settings' => Settings::class,
			'brand'    => Brand::class,
			'style'    => Style::class,
			'blocks'   => Blocks::class,
			'assets'   => Assets::class,
			'parts'    => Parts::class,
			'templates' => Templates::class,
			'canvas'   => Canvas::class,
			'seo'      => Seo\Seo::class,
			'forms'    => Forms::class,
			'starters' => Starters::class,
			'rest'     => Rest::class,
			'studio'   => Studio::class,
			'admin'    => Admin::class,
			'updater'  => Updater::class,
		);

		/**
		 * Filter the Foxi modules. Add-ons can register their own module class
		 * (it must have a public `init()` method).
		 */
		return apply_filters( 'foxi_modules', $map );
	}

	private function boot() {
		load_plugin_textdomain( 'foxi-builder', false, dirname( plugin_basename( FOXI_FILE ) ) . '/languages' );

		foreach ( $this->module_map() as $key => $class ) {
			if ( class_exists( $class ) ) {
				$this->modules[ $key ] = new $class();
				if ( method_exists( $this->modules[ $key ], 'init' ) ) {
					$this->modules[ $key ]->init();
				}
			}
		}

		do_action( 'foxi_loaded', $this );
	}

	/**
	 * @param string $key Module key.
	 * @return object|null
	 */
	public function module( $key ) {
		return isset( $this->modules[ $key ] ) ? $this->modules[ $key ] : null;
	}

	public static function activate() {
		// Register CPTs so rewrite rules include them.
		require_once FOXI_DIR . 'includes/functions.php';
		( new Parts() )->register_post_types();
		( new Forms() )->register_post_types();
		flush_rewrite_rules();

		if ( ! get_option( 'foxi_brand' ) ) {
			add_option( 'foxi_brand', Brand::defaults() );
		}
		if ( ! get_option( 'foxi_settings' ) ) {
			add_option( 'foxi_settings', Settings::defaults() );
		}
		update_option( 'foxi_activation_redirect', 1 );
	}
}
