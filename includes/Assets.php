<?php
/**
 * Front-end and editor-canvas assets.
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

class Assets {

	public function init() {
		add_action( 'init', array( $this, 'register' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend' ), 5 );
		add_action( 'enqueue_block_assets', array( $this, 'canvas' ) );
		add_action( 'wp_head', array( $this, 'js_flag' ), 0 );
		add_filter( 'script_loader_tag', array( $this, 'defer' ), 10, 2 );
	}

	public static function asset( $entry ) {
		$file = FOXI_DIR . 'build/' . $entry . '.asset.php';
		return is_readable( $file ) ? include $file : array( 'dependencies' => array(), 'version' => FOXI_VERSION );
	}

	public function register() {
		$style = self::asset( 'style' );
		wp_register_style( 'foxi-blocks', FOXI_URL . 'build/style.css', array(), $style['version'] );
		wp_add_inline_style( 'foxi-blocks', Brand::css_vars() );

		wp_register_style( 'foxi-swiper', FOXI_URL . 'assets/vendor/swiper/swiper-bundle.min.css', array(), '11' );
		wp_register_script( 'foxi-swiper', FOXI_URL . 'assets/vendor/swiper/swiper-bundle.min.js', array(), '11', true );

		$front = self::asset( 'frontend' );
		wp_register_script( 'foxi-frontend', FOXI_URL . 'build/frontend.js', array(), $front['version'], true );
		wp_localize_script(
			'foxi-frontend',
			'foxiFront',
			array(
				'rest'  => esc_url_raw( rest_url( 'foxi/v1/' ) ),
				'i18n'  => array(
					'sending' => __( 'Sending…', 'foxi-builder' ),
					'error'   => __( 'Your message could not be sent. Please check the highlighted fields and try again.', 'foxi-builder' ),
					'close'   => __( 'Close', 'foxi-builder' ),
					'next'    => __( 'Next image', 'foxi-builder' ),
					'prev'    => __( 'Previous image', 'foxi-builder' ),
				),
			)
		);

		$fonts = Brand::google_fonts_url();
		if ( $fonts ) {
			wp_register_style( 'foxi-fonts', $fonts, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
	}

	public function js_flag() {
		echo "<script>document.documentElement.classList.add('fx-js');</script>\n";
	}

	public function frontend() {
		// Collect block CSS for <head> so nothing renders unstyled.
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post && ! empty( $post->post_content ) ) {
				Style::collect( parse_blocks( $post->post_content ) );
			}
		}
		foreach ( array( 'header', 'footer' ) as $type ) {
			$part = Parts::active( $type );
			if ( $part ) {
				Style::collect( parse_blocks( (string) get_post_field( 'post_content', $part ) ) );
			}
		}
		$kind = Templates::current_kind();
		if ( $kind ) {
			Style::collect( parse_blocks( Templates::markup( $kind ) ) );
		}

		$has_foxi = false;
		foreach ( Style::used_blocks() as $name ) {
			if ( 0 === strpos( $name, 'foxi/' ) ) {
				$has_foxi = true;
				break;
			}
		}

		if ( $has_foxi || Canvas::uses_canvas() || $kind ) {
			wp_enqueue_style( 'foxi-blocks' );
			if ( wp_style_is( 'foxi-fonts', 'registered' ) ) {
				wp_enqueue_style( 'foxi-fonts' );
			}
			wp_enqueue_script( 'foxi-frontend' );
		}
		if ( Style::uses( 'foxi/slider' ) || Style::uses( 'foxi/carousel' ) ) {
			wp_enqueue_style( 'foxi-swiper' );
			wp_enqueue_script( 'foxi-swiper' );
		}
		$css = Style::head_css();
		if ( $css ) {
			wp_add_inline_style( 'foxi-blocks', $css );
		}
	}

	/**
	 * Loaded inside the editor canvas iframe (and on the front end).
	 */
	public function canvas() {
		if ( ! is_admin() ) {
			return;
		}
		wp_enqueue_style( 'foxi-blocks' );
		if ( wp_style_is( 'foxi-fonts', 'registered' ) ) {
			wp_enqueue_style( 'foxi-fonts' );
		}
		wp_enqueue_style( 'foxi-swiper' );
		$canvas = self::asset( 'canvas' );
		wp_enqueue_style( 'foxi-canvas', FOXI_URL . 'build/canvas.css', array( 'foxi-blocks' ), $canvas['version'] );
	}

	public function defer( $tag, $handle ) {
		if ( in_array( $handle, array( 'foxi-frontend', 'foxi-swiper' ), true ) && false === strpos( $tag, ' defer' ) ) {
			$tag = str_replace( ' src=', ' defer src=', $tag );
		}
		return $tag;
	}
}
