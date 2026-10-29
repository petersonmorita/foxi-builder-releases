<?php
/**
 * Full-page canvas: Foxi takes over the whole page (Foxi header, content,
 * Foxi footer) so sites look the same on any theme.
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

class Canvas {

	const META = '_foxi_page';

	public function init() {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_filter( 'template_include', array( $this, 'template' ), 99 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'isolate_theme' ), 9999 );
	}

	public function register_meta() {
		foreach ( array( 'page', 'post' ) as $type ) {
			register_post_meta(
				$type,
				self::META,
				array(
					'type'          => 'object',
					'single'        => true,
					'default'       => array(),
					'show_in_rest'  => array(
						'schema' => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => array(
								'layout'        => array( 'type' => 'string' ),
								'header'        => array( 'type' => 'integer' ),
								'footer'        => array( 'type' => 'integer' ),
								'headerOverlay' => array( 'type' => 'string' ),
								'background'    => array( 'type' => 'string' ),
							),
						),
					),
					'auth_callback' => static function ( $allowed, $key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}

	public static function page_settings( $post_id ) {
		$meta = get_post_meta( $post_id, self::META, true );
		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Does this post render on the Foxi canvas?
	 */
	public static function uses_canvas( $post_id = null ) {
		$post = get_post( $post_id ? $post_id : get_queried_object_id() );
		if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
			return false;
		}
		$meta   = self::page_settings( $post->ID );
		$layout = isset( $meta['layout'] ) ? $meta['layout'] : 'default';
		if ( 'canvas' === $layout ) {
			return true;
		}
		if ( 'theme' === $layout ) {
			return false;
		}
		return 'page' === $post->post_type && ! empty( Settings::get()['canvasDefault'] );
	}

	private function is_canvas_request() {
		if ( is_embed() ) {
			return false;
		}
		return ( is_singular( array( 'page', 'post' ) ) && self::uses_canvas() ) || '' !== Templates::current_kind();
	}

	public function template( $template ) {
		if ( is_embed() ) {
			return $template;
		}
		if ( is_singular( array( 'page', 'post' ) ) && self::uses_canvas() && ! post_password_required() ) {
			return FOXI_DIR . 'templates/canvas.php';
		}
		if ( '' !== Templates::current_kind() && ! ( is_singular() && post_password_required() ) ) {
			return FOXI_DIR . 'templates/site.php';
		}
		return $template;
	}

	public function body_class( $classes ) {
		if ( $this->is_canvas_request() ) {
			$classes[] = 'foxi-canvas';
			if ( Parts::active( 'header' ) ) {
				$classes[] = 'has-foxi-header';
			}
		}
		return $classes;
	}

	/**
	 * Remove the active theme's own CSS/JS on canvas pages so it can't clash.
	 */
	public function isolate_theme() {
		if ( ! $this->is_canvas_request() || empty( Settings::get()['isolateTheme'] ) ) {
			return;
		}
		$theme_urls = array_unique( array( get_template_directory_uri(), get_stylesheet_directory_uri() ) );

		foreach ( array( wp_styles(), wp_scripts() ) as $deps ) {
			foreach ( (array) $deps->queue as $handle ) {
				$src = isset( $deps->registered[ $handle ] ) ? (string) $deps->registered[ $handle ]->src : '';
				foreach ( $theme_urls as $url ) {
					if ( $src && 0 === strpos( $src, $url ) ) {
						$deps instanceof \WP_Styles ? wp_dequeue_style( $handle ) : wp_dequeue_script( $handle );
					}
				}
			}
		}
		// Global styles stay: core blocks used inside Foxi pages rely on them.
		// Foxi's body/heading rules are class-based, so they win over theme.json element styles.
	}
}
