<?php
/**
 * Foxi Studio: the visual editing experience layered on the block editor.
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

class Studio {

	public function init() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_assets' ) );
		add_filter( 'page_row_actions', array( $this, 'row_action' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_action' ), 10, 2 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 80 );
		add_filter( 'default_content', array( $this, 'default_content' ), 10, 2 );
	}

	public static function is_studio_type( $post_type ) {
		return in_array( $post_type, (array) Settings::get()['studioPostTypes'], true );
	}

	public static function edit_url( $post_id ) {
		return admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' );
	}

	public function editor_assets() {
		$asset = Assets::asset( 'editor' );
		wp_enqueue_script( 'foxi-editor', FOXI_URL . 'build/editor.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'foxi-editor', FOXI_URL . 'build/editor.css', array( 'wp-components' ), $asset['version'] );
		wp_set_script_translations( 'foxi-editor', 'foxi-builder', FOXI_DIR . 'languages' );

		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type = $screen && $screen->post_type ? $screen->post_type : '';
		$post_id   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

		wp_localize_script( 'foxi-editor', 'foxiEditor', self::data( $post_type, $post_id ) );
	}

	public static function data( $post_type = '', $post_id = 0 ) {
		$settings = Settings::get();
		return array(
			'version'      => FOXI_VERSION,
			'rest'         => esc_url_raw( rest_url( Rest::NS . '/' ) ),
			'url'          => FOXI_URL,
			'adminUrl'     => admin_url(),
			'homeUrl'      => home_url( '/' ),
			'dashboardUrl' => admin_url( 'admin.php?page=foxi' ),
			'studio'       => self::is_studio_type( $post_type ),
			'postType'     => $post_type,
			'partType'     => Parts::POST_TYPE === $post_type && $post_id ? get_post_meta( $post_id, '_foxi_part_type', true ) : '',
			'isFront'      => $post_id && (int) get_option( 'page_on_front' ) === $post_id,
			'canManage'    => current_user_can( 'manage_options' ),
			'brand'        => Brand::get(),
			'business'     => Brand::business(),
			'brandCss'     => Brand::css_vars(),
			'fontsUrl'     => Brand::google_fonts_url(),
			'settings'     => array(
				'canvasDefault' => (bool) $settings['canvasDefault'],
				'titleTemplate' => $settings['titleTemplate'],
				'homeTitle'     => $settings['homeTitle'],
				'separator'     => $settings['separator'],
				'headerId'      => (int) $settings['headerId'],
				'footerId'      => (int) $settings['footerId'],
				'siteSchema'    => $settings['siteSchema'],
				'bpTablet'      => (int) $settings['breakpointTablet'],
				'bpMobile'      => (int) $settings['breakpointMobile'],
			),
			'tokens'       => Tokens::labels(),
			'tokenValues'  => Tokens::values(),
			'schemaTypes'  => Seo\Schema::types(),
			'seoPlugin'    => foxi_active_seo_plugin(),
			'seoEnabled'   => Seo\Seo::enabled(),
			'siteName'     => get_bloginfo( 'name' ),
			'tagline'      => get_bloginfo( 'description' ),
			'iconsUrl'     => FOXI_URL . 'assets/icons/icons.json?v=' . FOXI_VERSION,
			'shapes'       => json_decode( (string) file_get_contents( FOXI_DIR . 'assets/shapes.json' ), true ), // phpcs:ignore
			'placeholder'  => FOXI_URL . 'assets/placeholders/',
			'socialLabels' => Render\SocialIcons::labels(),
			'brands'       => array_keys( Icons::brands() ),
		);
	}

	public function row_action( $actions, $post ) {
		if ( self::is_studio_type( $post->post_type ) && current_user_can( 'edit_post', $post->ID ) ) {
			$actions = array( 'foxi' => '<a href="' . esc_url( self::edit_url( $post->ID ) ) . '"><strong>' . esc_html__( 'Edit with Foxi', 'foxi-builder' ) . '</strong></a>' ) + $actions;
		}
		return $actions;
	}

	public function admin_bar( $bar ) {
		if ( is_admin() || ! is_singular() ) {
			return;
		}
		$id = get_queried_object_id();
		if ( ! current_user_can( 'edit_post', $id ) || ! self::is_studio_type( get_post_type( $id ) ) ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'foxi-edit',
				'title' => '<span class="ab-icon dashicons dashicons-edit-page" style="top:2px"></span>' . esc_html__( 'Edit with Foxi', 'foxi-builder' ),
				'href'  => self::edit_url( $id ),
			)
		);
	}

	/**
	 * New pages start with an empty Foxi section so the canvas isn't blank.
	 */
	public function default_content( $content, $post ) {
		if ( $content || ! $post || 'page' !== $post->post_type || ! self::is_studio_type( 'page' ) ) {
			return $content;
		}
		return Starters::serialize(
			Starters::resolve(
				array(
					array(
						'name'  => 'foxi/section',
						'inner' => array(
							array( 'name' => 'foxi/heading', 'attrs' => array( 'level' => 1, 'content' => '' ) ),
						),
					),
				),
				array( 'images' => array(), 'pages' => array(), 'menus' => array() )
			)
		);
	}
}
