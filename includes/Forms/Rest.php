<?php
/**
 * REST routes for Foxi › Forms (managing forms, entries and settings) and
 * the public submit endpoint.
 *
 * @package FoxiBuilder
 */

namespace Foxi\Forms;

defined( 'ABSPATH' ) || exit;

class Rest {

	const NS = 'foxi/v1';

	public static function can_edit() {
		return current_user_can( 'edit_pages' );
	}

	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	public static function register() {
		$edit   = array( __CLASS__, 'can_edit' );
		$manage = array( __CLASS__, 'can_manage' );
		$id     = '(?P<id>\d+)';

		register_rest_route(
			self::NS,
			'/forms',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $edit,
					'callback'            => static function () {
						return array(
							'forms'     => array_map( array( __CLASS__, 'list_item' ), Store::all() ),
							'templates' => Store::templates(),
							'limits'    => Store::limits(),
							'older'     => Entries::query( array( 'form' => 0, 'per_page' => 1 ) )['total'],
						);
					},
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => $edit,
					'callback'            => static function ( \WP_REST_Request $r ) {
						if ( $r->get_param( 'legacy' ) ) {
							$form  = Store::from_legacy_block( (array) $r->get_param( 'legacy' ) );
							$title = $r->get_param( 'title' ) ? $r->get_param( 'title' ) : __( 'Contact form', 'foxi-builder' );
						} else {
							$tpl   = sanitize_key( (string) $r->get_param( 'template' ) );
							$tpls  = Store::templates();
							$form  = Store::from_template( $tpl );
							$title = $r->get_param( 'title' ) ? $r->get_param( 'title' ) : ( isset( $tpls[ $tpl ] ) && 'blank' !== $tpl ? $tpls[ $tpl ]['title'] : __( 'Untitled form', 'foxi-builder' ) );
						}
						$new = Store::create( (string) $title, $form );
						return is_wp_error( $new ) ? $new : Store::get( $new );
					},
				),
			)
		);

		register_rest_route(
			self::NS,
			'/forms/' . $id,
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $edit,
					'callback'            => static function ( \WP_REST_Request $r ) {
						$form = Store::get( $r['id'] );
						return $form ? $form : new \WP_Error( 'foxi_form', __( 'Form not found.', 'foxi-builder' ), array( 'status' => 404 ) );
					},
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => $edit,
					'callback'            => static function ( \WP_REST_Request $r ) {
						return Store::save( $r['id'], (array) $r->get_json_params() );
					},
				),
				array(
					'methods'             => 'DELETE',
					'permission_callback' => $edit,
					'callback'            => static function ( \WP_REST_Request $r ) {
						$form = Store::get( $r['id'] );
						if ( ! $form ) {
							return new \WP_Error( 'foxi_form', __( 'Form not found.', 'foxi-builder' ), array( 'status' => 404 ) );
						}
						wp_delete_post( $form['id'], true ); // Its entries stay, still labelled with the form name.
						return array( 'deleted' => true );
					},
				),
			)
		);

		register_rest_route(
			self::NS,
			'/forms/' . $id . '/duplicate',
			array(
				'methods'             => 'POST',
				'permission_callback' => $edit,
				'callback'            => static function ( \WP_REST_Request $r ) {
					$form = Store::get( $r['id'] );
					if ( ! $form ) {
						return new \WP_Error( 'foxi_form', __( 'Form not found.', 'foxi-builder' ), array( 'status' => 404 ) );
					}
					/* translators: %s: form name */
					$new = Store::create( sprintf( __( '%s (copy)', 'foxi-builder' ), $form['title'] ), $form );
					return is_wp_error( $new ) ? $new : self::list_item( get_post( $new ) );
				},
			)
		);

		register_rest_route(
			self::NS,
			'/forms/' . $id . '/submit',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // Public form; protected by honeypot, timing, rate limit and optional captcha.
				'callback'            => static function ( \WP_REST_Request $r ) {
					return Submit::handle( (int) $r['id'], $r );
				},
			)
		);

		/* ------------------------------ Entries ------------------------------ */

		register_rest_route(
			self::NS,
			'/entries',
			array(
				'methods'             => 'GET',
				'permission_callback' => $edit,
				'callback'            => static function ( \WP_REST_Request $r ) {
					return Entries::query( $r->get_params() );
				},
			)
		);

		register_rest_route(
			self::NS,
			'/entries/' . $id,
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $edit,
					'callback'            => static function ( \WP_REST_Request $r ) {
						$e = Entries::detail( $r['id'] );
						if ( ! $e ) {
							return new \WP_Error( 'foxi_entry', __( 'Entry not found.', 'foxi-builder' ), array( 'status' => 404 ) );
						}
						if ( ! $e['read'] ) {
							update_post_meta( $e['id'], '_foxi_read', 1 );
							$e['read'] = true;
						}
						return $e;
					},
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => $edit,
					'callback'            => static function ( \WP_REST_Request $r ) {
						$e = Entries::detail( $r['id'] );
						if ( ! $e ) {
							return new \WP_Error( 'foxi_entry', __( 'Entry not found.', 'foxi-builder' ), array( 'status' => 404 ) );
						}
						foreach ( array( 'read' => '_foxi_read', 'star' => '_foxi_star' ) as $k => $meta ) {
							if ( null !== $r->get_param( $k ) ) {
								$r->get_param( $k ) ? update_post_meta( $e['id'], $meta, 1 ) : delete_post_meta( $e['id'], $meta );
							}
						}
						return Entries::detail( $e['id'] );
					},
				),
				array(
					'methods'             => 'DELETE',
					'permission_callback' => $edit,
					'callback'            => static function ( \WP_REST_Request $r ) {
						return array( 'deleted' => Entries::delete( $r['id'] ) );
					},
				),
			)
		);

		register_rest_route(
			self::NS,
			'/entries/bulk',
			array(
				'methods'             => 'POST',
				'permission_callback' => $edit,
				'callback'            => static function ( \WP_REST_Request $r ) {
					$action = (string) $r->get_param( 'action' );
					$done   = 0;
					foreach ( array_map( 'absint', (array) $r->get_param( 'ids' ) ) as $eid ) {
						if ( get_post_type( $eid ) !== Entries::POST_TYPE ) {
							continue;
						}
						if ( 'delete' === $action ) {
							Entries::delete( $eid );
						} elseif ( 'read' === $action ) {
							update_post_meta( $eid, '_foxi_read', 1 );
						} elseif ( 'unread' === $action ) {
							delete_post_meta( $eid, '_foxi_read' );
						}
						++$done;
					}
					return array( 'done' => $done );
				},
			)
		);

		register_rest_route(
			self::NS,
			'/entries/export',
			array(
				'methods'             => 'GET',
				'permission_callback' => $edit,
				'callback'            => static function ( \WP_REST_Request $r ) {
					$fid   = (int) $r->get_param( 'form' );
					$title = $fid && get_post( $fid ) ? get_the_title( $fid ) : 'older-messages';
					return array(
						'filename' => sanitize_file_name( $title . '-entries-' . gmdate( 'Y-m-d' ) . '.csv' ),
						'csv'      => Entries::csv( $fid ),
					);
				},
			)
		);

		/* ------------------------------ Settings ----------------------------- */

		register_rest_route(
			self::NS,
			'/forms-settings',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $manage,
					'callback'            => array( Config::class, 'for_admin' ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => $manage,
					'callback'            => static function ( \WP_REST_Request $r ) {
						return Config::save( (array) $r->get_json_params() );
					},
				),
			)
		);

		register_rest_route(
			self::NS,
			'/forms-settings/test-email',
			array(
				'methods'             => 'POST',
				'permission_callback' => $manage,
				'callback'            => static function ( \WP_REST_Request $r ) {
					return Mail::test( sanitize_email( (string) $r->get_param( 'to' ) ) );
				},
			)
		);
	}

	public static function list_item( $p ) {
		$form = Store::get( $p->ID );
		$last = get_posts(
			array(
				'post_type'      => Entries::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'meta_query'     => array( array( 'key' => '_foxi_form_id', 'value' => $p->ID ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		return array(
			'id'      => $p->ID,
			'title'   => $p->post_title,
			'fields'  => count( array_filter( $form['fields'], static function ( $f ) { return in_array( $f['type'], Store::INPUTS, true ); } ) ),
			'entries' => Entries::count( $p->ID ),
			'unread'  => Entries::count( $p->ID, true ),
			'last'    => $last ? human_time_diff( get_post_time( 'U', true, $last[0] ), time() ) : '',
			'emails'  => count( array_filter( $form['notifications'], static function ( $n ) { return $n['enabled']; } ) ),
		);
	}
}
