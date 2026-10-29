<?php
/**
 * Site parts: headers and footers built with Foxi blocks, stored as the
 * `foxi_part` post type so they can be edited visually in Foxi Studio.
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

class Parts {

	const POST_TYPE = 'foxi_part';

	public function init() {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'after_setup_theme', array( $this, 'menus' ), 20 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
	}

	public function register_post_types() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Site parts', 'foxi-builder' ),
					'singular_name' => __( 'Site part', 'foxi-builder' ),
					'add_new_item'  => __( 'Add site part', 'foxi-builder' ),
					'edit_item'     => __( 'Edit site part', 'foxi-builder' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'show_in_rest'    => true,
				'rest_base'       => 'foxi-parts',
				'supports'        => array( 'title', 'editor', 'revisions', 'custom-fields' ),
				'capability_type' => 'page',
				'map_meta_cap'    => true,
			)
		);
		register_post_meta(
			self::POST_TYPE,
			'_foxi_part_type',
			array(
				'type'          => 'string',
				'single'        => true,
				'default'       => 'header',
				'show_in_rest'  => true,
				'auth_callback' => static function () {
					return current_user_can( 'edit_pages' );
				},
			)
		);
	}

	public function menus() {
		register_nav_menus(
			array(
				'foxi-primary' => __( 'Foxi main menu', 'foxi-builder' ),
				'foxi-footer'  => __( 'Foxi footer menu', 'foxi-builder' ),
			)
		);
	}

	public function columns( $cols ) {
		$cols['foxi_type']   = __( 'Type', 'foxi-builder' );
		$cols['foxi_active'] = __( 'In use', 'foxi-builder' );
		return $cols;
	}

	public function column( $col, $post_id ) {
		if ( 'foxi_type' === $col ) {
			echo esc_html( Templates::label( (string) get_post_meta( $post_id, '_foxi_part_type', true ) ) );
		}
		if ( 'foxi_active' === $col ) {
			$s = Settings::get();
			$in_use = false;
			foreach ( self::types() as $t ) {
				$in_use = $in_use || (int) $s[ self::setting_key( $t ) ] === (int) $post_id;
			}
			echo $in_use ? '✓' : '';
		}
	}

	/**
	 * Which header/footer applies to the current request.
	 *
	 * @param string $type header|footer.
	 * @return int Post id, 0 for none.
	 */
	public static function setting_key( $type ) {
		$keys = array(
			'header'  => 'headerId',
			'footer'  => 'footerId',
			'archive' => 'archiveId',
			'single'  => 'singleId',
			'404'     => 'notFoundId',
		);
		return isset( $keys[ $type ] ) ? $keys[ $type ] : '';
	}

	/** All part types: header, footer and the page layouts. */
	public static function types() {
		return array( 'header', 'footer', 'archive', 'single', '404' );
	}

	public static function active( $type ) {
		$settings = Settings::get();
		$key      = self::setting_key( $type );
		$id       = $key ? (int) $settings[ $key ] : 0;

		if ( is_singular() && in_array( $type, array( 'header', 'footer' ), true ) ) {
			$page = get_post_meta( get_queried_object_id(), '_foxi_page', true );
			$over = is_array( $page ) && isset( $page[ $type ] ) ? (int) $page[ $type ] : 0;
			if ( -1 === $over ) {
				return 0;
			}
			if ( $over > 0 ) {
				$id = $over;
			}
		}
		$post = $id ? get_post( $id ) : null;
		return ( $post && self::POST_TYPE === $post->post_type && 'publish' === $post->post_status ) ? $id : 0;
	}

	/**
	 * Render a site part.
	 *
	 * @param int $id Part id.
	 * @return string
	 */
	public static function render( $id ) {
		$post = get_post( $id );
		if ( ! $post ) {
			return '';
		}
		$GLOBALS['foxi_rendering_part'] = $post->ID;
		$html                            = do_blocks( $post->post_content );
		$GLOBALS['foxi_rendering_part'] = 0;
		return $html;
	}

	/**
	 * All parts of a type, for pickers.
	 *
	 * @param string $type header|footer.
	 * @return array<int,array{id:int,title:string}>
	 */
	public static function list_parts( $type ) {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => 50,
				'post_status'    => array( 'publish', 'draft' ),
				'meta_key'       => '_foxi_part_type', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => $type, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		return array_map(
			static function ( $p ) {
				return array( 'id' => $p->ID, 'title' => $p->post_title, 'status' => $p->post_status );
			},
			$posts
		);
	}
}
