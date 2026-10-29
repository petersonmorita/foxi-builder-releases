<?php
/**
 * REST API (namespace foxi/v1).
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

class Rest {

	const NS = 'foxi/v1';

	public function init() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public static function can_edit() {
		return current_user_can( 'edit_pages' );
	}

	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	public function routes() {
		register_rest_route(
			self::NS,
			'/brand',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => static function () {
						return array( 'brand' => Brand::get(), 'business' => Brand::business(), 'css' => Brand::css_vars() );
					},
					'permission_callback' => array( __CLASS__, 'can_edit' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => static function ( \WP_REST_Request $r ) {
						$out = array();
						if ( is_array( $r['brand'] ) ) {
							$out['brand'] = Brand::update( $r['brand'] );
						}
						if ( is_array( $r['business'] ) ) {
							$out['business'] = Brand::update_business( $r['business'] );
						}
						$out['css']   = Brand::css_vars();
						$out['fonts'] = Brand::google_fonts_url();
						return $out;
					},
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => static function () {
						return Settings::get();
					},
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => static function ( \WP_REST_Request $r ) {
						return Settings::update( (array) $r->get_json_params() );
					},
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/library',
			array(
				'methods'             => 'GET',
				'callback'            => static function () {
					return Starters::library();
				},
				'permission_callback' => array( __CLASS__, 'can_edit' ),
			)
		);

		register_rest_route(
			self::NS,
			'/starters',
			array(
				'methods'             => 'GET',
				'callback'            => static function () {
					return Starters::catalog();
				},
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/starters/(?P<slug>[a-z0-9-]+)/import',
			array(
				'methods'             => 'POST',
				'callback'            => static function ( \WP_REST_Request $r ) {
					$kit = Starters::load( $r['slug'] );
					if ( ! $kit ) {
						return new \WP_Error( 'foxi_starter', __( 'Starter site not found.', 'foxi-builder' ), array( 'status' => 404 ) );
					}
					return Starters::import( $kit, (array) $r['options'] );
				},
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/kit/export',
			array(
				'methods'             => 'GET',
				'callback'            => static function () {
					return Starters::export();
				},
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/kit/import',
			array(
				'methods'             => 'POST',
				'callback'            => static function ( \WP_REST_Request $r ) {
					$kit = $r['kit'];
					if ( ! is_array( $kit ) || empty( $kit['foxiKit'] ) ) {
						return new \WP_Error( 'foxi_kit', __( 'This file is not a Foxi site kit.', 'foxi-builder' ), array( 'status' => 400 ) );
					}
					return Starters::import( $kit, (array) $r['options'] );
				},
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/parts',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => static function () {
						$s   = Settings::get();
						$out = array();
						foreach ( Parts::types() as $t ) {
							$out[ $t ]                          = Parts::list_parts( $t );
							$out[ Parts::setting_key( $t ) ] = (int) $s[ Parts::setting_key( $t ) ];
						}
						return $out;
					},
					'permission_callback' => array( __CLASS__, 'can_edit' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => static function ( \WP_REST_Request $r ) {
						$type = in_array( $r['type'], Parts::types(), true ) ? $r['type'] : 'header';
						$id   = Starters::create_part( $type, (string) $r['template'] );
						if ( ! empty( $r['activate'] ) ) {
							Settings::update( array( Parts::setting_key( $type ) => $id ) );
						}
						return array( 'id' => $id, 'editUrl' => admin_url( 'post.php?post=' . $id . '&action=edit' ) );
					},
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/parts/render',
			array(
				'methods'             => 'GET',
				'callback'            => static function ( \WP_REST_Request $r ) {
					$type = 'footer' === $r['type'] ? 'footer' : 'header';
					$id   = (int) $r['id'];
					if ( ! $id ) {
						$s  = Settings::get();
						$id = (int) ( 'header' === $type ? $s['headerId'] : $s['footerId'] );
					}
					if ( ! $id || ! get_post( $id ) ) {
						return array( 'id' => 0, 'html' => '' );
					}
					$GLOBALS['foxi_preview_post'] = (int) $r['post'];
					$content                      = (string) get_post_field( 'post_content', $id );
					Style::collect( parse_blocks( $content ) );
					$html = Parts::render( $id );
					return array(
						'id'      => $id,
						'html'    => $html,
						'css'     => Style::head_css(),
						'editUrl' => admin_url( 'post.php?post=' . $id . '&action=edit' ),
					);
				},
				'permission_callback' => array( __CLASS__, 'can_edit' ),
			)
		);

		register_rest_route(
			self::NS,
			'/menus',
			array(
				'methods'             => 'GET',
				'callback'            => static function () {
					return array_map(
						static function ( $m ) {
							return array( 'id' => (int) $m->term_id, 'name' => $m->name, 'count' => (int) $m->count );
						},
						wp_get_nav_menus()
					);
				},
				'permission_callback' => array( __CLASS__, 'can_edit' ),
			)
		);

		register_rest_route(
			self::NS,
			'/form',
			array(
				'methods'             => 'POST',
				'callback'            => static function ( \WP_REST_Request $r ) {
					$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
					return Forms::submit( (array) $r->get_params(), $ip );
				},
				'permission_callback' => '__return_true', // Public contact form; protected by honeypot, timing and rate limit.
			)
		);
	}
}
