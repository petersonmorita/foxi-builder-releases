<?php
/**
 * Block registry. Each block lives in /blocks/<name>/block.json and has a
 * server renderer in includes/Render/<Name>.php.
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

class Blocks {

	/** @var array<string,array> block name => element selectors */
	private static $elements = array();

	/** @var array<string,array> */
	private static $meta = array();

	public function init() {
		add_action( 'init', array( $this, 'register' ), 9 );
		add_filter( 'block_categories_all', array( $this, 'categories' ), 5 );
	}

	/**
	 * @return string[] Block directory names.
	 */
	public static function names() {
		$dirs = glob( FOXI_DIR . 'blocks/*', GLOB_ONLYDIR );
		return array_map( 'basename', $dirs ? $dirs : array() );
	}

	public function register() {
		// Renderer files may hold several small classes, so load them all up front.
		foreach ( (array) glob( FOXI_DIR . 'includes/Render/*.php' ) as $file ) {
			require_once $file;
		}

		foreach ( self::names() as $slug ) {
			$dir  = FOXI_DIR . 'blocks/' . $slug;
			$json = $dir . '/block.json';
			if ( ! is_readable( $json ) ) {
				continue;
			}
			$meta = json_decode( (string) file_get_contents( $json ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( ! is_array( $meta ) || empty( $meta['name'] ) ) {
				continue;
			}
			self::$meta[ $meta['name'] ]     = $meta;
			self::$elements[ $meta['name'] ] = isset( $meta['foxi']['elements'] ) ? (array) $meta['foxi']['elements'] : array();

			$class = 'Foxi\\Render\\' . str_replace( ' ', '', ucwords( str_replace( '-', ' ', $slug ) ) );
			$args  = array();
			if ( class_exists( $class ) ) {
				$args['render_callback'] = array( $class, 'render' );
			}
			register_block_type( $dir, $args );
		}
	}

	/**
	 * @param string $name Block name.
	 * @return array
	 */
	public static function elements( $name ) {
		return isset( self::$elements[ $name ] ) ? self::$elements[ $name ] : array();
	}

	public static function meta( $name ) {
		return isset( self::$meta[ $name ] ) ? self::$meta[ $name ] : array();
	}

	public function categories( $categories ) {
		$ours = array(
			array( 'slug' => 'foxi-layout', 'title' => __( 'Foxi · Layout', 'foxi-builder' ) ),
			array( 'slug' => 'foxi-content', 'title' => __( 'Foxi · Content', 'foxi-builder' ) ),
			array( 'slug' => 'foxi-media', 'title' => __( 'Foxi · Media', 'foxi-builder' ) ),
			array( 'slug' => 'foxi-interactive', 'title' => __( 'Foxi · Interactive', 'foxi-builder' ) ),
			array( 'slug' => 'foxi-site', 'title' => __( 'Foxi · Site', 'foxi-builder' ) ),
		);
		foreach ( $ours as &$cat ) {
			$cat['icon'] = null;
		}
		return array_merge( $ours, $categories );
	}
}
